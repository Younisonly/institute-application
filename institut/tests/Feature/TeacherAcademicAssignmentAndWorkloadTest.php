<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Staff;
use App\Models\StaffPayrollPeriod;
use App\Models\TeacherAssignment;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAcademicAssignmentAndWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_course_batch_teacher_auto_creates_teacher_assignment_history(): void
    {
        $programType = \App\Models\ProgramType::create([
            'name' => 'General Program',
            'code' => 'GEN',
        ]);

        $course = Course::create([
            'program_type_id' => $programType->id,
            'name' => 'English Level 1',
            'code' => 'ENG-101',
            'price' => 15000,
            'months' => 2,
        ]);

        $teacherA = Staff::create([
            'name' => 'Teacher A',
            'salary_type' => 'per_hour',
            'salary_value' => 1000,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $teacherB = Staff::create([
            'name' => 'Teacher B',
            'salary_type' => 'per_hour',
            'salary_value' => 1200,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        // 1. Create batch with Teacher A
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Batch 1',
            'teacher_id' => $teacherA->id,
            'start_date' => '2026-08-01',
            'daily_hours' => 2.00,
            'total_hours' => 30,
            'status' => 'open',
        ]);

        $assignmentA = TeacherAssignment::where('course_batch_id', $batch->id)
            ->where('staff_id', $teacherA->id)
            ->first();

        $this->assertNotNull($assignmentA);
        $this->assertTrue($assignmentA->is_active);
        $this->assertEquals('primary', $assignmentA->role);

        // 2. Change batch teacher to Teacher B
        $batch->update(['teacher_id' => $teacherB->id]);

        $assignmentA->refresh();
        $this->assertFalse($assignmentA->is_active);
        $this->assertNotNull($assignmentA->end_date);

        $assignmentB = TeacherAssignment::where('course_batch_id', $batch->id)
            ->where('staff_id', $teacherB->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($assignmentB);
        $this->assertEquals('primary', $assignmentB->role);
    }

    public function test_substitute_teacher_logging_and_hourly_payroll_calculation(): void
    {
        $programType = \App\Models\ProgramType::create([
            'name' => 'IT Program',
            'code' => 'IT',
        ]);

        $course = Course::create([
            'program_type_id' => $programType->id,
            'name' => 'IT Fundamentals',
            'code' => 'IT-101',
            'price' => 20000,
            'months' => 1,
        ]);

        $primaryTeacher = Staff::create([
            'name' => 'Primary Teacher',
            'salary_type' => 'per_hour',
            'salary_value' => 1000,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $substituteTeacher = Staff::create([
            'name' => 'Substitute Teacher',
            'salary_type' => 'per_hour',
            'salary_value' => 1500,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'IT Batch A',
            'teacher_id' => $primaryTeacher->id,
            'start_date' => '2026-08-01',
            'daily_hours' => 2.00,
            'status' => 'open',
        ]);

        // Log session taught by substitute teacher
        $session = TeachingSession::create([
            'course_batch_id' => $batch->id,
            'primary_teacher_id' => $primaryTeacher->id,
            'actual_teacher_id' => $substituteTeacher->id,
            'date' => '2026-08-10',
            'status' => 'substituted',
            'planned_hours' => 2.00,
            'actual_hours' => 2.00,
        ]);

        // Student attendance session should be linked automatically
        $attSession = AttendanceSession::where('teaching_session_id', $session->id)->first();
        $this->assertNotNull($attSession);

        // Workload & payroll calculation checks
        $this->assertEquals(0.0, $primaryTeacher->getEarnedSalaryForMonth('2026-08'));
        $this->assertEquals(3000.0, $substituteTeacher->getEarnedSalaryForMonth('2026-08')); // 2 hrs * 1500
    }

    public function test_cannot_modify_teaching_session_in_approved_payroll_month(): void
    {
        $programType = \App\Models\ProgramType::create([
            'name' => 'Math Program',
            'code' => 'MTH',
        ]);

        $teacher = Staff::create([
            'name' => 'Staff Teacher',
            'salary_type' => 'per_hour',
            'salary_value' => 1000,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $course = Course::create([
            'program_type_id' => $programType->id,
            'name' => 'Math',
            'code' => 'MTH-1',
            'price' => 10000,
            'months' => 1,
        ]);

        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Math Batch',
            'teacher_id' => $teacher->id,
            'start_date' => '2026-08-01',
            'daily_hours' => 2.00,
            'status' => 'open',
        ]);

        $session = TeachingSession::create([
            'course_batch_id' => $batch->id,
            'primary_teacher_id' => $teacher->id,
            'actual_teacher_id' => $teacher->id,
            'date' => '2026-08-05',
            'status' => 'completed',
            'planned_hours' => 2.00,
            'actual_hours' => 2.00,
        ]);

        // Approve payroll period for 2026-08
        StaffPayrollPeriod::create([
            'staff_id' => $teacher->id,
            'salary_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_salary' => 2000,
            'worked_hours' => 2,
            'gross_salary' => 2000,
            'net_salary' => 2000,
            'status' => 'approved',
        ]);

        $this->expectException(\RuntimeException::class);
        $session->update(['actual_hours' => 4.00]);
    }
}
