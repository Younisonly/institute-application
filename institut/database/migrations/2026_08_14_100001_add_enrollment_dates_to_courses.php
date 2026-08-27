<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // Enrollment window: the period during which new students can register
            $table->string('enrollment_start', 7)->nullable()->after('is_active')->comment('YYYY-MM — first month registrations are accepted');
            $table->string('enrollment_end', 7)->nullable()->after('enrollment_start')->comment('YYYY-MM — last month registrations are accepted (inclusive)');
            // Course run window: when the cohort actually runs
            $table->string('course_start_month', 7)->nullable()->after('enrollment_end')->comment('YYYY-MM — first month of the course run');
            $table->string('course_end_month', 7)->nullable()->after('course_start_month')->comment('YYYY-MM — last month of the course run');

            $table->index('enrollment_start');
            $table->index('enrollment_end');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['enrollment_start']);
            $table->dropIndex(['enrollment_end']);
            $table->dropColumn(['enrollment_start', 'enrollment_end', 'course_start_month', 'course_end_month']);
        });
    }
};
