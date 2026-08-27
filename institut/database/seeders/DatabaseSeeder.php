<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\ExpenseCategory;
use App\Models\InstituteSetting;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JobTitle;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ChartOfAccountsSeeder::class,
        ]);

        foreach (['admin', 'accountant', 'registrar', 'teacher'] as $role) {
            Role::findOrCreate($role);
        }

        foreach (['معلم', 'مدير', 'محاسب', 'سكرتير', 'حارس', 'عامل نظافة', 'موظف إداري'] as $job) {
            JobTitle::query()->firstOrCreate(['name' => $job]);
        }

        foreach (['دورة قصيرة', 'دبلوم'] as $i => $program) {
            ProgramType::query()->firstOrCreate(
                ['name' => $program],
                ['months_count' => $i === 0 ? 6 : 24]
            );
        }

        foreach (['كتب', 'قرطاسية', 'مستلزمات'] as $category) {
            ItemCategory::query()->firstOrCreate(['name' => $category]);
        }

        foreach (['إيجار', 'كهرباء', 'مياه', 'رواتب', 'صيانة', 'إنترنت', 'نفقات أخرى'] as $category) {
            ExpenseCategory::query()->firstOrCreate(['name' => $category]);
        }

        foreach (['مورد الكتب', 'مورد القرطاسية'] as $supplier) {
            Supplier::query()->firstOrCreate(['name' => $supplier]);
        }

        $shortCourse = ProgramType::query()->where('name', 'دورة قصيرة')->first();
        $diploma = ProgramType::query()->where('name', 'دبلوم')->first();

        $morning = Period::query()->firstOrCreate(
            ['name_ar' => 'صباحي'],
            [
                'name_en' => 'Morning',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
            ]
        );
        $evening = Period::query()->firstOrCreate(
            ['name_ar' => 'مسائي'],
            [
                'name_en' => 'Evening',
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'],
            ]
        );

        $englishCourse = Course::query()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الأول'],
            ['program_type_id' => $shortCourse->id, 'months' => 6, 'price' => 35000]
        );
        $englishBatch = $englishCourse->batches()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الأول — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $englishBatch->periods()->syncWithoutDetaching([$morning->id]);

        $pcDiploma = Course::query()->firstOrCreate(
            ['name' => 'دبلوم صيانة الحاسوب'],
            ['program_type_id' => $diploma->id, 'months' => 24, 'price' => 180000]
        );
        $pcDiplomaBatch = $pcDiploma->batches()->firstOrCreate(
            ['name' => 'دبلوم صيانة الحاسوب — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $pcDiplomaBatch->periods()->syncWithoutDetaching([$evening->id]);

        $books = ItemCategory::query()->where('name', 'كتب')->first();
        $stationery = ItemCategory::query()->where('name', 'قرطاسية')->first();
        $bookSupplier = Supplier::query()->where('name', 'مورد الكتب')->first();
        $stationerySupplier = Supplier::query()->where('name', 'مورد القرطاسية')->first();

        Item::query()->firstOrCreate(
            ['name' => 'كتاب اللغة الإنجليزية - المستوى الأول'],
            [
                'category_id' => $books->id,
                'supplier_id' => $bookSupplier->id,
                'stock_qty' => 50,
                'low_stock_threshold' => 5,
                'purchase_price' => 1500,
                'sale_price' => 2000,
            ]
        );
        Item::query()->firstOrCreate(
            ['name' => 'دفتر ملاحظات'],
            [
                'category_id' => $stationery->id,
                'supplier_id' => $stationerySupplier->id,
                'stock_qty' => 100,
                'low_stock_threshold' => 10,
                'purchase_price' => 300,
                'sale_price' => 500,
            ]
        );
        Item::query()->firstOrCreate(
            ['name' => 'قلم حبر'],
            [
                'category_id' => $stationery->id,
                'supplier_id' => $stationerySupplier->id,
                'stock_qty' => 200,
                'low_stock_threshold' => 20,
                'purchase_price' => 100,
                'sale_price' => 200,
            ]
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@institute.local'],
            ['name' => 'Admin', 'password' => 'admin123']
        );
        $admin->assignRole('admin');

        InstituteSetting::query()->firstOrCreate([], [
            'name_ar' => 'تنظيم',
            'name_en' => 'Tanzim',
            'currency_label' => 'YER',
            'current_month' => now()->format('Y-m'),
        ]);

        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
