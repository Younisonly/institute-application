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
            $table->string('method')->default('cash')->after('type');
            $table->unsignedBigInteger('bank_id')->nullable()->after('method');
            $table->unsignedBigInteger('wallet_id')->nullable()->after('bank_id');
            $table->string('transaction_ref')->nullable()->after('wallet_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('transaction_ref');

            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index('journal_entry_id');
        });

        DB::statement(
            "UPDATE stock_movements m JOIN journal_entries e
             ON e.document_type = 'App\\Models\\StockMovement' AND e.document_id = m.id AND e.voided_at IS NULL
             SET m.journal_entry_id = e.id"
        );

        Schema::table('accounts', function (Blueprint $table) {
            $table->index('type');
            $table->index(['place_type', 'place_id']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['place_type', 'place_id']);
            $table->dropIndex(['type']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['wallet_id']);
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['journal_entry_id', 'transaction_ref', 'wallet_id', 'bank_id', 'method']);
        });
    }
};
