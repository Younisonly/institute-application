<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Account::query()->firstOrCreate(
            ['code' => '5150'],
            [
                'name_ar'   => 'مصروف الديون المشطوبة',
                'name_en'   => 'Debt Write-Off Expense',
                'type'      => Account::TYPE_EXPENSE,
                'is_system' => true,
            ]
        );
    }

    public function down(): void
    {
        Account::query()
            ->where('code', '5150')
            ->where('is_system', true)
            ->whereDoesntHave('journalEntryLines')
            ->delete();
    }
};
