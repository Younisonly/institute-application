<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-exam approvals: the gate between the original attempt and a new
     * attempt on the same assessment. Approval is REQUIRED before attempt 2
     * (or later) can be recorded; the policy decides which attempt counts
     * (best | latest | replace) and an optional cap limits the new mark.
     * The original attempt row is never modified.
     */
    public function up(): void
    {
        Schema::create('re_exam_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(2);
            $table->string('policy', 20)->default('best'); // best|latest|replace
            $table->decimal('cap_mark', 8, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'registration_id', 'attempt_no'], 'rea_assessment_reg_attempt_uniq');
            $table->index(['assessment_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_exam_approvals');
    }
};