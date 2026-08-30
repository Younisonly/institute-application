<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Course-run and batch-run windows become real dates instead of YYYY-MM
     * month strings: courses.start_date/end_date and course_batches.start_date/
     * end_date. Existing month values are migrated to the 1st / last day of
     * their month so a course that "ran in 2026-12" keeps ending in December.
     * Registration start_month (monthly billing) stays a month — untouched.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'start_date')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable()->after('start_date');
                $table->index('start_date');
                $table->index('end_date');
            });

            if (Schema::hasColumn('courses', 'course_start_month')) {
                DB::statement(
                    "UPDATE courses SET
                        start_date = IF(course_start_month IS NULL, NULL, STR_TO_DATE(CONCAT(course_start_month, '-01'), '%Y-%m-%d')),
                        end_date   = IF(course_end_month IS NULL, NULL, LAST_DAY(STR_TO_DATE(CONCAT(course_end_month, '-01'), '%Y-%m-%d')))"
                );

                Schema::table('courses', function (Blueprint $table) {
                    $table->dropColumn(['course_start_month', 'course_end_month']);
                });
            }
        }

        if (! Schema::hasColumn('course_batches', 'start_date')) {
            Schema::table('course_batches', function (Blueprint $table) {
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable()->after('start_date');
                $table->index('start_date');
                $table->index('end_date');
            });

            if (Schema::hasColumn('course_batches', 'start_month')) {
                DB::statement(
                    "UPDATE course_batches SET
                        start_date = IF(start_month IS NULL, NULL, STR_TO_DATE(CONCAT(start_month, '-01'), '%Y-%m-%d')),
                        end_date   = IF(end_month IS NULL, NULL, LAST_DAY(STR_TO_DATE(CONCAT(end_month, '-01'), '%Y-%m-%d')))"
                );

                Schema::table('course_batches', function (Blueprint $table) {
                    $table->dropColumn(['start_month', 'end_month']);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('course_start_month', 7)->nullable()->after('end_date');
            $table->string('course_end_month', 7)->nullable()->after('course_start_month');
        });

        DB::statement(
            "UPDATE courses SET
                course_start_month = IF(start_date IS NULL, NULL, DATE_FORMAT(start_date, '%Y-%m')),
                course_end_month   = IF(end_date IS NULL, NULL, DATE_FORMAT(end_date, '%Y-%m'))"
        );

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $table->char('start_month', 7)->nullable()->after('end_date');
            $table->char('end_month', 7)->nullable()->after('start_month');
        });

        DB::statement(
            "UPDATE course_batches SET
                start_month = IF(start_date IS NULL, NULL, DATE_FORMAT(start_date, '%Y-%m')),
                end_month   = IF(end_date IS NULL, NULL, DATE_FORMAT(end_date, '%Y-%m'))"
        );

        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};