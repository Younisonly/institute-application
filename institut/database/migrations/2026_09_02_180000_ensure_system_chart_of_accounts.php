<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $chart = [
            ['code' => '1100', 'ar' => 'الصندوق (نقدية)', 'en' => 'Cash on hand', 'type' => 'asset', 'system' => true],
            ['code' => '1410', 'ar' => 'ذمم الطلاب', 'en' => 'Student receivables', 'type' => 'asset', 'system' => true],
            ['code' => '1420', 'ar' => 'سلف الموظفين', 'en' => 'Staff advances', 'type' => 'asset', 'system' => true],
            ['code' => '1430', 'ar' => 'أرصدة الأشخاص الآخرين', 'en' => 'Other-people balances', 'type' => 'asset', 'system' => true],
            ['code' => '1440', 'ar' => 'عجز الصناديق - عهدة المحصل', 'en' => 'Cash Shortage Receivables', 'type' => 'asset', 'system' => true],
            ['code' => '1510', 'ar' => 'مخزون الكتب', 'en' => 'Books inventory', 'type' => 'asset', 'system' => true],
            ['code' => '1520', 'ar' => 'مخزون المستلزمات', 'en' => 'Items inventory', 'type' => 'asset', 'system' => true],
            ['code' => '2110', 'ar' => 'ذمم الموردين', 'en' => 'Suppliers payable', 'type' => 'liability', 'system' => true],
            ['code' => '2120', 'ar' => 'مستحقات رواتب الموظفين', 'en' => 'Staff salary payable', 'type' => 'liability', 'system' => true],
            ['code' => '3100', 'ar' => 'رأس المال', 'en' => 'Capital', 'type' => 'equity', 'system' => true],
            ['code' => '3200', 'ar' => 'الأرباح المرحلة', 'en' => 'Retained earnings', 'type' => 'equity', 'system' => true],
            ['code' => '4100', 'ar' => 'الرسوم الدراسية', 'en' => 'Course fees', 'type' => 'income', 'system' => true],
            ['code' => '4200', 'ar' => 'مبيعات الكتب', 'en' => 'Book sales', 'type' => 'income', 'system' => true],
            ['code' => '4300', 'ar' => 'مبيعات المستلزمات', 'en' => 'Items sales', 'type' => 'income', 'system' => true],
            ['code' => '4400', 'ar' => 'إيرادات أخرى', 'en' => 'Other income', 'type' => 'income', 'system' => true],
            ['code' => '4500', 'ar' => 'فائض الصناديق', 'en' => 'Cash Surplus Income', 'type' => 'income', 'system' => true],
            ['code' => '4510', 'ar' => 'خصومات وجزاءات الموظفين', 'en' => 'Staff penalty income', 'type' => 'income', 'system' => true],
            ['code' => '5100', 'ar' => 'الرواتب', 'en' => 'Salaries', 'type' => 'expense', 'system' => true],
            ['code' => '5900', 'ar' => 'نفقات أخرى', 'en' => 'Other expenses', 'type' => 'expense', 'system' => true],
        ];

        foreach ($chart as $row) {
            Account::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name_ar' => $row['ar'],
                    'name_en' => $row['en'],
                    'type' => $row['type'],
                    'is_system' => $row['system'],
                ]
            );
        }
    }

    public function down(): void
    {
        // System accounts should not be dropped
    }
};
