<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assessments (batch-scoped exams/components) and their per-student
     * results WITH attempt numbers: an original attempt is never overwritten
     * by a re-exam — attempt rows accumulate and the configured policy picks
     * the effective mark. Financial/history rules apply: results are never
     * hard-deleted; corrections keep audit entries.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('exam'); // midterm|final|assignment|project|quiz|practical|makeup
            $table->decimal('max_mark', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(100); // % of the final total
            $table->date('date')->nullable();
            $table->string('status', 20)->default('draft'); // draft|open|closed|finalized
            $table->unsignedTinyInteger('passing_requirement')->nullable(); // % of max_mark required
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['course_batch_id', 'status']);
            $table->index(['course_batch_id', 'sort_order']);
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->decimal('mark', 8, 2)->nullable();
            $table->string('status', 20)->default('not_recorded'); // not_recorded|recorded|absent|excused|invalid
            $table->string('grade_label', 30)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'registration_id', 'attempt_no'], 'ar_assessment_reg_attempt_uniq');
            $table->index(['registration_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessments');
    }
};