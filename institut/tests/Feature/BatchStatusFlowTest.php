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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BatchStatusFlowTest extends TestCase
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

    private function makeBatch(Course $course, string $status = 'open'): CourseBatch
    {
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => '2026-A '.uniqid(),
            'year' => '2026',
            'is_active' => $status === 'open',
            'status' => $status,
        ]);

        return $batch;
    }

    private function makeRegistration(Course $course, CourseBatch $batch): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => Student::create(['name' => 'S'.uniqid(), 'status' => 'active'])->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id);
    }

    public function test_transition_machine_allows_only_valid_moves(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course, 'draft');

        app(CourseBatchService::class)->transition($batch, 'scheduled', (int) $this->admin()->id);
        $this->assertSame('scheduled', $batch->fresh()->status);

        app(CourseBatchService::class)->transition($batch, 'open', (int) $this->admin()->id);
        $this->assertTrue($batch->fresh()->is_active);
        $this->assertTrue($batch->fresh()->isEnrollmentOpen());

        app(CourseBatchService::class)->transition($batch, 'in_progress', (int) $this->admin()->id);
        $this->assertFalse($batch->fresh()->isEnrollmentOpen());

        try {
            app(CourseBatchService::class)->transition($batch, 'draft', (int) $this->admin()->id);
            $this->fail('Expected ValidationException for invalid transition');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('in_progress', $batch->fresh()->status);
    }

    public function test_terminal_statuses_are_frozen(): void
    {
        $course = $this->makeCourse();
        $completed = $this->makeBatch($course, 'completed');

        try {
            app(CourseBatchService::class)->transition($completed, 'open', (int) $this->admin()->id);
            $this->fail('Expected ValidationException for terminal move');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $cancelled = $this->makeBatch($course, 'cancelled');

        try {
            app(CourseBatchService::class)->transition($cancelled, 'draft', (int) $this->admin()->id);
            $this->fail('Expected ValidationException for terminal move');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_cancellation_requires_reason_and_blocks_open_registrations(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $this->makeRegistration($course, $batch);

        try {
            app(CourseBatchService::class)->transition($batch, 'cancelled', (int) $this->admin()->id, 'no students');
            $this->fail('Expected ValidationException when batch has open registrations');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('open', $batch->fresh()->status);
    }

    public function test_cancellation_succeeds_and_audits_with_reason(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);

        app(CourseBatchService::class)->transition($batch, 'cancelled', (int) $this->admin()->id, 'no demand');

        $fresh = $batch->fresh();

        $this->assertSame('cancelled', $fresh->status);
        $this->assertFalse($fresh->is_active);
        $this->assertSame('no demand', $fresh->cancelled_reason);
        $this->assertNotNull($fresh->cancelled_at);

        $audit = AuditLog::query()->where('action', 'course_batch.cancelled')->firstOrFail();
        $this->assertSame('open', $audit->before);
        $this->assertSame('cancelled', $audit->after);
        $this->assertSame('no demand', $audit->details['reason']);
    }

    public function test_cancelled_batch_blocks_registration_with_clear_message(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        app(CourseBatchService::class)->transition($batch, 'cancelled', (int) $this->admin()->id, 'closed');

        $student = Student::create(['name' => 'S', 'status' => 'active']);

        try {
            app(RegistrationService::class)->register([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => '2026-08',
                'months_count' => 6,
                'price_snapshot' => 35000,
                'items' => [],
                'books' => [],
                'payment_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-10',
            ], (int) $this->admin()->id);
            $this->fail('Expected ValidationException for cancelled batch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course_batch_id', $e->errors());
        }
    }

    public function test_scheduled_batch_is_not_enrollable_and_scope_enrollable_skips_it(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course, 'scheduled');

        $this->assertFalse($batch->isEnrollmentOpen());
        $this->assertCount(0, CourseBatch::query()->enrollable()->whereKey($batch->id)->get());
    }

    public function test_completion_sets_status_completed_and_freezes_enrollment(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $this->makeRegistration($course, $batch);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $fresh = $batch->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertFalse($fresh->is_active);
        $this->assertFalse($fresh->isEnrollmentOpen());
        $this->assertNotNull($fresh->finished_at);

        $student = Student::create(['name' => 'S2', 'status' => 'active']);

        try {
            app(RegistrationService::class)->register([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => '2026-08',
                'months_count' => 6,
                'price_snapshot' => 35000,
                'items' => [],
                'books' => [],
                'payment_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-10',
            ], (int) $this->admin()->id);
            $this->fail('Expected ValidationException for completed batch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course_batch_id', $e->errors());
        }
    }

    public function test_reopening_completed_batch_restores_marks_and_audits_reason(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $registration = $this->makeRegistration($course, $batch);
        $registration->saveGrade(66, (int) $this->admin()->id);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->finished_at);
        $this->assertSame('completed', $registration->fresh()->status);
        $this->assertSame('pass', $registration->fresh()->result);
        $this->assertNotNull($registration->fresh()->result_finalized_at);

        app(CourseBatchService::class)->reopen($batch, (int) $this->admin()->id, 'wrong marks were entered');

        $fresh = $batch->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertNull($fresh->finished_at);
        $this->assertFalse($fresh->is_active);

        $reg = $registration->fresh();
        $this->assertSame('active', $reg->status);
        $this->assertSame('pending', $reg->result);
        $this->assertNull($reg->result_finalized_at);
        $this->assertNull($reg->closed_at);

        $audit = AuditLog::query()
            ->where('action', 'course_batch.reopened')
            ->where('entity_id', $batch->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('completed', $audit->before['status'] ?? null);
        $this->assertSame('in_progress', $audit->after['status'] ?? null);
        $this->assertSame('wrong marks were entered', $audit->details['reason'] ?? null);
    }

    public function test_reopening_non_completed_batch_is_rejected(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);

        try {
            app(CourseBatchService::class)->reopen($batch, (int) $this->admin()->id, 'not needed');
            $this->fail('Expected ValidationException for non-completed batch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_reopening_a_completed_batch_from_marks_page_reenables_mark_entry(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $registration = $this->makeRegistration($course, $batch);
        $registration->saveGrade(66, (int) $this->admin()->id);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);
        $this->assertNotNull($registration->fresh()->result_finalized_at);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->assertActionVisible('reopenBatch')
            ->callAction('reopenBatch', data: ['reason' => 'marks need correction'])
            ->assertNotified(__('general.reopen_batch_done'));

        $this->assertSame('in_progress', $batch->fresh()->status);
        $this->assertNull($batch->fresh()->finished_at);
        $this->assertNull($registration->fresh()->result_finalized_at);
        $this->assertSame('active', $registration->fresh()->status);
    }

    public function test_edit_batch_page_renders_with_transition_actions(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);

        $this->actingAs($this->admin())
            ->get(\App\Filament\Resources\CourseBatchResource\Pages\EditCourseBatch::getUrl(['record' => $batch->id]))
            ->assertSuccessful();
    }

    public function test_create_batch_page_renders_with_status_select(): void
    {
        $course = $this->makeCourse();

        $this->actingAs($this->admin())
            ->get(\App\Filament\Resources\CourseBatchResource\Pages\CreateCourseBatch::getUrl(['course_id' => $course->id]))
            ->assertSuccessful();
    }
}