<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->foreignId('registration_id')->nullable()->after('student_id')
                ->constrained('registrations')->restrictOnDelete();
            $table->string('method')->default('cash')->after('receipt_no'); // cash|transfer|cheque|other

            $table->index(['registration_id', 'type', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->dropIndex(['registration_id', 'type', 'voided_at']);
            $table->dropColumn(['registration_id', 'method']);
        });
    }
};
