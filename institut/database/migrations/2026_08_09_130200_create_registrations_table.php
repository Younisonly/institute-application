<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->decimal('price_snapshot', 10, 2);
            $table->string('start_month', 7); // YYYY-MM
            $table->unsignedTinyInteger('months_count');
            $table->string('status')->default('active'); // active|suspended|closed|transferred
            $table->unsignedBigInteger('transferred_to_id')->nullable(); // self-reference on transfer
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('transferred_to_id')->references('id')->on('registrations')->nullOnDelete();
            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index('start_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
