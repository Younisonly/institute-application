<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assessments move from batch-scoped (course_batch_id) to course-scoped
 * (course_id): every batch of a course runs the same assessment plan
 * (Yemeni practice — the plan belongs to the course, marks belong to the
 * registration). Backfill from the owning batch, then drop the batch FK.
 * MySQL 1553: the FK constraint must go BEFORE the composite indexes that
 * share its leading column. Guards make it safe against interrupted runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'course_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();
            });
        }

        DB::table('assessments')
            ->whereNull('assessments.course_id')
            ->join('course_batches', 'course_batches.id', '=', 'assessments.course_batch_id')
            ->update(['assessments.course_id' => DB::raw('course_batches.course_id')]);

        if (Schema::hasColumn('assessments', 'course_batch_id')) {
            $batchFk = DB::selectOne(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'assessments'
                   AND CONSTRAINT_NAME = 'assessments_course_batch_id_foreign'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            );

            if ($batchFk !== null) {
                Schema::table('assessments', fn (Blueprint $table) => $table->dropForeign(['course_batch_id']));
            }

            $statusIndex = DB::selectOne(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'assessments'
                   AND INDEX_NAME = 'assessments_course_batch_id_status_index'",
            );

            if ($statusIndex !== null) {
                Schema::table('assessments', fn (Blueprint $table) => $table->dropIndex(['course_batch_id', 'status']));
            }

            $sortIndex = DB::selectOne(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'assessments'
                   AND INDEX_NAME = 'assessments_course_batch_id_sort_order_index'",
            );

            if ($sortIndex !== null) {
                Schema::table('assessments', fn (Blueprint $table) => $table->dropIndex(['course_batch_id', 'sort_order']));
            }

            Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn('course_batch_id'));
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
            $table->index(['course_id', 'status']);
            $table->index(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assessments', 'course_batch_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->foreignId('course_batch_id')->nullable()->constrained()->restrictOnDelete();
            });

            DB::table('assessments')
                ->whereNull('course_batch_id')
                ->join('course_batches', 'course_batches.course_id', '=', 'assessments.course_id')
                ->update(['assessments.course_batch_id' => DB::raw('course_batches.id')]);
        }

        if (Schema::hasColumn('assessments', 'course_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                try {
                    $table->dropIndex(['course_id', 'status']);
                } catch (Throwable) {
                }

                try {
                    $table->dropIndex(['course_id', 'sort_order']);
                } catch (Throwable) {
                }

                $table->dropConstrainedForeignId('course_id');

                if (! Schema::hasColumn('assessments', 'course_batch_id')) {
                    $table->index(['course_batch_id', 'status']);
                    $table->index(['course_batch_id', 'sort_order']);
                }
            });
        }
    }
};