<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\Course;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CashboxShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCashboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function makeRegistration(Student $student): Registration
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 50000,
            'is_active' => true,
        ]);

        return Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'price_snapshot' => 50000,
            'start_month' => '2026-08',
            'months_count' => 6,
            'status' => 'active',
        ]);
    }

    public function test_cashbox_creation_and_account_link(): void
    {
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-01',
            'name_ar' => 'صندوق الخزينة الفرعية 1',
            'name_en' => 'Cashbox Desk 1',
            'is_default' => false,
            'is_active' => true,
        ]);

        $account = app(AccountService::class)->ensureForPlace($cashbox);

        $this->assertNotNull($account);
        $this->assertEquals('BOX-01', $cashbox->code);
        $this->assertEquals($account->id, $cashbox->fresh()->getAccountId());
    }

    public function test_cashbox_auto_generated_code_uniqueness(): void
    {
        $code1 = Cashbox::generateNextCode();
        $this->assertStringStartsWith('BOX-', $code1);

        $box1 = Cashbox::query()->create([
            'code' => $code1,
            'name_ar' => 'خزينة 1',
            'name_en' => 'Safe 1',
            'is_default' => false,
            'is_active' => true,
        ]);

        $code2 = Cashbox::generateNextCode();
        $this->assertNotEquals($code1, $code2);
        $this->assertStringStartsWith('BOX-', $code2);
    }

    public function test_student_payment_posted_to_specific_cashbox(): void
    {
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-02',
            'name_ar' => 'صندوق المحصل علي',
            'name_en' => 'Cashier Ali',
            'is_default' => false,
            'is_active' => true,
        ]);

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $registration = $this->makeRegistration($student);

        $tx = StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 20000,
            'date' => now()->toDateString(),
            'method' => 'cash',
            'cashbox_id' => $cashbox->id,
        ]);

        $this->assertEquals($cashbox->id, $tx->cashbox_id);

        $cashboxAccount = app(AccountService::class)->ensureForPlace($cashbox);
        $this->assertEquals(20000.0, $cashboxAccount->balance());
    }

    public function test_expense_posted_to_specific_cashbox(): void
    {
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-03',
            'name_ar' => 'خزينة البوفيه',
            'name_en' => 'Buffet Box',
            'is_default' => false,
            'is_active' => true,
        ]);

        $category = ExpenseCategory::query()->create(['name' => 'لوازم']);

        Expense::query()->create([
            'expense_category_id' => $category->id,
            'amount' => 3000,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'cashbox_id' => $cashbox->id,
            'description' => 'شراء ضيافة',
        ]);

        $cashboxAccount = app(AccountService::class)->ensureForPlace($cashbox);
        $this->assertEquals(-3000.0, $cashboxAccount->balance());
    }

    public function test_shift_open_totals_and_reconciliation_surplus(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-04',
            'name_ar' => 'صندوق الرئيسي 4',
            'name_en' => 'Box 4',
            'is_default' => false,
            'is_active' => true,
            'keeper_id' => $user->id,
        ]);

        $service = app(CashboxShiftService::class);
        $shift = $service->openShift($cashbox, $user, 5000);

        $this->assertEquals('open', $shift->status);
        $this->assertEquals(5000.0, $shift->opening_balance);

        $student = Student::create(['name' => 'Sara', 'status' => 'active']);
        $registration = $this->makeRegistration($student);

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 15000,
            'date' => now()->toDateString(),
            'method' => 'cash',
            'cashbox_id' => $cashbox->id,
        ]);

        $totals = $service->calculateShiftTotals($shift);
        $this->assertEquals(15000.0, $totals['cash_in']);
        $this->assertEquals(0.0, $totals['cash_out']);
        $this->assertEquals(20000.0, $totals['expected']);

        $closedShift = $service->closeAndReconcile($shift, 20500, 'فائض 500 ريال', false, $user->id);

        $this->assertEquals('reconciled', $closedShift->status);
        $this->assertEquals('surplus', $closedShift->variance_type);
        $this->assertEquals(500.0, $closedShift->variance_amount);
        $this->assertNotNull($closedShift->journal_entry_id);
    }

    public function test_shift_reconciliation_shortage(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-05',
            'name_ar' => 'صندوق المحصل أحمد',
            'name_en' => 'Cashier Ahmed',
            'is_default' => false,
            'is_active' => true,
            'keeper_id' => $user->id,
        ]);

        $service = app(CashboxShiftService::class);
        $shift = $service->openShift($cashbox, $user, 10000);

        $closedShift = $service->closeAndReconcile($shift, 9800, 'عجز 200 ريال', false, $user->id);

        $this->assertEquals('reconciled', $closedShift->status);
        $this->assertEquals('shortage', $closedShift->variance_type);
        $this->assertEquals(-200.0, $closedShift->variance_amount);
        $this->assertNotNull($closedShift->journal_entry_id);
    }

    public function test_shift_close_auto_transfer_to_main_safe(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $mainSafe = Cashbox::query()->create([
            'code' => 'SAFE-MAIN',
            'name_ar' => 'الخزينة الرئيسية',
            'name_en' => 'Main Safe',
            'is_default' => true,
            'is_active' => true,
        ]);

        $cashierBox = Cashbox::query()->create([
            'code' => 'BOX-DESK',
            'name_ar' => 'صندوق المحصل 1',
            'name_en' => 'Cashier 1',
            'is_default' => false,
            'is_active' => true,
        ]);

        $service = app(CashboxShiftService::class);
        $shift = $service->openShift($cashierBox, $admin, 0);

        $student = Student::create(['name' => 'Omar', 'status' => 'active']);
        $registration = $this->makeRegistration($student);

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 40000,
            'date' => now()->toDateString(),
            'method' => 'cash',
            'cashbox_id' => $cashierBox->id,
        ]);

        $closedShift = $service->closeAndReconcile($shift, 40000, 'تحويل الخزينة الرئيسية', true, $admin->id);

        $this->assertEquals('reconciled', $closedShift->status);

        $this->assertDatabaseHas('transfers', [
            'amount' => 40000.00,
            'reference' => '#'.$shift->shift_no,
        ]);
    }

    public function test_mid_shift_transfer_included_in_shift_totals(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $mainSafe = Cashbox::query()->create([
            'code' => 'SAFE-MAIN-2',
            'name_ar' => 'الخزينة الرئيسية 2',
            'name_en' => 'Main Safe 2',
            'is_default' => false,
            'is_active' => true,
        ]);
        $mainSafeAccount = app(AccountService::class)->ensureForPlace($mainSafe);

        $cashierBox = Cashbox::query()->create([
            'code' => 'BOX-DESK-2',
            'name_ar' => 'صندوق المحصل 2',
            'name_en' => 'Cashier 2',
            'is_default' => false,
            'is_active' => true,
        ]);
        $cashierAccount = app(AccountService::class)->ensureForPlace($cashierBox);

        $service = app(CashboxShiftService::class);
        $shift = $service->openShift($cashierBox, $admin, 10000);

        $student = Student::create(['name' => 'Tariq', 'status' => 'active']);
        $registration = $this->makeRegistration($student);

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 50000,
            'date' => now()->toDateString(),
            'method' => 'cash',
            'cashbox_id' => $cashierBox->id,
        ]);

        \App\Models\Transfer::create([
            'from_account_id' => $cashierAccount->id,
            'to_account_id' => $mainSafeAccount->id,
            'amount' => 30000,
            'date' => now()->toDateString(),
            'reference' => 'MID-SHIFT-01',
            'created_by' => $admin->id,
        ]);

        $totals = $service->calculateShiftTotals($shift);

        $this->assertEquals(50000.0, $totals['cash_in']);
        $this->assertEquals(30000.0, $totals['cash_out']);
        $this->assertEquals(30000.0, $totals['expected']);
    }

    public function test_payment_details_defaults_to_active_open_shift(): void
    {
        $user = User::factory()->create();
        $cashbox = Cashbox::query()->create([
            'code' => 'BOX-PREFILL',
            'name_ar' => 'صندوق الوردية الحالية',
            'name_en' => 'Active Shift Box',
            'is_default' => false,
            'is_active' => true,
        ]);

        $service = app(CashboxShiftService::class);
        $service->openShift($cashbox, $user, 1000);

        $this->actingAs($user);

        $fields = \App\Filament\Forms\Components\PaymentDetails::fields();
        $cashboxField = collect($fields)->firstWhere(fn ($f) => $f->getName() === 'cashbox_id');

        $this->assertNotNull($cashboxField);
        $this->assertEquals($cashbox->id, $cashboxField->getDefaultState());
    }
}
