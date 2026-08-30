<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_salary_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('salary_type', 20)->default('monthly'); // monthly, percentage, per_hour, daily
            $table->decimal('salary_value', 10, 2)->default(0.00);
            $table->decimal('percentage_value', 5, 2)->default(0.00);
            $table->string('payment_frequency', 20)->default('monthly'); // monthly, weekly, biweekly, custom
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'effective_from']);
        });

        Schema::create('staff_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('salary_month', 7); // YYYY-MM
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('base_salary', 10, 2)->default(0.00);
            $table->decimal('worked_hours', 8, 2)->default(0.00);
            $table->decimal('additions_amount', 10, 2)->default(0.00);
            $table->decimal('penalties_amount', 10, 2)->default(0.00);
            $table->decimal('advance_deduction_amount', 10, 2)->default(0.00);
            $table->decimal('gross_salary', 10, 2)->default(0.00);
            $table->decimal('net_salary', 10, 2)->default(0.00);
            $table->string('status', 20)->default('draft'); // draft, calculated, approved, partially_paid, paid, cancelled
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'salary_month']);
            $table->index(['salary_month', 'status']);
        });

        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->after('staff_id')->constrained('staff_payroll_periods')->nullOnDelete();
            $table->decimal('advance_deduction_amount', 10, 2)->default(0.00)->after('penalty_amount');
        });
    }

    public function down(): void
    {
        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->dropForeign(['payroll_period_id']);
            $table->dropColumn(['payroll_period_id', 'advance_deduction_amount']);
        });

        Schema::dropIfExists('staff_payroll_periods');
        Schema::dropIfExists('staff_salary_histories');
    }
};
