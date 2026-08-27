<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarksFlowTest extends TestCase
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

    public function test_save_grade_snapshots_total_grade_and_pass_verdict(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $registration->saveGrade(78, (int) $this->admin()->id);

        $registration->refresh();
        $this->assertSame(78.0, (float) $registration->grade_total);
        $this->assertTrue($registration->grades['passed']);
        $this->assertSame('passed', $registration->grade_result);
        $this->assertSame(100, $registration->grades['full_mark']);
    }

    public function test_below_success_marks_is_failed(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $registration->saveGrade(40, (int) $this->admin()->id);

        $registration->refresh();
        $this->assertFalse($registration->grades['passed']);
        $this->assertSame('failed', $registration->grade_result);
    }

    public function test_correcting_a_mark_keeps_audit_trail(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $registration->saveGrade(55, (int) $this->admin()->id);
        $registration->saveGrade(72, (int) $this->admin()->id);

        $entries = AuditLog::query()
            ->where('action', 'registration.graded')
            ->where('entity_id', $registration->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(55.0, (float) $entries[0]->details['total']);
        $this->assertSame(72.0, (float) $entries[1]->details['total']);
    }

    public function test_batch_marks_page_renders_and_shows_students(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $registration->saveGrade(66, (int) $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(\App\Filament\Pages\BatchMarks::getUrl([
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
            ]))
            ->assertSuccessful();
    }

    public function test_batch_marks_table_shows_students_after_selecting_batch(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->assertSuccessful()
            ->assertSee($registration->student->name);
    }

    public function test_completing_registration_finalizes_result(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeRegistration($course);

        $registration->saveGrade(66, (int) $this->admin()->id);
        app(RegistrationService::class)->complete($registration, (int) $this->admin()->id, 'pass');

        $fresh = $registration->fresh();
        $this->assertSame('pass', $fresh->result);
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->result_finalized_at);
    }

    public function test_reopen_result_unfreezes_and_allows_correction(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeRegistration($course);

        $registration->saveGrade(66, (int) $this->admin()->id);
        app(RegistrationService::class)->complete($registration, (int) $this->admin()->id, 'pass');
        $this->assertNotNull($registration->fresh()->result_finalized_at);

        app(RegistrationService::class)->reopenResult(
            $registration->fresh(),
            (int) $this->admin()->id,
            'wrong mark was entered',
        );

        $fresh = $registration->fresh();
        $this->assertSame('pending', $fresh->result);
        $this->assertNull($fresh->result_finalized_at);
        $this->assertNull($fresh->result_finalized_by);

        $audit = AuditLog::query()
            ->where('action', 'registration.result_reopened')
            ->where('entity_id', $registration->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('pass', $audit->before['result'] ?? null);
        $this->assertSame('wrong mark was entered', $audit->details['reason'] ?? null);

        $registration->fresh()->saveGrade(88, (int) $this->admin()->id);
        app(RegistrationService::class)->complete($registration->fresh(), (int) $this->admin()->id, 'pass');
        $this->assertNotNull($registration->fresh()->result_finalized_at);
    }

    public function test_reopen_result_requires_finalized_result(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeRegistration($course);

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->reopenResult($registration, (int) $this->admin()->id, 'no reason needed');
    }

    public function test_enter_marks_action_is_hidden_after_finalization(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $registration->saveGrade(66, (int) $this->admin()->id);
        app(RegistrationService::class)->complete($registration, (int) $this->admin()->id, 'pass');

        $this->actingAs($this->admin())
            ->get(\App\Filament\Pages\BatchMarks::getUrl([
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
            ]))
            ->assertSuccessful()
            ->assertDontSee(__('general.enter_marks'))
            ->assertSee(__('general.passed'));
    }

    public function test_marks_sheet_print_handles_ungraded_registrations(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $this->assertNull($registration->grades);

        $this->actingAs($this->admin())
            ->get(route('marks.batch.print', $batch))
            ->assertSuccessful()
            ->assertSee($registration->student->name)
            ->assertSee(__('general.not_graded'));
    }

    public function test_enter_mark_uses_course_grading_schema_components(): void
    {
        $course = $this->makeCourse();
        $course->update([
            'grading_schema' => [
                ['label' => 'oral', 'max' => 30],
                ['label' => 'written', 'max' => 70],
            ],
        ]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->callTableAction('enterMark', $registration, data: [
                'grades' => ['oral' => 25, 'written' => 60],
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('general.grade_saved'));

        $registration->refresh();
        $this->assertSame(85.0, (float) $registration->grade_total);
        $this->assertSame(25.0, (float) $registration->grades['oral']);
        $this->assertSame(60.0, (float) $registration->grades['written']);
        $this->assertTrue($registration->grades['passed']);
    }

    public function test_component_mark_above_max_is_rejected(): void
    {
        $course = $this->makeCourse();
        $course->update([
            'grading_schema' => [
                ['label' => 'oral', 'max' => 30],
                ['label' => 'written', 'max' => 70],
            ],
        ]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->callTableAction('enterMark', $registration, data: [
                'grades' => ['oral' => 31, 'written' => 60],
            ])
            ->assertHasTableActionErrors()
            ->assertSee(__('general.mark_exceeds_max', ['max' => 30]));

        $registration->refresh();
        $this->assertNull($registration->grades['total'] ?? null);
    }

    public function test_fallback_total_above_full_mark_is_rejected_with_localized_message(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->callTableAction('enterMark', $registration, data: [
                'total' => '150',
            ])
            ->assertHasTableActionErrors()
            ->assertSee(__('general.mark_exceeds_max', ['max' => 100]));

        $registration->refresh();
        $this->assertNull($registration->grades['total'] ?? null);
    }

    public function test_fallback_total_non_numeric_is_rejected_with_localized_message(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Filament\Pages\BatchMarks::class)
            ->set('data.course_id', $course->id)
            ->set('data.course_batch_id', $batch->id)
            ->callTableAction('enterMark', $registration, data: [
                'total' => 'abc',
            ])
            ->assertHasTableActionErrors()
            ->assertSee(__('general.mark_not_numeric'));
    }

    public function test_marks_sheet_print_shows_schema_components(): void
    {
        $course = $this->makeCourse();
        $course->update([
            'grading_schema' => [
                ['label' => 'oral', 'max' => 30],
                ['label' => 'written', 'max' => 70],
            ],
        ]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);
        $registration->saveGradeComponents(['oral' => 25, 'written' => 60], (int) $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('marks.batch.print', $batch))
            ->assertSuccessful()
            ->assertSee('oral')
            ->assertSee('written')
            ->assertSee('85')
            ->assertSee(__('general.passed'));
    }
}
