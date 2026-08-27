<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type'); // asset|liability|equity|income|expense
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('place_type')->nullable();
            $table->unsignedBigInteger('place_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_no')->nullable();
            $table->string('branch')->nullable();
            $table->string('phone')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('phone')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('party_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('other_people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('party_type_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('party_type_id')->references('id')->on('party_types')->nullOnDelete();
            $table->index(['is_active', 'name']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_no')->unique();
            $table->date('date')->index();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->string('document_type')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('reversed_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['document_type', 'document_id']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 10, 2)->default(0);
            $table->decimal('credit', 10, 2)->default(0);
            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->decimal('amount', 10, 2);
            $table->date('date')->index();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('from_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('to_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('income_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('type'); // payment|adjustment
            $table->decimal('amount', 10, 2);
            $table->date('date')->index();
            $table->string('method')->default('cash');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('receipt_no')->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['supplier_id', 'type', 'voided_at']);
        });

        Schema::create('other_people_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('other_person_id');
            $table->string('type'); // in|out
            $table->decimal('amount', 10, 2);
            $table->date('date')->index();
            $table->string('method')->default('cash');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->unsignedBigInteger('income_category_id')->nullable();
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('receipt_no')->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('other_person_id')->references('id')->on('other_people')->cascadeOnDelete();
            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
            $table->foreign('income_category_id')->references('id')->on('income_categories')->nullOnDelete();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['other_person_id', 'type', 'voided_at']);
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('buy_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->text('details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->index(['is_active', 'title']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
        Schema::dropIfExists('other_people_transactions');
        Schema::dropIfExists('supplier_transactions');
        Schema::dropIfExists('income_categories');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('other_people');
        Schema::dropIfExists('party_types');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('accounts');
    }
};
