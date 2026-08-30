<?php

namespace App\Services;

use App\Models\Cashbox;
use App\Models\CashboxShift;
use App\Models\Expense;
use App\Models\OtherPeopleTransaction;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashboxShiftService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly JournalService $journal,
    ) {
    }

    public function generateShiftNo(): string
    {
        $year = date('Y');
        $nextId = (CashboxShift::query()->max('id') ?? 0) + 1;

        return sprintf('SH-%s-%05d', $year, $nextId);
    }

    public function openShift(Cashbox|int $cashbox, User|int $user, float $openingBalance = 0.0): CashboxShift
    {
        $cashboxId = $cashbox instanceof Cashbox ? $cashbox->id : $cashbox;
        $userId = $user instanceof User ? $user->id : $user;

        $existingOpen = CashboxShift::query()
            ->where('cashbox_id', $cashboxId)
            ->where('status', CashboxShift::STATUS_OPEN)
            ->first();

        if ($existingOpen) {
            throw ValidationException::withMessages([
                'cashbox_id' => __('general.active_shift_exists'),
            ]);
        }

        return CashboxShift::query()->create([
            'shift_no' => $this->generateShiftNo(),
            'cashbox_id' => $cashboxId,
            'user_id' => $userId,
            'opened_at' => now(),
            'status' => CashboxShift::STATUS_OPEN,
            'opening_balance' => round($openingBalance, 2),
        ]);
    }

    public function calculateShiftTotals(CashboxShift $shift): array
    {
        $cashboxId = $shift->cashbox_id;
        $openedAt = $shift->opened_at;
        $closedAt = $shift->closed_at ?? now();

        $studentIn = (float) StudentTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'payment')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $otherIn = (float) OtherPeopleTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'in')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $stockIn = (float) StockMovement::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'sold')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->selectRaw('SUM(qty * unit_price) as total')
            ->value('total');

        $staffIn = (float) StaffTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'repayment')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $cashboxAccount = $this->accounts->ensureForPlace($shift->cashbox);
        $cashboxAccountId = $cashboxAccount->id;

        $transferIn = (float) Transfer::query()
            ->where('to_account_id', $cashboxAccountId)
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $cashIn = round($studentIn + $otherIn + $stockIn + $staffIn + $transferIn, 2);

        $expenseOut = (float) Expense::query()
            ->where('cashbox_id', $cashboxId)
            ->where('payment_method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $studentOut = (float) StudentTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'refund')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $staffOut = (float) StaffTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->whereIn('type', ['salary', 'advance'])
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $supplierOut = (float) SupplierTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'payment')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $otherOut = (float) OtherPeopleTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('type', 'out')
            ->where('method', 'cash')
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $transferOut = (float) Transfer::query()
            ->where('from_account_id', $cashboxAccountId)
            ->whereNull('voided_at')
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt)
            ->sum('amount');

        $cashOut = round($expenseOut + $studentOut + $staffOut + $supplierOut + $otherOut + $transferOut, 2);

        $expected = round($shift->opening_balance + $cashIn - $cashOut, 2);

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected' => $expected,
        ];
    }

    public function closeAndReconcile(
        CashboxShift $shift,
        float $physicalCashCount,
        ?string $notes = null,
        bool $transferToMainSafe = false,
        ?int $closedBy = null
    ): CashboxShift {
        return DB::transaction(function () use ($shift, $physicalCashCount, $notes, $transferToMainSafe, $closedBy): CashboxShift {
            $totals = $this->calculateShiftTotals($shift);
            $expected = $totals['expected'];
            $variance = round($physicalCashCount - $expected, 2);

            $varianceType = match (true) {
                $variance > 0.001 => CashboxShift::VARIANCE_SURPLUS,
                $variance < -0.001 => CashboxShift::VARIANCE_SHORTAGE,
                default => CashboxShift::VARIANCE_NONE,
            };

            $absVariance = abs($variance);
            $journalEntry = null;
            $cashboxAccount = $this->accounts->ensureForPlace($shift->cashbox);

            if ($varianceType === CashboxShift::VARIANCE_SURPLUS) {
                $surplusAccount = $this->accounts->account(AccountService::CODE_CASH_SURPLUS);
                $journalEntry = $this->journal->post(
                    lines: [
                        ['account_id' => $cashboxAccount->id, 'debit' => $absVariance],
                        ['account_id' => $surplusAccount->id, 'credit' => $absVariance],
                    ],
                    date: now()->toDateString(),
                    description: __('general.shift_reconciliation').' — '.$shift->shift_no.' ['.__('general.variance_surplus').']',
                    documentType: CashboxShift::class,
                    documentId: $shift->id
                );
            } elseif ($varianceType === CashboxShift::VARIANCE_SHORTAGE) {
                $shortageAccount = $this->accounts->account(AccountService::CODE_CASH_SHORTAGE);
                $journalEntry = $this->journal->post(
                    lines: [
                        ['account_id' => $shortageAccount->id, 'debit' => $absVariance, 'party_type' => User::class, 'party_id' => $shift->user_id],
                        ['account_id' => $cashboxAccount->id, 'credit' => $absVariance],
                    ],
                    date: now()->toDateString(),
                    description: __('general.shift_reconciliation').' — '.$shift->shift_no.' ['.__('general.variance_shortage').']',
                    documentType: CashboxShift::class,
                    documentId: $shift->id
                );
            }

            $shift->update([
                'closed_at' => now(),
                'status' => CashboxShift::STATUS_RECONCILED,
                'system_cash_in' => $totals['cash_in'],
                'system_cash_out' => $totals['cash_out'],
                'expected_closing_balance' => $expected,
                'physical_cash_count' => round($physicalCashCount, 2),
                'variance_amount' => $variance,
                'variance_type' => $varianceType,
                'variance_notes' => $notes,
                'journal_entry_id' => $journalEntry?->id,
                'closed_by' => $closedBy ?? Auth::id(),
            ]);

            if ($transferToMainSafe && $physicalCashCount > 0) {
                $mainSafe = Cashbox::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $shift->cashbox_id)
                    ->first();

                if ($mainSafe) {
                    $toAccount = $this->accounts->ensureForPlace($mainSafe);
                    Transfer::query()->create([
                        'from_account_id' => $cashboxAccount->id,
                        'to_account_id' => $toAccount->id,
                        'amount' => round($physicalCashCount, 2),
                        'date' => now()->toDateString(),
                        'reference' => '#'.$shift->shift_no,
                        'description' => __('general.transfer_to_main_safe').' — '.$shift->shift_no,
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            return $shift->fresh();
        });
    }
}
