<?php

namespace App\Console\Commands;

use App\Models\JournalEntryLine;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBalancesCommand extends Command
{
    protected $signature = 'finance:reconcile
                            {--students : Reconcile student payment journals vs operational balances}
                            {--suppliers : Reconcile supplier payable journals vs operational balances}
                            {--transfers : Verify transfer_debit/transfer_credit are self-balancing per registration}
                            {--all : Run all reconciliation checks (default when no option given)}';

    protected $description = 'Reconcile operational balances with the double-entry journal (finance:reconcile --all)';

    private int $totalDiscrepancies = 0;

    public function handle(): int
    {
        $runAll = $this->option('all')
            || (! $this->option('students') && ! $this->option('suppliers') && ! $this->option('transfers'));

        $this->info(__('general.reconcile_starting'));

        if ($runAll || $this->option('students')) {
            $this->reconcileStudents();
        }

        if ($runAll || $this->option('suppliers')) {
            $this->reconcileSuppliers();
        }

        if ($runAll || $this->option('transfers')) {
            $this->reconcileTransfers();
        }

        if ($this->totalDiscrepancies === 0) {
            $this->info(__('general.reconcile_complete_clean'));
        } else {
            $this->error(__('general.reconcile_complete_discrepancies', ['count' => $this->totalDiscrepancies]));
        }

        return $this->totalDiscrepancies > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reconcileStudents(): void
    {
        $this->info(__('general.reconcile_students'));
        $discrepancies = 0;

        Student::query()->withTrashed()->each(function (Student $student) use (&$discrepancies): void {
            $journalCredit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereNull('journal_entries.voided_at')
                ->where('party_type', $student->getMorphClass())
                ->where('party_id', $student->id)
                ->sum('credit');

            $journalDebit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereNull('journal_entries.voided_at')
                ->where('party_type', $student->getMorphClass())
                ->where('party_id', $student->id)
                ->sum('debit');

            $netJournal = $journalCredit - $journalDebit;

            $operationalPayments = (float) $student->transactions()
                ->whereNull('voided_at')
                ->where('type', 'payment')
                ->sum('amount');

            $operationalRefunds = (float) $student->transactions()
                ->whereNull('voided_at')
                ->where('type', 'refund')
                ->sum('amount');

            $netOperational = $operationalPayments - $operationalRefunds;

            if (round($netJournal, 2) !== round($netOperational, 2)) {
                $this->error(__('general.reconcile_student_mismatch', [
                    'id'          => $student->id,
                    'journal'     => $netJournal,
                    'operational' => $netOperational,
                ]));
                $discrepancies++;
            }
        });

        $this->totalDiscrepancies += $discrepancies;

        if ($discrepancies === 0) {
            $this->info('  ✓ '.__('general.reconcile_students_ok'));
        }
    }

    private function reconcileSuppliers(): void
    {
        $this->info(__('general.reconcile_suppliers'));
        $discrepancies = 0;

        Supplier::query()->withTrashed()->each(function (Supplier $supplier) use (&$discrepancies): void {
            $journalCredit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereNull('journal_entries.voided_at')
                ->where('party_type', $supplier->getMorphClass())
                ->where('party_id', $supplier->id)
                ->sum('credit');

            $journalDebit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereNull('journal_entries.voided_at')
                ->where('party_type', $supplier->getMorphClass())
                ->where('party_id', $supplier->id)
                ->sum('debit');

            $netJournal = $journalCredit - $journalDebit;
            $netOperational = (float) $supplier->debt - (float) $supplier->paid;

            if (round($netJournal, 2) !== round($netOperational, 2)) {
                $this->error(__('general.reconcile_supplier_mismatch', [
                    'id'          => $supplier->id,
                    'journal'     => $netJournal,
                    'operational' => $netOperational,
                ]));
                $discrepancies++;
            }
        });

        $this->totalDiscrepancies += $discrepancies;

        if ($discrepancies === 0) {
            $this->info('  ✓ '.__('general.reconcile_suppliers_ok'));
        }
    }

    /**
     * Verify that transfer_debit and transfer_credit are self-balancing per registration.
     *
     * Design note: transfers are internal reclassifications — no cash moves, deliberately
     * not journaled. This check confirms the net of debit+credit == 0 for every affected
     * registration so operational balances are never inflated.
     */
    private function reconcileTransfers(): void
    {
        $this->info(__('general.reconcile_transfers'));
        $discrepancies = 0;

        $rows = DB::table('student_transactions')
            ->whereNull('voided_at')
            ->whereIn('type', ['transfer_debit', 'transfer_credit'])
            ->select('registration_id', 'type', DB::raw('SUM(amount) as total'))
            ->groupBy('registration_id', 'type')
            ->get()
            ->groupBy('registration_id');

        foreach ($rows as $registrationId => $types) {
            $debit  = (float) ($types->firstWhere('type', 'transfer_debit')->total  ?? 0);
            $credit = (float) ($types->firstWhere('type', 'transfer_credit')->total ?? 0);

            if (round($debit, 2) !== round($credit, 2)) {
                $this->error(__('general.reconcile_transfer_mismatch', [
                    'registration_id' => $registrationId,
                    'debit'           => $debit,
                    'credit'          => $credit,
                ]));
                $discrepancies++;
            }
        }

        $this->totalDiscrepancies += $discrepancies;

        if ($discrepancies === 0) {
            $this->info('  ✓ '.__('general.reconcile_transfers_ok'));
        }
    }
}
