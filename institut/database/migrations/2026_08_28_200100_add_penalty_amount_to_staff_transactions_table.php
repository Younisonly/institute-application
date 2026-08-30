<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->decimal('penalty_amount', 10, 2)->default(0.00)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('staff_transactions', function (Blueprint $table) {
            $table->dropColumn('penalty_amount');
        });
    }
};
