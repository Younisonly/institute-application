<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->unsignedBigInteger('book_id')->nullable()->after('item_id');
            $table->unsignedBigInteger('supplier_id')->nullable()->after('book_id');

            $table->foreign('book_id')->references('id')->on('books')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->index('book_id');
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->unsignedBigInteger('book_id')->nullable()->after('item_id');

            $table->foreign('book_id')->references('id')->on('books')->restrictOnDelete();
            $table->index('book_id');
        });

        Schema::table('student_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('method');
            $table->unsignedBigInteger('wallet_id')->nullable()->after('bank_id');
            $table->string('transaction_ref')->nullable()->after('wallet_id');
            $table->unsignedBigInteger('income_account_id')->nullable()->after('transaction_ref');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('income_account_id');

            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('income_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->string('method')->default('cash')->after('reference');
            $table->unsignedBigInteger('bank_id')->nullable()->after('method');
            $table->unsignedBigInteger('wallet_id')->nullable()->after('bank_id');
            $table->string('transaction_ref')->nullable()->after('wallet_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('transaction_ref');

            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
            $table->unsignedBigInteger('wallet_id')->nullable()->after('bank_id');
            $table->string('transaction_ref')->nullable()->after('wallet_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('transaction_ref');

            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('name');
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::table('institute_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('journal_next_no')->default(1)->after('receipt_next_no');
        });

        foreach (['student_transactions' => 'method', 'expenses' => 'payment_method', 'staff_transactions' => 'method'] as $table => $column) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM('cash','transfer','bank','wallet','cheque','other') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `expenses` MODIFY `payment_method` ENUM('cash','transfer','cheque','other') NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE `student_transactions` MODIFY `method` ENUM('cash','transfer','cheque','other') NOT NULL DEFAULT 'cash'");

        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropColumn('journal_next_no');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['bank_id', 'wallet_id', 'transaction_ref', 'journal_entry_id']);
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['method', 'bank_id', 'wallet_id', 'transaction_ref', 'journal_entry_id']);
        });

        Schema::table('student_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['income_account_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['bank_id', 'wallet_id', 'transaction_ref', 'income_account_id', 'journal_entry_id']);
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
            $table->dropIndex(['book_id']);
            $table->dropColumn('book_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['book_id']);
            $table->dropColumn(['book_id', 'supplier_id']);
        });
    }
};
