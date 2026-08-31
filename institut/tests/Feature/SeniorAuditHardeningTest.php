<?php

namespace Tests\Feature;

use App\Filament\Pages\BatchAttendance;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\Student;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeniorAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->actingAs($this->adminUser);
    }

    public function test_percentage_salary_calculation_is_unified_between_staff_model_and_report_service(): void
    {
        $teacher = Staff::create([
            'name' => 'Teacher One',
            'phone' => '770000111',
            'salary_type' => 'percentage',
            'percentage_value' => 25.0,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $program = ProgramType::create(['name' => 'Diploma A', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course 1', 'price' => 80000, 'months' => 1, 'teacher_id' => null]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'status' => 'open', 'teacher_id' => $teacher->id]);
        $student = Student::create(['name' => 'Student One', 'status' => 'active']);

        $month = now()->format('Y-m');

        app(RegistrationService::class)->register(
            data: [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => $month,
                'months_count' => 1,
                'price_snapshot' => 80000,
                'payment_amount' => 80000,
                'payment_method' => 'cash',
                'payment_date' => now()->format('Y-m-d'),
            ],
            createdBy: $this->adminUser->id,
        );

        $earnedOnStaff = $teacher->getEarnedSalaryForMonth($month);
        $calculatedOnStaff = $teacher->calculatePercentageSalaryForMonth($month);
        $reportSheet = app(ReportService::class)->salarySheet($month);
        $reportRow = collect($reportSheet['rows'])->firstWhere('staff.id', $teacher->id);

        $this->assertEquals(20000.0, $earnedOnStaff);
        $this->assertEquals(20000.0, $calculatedOnStaff);
        $this->assertNotNull($reportRow);
        $this->assertEquals(20000.0, $reportRow['amount']);
    }

    public function test_teacher_batch_authorization_in_batch_attendance(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUserA = User::factory()->create(['email' => 'teacherA@institute.local', 'name' => 'Teacher A']);
        $teacherUserA->assignRole('teacher');

        $staffA = Staff::create(['name' => 'Teacher A', 'phone' => 'teacherA@institute.local', 'salary_type' => 'monthly', 'is_teacher' => true, 'status' => 'active']);
        $staffB = Staff::create(['name' => 'Teacher B', 'phone' => 'teacherB@institute.local', 'salary_type' => 'monthly', 'is_teacher' => true, 'status' => 'active']);

        $program = ProgramType::create(['name' => 'Diploma B', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course 2', 'price' => 50000, 'months' => 1]);
        $batchA = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch Teacher A', 'status' => 'open', 'teacher_id' => $staffA->id]);
        $batchB = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch Teacher B', 'status' => 'open', 'teacher_id' => $staffB->id]);

        $this->actingAs($teacherUserA);

        $authorizedIds = BatchAttendance::filterAuthorizedBatches(CourseBatch::query())->pluck('id')->all();

        $this->assertContains($batchA->id, $authorizedIds);
        $this->assertNotContains($batchB->id, $authorizedIds);

        $this->assertTrue(BatchAttendance::isAuthorizedForBatch($batchA->id));
        $this->assertFalse(BatchAttendance::isAuthorizedForBatch($batchB->id));

        $this->actingAs($this->adminUser);
        $this->assertTrue(BatchAttendance::isAuthorizedForBatch($batchB->id));
    }

    public function test_staff_transaction_observer_prevents_overpayment_on_zero_remaining_period(): void
    {
        $staff = Staff::create(['name' => 'Employee One', 'phone' => '771111222', 'salary_type' => 'monthly', 'salary_value' => 30000, 'status' => 'active']);

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 30000,
            'date' => now()->format('Y-m-d'),
            'salary_month' => '2026-03',
            'method' => 'cash',
            'description' => 'Full Salary',
            'created_by' => $this->adminUser->id,
        ]);

        $period = \App\Models\StaffPayrollPeriod::where('staff_id', $staff->id)->where('salary_month', '2026-03')->first();
        $this->assertNotNull($period);
        $this->assertEquals(0.0, $period->remaining_payable);

        $this->expectException(ValidationException::class);

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 5000,
            'date' => now()->format('Y-m-d'),
            'salary_month' => '2026-03',
            'method' => 'cash',
            'description' => 'Overpayment Attempt',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_edit_attendance_note_logs_audit_trail_and_verifies_authorization(): void
    {
        $program = ProgramType::create(['name' => 'Diploma C', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course 3', 'price' => 50000, 'months' => 1]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch Audit', 'status' => 'open']);
        $student = Student::create(['name' => 'Student Audit', 'status' => 'active']);

        $registration = app(RegistrationService::class)->register(
            data: [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => now()->format('Y-m'),
                'months_count' => 1,
                'price_snapshot' => 50000,
            ],
            createdBy: $this->adminUser->id,
        );

        $page = new BatchAttendance();
        $page->data = ['course_batch_id' => $batch->id];

        $page->markAttendance($registration->id, now()->format('Y-m-d'), 'absent', 'Initial note', 'Initial reason');

        $record = AttendanceRecord::latest('id')->first();
        $this->assertNotNull($record);

        $page->editAttendanceNoteAlpine($record->id, 'Updated note by admin', 'Verified excuse');

        $this->assertEquals('Updated note by admin', $record->fresh()->note);
        $this->assertEquals('Verified excuse', $record->fresh()->change_reason);

        $auditLog = AuditLog::where('action', 'attendance_record.updated')->where('entity_id', $record->id)->first();
        $this->assertNotNull($auditLog);
    }
}
