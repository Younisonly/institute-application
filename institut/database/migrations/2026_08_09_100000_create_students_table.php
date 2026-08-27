<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('join_date')->nullable();
            $table->string('status')->default('active'); // active|suspended|closed|graduate
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
