<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch status machine: draft|scheduled|open|in_progress|completed|cancelled.
     * 'full' is DERIVED (capacity reached), never stored. is_active is kept in
     * sync (legacy reads) — status is the source of truth. Backfill maps the
     * legacy flags: active → open; finished (finished_at or a closed batch
     * that actually ran) → completed; closed before running → cancelled.
     */
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('status', 20)->default('open');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason', 255)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('status');
        });

        DB::table('course_batches')->where('is_active', true)->update(['status' => 'open']);

        DB::table('course_batches')
            ->where('is_active', false)
            ->whereNotNull('finished_at')
            ->update(['status' => 'completed']);

        $ran = DB::table('course_batches')
            ->where('is_active', false)
            ->whereNull('finished_at')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('registrations')
                    ->whereColumn('registrations.course_batch_id', 'course_batches.id');
            })
            ->update(['status' => 'completed']);

        $batchIdsWithStudents = DB::table('registrations')
            ->whereNotNull('course_batch_id')
            ->distinct()
            ->pluck('course_batch_id');

        DB::table('course_batches')
            ->where('is_active', false)
            ->whereNull('finished_at')
            ->whereNotIn('id', $batchIdsWithStudents)
            ->update([
                'status' => 'cancelled',
                'cancelled_reason' => 'Legacy closed batch',
                'cancelled_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['status', 'cancelled_at', 'cancelled_reason', 'cancelled_by']);
        });
    }
};