<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_no', 30)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained('program_types')->restrictOnDelete();
            $table->string('title_ar');
            $table->string('title_en');
            $table->date('issue_date');
            $table->date('completion_date');
            $table->string('status', 20)->default('issued'); // issued|voided
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->string('verification_code', 40)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('earned_courses')->nullable(); // snapshot: attempt best per course
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['program_id', 'status']);
        });

        Schema::table('institute_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_next_no')->default(1)->after('receipt_next_no');
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropColumn('certificate_next_no');
        });

        Schema::dropIfExists('certificates');
    }
};