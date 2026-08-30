<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'code' => '1440',
                'name_ar' => 'عجز الصناديق - عهدة المحصل',
                'name_en' => 'Cash Shortage Receivables',
                'type' => 'asset',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Account for cash shortfalls detected during cashier shift reconciliation.',
            ],
            [
                'code' => '4500',
                'name_ar' => 'فائض الصناديق',
                'name_en' => 'Cash Surplus Income',
                'type' => 'income',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Income account for cash surpluses detected during cashier shift reconciliation.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('accounts')->updateOrInsert(['code' => $row['code']], $row);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['1440', '4500'])->delete();
    }
};
