<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('payment_method')->default('cash'); // cash|transfer|cheque|other
            $table->string('description')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'voided_at']);
            $table->index(['expense_category_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
