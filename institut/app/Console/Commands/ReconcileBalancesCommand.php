<?php

namespace App\Console\Commands;

use App\Models\JournalEntryLine;
use App\Models\Student;
use App\Models\Supplier;
use Illuminate\Console\Command;

class ReconcileBalancesCommand extends Command
{
    protected $signature = 'app:reconcile-balances';
    protected $description = 'Reconcile operational balances with the double-entry journal';

    public function handle(): void
    {
        $this->info('Starting balance reconciliation...');
        
        $this->reconcileStudents();
        $this->reconcileSuppliers();
        
        $this->info('Reconciliation complete.');
    }

    private function reconcileStudents(): void
    {
        $this->info('Reconciling Students...');
        $students = Student::withTrashed()->get();
        $discrepancies = 0;

        foreach ($students as $student) {
            $journalCredit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.voided_at', null)
                ->where('party_type', $student->getMorphClass())
                ->where('party_id', $student->id)
                ->sum('credit');

            $journalDebit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.voided_at', null)
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
                $this->error("Student ID {$student->id}: Journal Net Credit ({$netJournal}) != Operational Net Payments ({$netOperational})");
                $discrepancies++;
            }
        }

        if ($discrepancies === 0) {
            $this->info('✓ All student balances reconciled.');
        }
    }

    private function reconcileSuppliers(): void
    {
        $this->info('Reconciling Suppliers...');
        $suppliers = Supplier::withTrashed()->get();
        $discrepancies = 0;

        foreach ($suppliers as $supplier) {
            $journalCredit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.voided_at', null)
                ->where('party_type', $supplier->getMorphClass())
                ->where('party_id', $supplier->id)
                ->sum('credit');

            $journalDebit = (float) JournalEntryLine::query()
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.voided_at', null)
                ->where('party_type', $supplier->getMorphClass())
                ->where('party_id', $supplier->id)
                ->sum('debit');

            $netJournal = $journalCredit - $journalDebit;

            $operationalDebt = $supplier->debt;
            $operationalPaid = $supplier->paid;
            $netOperational = $operationalDebt - $operationalPaid;

            if (round($netJournal, 2) !== round($netOperational, 2)) {
                $this->error("Supplier ID {$supplier->id}: Journal Net Credit ({$netJournal}) != Operational Balance ({$netOperational})");
                $discrepancies++;
            }
        }

        if ($discrepancies === 0) {
            $this->info('✓ All supplier balances reconciled.');
        }
    }
}
