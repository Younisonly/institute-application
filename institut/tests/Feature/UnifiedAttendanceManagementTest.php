<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayrollPeriod;
use App\Models\User;
use App\Filament\Resources\StaffAttendanceResource\Pages;
use App\Services\AttendanceManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedAttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $accountantUser;

    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->accountantUser = User::factory()->create();
        $this->accountantUser->assignRole('accountant');

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->assignRole('teacher');
    }

    protected function createStaff(array $attributes = []): Staff
    {
        return Staff::create(array_merge([
            'name' => 'Test Staff '.rand(100, 999),
            'phone' => '77'.rand(1000000, 9999999),
            'salary_type' => 'monthly',
            'salary_value' => 50000,
            'status' => 'active',
            'is_teacher' => false,
        ], $attributes));
    }

    public function test_master_attendance_overview_page_renders_all_staff(): void
    {
        $teacher = $this->createStaff(['is_teacher' => true, 'name' => 'Teacher Alpha']);
        $employee = $this->createStaff(['is_teacher' => false, 'name' => 'Employee Beta']);

        $this->actingAs($this->adminUser)
            ->get('/admin/staff-attendances')
            ->assertStatus(200)
            ->assertSee('Teacher Alpha')
            ->assertSee('Employee Beta');
    }

    public function test_past_date_attendance_edit_restricted_to_admin(): void
    {
        $staff = $this->createStaff(['is_teacher' => false]);
        $pastDate = Carbon::now()->subDays(3)->toDateString();

        $service = app(AttendanceManagementService::class);

        // Unprivileged teacher attempt on past date should fail
        try {
            $service->saveAttendance([
                'staff_id' => $staff->id,
                'date' => $pastDate,
                'status' => 'present',
                'hours_worked' => 2.0,
            ], $this->teacherUser);
            $this->fail('Unprivileged user should not be able to record attendance on past date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->errors());
        }

        // Admin attempt on past date should succeed
        $att = $service->saveAttendance([
            'staff_id' => $staff->id,
            'date' => $pastDate,
            'status' => 'present',
            'hours_worked' => 2.0,
        ], $this->adminUser);

        $this->assertDatabaseHas('staff_attendances', [
            'id' => $att->id,
            'staff_id' => $staff->id,
            'date' => $pastDate,
            'status' => 'present',
        ]);
    }

    public function test_dynamic_teacher_batch_filtering_and_session_sync(): void
    {
        $teacher = $this->createStaff(['is_teacher' => true]);

        $program = ProgramType::create(['name' => 'Diploma']);
        $course = Course::create([
            'name' => 'English 101',
            'program_type_id' => $program->id,
            'months' => 1,
            'price' => 1000,
        ]);

        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'name' => 'Batch 2026-A',
            'status' => 'in_progress',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'daily_hours' => 2.0,
        ]);

        $service = app(AttendanceManagementService::class);

        $att = $service->saveAttendance([
            'staff_id' => $teacher->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'course_batch_id' => $batch->id,
            'hours_worked' => 2.5,
        ], $this->adminUser);

        $this->assertDatabaseHas('staff_attendances', [
            'staff_id' => $teacher->id,
            'course_batch_id' => $batch->id,
            'hours_worked' => 2.5,
        ]);

        $this->assertDatabaseHas('teaching_sessions', [
            'course_batch_id' => $batch->id,
            'actual_teacher_id' => $teacher->id,
            'status' => 'completed',
            'actual_hours' => 2.5,
        ]);
    }

    public function test_employee_attendance_history_page_renders_and_calculates_stats(): void
    {
        $staff = $this->createStaff(['is_teacher' => true]);

        StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'hours_worked' => 3.0,
        ]);

        $this->actingAs($this->adminUser)
            ->get("/admin/staff-attendances/employee/{$staff->id}")
            ->assertStatus(200)
            ->assertSee($staff->name);
    }

    public function test_employee_attendance_history_date_range_filtering_recalculates_stats(): void
    {
        $staff = $this->createStaff(['is_teacher' => false]);

        StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => '2026-08-01',
            'status' => 'present',
            'hours_worked' => 5.0,
        ]);

        StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => '2026-08-10',
            'status' => 'absent',
            'hours_worked' => 0.0,
        ]);

        StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => '2026-08-20',
            'status' => 'present',
            'hours_worked' => 4.0,
        ]);

        $component = \Livewire\Livewire::actingAs($this->adminUser)
            ->test(Pages\EmployeeAttendanceHistory::class, ['record' => $staff->id]);

        $statsAll = $component->get('stats');
        $this->assertEquals(2, $statsAll['present']);
        $this->assertEquals(1, $statsAll['absent']);
        $this->assertEquals(9.0, $statsAll['hours']);

        // Filter date_range: 2026-08-01 to 2026-08-05
        $component->set('tableFilters.date_range.from_date', '2026-08-01')
            ->set('tableFilters.date_range.to_date', '2026-08-05');

        $statsFiltered = $component->get('stats');
        $this->assertEquals(1, $statsFiltered['present']);
        $this->assertEquals(0, $statsFiltered['absent']);
        $this->assertEquals(5.0, $statsFiltered['hours']);
    }

    public function test_closed_payroll_month_prevents_attendance_edit(): void
    {
        $staff = $this->createStaff(['is_teacher' => false]);
        $closedMonth = '2026-05';

        StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => $closedMonth,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'gross_salary' => 500,
            'net_salary' => 500,
            'status' => 'approved',
        ]);

        $service = app(AttendanceManagementService::class);

        // Unprivileged teacher attempt on closed month fails
        try {
            $service->saveAttendance([
                'staff_id' => $staff->id,
                'date' => '2026-05-15',
                'status' => 'present',
                'hours_worked' => 2.0,
            ], $this->teacherUser);
            $this->fail('Unprivileged user should not be able to edit attendance in closed payroll month');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->errors());
        }

        // Admin attempt on closed month succeeds with retroactive audit log
        $att = $service->saveAttendance([
            'staff_id' => $staff->id,
            'date' => '2026-05-15',
            'status' => 'present',
            'hours_worked' => 2.0,
        ], $this->adminUser);

        $this->assertTrue((bool) $att->getAttribute('is_closed_payroll_edit'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff_attendance.retroactive_edit',
            'entity_id' => $att->id,
        ]);
    }

    public function test_staff_attendance_policy_gates(): void
    {
        $staff = $this->createStaff(['is_teacher' => false]);
        $pastAttendance = StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => Carbon::now()->subDays(5)->toDateString(),
            'status' => 'present',
            'hours_worked' => 4.0,
        ]);

        $todayAttendance = StaffAttendance::create([
            'staff_id' => $staff->id,
            'date' => Carbon::now()->toDateString(),
            'status' => 'present',
            'hours_worked' => 4.0,
        ]);

        // Non-admin (accountant) cannot update/delete past attendance record
        $this->assertFalse($this->accountantUser->can('update', $pastAttendance));
        $this->assertFalse($this->accountantUser->can('delete', $pastAttendance));

        // Non-admin (accountant) CAN update/delete today's attendance record
        $this->assertTrue($this->accountantUser->can('update', $todayAttendance));
        $this->assertTrue($this->accountantUser->can('delete', $todayAttendance));

        // Admin CAN update/delete both past and today's attendance records
        $this->assertTrue($this->adminUser->can('update', $pastAttendance));
        $this->assertTrue($this->adminUser->can('delete', $pastAttendance));
        $this->assertTrue($this->adminUser->can('update', $todayAttendance));
        $this->assertTrue($this->adminUser->can('delete', $todayAttendance));
    }

    public function test_substitute_teacher_attendance_records_substituted_status(): void
    {
        $primaryTeacher = $this->createStaff(['is_teacher' => true, 'name' => 'Primary Teacher']);
        $substituteTeacher = $this->createStaff(['is_teacher' => true, 'name' => 'Substitute Teacher']);

        $program = ProgramType::create(['name' => 'Short Course']);
        $course = Course::create([
            'name' => 'Arabic 101',
            'program_type_id' => $program->id,
            'months' => 1,
            'price' => 2000,
        ]);

        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'teacher_id' => $primaryTeacher->id,
            'name' => 'Batch Sub Test',
            'status' => 'in_progress',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'daily_hours' => 2.0,
        ]);

        $service = app(AttendanceManagementService::class);

        $att = $service->saveAttendance([
            'staff_id' => $substituteTeacher->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'course_batch_id' => $batch->id,
            'primary_teacher_id' => $primaryTeacher->id,
            'actual_teacher_id' => $substituteTeacher->id,
            'hours_worked' => 2.0,
        ], $this->adminUser);

        $this->assertDatabaseHas('teaching_sessions', [
            'course_batch_id' => $batch->id,
            'primary_teacher_id' => $primaryTeacher->id,
            'actual_teacher_id' => $substituteTeacher->id,
            'status' => 'substituted',
        ]);
    }
}
