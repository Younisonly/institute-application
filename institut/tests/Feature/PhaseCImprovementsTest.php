<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Certificate;
use App\Models\Item;
use App\Models\Staff;
use App\Models\StaffPayrollPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\FinancePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhaseCImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    public function test_item_and_book_min_stock_qty_threshold(): void
    {
        $item = Item::create([
            'name' => 'Marker Pen',
            'stock_qty' => 3,
            'min_stock_qty' => 5,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue($item->isLowStock());

        $book = Book::create([
            'title' => 'Laravel Guide',
            'stock_qty' => 12,
            'min_stock_qty' => 10,
            'low_stock_threshold' => 15,
            'is_active' => true,
        ]);

        $this->assertFalse($book->isLowStock());
    }

    public function test_certificate_expires_at_date_casting(): void
    {
        $student = Student::create(['name' => 'Tariq', 'status' => 'active']);
        $program = \App\Models\ProgramType::create(['name' => 'IT Diploma', 'months_count' => 12]);

        $certificate = Certificate::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'certificate_no' => 'CERT-2026-0001',
            'title_ar' => 'شهادة حاسوب',
            'title_en' => 'Computer Certificate',
            'issue_date' => '2026-01-01',
            'completion_date' => '2025-12-31',
            'expires_at' => '2028-01-01',
            'status' => 'issued',
            'verification_code' => 'VER-123456',
        ]);

        $this->assertNotNull($certificate->expires_at);
        $this->assertEquals('2028-01-01', $certificate->expires_at->format('Y-m-d'));
    }

    public function test_payroll_approval_sends_notification_to_staff_user(): void
    {
        $admin = $this->admin();

        $staff = Staff::create([
            'name' => 'Teacher Khaled',
            'phone' => '777123456',
            'salary_type' => 'monthly',
            'salary_value' => 60000,
            'status' => 'active',
        ]);

        $staffUser = User::create([
            'name' => 'Teacher Khaled',
            'email' => 'khaled@institute.local',
            'password' => 'password123',
            'staff_id' => $staff->id,
        ]);

        $period = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'base_salary' => 60000,
            'gross_salary' => 60000,
            'net_salary' => 60000,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        app(FinancePostingService::class)->postPayrollApproval($period);

        $this->assertEquals(60000.00, (float) $period->gross_salary);
    }
}
