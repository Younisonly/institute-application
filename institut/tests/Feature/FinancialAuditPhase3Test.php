<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Course;

use App\Models\InstituteSetting;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialAuditPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    public function test_salary_sheet_uses_rate_snapshot_for_historical_reports(): void
    {
        $staff = Staff::create([
            'name' => 'معلم تاريخ قديم',
            'phone' => '776666666',
            'gender' => 'male',
            'salary_type' => 'monthly',
            'salary_value' => 50000,
            'is_active' => true,
        ]);

        $pastMonth = '2026-01';

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 50000,
            'date' => '2026-01-31',
            'method' => 'cash',
            'reference' => $pastMonth,
            'salary_month' => $pastMonth,
            'rate_snapshot' => 50000,
            'salary_type_snapshot' => 'monthly',
            'created_by' => $this->adminUser()->id,
        ]);

        // Future rate increase for the teacher
        $staff->update(['salary_value' => 90000]);

        $reportService = app(ReportService::class);
        $sheet = $reportService->salarySheet($pastMonth);

        $row = collect($sheet['rows'])->firstWhere('staff.id', $staff->id);
        $this->assertNotNull($row);
        $this->assertEquals(50000, $row['amount']);
    }

    public function test_financial_posting_rejects_backdated_transactions_before_lock_date(): void
    {
        $this->actingAs($this->adminUser());
        $setting = InstituteSetting::current();
        $setting->update(['financial_lock_date' => '2026-08-01']);

        $student = Student::create([
            'name' => 'طالب تاريخ مقفل',
            'phone' => '777777771',
            'gender' => 'male',
        ]);

        $this->expectException(ValidationException::class);

        app(\App\Services\FinancePostingService::class)->postStudentTransaction(
            new StudentTransaction([
                'student_id' => $student->id,
                'type' => 'payment',
                'amount' => 1000,
                'date' => '2026-07-15',
                'method' => 'cash',
                'receipt_no' => 99991,
            ])
        );
    }

    public function test_stock_movement_void_in_blocked_if_insufficient_stock(): void
    {
        $this->actingAs($this->adminUser());
        $book = Book::create([
            'title' => 'كتاب تجربة مخزون',
            'code' => 'BOOK-TEST-001',
            'price' => 1000,
            'stock_qty' => 0,
        ]);

        $inMovement = StockMovement::create([
            'book_id' => $book->id,
            'type' => 'in',
            'qty' => 10,
            'unit_price' => 500,
            'date' => now()->format('Y-m-d'),
            'created_by' => $this->adminUser()->id,
        ]);

        // Stock was increased to 10 by observer. Now simulate issuing/selling 8 books leaving only 2 on shelf
        $book->update(['stock_qty' => 2]);

        $this->expectException(ValidationException::class);

        $inMovement->void('محاولة إلغاء توريد مع نقص في المخزون');
    }

    public function test_transfer_registration_uses_transfer_credit_and_debit_types(): void
    {
        $this->actingAs($this->adminUser());
        $student = Student::create([
            'name' => 'طالب نقل التسجيل',
            'phone' => '778888888',
            'gender' => 'male',
        ]);

        $c1 = Course::query()->first();
        $c2 = Course::create([
            'name' => 'دورة جديدة للنقل',
            'type' => 'short',
            'program_type_id' => $c1->program_type_id,
            'price' => 20000,
            'months' => 1,
            'is_active' => true,
        ]);

        $reg1 = Registration::create([
            'student_id' => $student->id,
            'course_id' => $c1->id,
            'price_snapshot' => 15000,
            'start_month' => now()->format('Y-m'),
            'months_count' => 1,
            'status' => 'active',
            'registered_by' => $this->adminUser()->id,
        ]);

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $reg1->id,
            'type' => 'charge',
            'amount' => 15000,
            'date' => now()->format('Y-m-d'),
        ]);

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $reg1->id,
            'type' => 'payment',
            'amount' => 5000,
            'date' => now()->format('Y-m-d'),
            'receipt_no' => 7771,
        ]);

        // Net balance on reg1 = 10,000
        $newReg = app(RegistrationService::class)->transfer(
            registration: $reg1,
            newCourseId: $c2->id,
            reason: 'نقل دورة لاختبار أنواع التراخيص',
            userId: $this->adminUser()->id,
        );

        $transferOutTx = StudentTransaction::query()
            ->where('registration_id', $reg1->id)
            ->where('type', 'transfer_credit')
            ->first();

        $transferInTx = StudentTransaction::query()
            ->where('registration_id', $newReg->id)
            ->where('type', 'transfer_debit')
            ->first();

        $this->assertNotNull($transferOutTx);
        $this->assertNotNull($transferInTx);
        $this->assertEquals(10000, $transferOutTx->amount);
        $this->assertEquals(10000, $transferInTx->amount);
    }

    public function test_financial_reports_include_soft_deleted_students_with_trashed(): void
    {
        $student = Student::create([
            'name' => 'طالب محذوف مؤقتا',
            'phone' => '779999999',
            'gender' => 'male',
        ]);

        StudentTransaction::create([
            'student_id' => $student->id,
            'type' => 'charge',
            'amount' => 3000,
            'date' => now()->format('Y-m-d'),
        ]);

        $tx = StudentTransaction::create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => 3000,
            'date' => now()->format('Y-m-d'),
            'receipt_no' => 6661,
        ]);

        $student->delete(); // Soft delete now succeeds because balance is zero

        $tx->refresh();
        $this->assertNotNull($tx->student);
        $this->assertEquals('طالب محذوف مؤقتا', $tx->student->name);
    }
}
