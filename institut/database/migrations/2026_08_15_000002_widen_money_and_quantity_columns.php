<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen money columns to decimal(16,2) (max 99,999,999,999,999.99 — 999B
     * fits comfortably) and quantity/capacity columns to unsignedBigInteger so
     * values up to 999 billion never overflow the column and throw a DB error.
     */
    public function up(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('salary_value', 16, 2)->nullable()->change();
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 16, 2)->change();
            $table->unsignedBigInteger('capacity')->nullable()->change();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('price_snapshot', 16, 2)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('purchase_price', 16, 2)->nullable()->change();
            $table->decimal('sale_price', 16, 2)->nullable()->change();
            $table->unsignedBigInteger('stock_qty')->default(0)->change();
            $table->unsignedBigInteger('low_stock_threshold')->default(5)->change();
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->decimal('unit_price', 16, 2)->change();
            $table->unsignedBigInteger('qty')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 16, 2)->nullable()->change();
            $table->unsignedBigInteger('qty')->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->decimal('debit', 16, 2)->default(0)->change();
            $table->decimal('credit', 16, 2)->default(0)->change();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('other_people_transactions', function (Blueprint $table) {
            $table->decimal('amount', 16, 2)->change();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->decimal('buy_price', 16, 2)->nullable()->change();
            $table->decimal('sale_price', 16, 2)->nullable()->change();
            $table->unsignedBigInteger('stock_qty')->default(0)->change();
            $table->unsignedBigInteger('low_stock_threshold')->default(5)->change();
        });

        Schema::table('fiscal_year_closings', function (Blueprint $table) {
            $table->decimal('net', 16, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('salary_value', 10, 2)->nullable()->change();
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
            $table->unsignedSmallInteger('capacity')->nullable()->change();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('price_snapshot', 10, 2)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable()->change();
            $table->decimal('sale_price', 10, 2)->nullable()->change();
            $table->unsignedInteger('stock_qty')->default(0)->change();
            $table->unsignedInteger('low_stock_threshold')->default(5)->change();
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->unsignedInteger('qty')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->change();
            $table->unsignedInteger('qty')->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->decimal('debit', 10, 2)->default(0)->change();
            $table->decimal('credit', 10, 2)->default(0)->change();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('other_people_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->decimal('buy_price', 10, 2)->nullable()->change();
            $table->decimal('sale_price', 10, 2)->nullable()->change();
            $table->unsignedInteger('stock_qty')->default(0)->change();
            $table->unsignedInteger('low_stock_threshold')->default(5)->change();
        });

        Schema::table('fiscal_year_closings', function (Blueprint $table) {
            $table->decimal('net', 10, 2)->default(0)->change();
        });
    }
};