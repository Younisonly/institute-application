<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_payroll_periods', function (Blueprint $table) {
            $table->index('staff_id');
            $table->dropUnique(['staff_id', 'salary_month']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_payroll_periods', function (Blueprint $table) {
            $table->unique(['staff_id', 'salary_month']);
        });
    }
};
