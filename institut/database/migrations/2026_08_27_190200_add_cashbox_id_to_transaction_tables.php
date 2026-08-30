<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'student_transactions',
            'supplier_transactions',
            'staff_transactions',
            'other_people_transactions',
            'expenses',
            'stock_movements',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'student_transactions',
            'supplier_transactions',
            'staff_transactions',
            'other_people_transactions',
            'expenses',
            'stock_movements',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['cashbox_id']);
                $table->dropColumn('cashbox_id');
            });
        }
    }
};
