<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->decimal('daily_hours', 5, 2)->default(2.00)->after('capacity');
            $table->unsignedSmallInteger('total_hours')->default(30)->after('daily_hours');
            $table->json('working_days')->nullable()->after('total_hours');
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn(['daily_hours', 'total_hours', 'working_days']);
        });
    }
};
