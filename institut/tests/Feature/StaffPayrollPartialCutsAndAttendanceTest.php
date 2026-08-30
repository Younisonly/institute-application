<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\JobTitle;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayrollPeriod;
use App\Models\StaffTransaction;
use App\Services\AccountService;
use App\Services\FinancePostingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPayrollPartialCutsAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_course_workload_configuration_and_batch_inheritance(): void
    {
        $program = \App\Models\ProgramType::create(['name' => 'Diploma Program', 'months_count' => 3]);
        $course = Course::create([
            'name' => 'Graphic Design',
            'program_type_id' => $program->id,
            'months' => 3,
            'price' => 50000,
            'hours_per_session' => 3.00,
            'number_of_sessions' => 20,
            'total_planned_hours' => 60,
            'working_days' => ['sat', 'mon', 'wed'],
            'break_duration' => 15,
        ]);

        $this->assertEquals(3.00, (float) $course->hours_per_session);
        $this->assertEquals(20, $course->number_of_sessions);
        $this->assertEquals(60, $course->total_planned_hours);
        $this->assertEquals(['sat', 'mon', 'wed'], $course->working_days);
        $this->assertEquals(15, $course->break_duration);

        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'GD-101',
            'daily_hours' => $course->hours_per_session,
            'total_hours' => $course->total_planned_hours,
            'working_days' => $course->working_days,
            'break_duration' => $course->break_duration,
        ]);

        $this->assertEquals(3.00, (float) $batch->daily_hours);
        $this->assertEquals(60, $batch->total_hours);
        $this->assertEquals(['sat', 'mon', 'wed'], $batch->working_days);
        $this->assertEquals(15, $batch->break_duration);
    }

    public function test_batch_schedule_and_working_days_configuration(): void
    {
        $program = \App\Models\ProgramType::create(['name' => 'Short Courses', 'months_count' => 1]);
        $course = Course::create(['name' => 'English Level 1', 'program_type_id' => $program->id, 'months' => 1, 'price' => 20000]);
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Eng-101',
            'daily_hours' => 2.50,
            'total_hours' => 40,
            'working_days' => ['sun', 'tue', 'thu'],
        ]);

        $this->assertEquals(2.50, (float) $batch->daily_hours);
        $this->assertEquals(40, $batch->total_hours);
        $this->assertEquals(['sun', 'tue', 'thu'], $batch->working_days);
    }

    public function test_teacher_attendance_drives_hourly_salary_calculation(): void
    {
        $job = JobTitle::create(['name' => 'Teacher']);
        $teacher = Staff::create([
            'name' => 'Ahmad Teacher',
            'job_title_id' => $job->id,
            'salary_type' => 'per_hour',
            'salary_value' => 3000.00,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $program = \App\Models\ProgramType::create(['name' => 'IT Courses', 'months_count' => 1]);
        $course = Course::create(['name' => 'IT Fundamentals', 'program_type_id' => $program->id, 'months' => 1, 'price' => 15000]);
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'IT-01',
            'teacher_id' => $teacher->id,
            'daily_hours' => 2.00,
        ]);

        // Record 5 sessions present (10 hours total)
        for ($i = 1; $i <= 5; $i++) {
            StaffAttendance::create([
                'staff_id' => $teacher->id,
                'course_batch_id' => $batch->id,
                'date' => "2026-03-0{$i}",
                'status' => 'present',
                'hours_worked' => 2.00,
            ]);
        }

        // Earned salary for March 2026 = 10 hours * 3000 YER = 30,000 YER
        $earned = $teacher->getEarnedSalaryForMonth('2026-03');
        $this->assertEquals(30000.00, $earned);
    }

    public function test_backdated_month_payroll_and_multi_installment_payout_cuts(): void
    {
        $job = JobTitle::create(['name' => 'Accountant']);
        $staff = Staff::create([
            'name' => 'Salem Employee',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 100000.00,
            'status' => 'active',
        ]);

        // Create & approve payroll period for March 2026 (100,000 YER)
        $period = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'base_salary' => 100000.00,
            'gross_salary' => 100000.00,
            'net_salary' => 100000.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        app(FinancePostingService::class)->postPayrollApproval($period);

        // Verify Journal Entry for Entitlement Accrual:
        // Debit: Salary Expense (5100) 100,000, Credit: Staff Salary Payable (2120) 100,000
        $salaryExpense = Account::where('code', AccountService::CODE_EXPENSE_SALARIES)->first();
        $salaryPayable = Account::where('code', AccountService::CODE_STAFF_PAYABLE)->first();

        $this->assertEquals('approved', $period->status);
        $this->assertEquals(100000.00, $period->remaining_payable);

        // Installment Cut 1 (50,000 YER paid in June 2026)
        $cut1 = StaffTransaction::create([
            'staff_id' => $staff->id,
            'payroll_period_id' => $period->id,
            'type' => 'salary',
            'amount' => 50000.00,
            'date' => '2026-06-10',
            'salary_month' => '2026-03',
            'method' => 'cash',
        ]);

        $period->refresh();
        $this->assertEquals('partially_paid', $period->status);
        $this->assertEquals(50000.00, $period->remaining_payable);
        $this->assertEquals(50000.00, $period->total_paid);

        // Installment Cut 2 (30,000 YER paid in June 2026)
        $cut2 = StaffTransaction::create([
            'staff_id' => $staff->id,
            'payroll_period_id' => $period->id,
            'type' => 'salary',
            'amount' => 30000.00,
            'date' => '2026-06-20',
            'salary_month' => '2026-03',
            'method' => 'cash',
        ]);

        $period->refresh();
        $this->assertEquals('partially_paid', $period->status);
        $this->assertEquals(20000.00, $period->remaining_payable);

        // Installment Cut 3 (Final 20,000 YER paid in July 2026)
        $cut3 = StaffTransaction::create([
            'staff_id' => $staff->id,
            'payroll_period_id' => $period->id,
            'type' => 'salary',
            'amount' => 20000.00,
            'date' => '2026-07-05',
            'salary_month' => '2026-03',
            'method' => 'cash',
        ]);

        $period->refresh();
        $this->assertEquals('paid', $period->status);
        $this->assertEquals(0.00, $period->remaining_payable);
        $this->assertEquals(100000.00, $period->total_paid);
    }

    public function test_salary_advance_and_recovery_during_payroll(): void
    {
        $job = JobTitle::create(['name' => 'Manager']);
        $staff = Staff::create([
            'name' => 'Khalid Manager',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 80000.00,
            'status' => 'active',
        ]);

        // Issue Salary Advance in Feb 2026 (20,000 YER)
        $advance = StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'advance',
            'amount' => 20000.00,
            'date' => '2026-02-15',
            'method' => 'cash',
        ]);

        $this->assertEquals(20000.00, $staff->outstanding_advance);

        // Pay March Salary with 10,000 YER advance deduction
        $salaryCut = StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 70000.00,
            'advance_deduction_amount' => 10000.00,
            'date' => '2026-03-30',
            'salary_month' => '2026-03',
            'method' => 'cash',
        ]);

        $staff->refresh();
        $this->assertEquals(10000.00, $staff->outstanding_advance);
    }

    public function test_voiding_salary_transaction_reverses_journal_and_recalculates_status(): void
    {
        $job = JobTitle::create(['name' => 'Assistant']);
        $staff = Staff::create([
            'name' => 'Mona Assistant',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 60000.00,
            'status' => 'active',
        ]);

        $cut = StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 60000.00,
            'date' => '2026-04-01',
            'salary_month' => '2026-04',
            'method' => 'cash',
        ]);

        $period = StaffPayrollPeriod::where('staff_id', $staff->id)->where('salary_month', '2026-04')->first();
        $this->assertNotNull($period);
        $this->assertEquals('paid', $period->status);

        // Void the cut transaction
        $cut->void('Entered by mistake');

        $period->refresh();
        $this->assertEquals('approved', $period->status);
        $this->assertEquals(60000.00, $period->remaining_payable);
        $this->assertTrue($cut->isVoided());
    }
}
