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
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
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
        $type = ProgramType::create(['name' => 'Short '.uniqid(), 'months_count' => 6]);

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

    private function makeRegistration(Course $course, CourseBatch $batch, ?string $status = null): Registration
    {
        $registration = app(RegistrationService::class)->register([
            'student_id' => Student::create(['name' => 'S'.uniqid(), 'status' => 'active'])->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id);

        if ($status !== null && $status !== $registration->status) {
            $registration->update(['status' => $status]);
        }

        return $registration;
    }

    private function makeSession(CourseBatch $batch, string $date = '2026-08-16'): AttendanceSession
    {
        return app(AttendanceService::class)->createSession(
            $batch,
            $date,
            null,
            null,
            (int) $this->admin()->id,
        );
    }

    public function test_session_creates_present_records_for_active_students_only(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $active = $this->makeRegistration($course, $batch);
        $suspended = $this->makeRegistration($course, $batch, 'suspended');

        $session = $this->makeSession($batch);

        $this->assertSame(1, $session->records()->count());
        $this->assertSame('present', $session->records()->first()->status);
        $this->assertSame($active->id, $session->records()->first()->registration_id);

        $log = AuditLog::query()->where('action', 'attendance.session_created')->firstOrFail();
        $this->assertSame(1, $log->details['students']);
    }

    public function test_status_change_is_audited_and_corrected(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $session = $this->makeSession($batch);

        app(AttendanceService::class)->recordStatus($session, $registration, 'absent', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session, $registration, 'present', (int) $this->admin()->id);

        $record = AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame('present', $record->status);
        $this->assertNotNull($record->corrected_at);

        $audit = AuditLog::query()
            ->where('action', 'attendance.recorded')
            ->where('entity_type', AttendanceRecord::class)
            ->orderBy('id')
            ->get();

        $this->assertSame('absent', $audit->last()->before);
        $this->assertSame('present', $audit->last()->after);

        $this->assertCount(1, AttendanceRecord::query()->where('registration_id', $registration->id)->get());
    }

    public function test_invalid_status_is_rejected(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $session = $this->makeSession($batch);

        try {
            app(AttendanceService::class)->recordStatus($session, $registration, 'dodged', (int) $this->admin()->id);
            $this->fail('Expected ValidationException for invalid status');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_absence_rate_counts_only_unexcused_absences(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $session1 = $this->makeSession($batch, '2026-08-10');
        $session2 = $this->makeSession($batch, '2026-08-12');
        $session3 = $this->makeSession($batch, '2026-08-14');
        $session4 = $this->makeSession($batch, '2026-08-16');

        app(AttendanceService::class)->recordStatus($session1, $registration, 'absent', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session2, $registration, 'absent', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session3, $registration, 'excused', (int) $this->admin()->id);

        $summary = app(AttendanceService::class)->absenceSummary($registration, $batch);

        $this->assertSame(4, $summary['sessions']);
        $this->assertSame(2, $summary['absent']);

        $rate = (2 / 4) * 100;
        $this->assertSame(50.0, $rate);
        $this->assertTrue(app(AttendanceService::class)->isForbiddenFromExam($registration, $batch));
    }

    public function test_student_with_excused_and_late_only_is_never_barred(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $session1 = $this->makeSession($batch, '2026-08-10');
        $session2 = $this->makeSession($batch, '2026-08-12');
        $session3 = $this->makeSession($batch, '2026-08-14');

        app(AttendanceService::class)->recordStatus($session1, $registration, 'late', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session2, $registration, 'excused', (int) $this->admin()->id);

        $this->assertFalse(app(AttendanceService::class)->isForbiddenFromExam($registration, $batch));
    }

    public function test_attendance_page_renders_and_marks_student(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $this->actingAs($this->admin())
            ->get(\App\Filament\Pages\BatchAttendance::getUrl())
            ->assertSuccessful();
    }

    public function test_attendance_table_shows_records_after_selecting_session(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $session = $this->makeSession($batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchAttendance::class)
            ->set('data.course_batch_id', $batch->id)
            ->set('data.session_id', $session->id)
            ->assertSuccessful()
            ->assertSee($registration->student->name);
    }
}