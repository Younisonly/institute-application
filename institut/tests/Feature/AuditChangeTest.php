<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CourseBatchService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditChangeTest extends TestCase
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

    private function makeCourse(): Course
    {
        $type = ProgramType::create(['name' => 'Dip '.uniqid(), 'months_count' => 24]);

        return Course::create([
            'name' => 'English L1 '.uniqid(),
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'is_active' => true,
        ]);
    }

    private function makeRegistration(Course $course, ?CourseBatch $batch = null): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => Student::create(['name' => 'S'.uniqid(), 'status' => 'active'])->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch?->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id);
    }

    public function test_registration_completion_records_previous_result_in_before(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeRegistration($course);

        app(RegistrationService::class)->complete($registration, (int) $this->admin()->id, 'pass');

        $audit = AuditLog::query()->where('action', 'registration.completed')->firstOrFail();

        $this->assertSame('pending', $audit->before);
        $this->assertSame('pass', $audit->after);
        $this->assertSame('completed', $registration->fresh()->status);
    }

    public function test_batch_status_transition_records_before_and_after(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        app(CourseBatchService::class)->transition($batch, 'in_progress', (int) $this->admin()->id);

        $audit = AuditLog::query()->where('action', 'course_batch.status_changed')->firstOrFail();

        $this->assertSame('open', $audit->before);
        $this->assertSame('in_progress', $audit->after);
    }

    public function test_attendance_records_before_and_after_columns(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $session = app(AttendanceService::class)->createSession($batch, '2026-08-17', null, null, (int) $this->admin()->id);

        app(AttendanceService::class)->recordStatus($session, $registration, 'absent', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session, $registration, 'present', (int) $this->admin()->id);

        $audit = AuditLog::query()
            ->where('action', 'attendance.recorded')
            ->where('entity_type', AttendanceRecord::class)
            ->orderBy('id')
            ->get();

        $this->assertSame('present', $audit->first()->before);
        $this->assertSame('absent', $audit->first()->after);
        $this->assertSame('absent', $audit->last()->before);
        $this->assertSame('present', $audit->last()->after);
    }

    public function test_mark_records_before_and_after_columns(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $registration->saveGrade(15, (int) $this->admin()->id);
        $registration->saveGrade(18, (int) $this->admin()->id);

        $entries = AuditLog::query()
            ->where('action', 'registration.graded')
            ->where('entity_type', Registration::class)
            ->orderBy('id')
            ->get();

        $this->assertSame(15.0, (float) $entries->first()->details['total']);
        $this->assertSame(18.0, (float) $entries->last()->details['total']);
    }
}