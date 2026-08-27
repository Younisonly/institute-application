<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->string('status')->default('open'); // open|closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'month']);
            $table->index(['registration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_months');
    }
};
