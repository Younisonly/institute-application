<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\IncomeCategory;
use App\Models\PartyType;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $chart = [
            // Assets
            ['code' => '1100', 'ar' => 'الصندوق (نقدية)', 'en' => 'Cash on hand', 'type' => 'asset', 'system' => true],
            ['code' => '1410', 'ar' => 'ذمم الطلاب', 'en' => 'Student receivables', 'type' => 'asset', 'system' => true],
            ['code' => '1420', 'ar' => 'سلف الموظفين', 'en' => 'Staff advances', 'type' => 'asset', 'system' => true],
            ['code' => '1430', 'ar' => 'أرصدة الأشخاص الآخرين', 'en' => 'Other-people balances', 'type' => 'asset', 'system' => true],
            ['code' => '1510', 'ar' => 'مخزون الكتب', 'en' => 'Books inventory', 'type' => 'asset', 'system' => true],
            ['code' => '1520', 'ar' => 'مخزون المستلزمات', 'en' => 'Items inventory', 'type' => 'asset', 'system' => true],
            // Liabilities
            ['code' => '2110', 'ar' => 'ذمم الموردين', 'en' => 'Suppliers payable', 'type' => 'liability', 'system' => true],
            ['code' => '2120', 'ar' => 'مستحقات رواتب الموظفين', 'en' => 'Staff salary payable', 'type' => 'liability', 'system' => true],
            // Equity
            ['code' => '3100', 'ar' => 'رأس المال', 'en' => 'Capital', 'type' => 'equity', 'system' => true],
            ['code' => '3200', 'ar' => 'الأرباح المرحلة', 'en' => 'Retained earnings', 'type' => 'equity', 'system' => true],
            // Income
            ['code' => '4100', 'ar' => 'الرسوم الدراسية', 'en' => 'Course fees', 'type' => 'income', 'system' => true],
            ['code' => '4200', 'ar' => 'مبيعات الكتب', 'en' => 'Book sales', 'type' => 'income', 'system' => true],
            ['code' => '4300', 'ar' => 'مبيعات المستلزمات', 'en' => 'Items sales', 'type' => 'income', 'system' => true],
            ['code' => '4400', 'ar' => 'إيرادات أخرى', 'en' => 'Other income', 'type' => 'income', 'system' => true],
            ['code' => '4510', 'ar' => 'خصومات وجزاءات الموظفين', 'en' => 'Staff penalty income', 'type' => 'income', 'system' => true],
            // Expenses
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

        foreach (['طالب', 'موظف', 'مورد', 'أخرى'] as $i => $name) {
            PartyType::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => $i !== 3]
            );
        }

        foreach (['تبرعات', 'دعم', 'مبيعات متفرقة'] as $name) {
            IncomeCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
