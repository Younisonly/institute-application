<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // charge|payment|refund
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('receipt_no')->nullable()->unique();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'type', 'voided_at']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transactions');
    }
};
