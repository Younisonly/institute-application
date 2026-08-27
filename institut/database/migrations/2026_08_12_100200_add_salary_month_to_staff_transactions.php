<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->string('salary_month', 7)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->dropColumn('salary_month');
        });
    }
};