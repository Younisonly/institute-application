<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // salary|advance|repayment|deduction
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('reference')->nullable(); // e.g. salary month "2026-08"
            $table->string('description')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'type', 'voided_at']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_transactions');
    }
};
