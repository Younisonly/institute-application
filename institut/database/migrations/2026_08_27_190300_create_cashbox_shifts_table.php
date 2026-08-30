<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbox_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_no', 50)->unique();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('system_cash_in', 16, 2)->default(0);
            $table->decimal('system_cash_out', 16, 2)->default(0);
            $table->decimal('expected_closing_balance', 16, 2)->default(0);
            $table->decimal('physical_cash_count', 16, 2)->nullable();
            $table->decimal('variance_amount', 16, 2)->default(0);
            $table->string('variance_type', 20)->default('none');
            $table->text('variance_notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbox_shifts');
    }
};
