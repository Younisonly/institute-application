<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Book;
use App\Models\Course;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategoryAccount;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JobTitle;
use App\Models\OtherPerson;
use App\Models\PartyType;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Supplier;
use App\Models\Wallet;
use App\Services\ReceiptNumberService;
use App\Services\RegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->warn('DemoDataSeeder only runs in local environment. Use --force to override.');
            return;
        }

        $this->command->info('Seeding demo data…');

        $admin = \App\Models\User::query()->where('email', 'admin@institute.local')->first();
        $adminId = $admin?->id ?? 1;

        // ── Bank & Wallet ──────────────────────────────────────────────────────────
        $bank = Bank::query()->firstOrCreate(
            ['name' => 'بنك اليمن والكويت'],
            ['account_no' => '0001234567890', 'branch' => 'صنعاء الرئيسي']
        );

        $wallet = Wallet::query()->firstOrCreate(
            ['name' => 'محفظة فلوسي'],
            ['provider' => 'فلوسي']
        );

        // ── Program Types & Periods ────────────────────────────────────────────────
        $shortCourse = ProgramType::query()->firstOrCreate(
            ['name' => 'دورة قصيرة'],
            ['months_count' => 6]
        );
        $diploma = ProgramType::query()->firstOrCreate(
            ['name' => 'دبلوم'],
            ['months_count' => 24]
        );
        $intensive = ProgramType::query()->firstOrCreate(
            ['name' => 'دورة مكثفة'],
            ['months_count' => 3]
        );

        $morning = Period::query()->firstOrCreate(
            ['name_ar' => 'صباحي'],
            ['name_en' => 'Morning', 'start_time' => '08:00:00', 'end_time' => '10:00:00',
             'days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu']]
        );
        $evening = Period::query()->firstOrCreate(
            ['name_ar' => 'مسائي'],
            ['name_en' => 'Evening', 'start_time' => '16:00:00', 'end_time' => '18:00:00',
             'days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu']]
        );

        // ── Courses ────────────────────────────────────────────────────────────────
        $english1 = Course::query()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الأول'],
            ['program_type_id' => $shortCourse->id, 'months' => 6, 'price' => 35000, 'is_active' => true]
        );
        $english1Batch = $english1->batches()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الأول — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $english1Batch->periods()->syncWithoutDetaching([$morning->id]);

        $english2 = Course::query()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الثاني'],
            ['program_type_id' => $shortCourse->id, 'months' => 6, 'price' => 40000, 'is_active' => true]
        );
        $english2Batch = $english2->batches()->firstOrCreate(
            ['name' => 'اللغة الإنجليزية - المستوى الثاني — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $english2Batch->periods()->syncWithoutDetaching([$evening->id]);

        $pcDiploma = Course::query()->firstOrCreate(
            ['name' => 'دبلوم صيانة الحاسوب'],
            ['program_type_id' => $diploma->id, 'months' => 24, 'price' => 180000, 'is_active' => true]
        );
        $pcDiplomaBatch = $pcDiploma->batches()->firstOrCreate(
            ['name' => 'دبلوم صيانة الحاسوب — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $pcDiplomaBatch->periods()->syncWithoutDetaching([$morning->id, $evening->id]);

        $excel = Course::query()->firstOrCreate(
            ['name' => 'دورة Excel المكثفة'],
            ['program_type_id' => $intensive->id, 'months' => 3, 'price' => 20000, 'is_active' => true]
        );
        $excelBatch = $excel->batches()->firstOrCreate(
            ['name' => 'دورة Excel المكثفة — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $excelBatch->periods()->syncWithoutDetaching([$morning->id]);

        $graphics = Course::query()->firstOrCreate(
            ['name' => 'تصميم جرافيك'],
            ['program_type_id' => $shortCourse->id, 'months' => 6, 'price' => 45000, 'is_active' => true]
        );
        $graphicsBatch = $graphics->batches()->firstOrCreate(
            ['name' => 'تصميم جرافيك — دفعة 2026'],
            ['year' => '2026', 'is_active' => true]
        );
        $graphicsBatch->periods()->syncWithoutDetaching([$evening->id]);

        // ── Item Categories & Items ────────────────────────────────────────────────
        $booksCategory = ItemCategory::query()->firstOrCreate(['name' => 'كتب']);
        $stationery = ItemCategory::query()->firstOrCreate(['name' => 'قرطاسية']);

        $bookSupplier = Supplier::query()->firstOrCreate(
            ['name' => 'مورد الكتب'],
            ['phone' => '967777000001', 'address' => 'صنعاء']
        );
        $stationerySupplier = Supplier::query()->firstOrCreate(
            ['name' => 'مورد القرطاسية'],
            ['phone' => '967777000002', 'address' => 'صنعاء']
        );

        Item::query()->firstOrCreate(
            ['name' => 'كتاب اللغة الإنجليزية - المستوى الأول'],
            ['category_id' => $booksCategory->id, 'supplier_id' => $bookSupplier->id,
             'stock_qty' => 50, 'low_stock_threshold' => 5, 'purchase_price' => 1500, 'sale_price' => 2000]
        );
        Item::query()->firstOrCreate(
            ['name' => 'دفتر ملاحظات'],
            ['category_id' => $stationery->id, 'supplier_id' => $stationerySupplier->id,
             'stock_qty' => 100, 'low_stock_threshold' => 10, 'purchase_price' => 300, 'sale_price' => 500]
        );
        Item::query()->firstOrCreate(
            ['name' => 'قلم حبر'],
            ['category_id' => $stationery->id, 'supplier_id' => $stationerySupplier->id,
             'stock_qty' => 200, 'low_stock_threshold' => 20, 'purchase_price' => 100, 'sale_price' => 200]
        );
        Item::query()->firstOrCreate(
            ['name' => 'أقراص CD فارغة (عبوة 10)'],
            ['category_id' => $stationery->id, 'supplier_id' => $stationerySupplier->id,
             'stock_qty' => 30, 'low_stock_threshold' => 5, 'purchase_price' => 500, 'sale_price' => 800]
        );
        Item::query()->firstOrCreate(
            ['name' => 'USB 8GB'],
            ['category_id' => $stationery->id, 'supplier_id' => $stationerySupplier->id,
             'stock_qty' => 20, 'low_stock_threshold' => 3, 'purchase_price' => 1200, 'sale_price' => 2000]
        );

        // ── Books ──────────────────────────────────────────────────────────────────
        Book::query()->firstOrCreate(
            ['title' => 'English Fundamentals Level 1'],
            ['author' => 'Dr. Ahmed Ali', 'supplier_id' => $bookSupplier->id,
             'course_id' => $english1->id, 'buy_price' => 2000, 'sale_price' => 3000, 'stock_qty' => 30]
        );
        Book::query()->firstOrCreate(
            ['title' => 'Computer Maintenance Guide'],
            ['author' => 'Tech Publishers', 'supplier_id' => $bookSupplier->id,
             'course_id' => $pcDiploma->id, 'buy_price' => 3500, 'sale_price' => 5000, 'stock_qty' => 20]
        );
        Book::query()->firstOrCreate(
            ['title' => 'Excel & Office Basics'],
            ['author' => 'مركز التدريب', 'supplier_id' => $bookSupplier->id,
             'course_id' => $excel->id, 'buy_price' => 1500, 'sale_price' => 2500, 'stock_qty' => 15]
        );

        // ── Expense Categories ─────────────────────────────────────────────────────
        $rentCat = ExpenseCategory::query()->firstOrCreate(['name' => 'إيجار']);
        $electricCat = ExpenseCategory::query()->firstOrCreate(['name' => 'كهرباء']);
        $waterCat = ExpenseCategory::query()->firstOrCreate(['name' => 'مياه']);
        ExpenseCategory::query()->firstOrCreate(['name' => 'رواتب']);
        ExpenseCategory::query()->firstOrCreate(['name' => 'صيانة']);
        ExpenseCategory::query()->firstOrCreate(['name' => 'إنترنت']);
        ExpenseCategory::query()->firstOrCreate(['name' => 'نفقات أخرى']);

        // ── Job Titles & Staff ────────────────────────────────────────────────────
        foreach (['معلم', 'مدير', 'محاسب', 'سكرتير', 'حارس', 'عامل نظافة', 'موظف إداري'] as $job) {
            JobTitle::query()->firstOrCreate(['name' => $job]);
        }
        $teacherJob = JobTitle::query()->where('name', 'معلم')->first();
        $adminJob = JobTitle::query()->where('name', 'مدير')->first();
        $accountantJob = JobTitle::query()->where('name', 'محاسب')->first();

        $staff1 = Staff::query()->firstOrCreate(
            ['name' => 'أستاذ محمد أحمد'],
            ['job_title_id' => $teacherJob->id, 'phone' => '967777100001',
             'salary_type' => 'monthly', 'salary_value' => 80000, 'is_teacher' => true]
        );
        $staff2 = Staff::query()->firstOrCreate(
            ['name' => 'أستاذة فاطمة علي'],
            ['job_title_id' => $teacherJob->id, 'phone' => '967777100002',
             'salary_type' => 'percentage', 'salary_value' => 30, 'is_teacher' => true]
        );
        $staff3 = Staff::query()->firstOrCreate(
            ['name' => 'محاسب خالد عمر'],
            ['job_title_id' => $accountantJob->id, 'phone' => '967777100003',
             'salary_type' => 'per_hour', 'salary_value' => 1500, 'is_teacher' => false]
        );

        // ── Students (10) ─────────────────────────────────────────────────────────
        $students = [];
        $studentData = [
            ['name' => 'أحمد محمد الغامدي', 'gender' => 'male', 'phone' => '967777200001', 'guardian_phone' => '967777200011'],
            ['name' => 'سارة علي الزهراني', 'gender' => 'female', 'phone' => '967777200002', 'guardian_phone' => '967777200012'],
            ['name' => 'محمد عبدالله القحطاني', 'gender' => 'male', 'phone' => '967777200003', 'guardian_phone' => '967777200013'],
            ['name' => 'نور حسن الشمري', 'gender' => 'female', 'phone' => '967777200004', 'guardian_phone' => '967777200014'],
            ['name' => 'عمر سالم العسيري', 'gender' => 'male', 'phone' => '967777200005', 'guardian_phone' => '967777200015'],
            ['name' => 'ريم خالد الدوسري', 'gender' => 'female', 'phone' => '967777200006', 'guardian_phone' => '967777200016'],
            ['name' => 'يوسف إبراهيم المالكي', 'gender' => 'male', 'phone' => '967777200007', 'guardian_phone' => '967777200017'],
            ['name' => 'آمنة فهد الرشيدي', 'gender' => 'female', 'phone' => '967777200008', 'guardian_phone' => '967777200018'],
            ['name' => 'إبراهيم صالح العتيبي', 'gender' => 'male', 'phone' => '967777200009', 'guardian_phone' => '967777200019'],
            ['name' => 'هيفاء جاسم الحربي', 'gender' => 'female', 'phone' => '967777200010', 'guardian_phone' => '967777200020'],
        ];

        foreach ($studentData as $sd) {
            $students[] = Student::query()->firstOrCreate(
                ['phone' => $sd['phone']],
                array_merge($sd, [
                    'status' => 'active',
                    'join_date' => Carbon::now()->subMonths(rand(1, 12)),
                    'address' => 'صنعاء',
                ])
            );
        }

        // ── Registrations & Payments ──────────────────────────────────────────────
        $service = app(RegistrationService::class);
        $receipt = app(ReceiptNumberService::class);
        $currentMonth = now()->format('Y-m');
        $prevMonth = now()->subMonth()->format('Y-m');

        $registrationScenarios = [
            // active with full payment
            ['student' => 0, 'course' => $english1, 'payment' => 35000, 'start' => $currentMonth],
            // active with partial payment
            ['student' => 1, 'course' => $english2, 'payment' => 20000, 'start' => $currentMonth],
            // active no payment
            ['student' => 2, 'course' => $pcDiploma, 'payment' => 0, 'start' => $prevMonth],
            // active full payment
            ['student' => 3, 'course' => $excel, 'payment' => 20000, 'start' => $currentMonth],
            // active partial
            ['student' => 4, 'course' => $graphics, 'payment' => 15000, 'start' => $currentMonth],
            // active
            ['student' => 5, 'course' => $english1, 'payment' => 35000, 'start' => $prevMonth],
            // active
            ['student' => 6, 'course' => $english2, 'payment' => 40000, 'start' => $currentMonth],
            // active
            ['student' => 7, 'course' => $pcDiploma, 'payment' => 100000, 'start' => $prevMonth],
        ];

        foreach ($registrationScenarios as $scenario) {
            $student = $students[$scenario['student']];
            $course = $scenario['course'];

            // Skip if already registered in this course
            $exists = Registration::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                DB::transaction(function () use ($scenario, $student, $course, $adminId, $receipt): void {
                    $registration = Registration::create([
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'course_batch_id' => $course->batches()->enrollable()->value('id'),
                        'price_snapshot' => $course->price,
                        'start_month' => $scenario['start'],
                        'months_count' => $course->months,
                        'status' => 'active',
                        'created_by' => $adminId,
                    ]);

                    // Charge
                    StudentTransaction::create([
                        'registration_id' => $registration->id,
                        'student_id' => $student->id,
                        'type' => 'charge',
                        'amount' => $course->price,
                        'date' => now(),
                        'description' => 'رسوم التسجيل — '.$course->name,
                        'method' => 'cash',
                        'created_by' => $adminId,
                    ]);

                    // Payment
                    if ($scenario['payment'] > 0) {
                        StudentTransaction::create([
                            'registration_id' => $registration->id,
                            'student_id' => $student->id,
                            'type' => 'payment',
                            'amount' => $scenario['payment'],
                            'date' => now(),
                            'method' => 'cash',
                            'receipt_no' => $receipt->next(),
                            'created_by' => $adminId,
                        ]);
                    }
                });
            } catch (\Throwable) {
                // skip if something fails (e.g. duplicate)
            }
        }

        // ── 3 months of Expenses ─────────────────────────────────────────────────
        $expenseData = [
            ['category' => $rentCat, 'amount' => 150000, 'months_ago' => 2],
            ['category' => $electricCat, 'amount' => 25000, 'months_ago' => 2],
            ['category' => $waterCat, 'amount' => 5000, 'months_ago' => 2],
            ['category' => $rentCat, 'amount' => 150000, 'months_ago' => 1],
            ['category' => $electricCat, 'amount' => 27000, 'months_ago' => 1],
            ['category' => $waterCat, 'amount' => 5500, 'months_ago' => 1],
            ['category' => $rentCat, 'amount' => 150000, 'months_ago' => 0],
            ['category' => $electricCat, 'amount' => 24000, 'months_ago' => 0],
            ['category' => $waterCat, 'amount' => 5000, 'months_ago' => 0],
        ];

        foreach ($expenseData as $ed) {
            Expense::query()->create([
                'expense_category_id' => $ed['category']->id,
                'amount' => $ed['amount'],
                'date' => Carbon::now()->subMonths($ed['months_ago'])->startOfMonth(),
                'payment_method' => 'cash',
                'description' => $ed['category']->name.' — '.Carbon::now()->subMonths($ed['months_ago'])->format('Y-m'),
                'created_by' => $adminId,
            ]);
        }

        // ── OtherPerson ───────────────────────────────────────────────────────────
        $partyType = PartyType::query()->firstOrCreate(['name' => 'دائن متنوع']);
        OtherPerson::query()->firstOrCreate(
            ['name' => 'مؤسسة الطاقة للصيانة'],
            ['phone' => '967777300001', 'party_type_id' => $partyType->id]
        );

        $this->command->info('✅ Demo data seeded successfully.');
    }
}
