<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceAudit extends Command
{
    protected $signature = 'finance:audit';

    protected $description = 'Verify that the double-entry journal is balanced (sum of debits == sum of credits)';

    public function handle(): int
    {
        $result = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereNull('journal_entries.voided_at')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit  = round((float) ($result->total_debit ?? 0), 2);
        $credit = round((float) ($result->total_credit ?? 0), 2);

        if ($debit !== $credit) {
            $diff = $debit - $credit;
            $message = "UNBALANCED JOURNAL: debit={$debit}, credit={$credit}, diff={$diff}";
            Log::critical('[finance:audit] '.$message);
            $this->error($message);
            return self::FAILURE;
        }

        $this->info("Journal balanced. Debit = Credit = {$debit}");
        return self::SUCCESS;
    }
}
