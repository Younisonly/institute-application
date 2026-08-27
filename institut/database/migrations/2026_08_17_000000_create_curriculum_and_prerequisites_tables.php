<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_course', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('program_types')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level_no')->default(1);
            $table->unsignedTinyInteger('semester_no')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->decimal('credit_hours', 4, 1)->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'course_id']);
            $table->index(['level_no', 'sort_order']);
        });

        Schema::create('course_prerequisites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('prerequisite_course_id')->constrained('courses')->restrictOnDelete();
            $table->string('rule_type', 20)->default('required');
            $table->unsignedInteger('group_no')->nullable();
            $table->decimal('min_mark', 8, 2)->nullable();
            $table->unsignedTinyInteger('min_attendance_percent')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'prerequisite_course_id']);
            $table->index(['rule_type', 'group_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('program_course');
    }
};