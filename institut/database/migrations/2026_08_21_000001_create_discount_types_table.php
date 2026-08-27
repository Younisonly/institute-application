<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the legacy hardcoded discount types so old records still display correctly
        DB::table('discount_types')->insert([
            ['name' => 'scholarship', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'merit',       'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'sibling',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'full_payment','is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'other',       'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_types');
    }
};
