<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('type'); // in|issue|sold|damaged
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 2)->nullable(); // snapshot at movement time
            $table->date('date');
            $table->foreignId('registration_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable(); // e.g. supplier invoice no
            $table->string('description')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'type', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
