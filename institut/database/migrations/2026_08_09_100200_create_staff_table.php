<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role'); // teacher|admin_staff
            $table->string('photo_path')->nullable();
            $table->string('contract_no')->nullable();
            $table->string('salary_type'); // monthly|percentage|per_hour
            $table->decimal('salary_value', 10, 2)->nullable(); // monthly amount or hourly rate
            $table->decimal('percentage_value', 5, 2)->nullable(); // % of collected fees
            $table->string('status')->default('active'); // active|inactive
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
            $table->index('salary_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
