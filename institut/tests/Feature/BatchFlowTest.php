<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\CourseBatchService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchFlowTest extends TestCase
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

    private function makeCourse(array $overrides = []): Course
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        return Course::create(array_merge([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function programType(int $months = 6): ProgramType
    {
        return ProgramType::create(['name' => 'Short '.uniqid(), 'months_count' => $months]);
    }

    private function register(Student $student, Course $course, ?CourseBatch $batch = null): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch?->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);
    }

    private function setPassed(Registration $registration, bool $passed): void
    {
        $registration->update([
            'grades' => [
                'total' => $passed ? 80 : 30,
                'full_mark' => 100,
                'grade' => $passed ? 'جيد جداً' : 'مقبول',
                'passed' => $passed,
                'graded_at' => now()->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function test_open_new_batch_creates_batch_without_duplicate_course(): void
    {
        $course = $this->makeCourse();
        $coursesBefore = Course::query()->count();
        $batch = app(CourseBatchService::class)->startNewBatch($course, [
            'name' => null,
            'enrollment_start' => '2027-01-01',
            'enrollment_end' => '2027-03-01',
            'start_date' => '2027-02-01',
            'end_date' => null,
            'close_previous_batch' => false,
            'close_old_registrations' => false,
        ], (int) $this->admin()->id);

        $this->assertSame($coursesBefore, Course::query()->count());
        $this->assertSame(1, Course::query()->where('name', 'English L1')->count());
        $this->assertSame($course->id, $batch->course_id);
        $this->assertSame('2027', $batch->year);
        $this->assertSame('2027-01-01', $batch->enrollment_start?->toDateString());
        $this->assertSame('2027-03-01', $batch->enrollment_end?->toDateString());
        $this->assertSame('2027-02-01', $batch->start_date?->toDateString());
        $this->assertNull($batch->end_date);
        $this->assertTrue($batch->is_active);

        $this->assertDatabaseHas((new AuditLog())->getTable(), [
            'action' => 'course_batch.opened',
            'entity_type' => CourseBatch::class,
            'entity_id' => $batch->id,
        ]);
    }

    public function test_open_new_batch_can_close_previous_batch(): void
    {
        $course = $this->makeCourse();
        $old = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        $new = app(CourseBatchService::class)->startNewBatch($course, [
            'name' => null,
            'enrollment_start' => '2027-01-01',
            'enrollment_end' => null,
            'start_date' => '2027-02-01',
            'end_date' => null,
            'close_previous_batch' => true,
            'close_old_registrations' => false,
        ], (int) $this->admin()->id);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($new->fresh()->is_active);
        $this->assertNull($old->fresh()->finished_at);
    }

    public function test_complete_batch_completes_enrolled_and_passed_students_only(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        $active = $this->register(Student::create(['name' => 'Ali', 'status' => 'active']), $course, $batch);
        $passed = $this->register(Student::create(['name' => 'Sami', 'status' => 'suspended']), $course, $batch);
        $this->setPassed($passed, true);
        $failed = $this->register(Student::create(['name' => 'Khalid', 'status' => 'active']), $course, $batch);
        $this->setPassed($failed, false);
        $closed = $this->register(Student::create(['name' => 'Nabil', 'status' => 'active']), $course, $batch);
        app(RegistrationService::class)->close($closed, 'test', (int) $this->admin()->id);

        $result = app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $this->assertSame(3, $result['completed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertSame('completed', $active->fresh()->status);
        $this->assertSame('completed', $passed->fresh()->status);
        $this->assertSame('completed', $failed->fresh()->status);
        $this->assertSame('closed', $closed->fresh()->status);

        $this->assertSame('pass', $passed->fresh()->result);
        $this->assertSame('fail', $failed->fresh()->result);
        $this->assertNotNull($passed->fresh()->result_finalized_at);
        $this->assertSame($this->admin()->id, $passed->fresh()->result_finalized_by);

        $batch->refresh();
        $this->assertFalse($batch->is_active);
        $this->assertNotNull($batch->finished_at);
        $this->assertNotNull($batch->end_date);
        $this->assertSame(CourseBatch::LIFECYCLE_FINISHED, $batch->lifecycle_status);

        $this->assertDatabaseHas((new AuditLog())->getTable(), [
            'action' => 'course_batch.completed',
            'entity_type' => CourseBatch::class,
            'entity_id' => $batch->id,
        ]);
    }

    public function test_complete_batch_never_marks_ungraded_student_as_pass(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        $ungraded = $this->register(Student::create(['name' => 'Reem', 'status' => 'active']), $course, $batch);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $ungraded->refresh();
        $this->assertSame('completed', $ungraded->status);
        $this->assertSame('incomplete', $ungraded->result);
        $this->assertNotNull($ungraded->result_finalized_at);
        $this->assertNotSame('pass', $ungraded->result);
    }

    public function test_complete_course_covers_batchless_registrations_and_finishes_empty_batches(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-B', 'year' => '2026', 'is_active' => true]);

        $batchlessActive = $this->register(Student::create(['name' => 'Omar', 'status' => 'active']), $course);
        $batchPassed = $this->register(Student::create(['name' => 'Huda', 'status' => 'active']), $course, $batch);
        $this->setPassed($batchPassed, true);
        $batchFailed = $this->register(Student::create(['name' => 'Layla', 'status' => 'active']), $course, $batch);
        $this->setPassed($batchFailed, false);

        $result = app(CourseBatchService::class)->completeCourse($course, (int) $this->admin()->id);

        $this->assertSame(3, $result['completed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertSame('completed', $batchlessActive->fresh()->status);
        $this->assertSame('completed', $batchFailed->fresh()->status);
        $this->assertSame('incomplete', $batchlessActive->fresh()->result);
        $this->assertSame('pass', $batchPassed->fresh()->result);
        $this->assertSame('fail', $batchFailed->fresh()->result);
        $this->assertNotNull($batch->fresh()->finished_at);
    }

    public function test_transfer_uses_picked_batch(): void
    {
        $type = $this->programType();
        $courseA = $this->makeCourse(['name' => 'English A', 'program_type_id' => $type->id]);
        $courseB = $this->makeCourse(['name' => 'English B', 'program_type_id' => $type->id]);
        $batchB = CourseBatch::create(['course_id' => $courseB->id, 'name' => '2027-A', 'year' => '2027', 'is_active' => true]);
        $otherBatch = CourseBatch::create(['course_id' => $courseB->id, 'name' => '2026-B', 'year' => '2026', 'is_active' => true]);

        $student = Student::create(['name' => 'Anas', 'status' => 'active']);
        $registration = $this->register($student, $courseA);

        $new = app(RegistrationService::class)->transfer(
            $registration,
            $courseB->id,
            'switch',
            (int) $this->admin()->id,
            false,
            $otherBatch->id,
        );

        $this->assertSame($otherBatch->id, $new->course_batch_id);
        $this->assertSame('transferred', $registration->fresh()->status);
    }

    public function test_complete_batch_leaves_transferred_student_untouched(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->register(Student::create(['name' => 'Yusuf', 'status' => 'active']), $course, $batch);
        $registration->update(['status' => 'transferred']);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $this->assertSame('transferred', $registration->fresh()->status);
    }
}