<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance: one row per teaching session per batch; one row per
     * enrolled (active) student per session. Statuses follow Yemeni practice:
     * a student barred from the final exam when unexcused absence exceeds 25%
     * (=> attendance below 75%). Excused absences never count as absence.
     * Records are never deleted — corrections keep corrected_at/corrected_by
     * and an audit trail.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_batch_id', 'date']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('present'); // present|absent|late|excused
            $table->text('note')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'registration_id'], 'att_rec_session_reg_uniq');
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};