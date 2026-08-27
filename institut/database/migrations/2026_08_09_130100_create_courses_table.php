<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('program_type_id')->constrained()->restrictOnDelete();
            $table->string('section')->default('morning'); // morning|evening
            $table->unsignedTinyInteger('months');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
            $table->index('section');
            $table->index('program_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
