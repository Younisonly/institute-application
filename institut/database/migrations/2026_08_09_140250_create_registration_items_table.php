<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_items');
    }
};
