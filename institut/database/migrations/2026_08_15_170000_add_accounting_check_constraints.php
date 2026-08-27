<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level accounting invariants (application validation is not enough):
 *  - a journal line: debit/credit never negative, exactly one side > 0
 *  - a transfer: amount > 0, never self-transfer
 *  - account type is one of the five IFRS-style elements
 * Plus the accounts.description column (chart-of-accounts UI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('description')->nullable()->after('is_active');
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT chk_accounts_type CHECK (type IN ('asset', 'liability', 'equity', 'income', 'expense'))");
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_debit_non_negative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_credit_non_negative CHECK (credit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_single_side CHECK ((debit = 0) <> (credit = 0))');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_not_self CHECK (from_account_id <> to_account_id)');
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS chk_accounts_type');
        DB::statement('ALTER TABLE journal_entry_lines DROP CONSTRAINT IF EXISTS chk_jel_debit_non_negative');
        DB::statement('ALTER TABLE journal_entry_lines DROP CONSTRAINT IF EXISTS chk_jel_credit_non_negative');
        DB::statement('ALTER TABLE journal_entry_lines DROP CONSTRAINT IF EXISTS chk_jel_single_side');
        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_amount_positive');
        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_not_self');
    }
};