<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CoursePrerequisite;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EligibilityFlowTest extends TestCase
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

    private function makeCourse(int $capacity = 0): Course
    {
        $type = ProgramType::create(['name' => 'Short '.uniqid(), 'months_count' => 6]);

        return Course::create([
            'name' => 'English L1 '.uniqid(),
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'capacity' => $capacity > 0 ? $capacity : null,
            'is_active' => true,
        ]);
    }

    private function makeBatch(Course $course, ?int $capacity = null): CourseBatch
    {
        return CourseBatch::create([
            'course_id' => $course->id,
            'name' => '2026-A '.uniqid(),
            'year' => '2026',
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    private function makeRegistration(Course $course, ?CourseBatch $batch = null, string $status = 'active'): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => Student::create(['name' => 'S'.uniqid(), 'status' => 'active'])->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch?->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id, false, null);
    }

    private function makePeriod(string $start, string $end, array $days = ['saturday']): Period
    {
        return Period::create([
            'name_ar' => 'فترة '.uniqid(),
            'name_en' => 'P'.uniqid(),
            'start_time' => $start,
            'end_time' => $end,
            'days' => $days,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_in_same_batch_is_blocked(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = Student::create(['name' => 'Dup', 'status' => 'active']);

        $first = app(RegistrationService::class)->register([
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

        $this->assertSame('active', $first->status);

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
            $this->fail('Expected ValidationException for duplicate');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course_batch_id', $e->errors());
        }

        $this->assertSame(1, Registration::query()->count());
    }

    public function test_registration_form_flags_course_blocked_by_missing_prerequisite(): void
    {
        $course = $this->makeCourse();
        $prereq = $this->makeCourse();
        CoursePrerequisite::create([
            'course_id' => $course->id,
            'prerequisite_course_id' => $prereq->id,
            'rule_type' => 'required',
        ]);
        $student = Student::create(['name' => 'Prereq'.uniqid(), 'status' => 'active']);
        $this->actingAs($this->admin());

        Livewire::test(CreateRegistration::class)
            ->assertOk()
            ->set('data.student_id', (string) $student->id)
            ->set('data.course_id', (string) $course->id)
            ->assertSee($prereq->name);
    }

    public function test_registration_form_allows_course_after_passing_prerequisite(): void
    {
        $course = $this->makeCourse();
        $prereq = $this->makeCourse();
        CoursePrerequisite::create([
            'course_id' => $course->id,
            'prerequisite_course_id' => $prereq->id,
            'rule_type' => 'required',
        ]);
        $student = Student::create(['name' => 'Prereq'.uniqid(), 'status' => 'active']);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $prereq->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id)->update(['result' => 'pass']);

        $this->actingAs($this->admin());

        Livewire::test(CreateRegistration::class)
            ->assertOk()
            ->set('data.student_id', (string) $student->id)
            ->set('data.course_id', (string) $course->id)
            ->assertDontSee(__('general.missing_prerequisites', ['courses' => $prereq->name]), false);
    }

    public function test_full_batch_is_blocked_and_override_works(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course, 1);
        $studentA = Student::create(['name' => 'A', 'status' => 'active']);
        $studentB = Student::create(['name' => 'B', 'status' => 'active']);

        $this->makeRegistration($course, $batch);

        try {
            app(RegistrationService::class)->register([
                'student_id' => $studentB->id,
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
            $this->fail('Expected ValidationException for full batch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course_batch_id', $e->errors());
        }

        $override = app(RegistrationService::class)->register([
            'student_id' => $studentB->id,
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
        ], (int) $this->admin()->id, true, 'emergency case');

        $this->assertSame('active', $override->status);

        $audit = AuditLog::query()->where('action', 'registration.eligibility_overridden')->firstOrFail();
        $this->assertSame('emergency case', $audit->details['reason']);
    }

    public function test_override_without_reason_is_rejected(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course, 1);
        $student = Student::create(['name' => 'C'.uniqid(), 'status' => 'active']);

        $this->makeRegistration($course, $batch);

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
            ], (int) $this->admin()->id, true, null);
            $this->fail('Expected ValidationException for a missing override reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('override_reason', $e->errors());
        }

        $this->assertSame(1, Registration::query()->count());
    }

    public function test_course_capacity_never_blocks_when_batch_has_seats(): void
    {
        $course = $this->makeCourse(1);
        $batchA = $this->makeBatch($course, 10);
        $batchB = $this->makeBatch($course, 10);

        $this->makeRegistration($course, $batchA);

        $second = $this->makeRegistration($course, $batchB);

        $this->assertSame('active', $second->status);
    }

    public function test_schedule_conflict_blocks_overlapping_periods(): void
    {
        $course = $this->makeCourse();
        $batchA = $this->makeBatch($course);
        $batchB = $this->makeBatch($course);
        $morning = $this->makePeriod('08:00', '10:00');
        $lateMorning = $this->makePeriod('09:30', '11:30');
        $batchA->periods()->attach($morning->id);
        $batchB->periods()->attach($lateMorning->id);

        $student = Student::create(['name' => 'Conflict', 'status' => 'active']);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batchA->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], (int) $this->admin()->id);

        $check = app(EligibilityService::class)->check($student->id, $course, $batchB);

        $this->assertFalse($check['eligible']);
        $this->assertStringContainsString('08:00', $check['blockers'][0]);
    }

    public function test_non_overlapping_periods_are_allowed(): void
    {
        $course = $this->makeCourse();
        $batchA = $this->makeBatch($course);
        $batchB = $this->makeBatch($course);
        $morning = $this->makePeriod('08:00', '10:00');
        $evening = $this->makePeriod('16:00', '18:00');
        $batchA->periods()->attach($morning->id);
        $batchB->periods()->attach($evening->id);

        $student = Student::create(['name' => 'Clear', 'status' => 'active']);

        $this->makeRegistration($course, $batchA);

        $check = app(EligibilityService::class)->check($student->id, $course, $batchB);

        $this->assertTrue($check['eligible']);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batchB->id,
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

    public function test_completed_course_can_be_repeated(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = Student::create(['name' => 'Repeat', 'status' => 'active']);

        $first = $this->makeRegistration($course, $batch);
        $first->update(['status' => 'completed']);

        $second = $this->makeRegistration($course, $batch);

        $this->assertSame('active', $second->status);
    }

    public function test_register_for_program_delegates_to_register(): void
    {
        $type = ProgramType::create(['name' => 'Diploma '.uniqid(), 'months_count' => 12]);
        $c1 = Course::create(['name' => 'A', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);
        $c2 = Course::create(['name' => 'B', 'program_type_id' => $type->id, 'months' => 3, 'price' => 15000, 'is_active' => true]);
        $b1 = $this->makeBatch($c1);
        $b2 = $this->makeBatch($c2);

        $student = Student::create(['name' => 'Prog', 'status' => 'active']);

        $registrations = app(RegistrationService::class)->registerForProgram([
            'student_id' => $student->id,
            'program_type_id' => $type->id,
            'course_ids' => [$c1->id, $c2->id],
            'start_month' => '2026-09',
            'payment_amount' => 40000,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-01',
        ], (int) $this->admin()->id);

        $this->assertCount(2, $registrations);
        $this->assertSame($b1->id, $registrations[0]->course_batch_id);
        $this->assertSame($b2->id, $registrations[1]->course_batch_id);
        $this->assertSame(2, Registration::query()->where('student_id', $student->id)->count());
    }

    public function test_level_sequencing_blocks_skipping_unpassed_levels(): void
    {
        $type = ProgramType::create(['name' => 'Dip '.uniqid(), 'months_count' => 24, 'study_system' => 'semester']);
        $l1 = Course::create(['name' => 'Fundamentals', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);
        $l2 = Course::create(['name' => 'Intermediate', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);
        $l3 = Course::create(['name' => 'Advanced', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);

        foreach ([[$l1, 1], [$l2, 2], [$l3, 3]] as [$course, $level]) {
            \App\Models\ProgramCourse::create([
                'program_id' => $type->id,
                'course_id' => $course->id,
                'level_no' => $level,
                'sort_order' => $level,
                'is_required' => true,
            ]);
        }

        $student = Student::create(['name' => 'Abeer', 'status' => 'active']);

        $attempt1 = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $l1->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 30000,
            'items' => [],
            'payment_amount' => 0,
        ], (int) $this->admin()->id);

        app(\App\Services\RegistrationService::class)->complete($attempt1, (int) $this->admin()->id, 'pass');

        $verdict = app(\App\Services\EligibilityService::class)->check($student->id, $l3, null, []);
        $this->assertFalse($verdict['eligible']);
        $this->assertTrue(collect($verdict['blockers'])->contains(fn (string $b): bool => str_contains($b, '3')));

        $verdict2 = app(\App\Services\EligibilityService::class)->check($student->id, $l2, null, []);
        $this->assertTrue($verdict2['eligible']);

        $verdictOverride = app(\App\Services\EligibilityService::class)->check($student->id, $l3, null, ['override' => true]);
        $this->assertTrue($verdictOverride['eligible']);
    }

    public function test_level_gate_allows_fresh_students_and_blocks_without_pass(): void
    {
        $type = ProgramType::create(['name' => 'Dip '.uniqid(), 'months_count' => 24]);
        $l1 = Course::create(['name' => 'Fundamentals', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);
        $l2 = Course::create(['name' => 'Intermediate', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);

        foreach ([[$l1, 1], [$l2, 2]] as [$course, $level]) {
            \App\Models\ProgramCourse::create([
                'program_id' => $type->id,
                'course_id' => $course->id,
                'level_no' => $level,
                'sort_order' => $level,
                'is_required' => true,
            ]);
        }

        $fresh = Student::create(['name' => 'Fresh', 'status' => 'active']);
        $verdict = app(\App\Services\EligibilityService::class)->check($fresh->id, $l2, null, []);
        $this->assertTrue($verdict['eligible']);

        $student = Student::create(['name' => 'Retry', 'status' => 'active']);
        $attempt1 = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $l1->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 30000,
            'items' => [],
            'payment_amount' => 0,
        ], (int) $this->admin()->id);

        app(\App\Services\RegistrationService::class)->complete($attempt1, (int) $this->admin()->id, 'fail');

        $verdictFail = app(\App\Services\EligibilityService::class)->check($student->id, $l2, null, []);
        $this->assertFalse($verdictFail['eligible']);

        $verdictL1 = app(\App\Services\EligibilityService::class)->check($student->id, $l1, null, []);
        $this->assertTrue($verdictL1['eligible']);
    }

    public function test_archived_programs_vanish_from_enrollable_courses(): void
    {
        $type = ProgramType::create(['name' => 'Old '.uniqid(), 'months_count' => 6]);
        $course = Course::create(['name' => 'Legacy', 'program_type_id' => $type->id, 'months' => 6, 'price' => 10000, 'is_active' => true]);

        $this->assertTrue(Course::query()->enrollable()->whereKey($course->id)->exists());

        $type->update(['status' => 'archived']);

        $this->assertFalse(Course::query()->enrollable()->whereKey($course->id)->exists());
    }
}