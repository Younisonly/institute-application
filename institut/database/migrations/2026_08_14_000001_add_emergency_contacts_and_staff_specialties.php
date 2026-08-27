<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('emergency_contact_name', 255)->nullable()->after('guardian_phone');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_phone');
        });

        Schema::create('staff_course_specialties', function (Blueprint $table): void {
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->primary(['staff_id', 'course_id']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation']);
        });

        Schema::dropIfExists('staff_course_specialties');
    }
};
