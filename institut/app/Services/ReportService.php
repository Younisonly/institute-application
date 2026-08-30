<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Book;
use App\Models\Expense;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\OtherPeopleTransaction;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Journal-derived cash flow across the money accounts (cash + bank/wallet
     * places) for a period. The journal is the single source of truth; the
     * per-document lists below are only the detail behind those entries.
     */
    public function journalCashFlow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $placeIds = Account::query()
            ->where(fn ($q) => $q->where('code', AccountService::CODE_CASH)->orWhereNotNull('place_type'))
            ->pluck('id')
            ->all();

        $lines = JournalEntryLine::query()
            ->whereIn('account_id', $placeIds)
            ->whereHas('entry', function ($q) use ($from, $to): void {
                $q->whereNull('voided_at')
                    ->whereDate('date', '>=', $from->toDateString())
                    ->whereDate('date', '<=', $to->toDateString())
                    ->where(fn ($q) => $q->whereNull('reference')->orWhere('reference', 'not like', 'yearly-closing-%'));
            })
            ->with(['entry' => fn ($q) => $q->with(['lines.account', 'lines.party', 'document'])])
            ->orderBy('journal_entry_id')
            ->get();

        $in = 0.0;
        $out = 0.0;
        $refunded = 0.0;
        $entries = [];

        foreach ($lines->groupBy('journal_entry_id') as $entryLines) {
            $entry = $entryLines->first()->entry;
            $entryIn = (float) $entryLines->sum('debit');
            $entryOut = (float) $entryLines->sum('credit');

            // A refund returns money the institute previously recognised as
            // income: the counter-side of a place credit lands on an INCOME
            // account (debit on income = reversal of revenue).
            $counterDebitsOnIncome = $entry->lines
                ->reject(fn (JournalEntryLine $line): bool => in_array($line->account_id, $placeIds, true))
                ->filter(fn (JournalEntryLine $line): bool => (float) $line->debit > 0 && $line->account?->type === 'income')
                ->values();

            $refundAmount = (float) $counterDebitsOnIncome->sum('debit');

            $entries[] = [
                'entry' => $entry,
                'in' => $entryIn,
                'out' => $entryOut,
                'refund' => $refundAmount,
            ];

            $in += $entryIn;
            $out += $entryOut;
            $refunded += $refundAmount;
        }

        $spent = $out - $refunded;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'in' => $in,
            'out' => $out,
            'collected' => $in,
            'refunded' => $refunded,
            'spent' => $spent,
            'net' => $in - $out,
            'entries' => collect($entries),
        ];
    }
    /**
     * Daily cash: what came in, what went out, and the net for one date.
     * Single source of truth is the JOURNAL (journalCashFlow); the lists are
     * the source documents that produced those exact entries.
     */
    public function dailyCash(string $date): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date);

        $payments = StudentTransaction::query()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->get();

        $refunds = StudentTransaction::query()
            ->where('type', 'refund')
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->get();

        $expenses = Expense::query()
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->get();

        $staffPayments = StaffTransaction::query()
            ->whereIn('type', ['salary', 'advance'])
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->with('staff')
            ->get();

        $supplierPayments = SupplierTransaction::query()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->with('supplier')
            ->get();

        $othersIn = OtherPeopleTransaction::query()
            ->where('type', 'in')
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->with('person')
            ->get();

        $othersOut = OtherPeopleTransaction::query()
            ->where('type', 'out')
            ->whereNull('voided_at')
            ->whereDate('date', $date)
            ->with('person')
            ->get();

        $flow = $this->journalCashFlow($day, $day);

        return [
            'date' => $date,
            'payments' => $payments,
            'refunds' => $refunds,
            'expenses' => $expenses,
            'staff_payments' => $staffPayments,
            'supplier_payments' => $supplierPayments,
            'others_in' => $othersIn,
            'others_out' => $othersOut,
            'collected' => $flow['collected'],
            'refunded' => $flow['refunded'],
            'spent' => $flow['spent'],
            'staff_total' => $outStaff = (float) $staffPayments->sum('amount'),
            'supplier_total' => (float) $supplierPayments->sum('amount'),
            'others_out_total' => (float) $othersOut->sum('amount'),
            'net' => $flow['net'],
            'entries' => $flow['entries'],
        ];
    }

    /**
     * Monthly income vs expenses (profit), derived from the JOURNAL so the
     * number always matches the income statement. Refunds and supplier
     * payments are handled by the double-entry side (refunds reduce revenue,
     * supplier payments reduce the payable) — they never distort profit.
     */
    public function profit(string $month): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $to = $from->endOfMonth();

        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereNull('journal_entries.voided_at')
            ->whereDate('journal_entries.date', '>=', $from->toDateString())
            ->whereDate('journal_entries.date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('journal_entries.reference')->orWhere('journal_entries.reference', 'not like', 'yearly-closing-%'))
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->selectRaw('journal_entry_lines.account_id, MAX(accounts.type) AS type, SUM(journal_entry_lines.debit) AS debit, SUM(journal_entry_lines.credit) AS credit')
            ->groupBy('journal_entry_lines.account_id')
            ->withCasts(['debit' => 'decimal:2', 'credit' => 'decimal:2'])
            ->get();

        $income = 0.0;
        $expenses = 0.0;
        $rows = [];

        $accounts = Account::query()
            ->whereIn('id', $totals->pluck('account_id'))
            ->get()
            ->keyBy('id');

        foreach ($totals as $row) {
            $account = $accounts->get($row->account_id);
            if ($account === null) {
                continue;
            }

            $amount = $row->type === 'income'
                ? (float) $row->credit - (float) $row->debit
                : (float) $row->debit - (float) $row->credit;
            $rows[] = [
                'account' => $account,
                'type' => $row->type,
                'amount' => $amount,
            ];
            if ($row->type === 'income') {
                $income += $amount;
            } else {
                $expenses += $amount;
            }
        }

        return [
            'month' => $month,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'rows' => collect($rows)->sortByDesc('amount')->values(),
            'income' => $income,
            'spent' => $expenses,
            'net' => $income - $expenses,
        ];
    }

    /**
     * Students with a remaining balance (derived from transactions only).
     */
    public function arrears(): Collection
    {
        return Student::query()
            ->withBalance()
            ->with(['registrations' => fn ($q) => $q->where('status', 'active')->with('course')])
            ->get()
            ->filter(fn (Student $student): bool => $student->balance > 0)
            ->sortByDesc(fn (Student $student): float => $student->balance)
            ->values();
    }

    /**
     * Registrations filtered for lists/reports. The study period comes from
     * the BATCH — there is no period on the registration itself.
     */
    public function registrationList(?int $courseId, ?int $batchId, ?string $status): Collection
    {
        return Registration::query()
            ->withTotals()
            ->with(['student', 'course', 'batch.periods'])
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($batchId, fn ($q) => $q->where('course_batch_id', $batchId))
            ->orderBy('start_month')
            ->get();
    }

    /**
     * Student payment history (non-voided payments only), optionally filtered.
     */
    public function paymentHistory(?string $from, ?string $to, ?int $studentId, ?int $registrationId): Collection
    {
        return StudentTransaction::query()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($registrationId, fn ($q) => $q->where('registration_id', $registrationId))
            ->with(['student', 'registration.course'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Enrollment: registrations matching month/course/batch/status + status counts.
     */
    public function enrollment(?string $month, ?int $courseId, ?int $batchId, ?string $status): array
    {
        $rows = Registration::query()
            ->with(['student', 'course', 'batch.periods'])
            ->when($month, fn ($q) => $q->where('start_month', $month))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($batchId, fn ($q) => $q->where('course_batch_id', $batchId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('start_month')
            ->orderBy('id')
            ->get();

        return [
            'rows' => $rows,
            'total' => $rows->count(),
            'active' => $rows->where('status', 'active')->count(),
            'suspended' => $rows->where('status', 'suspended')->count(),
            'closed' => $rows->where('status', 'closed')->count(),
            'transferred' => $rows->where('status', 'transferred')->count(),
        ];
    }

    /**
     * Stock inventory query: Items + Books unioned into one Eloquent query
     * (identical column aliases, hydrated as Item rows). Live stock_qty is
     * the single source of truth (opening stock is set on the column, not
     * recorded as a movement, so as-of-date reconstruction is not possible).
     */
    public function inventoryQuery(string $type = 'all', ?int $categoryId = null, bool $lowStockOnly = false)
    {
        $select = fn (string $typeName): array => [
            'id',
            'name',
            \Illuminate\Support\Facades\DB::raw("'{$typeName}' as type"),
            'category',
            'stock',
            'buy_price',
            'sale_price',
            'buy_value',
            'low_stock',
        ];

        $itemQuery = Item::query()
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->select('items.id', 'items.name', 'item_categories.name as category', 'items.stock_qty as stock', 'items.purchase_price as buy_price', 'items.sale_price')
            ->selectRaw('ROUND(items.stock_qty * items.purchase_price, 2) as buy_value')
            ->selectRaw('CASE WHEN items.stock_qty <= items.low_stock_threshold THEN 1 ELSE 0 END as low_stock')
            ->selectRaw("'item' as type")
            ->when($categoryId, fn ($q) => $q->where('items.category_id', $categoryId))
            ->when($lowStockOnly, fn ($q) => $q->whereColumn('items.stock_qty', '<=', 'items.low_stock_threshold'));

        $bookQuery = Book::query()
            ->leftJoin('courses', 'books.course_id', '=', 'courses.id')
            ->select('books.id', 'books.title as name', 'courses.name as category', 'books.stock_qty as stock', 'books.buy_price as buy_price', 'books.sale_price')
            ->selectRaw('ROUND(books.stock_qty * books.buy_price, 2) as buy_value')
            ->selectRaw('CASE WHEN books.stock_qty <= books.low_stock_threshold THEN 1 ELSE 0 END as low_stock')
            ->selectRaw("'book' as type")
            ->when($lowStockOnly, fn ($q) => $q->whereColumn('books.stock_qty', '<=', 'books.low_stock_threshold'));

        $queries = collect();

        if (in_array($type, ['all', 'items'], true)) {
            $queries->push($itemQuery);
        }

        if (in_array($type, ['all', 'books'], true)) {
            $queries->push($bookQuery);
        }

        if ($queries->isEmpty()) {
            return Item::query()->whereRaw('1 = 0');
        }

        $base = $queries->shift();
        $union = $queries->reduce(fn ($q, $next) => $q->union($next), $base);

        return (new Item)->newQueryWithoutScopes()
            ->fromSub($union, 'inventory_items')
            ->orderByDesc('buy_value');
    }

    /**
     * Stock inventory rows (for print/export): merged Items + Books snapshot.
     */
    public function inventory(string $type = 'all', ?int $categoryId = null, bool $lowStockOnly = false): Collection
    {
        return $this->inventoryQuery($type, $categoryId, $lowStockOnly)
            ->get()
            ->map(fn (Item $row): object => (object) [
                'id' => $row->id,
                'name' => $row->name,
                'type' => $row->type,
                'category' => $row->category ?: null,
                'stock' => (int) $row->stock,
                'buy_price' => (float) $row->buy_price,
                'sale_price' => (float) $row->sale_price,
                'buy_value' => (float) $row->buy_value,
                'low_stock' => (int) $row->low_stock === 1,
                'active' => true,
            ])
            ->values();
    }

    /**
     * Salary sheet for one month: computed amount per staff member.
     * Percentage staff share a % of the month's collected fees; per-hour
     * staff need manual hours (recorded via their account page).
     */
    public function salarySheet(string $month): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $to = $from->endOfMonth();

        $collected = (float) StudentTransaction::query()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        $paidTransactions = StaffTransaction::query()
            ->where('type', 'salary')
            ->whereNull('voided_at')
            ->where(function ($q) use ($month): void {
                $q->where('salary_month', $month)
                    ->orWhere(fn ($q2) => $q2->whereNull('salary_month')->where('reference', $month));
            })
            ->get()
            ->keyBy('staff_id');

        $rows = Staff::query()
            ->withTrashed()
            ->with('jobTitle')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (Staff $staff) use ($collected, $paidTransactions, $month, $from, $to): array {
                $paidTx = $paidTransactions->get($staff->id);
                $isPaid = $paidTx !== null;

                if ($isPaid) {
                    $amount = (float) $paidTx->amount;
                    $salaryType = $paidTx->salary_type_snapshot ?? $staff->salary_type;
                } else {
                    $salaryType = $staff->salary_type;
                    if ($staff->salary_type === 'percentage') {
                        $staffCollected = (float) StudentTransaction::query()
                            ->where('type', 'payment')
                            ->whereNull('voided_at')
                            ->whereBetween('date', [$from, $to])
                            ->where(function ($q) use ($staff) {
                                $q->whereHas('registration.course', fn ($q2) => $q2->where('teacher_id', $staff->id))
                                  ->orWhereHas('registration.batch', fn ($q3) => $q3->where('teacher_id', $staff->id));
                            })
                            ->sum('amount');
                        $amount = round(((float) $staff->percentage_value / 100) * $staffCollected, 2);
                    } else {
                        $amount = match ($staff->salary_type) {
                            'monthly' => (float) $staff->salary_value,
                            default => 0.0,
                        };
                    }
                }

                return [
                    'staff' => $staff,
                    'salary_type' => $salaryType,
                    'amount' => $amount,
                    'paid' => $isPaid,
                    'month' => $month,
                ];
            });

        return [
            'month' => $month,
            'collected' => $collected,
            'rows' => $rows,
            'total' => (float) $rows->sum('amount'),
        ];
    }

// ─── Ledger (double-entry) reports

    private function linesQuery(?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null, bool $excludeClosing = false)
    {
        return JournalEntryLine::query()
            ->whereHas('entry', function ($q) use ($from, $to, $excludeClosing): void {
                $q->whereNull('voided_at');
                if ($excludeClosing) {
                    $q->where(fn ($w) => $w
                        ->whereNull('document_type')
                        ->orWhere('document_type', '!=', \App\Models\FiscalYearClosing::class));
                }
                if ($from) {
                    $q->whereDate('date', '>=', $from->toDateString());
                }
                if ($to) {
                    $q->whereDate('date', '<=', $to->toDateString());
                }
            });
    }

    /**
     * Single aggregated pass over all lines: account_id => [debit, credit].
     */
    public function accountTotals(?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null, bool $excludeClosing = false): Collection
    {
        return $this->linesQuery($from, $to, $excludeClosing)
            ->get(['account_id', 'debit', 'credit'])
            ->groupBy('account_id')
            ->map(fn (Collection $lines): array => [
                'debit' => (float) $lines->sum('debit'),
                'credit' => (float) $lines->sum('credit'),
            ]);
    }

    /**
     * Per-account line totals for one calendar year.
     */
    public function yearAccountTotals(int $year, bool $excludeClosing = false): Collection
    {
        return $this->accountTotals(
            \Illuminate\Support\Carbon::createFromDate($year, 1, 1),
            \Illuminate\Support\Carbon::createFromDate($year, 12, 31),
            $excludeClosing,
        );
    }

    public function trialBalance(?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null): array
    {
        $totals = $this->accountTotals($from, $to);

        $rows = Account::query()
            ->get()
            ->map(function (Account $account) use ($totals): array {
                $total = $totals[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];

                return [
                    'account' => $account,
                    'debit' => $total['debit'],
                    'credit' => $total['credit'],
                    'balance' => $account->type === 'income' || $account->type === 'liability' || $account->type === 'equity'
                        ? $total['credit'] - $total['debit']
                        : $total['debit'] - $total['credit'],
                ];
            })
            ->filter(fn (array $row): bool => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return [
            'rows' => $rows,
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
        ];
    }

    public function accountLedger(Account $account, ?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null): array
    {
        $statement = $this->accountStatement($account, $from, $to);

        return [
            'account' => $account,
            'rows' => $statement['rows'],
            'total' => $statement['closing'],
        ];
    }

    /**
     * Real accounting statement for one account: one row per journal line with
     * the entry's other side(s) as the counterparty ("where the money came from /
     * went to"), a running balance in the account's normal-balance sign, and the
     * opening balance before the requested window.
     */
    public function accountStatement(Account $account, ?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null): array
    {
        $sign = $this->normalBalanceMultiplier($account->type);

        $lines = $this->linesQuery($from, $to)
            ->where('account_id', $account->id)
            ->with(['entry' => fn ($q) => $q->with(['lines.account', 'lines.party']), 'party'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereNull('journal_entries.voided_at')
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.id')
            ->get();

        $opening = 0.0;
        if ($from !== null) {
            $openingBefore = (float) JournalEntryLine::query()
                ->where('account_id', $account->id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->whereNull('journal_entries.voided_at')
                ->whereDate('journal_entries.date', '<', $from->toDateString())
                ->sum(\Illuminate\Support\Facades\DB::raw('debit - credit'));

            $opening = $sign * $openingBefore;
        }

        $running = $opening;
        $balances = [];
        $counterparties = [];
        $rows = $lines->map(function (JournalEntryLine $line) use (&$running, &$balances, &$counterparties, $sign): array {
            $amount = (float) $line->debit - (float) $line->credit;
            $running += $sign * $amount;

            $balances[$line->id] = $running;

            $counterparty = $line->entry->lines
                ->reject(fn (JournalEntryLine $other): bool => $other->id === $line->id)
                ->map(function (JournalEntryLine $other): string {
                    $value = (float) $other->debit > 0 ? (float) $other->debit : (float) $other->credit;
                    $partyName = $other->party_id !== null ? ($other->party()->first()?->name ?? '') : '';

                    return trim($other->account->name.' '.($partyName ? '('.$partyName.')' : '').' '.number_format($value));
                })
                ->unique()
                ->implode('، ');

            $counterparties[$line->id] = $counterparty ?: '—';

            return [
                'line_id' => $line->id,
                'entry' => $line->entry,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'party' => $line->party?->name ?? '—',
                'balance' => $running,
            ];
        });

        return [
            'account' => $account,
            'opening' => $opening,
            'rows' => $rows,
            'balances' => $balances,
            'counterparties' => $counterparties,
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
            'closing' => $running,
        ];
    }

    /**
     * +1 for debit-normal accounts (asset, expense), -1 for credit-normal
     * (liability, equity, income) — normal-balance sign convention.
     */
    private function normalBalanceMultiplier(string $type): int
    {
        return in_array($type, ['asset', 'expense'], true) ? 1 : -1;
    }

    /**
     * Subsidiary (party) ledger statement — one register per party, signed so
     * the running balance is the party's balance from the institute's side:
     *
     *   student : debit = charge/refund (receivable rises), credit = payment
     *   staff   : debit = advance, credit = repayment/deduction (owed to us)
     *   supplier: purchases (stock-in movements) are credits — the debt we owe;
     *             payments are debits that reduce the debt
     *   other   : debit = out (we paid them), credit = in (they paid us)
     *
     * Salary rows are intentionally excluded from the staff register — salaries
     * are compensation expense, tracked by the salary sheet, and never change
     * the advances balance. Rows are returned in the same shape as
     * accountStatement() so the statement page, print, and tests treat
     * account-mode and party-mode uniformly.
     */
    public function partyLedger(string $partyType, int $partyId, ?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null, string $staffMode = 'advances'): array
    {
        $party = match ($partyType) {
            'student' => \App\Models\Student::query()->withTrashed()->find($partyId),
            'staff' => \App\Models\Staff::query()->withTrashed()->find($partyId),
            'supplier' => \App\Models\Supplier::query()->find($partyId),
            'other' => \App\Models\OtherPerson::query()->withTrashed()->find($partyId),
            default => null,
        };

        if ($party === null) {
            throw new \InvalidArgumentException(__('general.unknown_party', ['type' => $partyType, 'id' => $partyId]));
        }

        // [type => [side (+1 = debit column, -1 = credit column), balanceDir]]
        // balanceDir +1 = receivable-style (balance = debit − credit),
        // balanceDir -1 = payable-style (balance = credit − debit).
        $sides = match ($partyType) {
            'student' => ['charge' => [1, 1], 'payment' => [-1, 1], 'refund' => [1, 1]],
            'staff' => $staffMode === 'comprehensive'
                ? ['advance' => [1, 1], 'salary' => [1, 1], 'repayment' => [-1, 1], 'deduction' => [-1, 1], 'salary_entitlement' => [-1, 1]]
                : ['advance' => [1, 1], 'repayment' => [-1, 1], 'deduction' => [-1, 1]],
            'supplier' => ['purchase' => [-1, -1], 'payment' => [1, -1]],
            'other' => ['out' => [1, 1], 'in' => [-1, 1]],
        };

        $cutoff = $from?->toDateString();

        $rows = $this->partyRows($partyType, $partyId, $from, $to, $staffMode);
        $opening = 0.0;
        if ($cutoff !== null) {
            $opening = $this->partyRows($partyType, $partyId, null, \Illuminate\Support\Carbon::parse($cutoff)->subDay(), $staffMode)
                ->sum(function (array $r) use ($sides): float {
                    [$side, $balanceDir] = $sides[$r['type']] ?? [0, 1];

                    return $side * $balanceDir * (float) $r['amount'];
                });
        }

        $running = $opening;
        $balances = [];
        $mapped = collect();
        foreach ($rows as $row) {
            [$side, $balanceDir] = $sides[$row['type']] ?? [0, 1];
            $debit = $side > 0 ? (float) $row['amount'] : 0.0;
            $credit = $side < 0 ? (float) $row['amount'] : 0.0;
            $running += $side * $balanceDir * (float) $row['amount'];

            $balances[$row['id']] = $running;
            $mapped->push([
                'id' => $row['id'],
                'date' => $row['date'],
                'description' => $row['description'],
                'reference' => $row['reference'],
                'counterparty' => $row['counterparty'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ]);
        }

        return [
            'party_type' => $partyType,
            'party' => $party,
            'opening' => $opening,
            'rows' => $mapped,
            'balances' => $balances,
            'counterparties' => $mapped->mapWithKeys(fn (array $r): array => [$r['id'] => $r['counterparty']])->all(),
            'totalDebit' => (float) $mapped->sum('debit'),
            'totalCredit' => (float) $mapped->sum('credit'),
            'closing' => $running,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'staff_mode' => $staffMode,
        ];
    }

    /**
     * Raw party register rows (pre-sign), sorted by date. Supplier rows merge
     * stock-in purchases (StockMovement) with payments (SupplierTransaction).
     */
    private function partyRows(string $partyType, int $partyId, ?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null, string $staffMode = 'advances'): \Illuminate\Support\Collection
    {
        $inWindow = function ($query) use ($from, $to) {
            return $query
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to->toDateString()));
        };

        $reference = function ($row): ?string {
            return $row->receipt_no ?? $row->reference ?? $row->transaction_ref ?? null;
        };

        $transactionRows = match ($partyType) {
            'student' => $inWindow(\App\Models\StudentTransaction::query()
                ->where('student_id', $partyId)->whereNull('voided_at'))
                ->with('incomeAccount')->get()
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'date' => $t->date,
                    'description' => $t->description
                        ?: __("general.{$t->type}"),
                    'reference' => $reference($t),
                    'counterparty' => $this->partyCounterpart($t->method),
                    'amount' => (float) $t->amount,
                    'balanceDirection' => 1,
                ]),
            'staff' => $this->staffPartyRows($partyId, $from, $to, $staffMode),
            'other' => $inWindow(\App\Models\OtherPeopleTransaction::query()
                ->where('other_person_id', $partyId)->whereNull('voided_at'))
                ->get()
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'date' => $t->date,
                    'description' => $t->description ?: __("general.{$t->type}"),
                    'reference' => $reference($t),
                    'counterparty' => $this->partyCounterpart($t->method),
                    'amount' => (float) $t->amount,
                    'balanceDirection' => 1,
                ]),
            'supplier' => collect(),
        };

        if ($partyType !== 'supplier') {
            return $transactionRows->sortBy(fn (array $r): string => $r['date']->format('Y-m-d').sprintf('%08d', $r['id']))->values();
        }

        $purchases = $inWindow(\App\Models\StockMovement::query()
            ->where('type', 'in')->where('supplier_id', $partyId))
            ->with(['book', 'item'])->get()
            ->map(fn ($m): array => [
                'id' => $m->id + 1_000_000_000,
                'type' => 'purchase',
                'date' => $m->date,
                'description' => __('general.purchase').' — '.($m->book?->title ?? $m->item?->name ?? ''),
                'reference' => $m->reference,
                'counterparty' => __('general.inventory'),
                'amount' => (float) $m->qty * (float) $m->unit_price,
                'balanceDirection' => -1,
            ]);

        $payments = $inWindow(\App\Models\SupplierTransaction::query()
            ->where('supplier_id', $partyId)->where('type', 'payment')->whereNull('voided_at'))
            ->get()
            ->map(fn ($t): array => [
                'id' => $t->id,
                'type' => 'payment',
                'date' => $t->date,
                'description' => $t->description ?: __('general.supplier_payment'),
                'reference' => $reference($t),
                'counterparty' => $this->partyCounterpart($t->method),
                'amount' => (float) $t->amount,
                'balanceDirection' => -1,
            ]);

        return $purchases->concat($payments)
            ->sortBy(fn (array $r): string => $r['date']->format('Y-m-d').sprintf('%08d', $r['id']))
            ->values();
    }

    private function partyCounterpart(?string $method): string
    {
        return $method ? __("general.method_{$method}") : '—';
    }

    private function staffPartyRows(int $partyId, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, string $staffMode): \Illuminate\Support\Collection
    {
        $query = \App\Models\StaffTransaction::query()
            ->where('staff_id', $partyId)
            ->whereNull('voided_at')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to->toDateString()));

        if ($staffMode !== 'comprehensive') {
            $query->whereIn('type', ['advance', 'repayment', 'deduction']);
        }

        $transactions = $query->orderBy('date')->orderBy('id')->get();

        $reference = fn ($row): ?string => $row->receipt_no ?? $row->reference ?? $row->transaction_ref ?? null;

        if ($staffMode !== 'comprehensive') {
            return $transactions->map(fn ($t): array => [
                'id' => $t->id,
                'type' => $t->type,
                'date' => $t->date,
                'description' => $t->description ?: __("general.{$t->type}"),
                'reference' => $reference($t),
                'counterparty' => $this->partyCounterpart($t->method),
                'amount' => (float) $t->amount,
                'balanceDirection' => 1,
            ]);
        }

        $rows = collect();
        $entitlementIdCounter = 2_000_000_000;

        foreach ($transactions as $t) {
            if ($t->type === 'salary') {
                $rows->push([
                    'id' => $entitlementIdCounter++,
                    'type' => 'salary_entitlement',
                    'date' => $t->date,
                    'description' => __('general.salary_entitlement').($t->salary_month ? " — {$t->salary_month}" : ''),
                    'reference' => $reference($t),
                    'counterparty' => __('general.account_type_expense'),
                    'amount' => (float) $t->amount,
                    'balanceDirection' => 1,
                ]);
            }

            $rows->push([
                'id' => $t->id,
                'type' => $t->type,
                'date' => $t->date,
                'description' => $t->description ?: ($t->type === 'salary' ? __('general.salary').($t->salary_month ? " — {$t->salary_month}" : '') : __("general.{$t->type}")),
                'reference' => $reference($t),
                'counterparty' => $this->partyCounterpart($t->method),
                'amount' => (float) $t->amount,
                'balanceDirection' => 1,
            ]);
        }

        return $rows->sortBy(fn (array $r): string => $r['date']->format('Y-m-d').sprintf('%08d', $r['id']))->values();
    }

    /**
     * Balance of a party CONTROL account that has no journal lines of its own
     * (charges are cash-basis billing rows) — derived from its subsidiary
     * register: 1410 = student receivables, 1430 = other-people balances.
     * 1420 and 2110 are journal-posted, so they use the journal balance.
     */
    public function controlAccountBalance(Account $account): ?float
    {
        return match ($account->code) {
            '1410' => (float) \App\Models\StudentTransaction::query()->whereNull('voided_at')
                ->selectRaw('SUM(CASE WHEN type = "charge" THEN amount WHEN type = "payment" THEN -amount WHEN type = "refund" THEN amount ELSE 0 END) AS bal')
                ->value('bal'),
            '1430' => (float) \App\Models\OtherPeopleTransaction::query()->whereNull('voided_at')
                ->selectRaw('SUM(CASE WHEN type = "out" THEN amount ELSE -amount END) AS bal')
                ->value('bal'),
            default => null,
        };
    }

    public function incomeStatement(?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null): array
    {
        $totals = $this->accountTotals($from, $to, excludeClosing: true);

        $income = Account::query()->ofType('income')->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => $total['credit'] - $total['debit']];
        })->filter(fn (array $row): bool => $row['amount'] != 0);

        $expenses = Account::query()->ofType('expense')->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => $total['debit'] - $total['credit']];
        })->filter(fn (array $row): bool => $row['amount'] != 0);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'totalIncome' => (float) $income->sum('amount'),
            'totalExpenses' => (float) $expenses->sum('amount'),
            'net' => (float) $income->sum('amount') - (float) $expenses->sum('amount'),
        ];
    }

    public function balanceSheet(): array
    {
        $totals = $this->accountTotals();

        $assets = Account::query()->ofType('asset')->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => $total['debit'] - $total['credit']];
        })->filter(fn (array $row): bool => $row['amount'] != 0);

        $liabilities = Account::query()->whereIn('type', ['liability', 'equity'])->get()->map(function (Account $a) use ($totals): array {
            $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

            return ['account' => $a, 'amount' => $total['credit'] - $total['debit']];
        })->filter(fn (array $row): bool => $row['amount'] != 0);

        $lastClosed = \App\Models\FiscalYearClosing::query()->max('year');
        $openFrom = $lastClosed !== null
            ? \Illuminate\Support\Carbon::createFromDate((int) $lastClosed + 1, 1, 1)
            : null;

        $netIncome = (float) $this->incomeStatement($openFrom)['net'];

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'netIncome' => $netIncome,
            'totalAssets' => (float) $assets->sum('amount'),
            'totalLiabilities' => (float) $liabilities->sum('amount') + $netIncome,
        ];
    }

    public function placeBalances(): array
    {
        $totals = $this->accountTotals();

        return Account::query()
            ->where('type', 'asset')
            ->where(fn ($q) => $q->whereIn('code', [AccountService::CODE_CASH])->orWhereNotNull('place_type'))
            ->get()
            ->map(function (Account $a) use ($totals): array {
                $total = $totals[$a->id] ?? ['debit' => 0.0, 'credit' => 0.0];

                return ['account' => $a, 'balance' => $total['debit'] - $total['credit']];
            })
            ->all();
    }
}
