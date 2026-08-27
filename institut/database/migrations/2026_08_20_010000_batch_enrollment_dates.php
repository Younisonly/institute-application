<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. The batch enrollment window becomes REAL dates (start/close of
     *    registration) instead of YYYY-MM months — the UI warns (never blocks)
     *    when a registration's start month falls outside this window.
     * 2. Drop the classroom column (الفصل) — removed from the UI by request.
     */
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('enrollment_start', 10)->nullable()->change();
            $table->string('enrollment_end', 10)->nullable()->change();
        });

        DB::table('course_batches')
            ->whereNotNull('enrollment_start')
            ->update(['enrollment_start' => DB::raw("CONCAT(enrollment_start, '-01')")]);

        DB::table('course_batches')
            ->whereNotNull('enrollment_end')
            ->update(['enrollment_end' => DB::raw("CONCAT(enrollment_end, '-01')")]);

        Schema::table('course_batches', function (Blueprint $table) {
            $table->date('enrollment_start')->nullable()->change();
            $table->date('enrollment_end')->nullable()->change();
            $table->dropColumn('classroom');
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('enrollment_start', 10)->nullable()->change();
            $table->string('enrollment_end', 10)->nullable()->change();
            $table->string('classroom', 100)->nullable()->after('teacher_id');
        });

        DB::table('course_batches')
            ->whereNotNull('enrollment_start')
            ->update(['enrollment_start' => DB::raw("DATE_FORMAT(enrollment_start, '%Y-%m')")]);

        DB::table('course_batches')
            ->whereNotNull('enrollment_end')
            ->update(['enrollment_end' => DB::raw("DATE_FORMAT(enrollment_end, '%Y-%m')")]);

        Schema::table('course_batches', function (Blueprint $table) {
            $table->char('enrollment_start', 7)->nullable()->change();
            $table->char('enrollment_end', 7)->nullable()->change();
        });
    }
};