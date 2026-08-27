<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->foreignId('original_transaction_id')
                ->nullable()
                ->after('registration_id')
                ->constrained('student_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->dropForeign(['original_transaction_id']);
            $table->dropColumn('original_transaction_id');
        });
    }
};
