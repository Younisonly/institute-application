<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FiscalYearClosing;
use App\Models\InstituteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearClosingService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountService $accounts,
        private readonly ReportService $reports,
    ) {
    }

    /**
     * Per-account income/expense balances for a year, ready to be closed.
     */
    public function preview(int $year): array
    {
        $totals = $this->reports->yearAccountTotals($year, excludeClosing: true);

        $income = Account::query()->ofType('income')->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => round((float) $total['credit'] - (float) $total['debit'], 2)];
        })->filter(fn (array $row): bool => $row['amount'] != 0)->values();

        $expenses = Account::query()->ofType('expense')->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => round((float) $total['debit'] - (float) $total['credit'], 2)];
        })->filter(fn (array $row): bool => $row['amount'] != 0)->values();

        return [
            'income' => $income,
            'expenses' => $expenses,
            'totalIncome' => (float) $income->sum('amount'),
            'totalExpenses' => (float) $expenses->sum('amount'),
            'net' => (float) $income->sum('amount') - (float) $expenses->sum('amount'),
        ];
    }

    /**
     * Close a fiscal year: one balanced journal entry zeroes all income/expense
     * accounts into retained earnings, then the year is locked.
     *
     * @throws ValidationException
     */
    public function close(int $year, ?int $userId = null): FiscalYearClosing
    {
        $currentYear = (int) substr(InstituteSetting::query()->firstOrFail()->current_month, 0, 4);
        if ($currentYear <= $year) {
            throw ValidationException::withMessages([
                'year' => __('general.year_not_completed', ['year' => $year]),
            ]);
        }

        $preview = $this->preview($year);
        if ($preview['totalIncome'] == 0 && $preview['totalExpenses'] == 0) {
            throw ValidationException::withMessages([
                'year' => __('general.year_no_activity', ['year' => $year]),
            ]);
        }

        return DB::transaction(function () use ($year, $userId, $preview): FiscalYearClosing {
            if (FiscalYearClosing::query()->where('year', $year)->exists()) {
                throw ValidationException::withMessages([
                    'year' => __('general.year_already_closed', ['year' => $year]),
                ]);
            }

            $lines = $this->buildLines($preview);

            $entry = $this->journal->post(
                lines: $lines,
                date: $year.'-12-31',
                description: __('general.year_closing').' '.$year,
                reference: 'yearly-closing-'.$year,
                documentType: FiscalYearClosing::class,
                userId: $userId,
            );

            $closing = FiscalYearClosing::query()->create([
                'year' => $year,
                'net' => $preview['net'],
                'journal_entry_id' => $entry->id,
                'closed_by' => $userId ?? auth()->id(),
                'closed_at' => now(),
            ]);

            AuditLog::log('fiscal_year.closed', FiscalYearClosing::class, $closing->id, [
                'year' => $year,
                'net' => $preview['net'],
                'entry_no' => $entry->entry_no,
            ]);

            return $closing;
        });
    }

    /**
     * Reopen a closed year: reverse the closing entry (audit trail kept) and unlock it.
     *
     * @throws ValidationException
     */
    public function reopen(int $year, ?int $userId = null): void
    {
        $closing = FiscalYearClosing::query()->where('year', $year)->first();
        if (! $closing) {
            throw ValidationException::withMessages([
                'year' => __('general.year_not_closed', ['year' => $year]),
            ]);
        }

        DB::transaction(function () use ($closing, $year, $userId): void {
            $entry = $closing->journalEntry;
            if ($entry && ! $entry->isVoided()) {
                $this->journal->reverse($entry, __('general.year_reopened').' '.$year, $userId);
            }
            $closing->delete();

            AuditLog::log('fiscal_year.reopened', FiscalYearClosing::class, $closing->id, [
                'year' => $year,
                'entry_no' => $entry?->entry_no,
            ]);
        });
    }

    /**
     * @return array<int, array{account_id: int, debit: float, credit: float}>
     */
    private function buildLines(array $preview): array
    {
        $lines = [];

        foreach ($preview['income'] as $row) {
            if ($row['amount'] > 0) {
                $lines[] = ['account_id' => $row['account']->id, 'debit' => $row['amount']];
            } elseif ($row['amount'] < 0) {
                $lines[] = ['account_id' => $row['account']->id, 'credit' => -$row['amount']];
            }
        }

        foreach ($preview['expenses'] as $row) {
            if ($row['amount'] > 0) {
                $lines[] = ['account_id' => $row['account']->id, 'credit' => $row['amount']];
            } elseif ($row['amount'] < 0) {
                $lines[] = ['account_id' => $row['account']->id, 'debit' => -$row['amount']];
            }
        }

        $net = (float) $preview['net'];
        if ($net > 0) {
            $lines[] = ['account_id' => $this->accounts->account(AccountService::CODE_RETAINED_EARNINGS)->id, 'credit' => round($net, 2)];
        } elseif ($net < 0) {
            $lines[] = ['account_id' => $this->accounts->account(AccountService::CODE_RETAINED_EARNINGS)->id, 'debit' => round(-$net, 2)];
        }

        return $lines;
    }
}
