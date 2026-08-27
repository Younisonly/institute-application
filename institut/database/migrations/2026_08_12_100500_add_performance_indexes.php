<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['item_id', 'type', 'voided_at']);
            $table->index(['book_id', 'type', 'voided_at']);
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->index(['salary_month']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['item_id', 'type', 'voided_at']);
            $table->dropIndex(['book_id', 'type', 'voided_at']);
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->dropIndex(['salary_month']);
        });
    }
};