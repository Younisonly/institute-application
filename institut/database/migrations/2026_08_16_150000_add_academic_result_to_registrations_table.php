<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Academic result on enrollments — SEPARATE from enrollment status.
     * status       = lifecycle of the enrollment (active/suspended/completed/...)
     * result       = academic verdict (pending/pass/fail/incomplete/absent/withdrawn)
     * A "completed" enrollment may still be result=incomplete (missing assessments)
     * — the batch ending never implies a pass.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('result', 20)->default('pending')->after('status');
            $table->timestamp('result_finalized_at')->nullable()->after('result');
            $table->foreignId('result_finalized_by')->nullable()->after('result_finalized_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['status', 'result']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['status', 'result']);
            $table->dropConstrainedForeignId('result_finalized_by');
            $table->dropColumn(['result', 'result_finalized_at']);
        });
    }
};