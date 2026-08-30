<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained('course_batches')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->foreignId('primary_teacher_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('actual_teacher_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('completed'); // completed, cancelled, postponed, substituted
            $table->decimal('planned_hours', 5, 2)->default(2.00);
            $table->decimal('actual_hours', 5, 2)->default(2.00);
            $table->string('cancellation_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_batch_id', 'date', 'period_id'], 'uk_batch_date_period');
            $table->index(['actual_teacher_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_sessions');
    }
};
