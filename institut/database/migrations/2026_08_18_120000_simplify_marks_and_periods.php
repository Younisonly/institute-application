<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpler academic model:
     *  1. Period lives on the BATCH only — dropped from registrations and
     *     courses (course_period pivot); course periods are migrated onto the
     *     course's batches so no schedule info is lost.
     *  2. pass_mark renamed success_marks (the pass/progression threshold a
     *     student needs to move to the next course).
     *  3. The assessments feature (assessments / assessment_results /
     *     re_exam_approvals) is removed — marks are a single final mark per
     *     registration (the simple marks architecture).
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['period_id']);
            $table->dropIndex(['period_id']);
            $table->dropColumn('period_id');
        });

        if (Schema::hasTable('course_period')) {
            $rows = DB::table('course_period')->select('course_id', 'period_id')->get();

            foreach ($rows as $row) {
                $batchIds = DB::table('course_batches')
                    ->where('course_id', $row->course_id)
                    ->pluck('id');

                foreach ($batchIds as $batchId) {
                    DB::table('course_batch_period')->insertOrIgnore([
                        'course_batch_id' => $batchId,
                        'period_id' => $row->period_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::dropIfExists('course_period');

        Schema::table('courses', function (Blueprint $table) {
            $table->renameColumn('pass_mark', 'success_marks');
        });

        Schema::dropIfExists('re_exam_approvals');
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessments');
    }

    public function down(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('exam');
            $table->decimal('max_mark', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(100);
            $table->date('date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('passing_requirement')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['course_id', 'sort_order']);
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->decimal('mark', 8, 2)->nullable();
            $table->string('status', 20)->default('not_recorded');
            $table->string('grade_label', 30)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'registration_id', 'attempt_no'], 'ar_assessment_reg_attempt_uniq');
            $table->index(['registration_id', 'attempt_no']);
        });

        Schema::create('re_exam_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(2);
            $table->string('policy', 20)->default('best');
            $table->decimal('cap_mark', 8, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'registration_id', 'attempt_no'], 'rea_assessment_reg_attempt_uniq');
            $table->index(['assessment_id', 'registration_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->renameColumn('success_marks', 'pass_mark');
        });

        Schema::create('course_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'period_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
            $table->index('period_id');
        });
    }
};
