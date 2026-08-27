<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'student_transactions',
        'supplier_transactions',
        'expenses',
        'staff_transactions',
        'other_people_transactions',
        'transfers',
        'stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'voided_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('voided_by')
                        ->nullable()
                        ->after('void_reason')
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'voided_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['voided_by']);
                    $table->dropColumn('voided_by');
                });
            }
        }
    }
};
