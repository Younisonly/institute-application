<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseResource;
use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\RelationManagers\PrerequisitesRelationManager;
use App\Filament\Resources\ProgramTypeResource\Pages\EditProgramType;
use App\Filament\Resources\ProgramTypeResource\RelationManagers\CurriculumRelationManager;
use App\Filament\Widgets\RecommendationsWidget;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CoursePrerequisite;
use App\Models\ProgramCourse;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EligibilityService;
use App\Services\ProgressionService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgressionTest extends TestCase
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

    private function makeProgram(): ProgramType
    {
        return ProgramType::create(['name' => 'Diploma '.uniqid(), 'months_count' => 24]);
    }

    private function makeCourse(ProgramType $program, string $label): Course
    {
        return Course::create([
            'name' => $label.' '.uniqid(),
            'program_type_id' => $program->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        return Student::create(['name' => 'Progression '.uniqid(), 'status' => 'active']);
    }

    private function register(Student $student, Course $course): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
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

    private function pass(Registration $registration): Registration
    {
        $registration->update(['result' => 'pass']);

        return $registration;
    }

    private function attempt(Student $student, Course $course, string $result, float $gradeTotal, ?CourseBatch $batch = null): Registration
    {
        $registration = Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch?->id,
            'start_month' => '2026-01',
            'months_count' => 6,
            'price_snapshot' => 35000,
        ]);
        $registration->saveGrade($gradeTotal, (int) $this->admin()->id);
        $registration->update(['result' => $result]);

        return $registration;
    }

    private function require(Course $course, Course $prerequisite, array $extra = []): CoursePrerequisite
    {
        return CoursePrerequisite::create(array_merge([
            'course_id' => $course->id,
            'prerequisite_course_id' => $prerequisite->id,
            'rule_type' => 'required',
        ], $extra));
    }

    public function test_curriculum_relation_manager_renders_level_entries(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'L1');
        $b = $this->makeCourse($program, 'L2');

        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $a->id, 'level_no' => 1, 'sort_order' => 1, 'is_required' => true]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $b->id, 'level_no' => 2, 'sort_order' => 1, 'is_required' => true]);

        Livewire::test(CurriculumRelationManager::class, [
            'ownerRecord' => $program,
            'pageClass' => EditProgramType::class,
        ])
            ->assertOk()
            ->assertCountTableRecords(2);
    }

    public function test_pass_on_prerequisite_clears_required_blocker(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'Arabic 1');
        $target = $this->makeCourse($program, 'Arabic 2');
        $this->require($target, $prereq);
        $student = $this->makeStudent();

        $progression = app(ProgressionService::class);
        $this->assertFalse($progression->prerequisitesSatisfied($student->id, $target));
        $this->assertSame([$prereq->name], $progression->missingRequiredPrerequisites($student->id, $target));

        $this->pass($this->register($student, $prereq));

        $this->assertTrue($progression->prerequisitesSatisfied($student->id, $target));
        $this->assertSame([], $progression->missingRequiredPrerequisites($student->id, $target));
    }

    public function test_alt_group_requirement_satisfied_when_any_passes(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Math A');
        $b = $this->makeCourse($program, 'Math B');
        $target = $this->makeCourse($program, 'Statistics');
        $this->require($target, $a, ['rule_type' => 'alt_group', 'group_no' => 1]);
        $this->require($target, $b, ['rule_type' => 'alt_group', 'group_no' => 1]);
        $student = $this->makeStudent();

        $progression = app(ProgressionService::class);
        $this->assertSame([$a->name.' or '.$b->name], $progression->missingRequiredPrerequisites($student->id, $target));

        $this->pass($this->register($student, $b));

        $this->assertSame([], $progression->missingRequiredPrerequisites($student->id, $target));
    }

    public function test_recommended_prerequisite_never_blocks(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'Optional');
        $target = $this->makeCourse($program, 'Main');
        $this->require($target, $prereq, ['rule_type' => 'recommended']);

        $this->assertTrue(app(ProgressionService::class)->prerequisitesSatisfied($this->makeStudent()->id, $target));
    }

    public function test_min_mark_modifier_requires_total(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'Physics');
        $target = $this->makeCourse($program, 'Engineering');
        $this->require($target, $prereq, ['min_mark' => 60]);
        $student = $this->makeStudent();
        $this->attempt($student, $prereq, 'pass', 55);

        $progression = app(ProgressionService::class);
        $this->assertFalse($progression->prerequisitesSatisfied($student->id, $target), '55 of 100 is below the 60 minimum');

        $this->attempt($student, $prereq, 'pass', 76);

        $this->assertTrue($progression->prerequisitesSatisfied($student->id, $target));
    }

    public function test_min_attendance_modifier_uses_last_attempt_with_sessions(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'Grammar');
        $target = $this->makeCourse($program, 'Literature');
        $this->require($target, $prereq, ['min_attendance_percent' => 50]);
        $student = $this->makeStudent();
        $registration = $this->pass($this->register($student, $prereq));
        $progression = app(ProgressionService::class);

        $this->assertTrue($progression->prerequisitesSatisfied($student->id, $target), 'no sessions must not fail the student');

        $batch = CourseBatch::create([
            'course_id' => $prereq->id,
            'name' => '2026-A '.uniqid(),
            'year' => '2026',
            'is_active' => true,
            'status' => 'open',
        ]);
        $registration->update(['course_batch_id' => $batch->id]);
        $session = app(AttendanceService::class)->createSession($batch, '2026-09-01', null, null, (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($session, $registration, 'absent', (int) $this->admin()->id);

        $this->assertFalse($progression->prerequisitesSatisfied($student->id, $target), 'below threshold must block');

        app(AttendanceService::class)->recordStatus($session, $registration, 'present', (int) $this->admin()->id);
        $this->assertTrue($progression->prerequisitesSatisfied($student->id, $target));
    }

    public function test_eligibility_reports_missing_prerequisites_blocker(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'Basics');
        $target = $this->makeCourse($program, 'Advanced');
        $this->require($target, $prereq);
        $student = $this->makeStudent();

        $check = app(EligibilityService::class)->check($student->id, $target, null);
        $this->assertFalse($check['eligible']);
        $this->assertContains(__('general.missing_prerequisites', ['courses' => $prereq->name]), $check['blockers']);

        $this->pass($this->register($student, $prereq));
        $check = app(EligibilityService::class)->check($student->id, $target, null);
        $this->assertTrue($check['eligible']);
    }

    public function test_recommendations_only_suggest_levels_within_reach(): void
    {
        $program = $this->makeProgram();
        $l1 = $this->makeCourse($program, 'Level 1');
        $l2 = $this->makeCourse($program, 'Level 2');
        $l3 = $this->makeCourse($program, 'Level 3');
        $l4 = $this->makeCourse($program, 'Level 4');
        $l5 = $this->makeCourse($program, 'Special 5');
        $student = $this->makeStudent();

        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l1->id, 'level_no' => 1, 'sort_order' => 1, 'is_required' => true, 'credit_hours' => 2]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l2->id, 'level_no' => 2, 'sort_order' => 1, 'is_required' => true, 'credit_hours' => 2]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l3->id, 'level_no' => 3, 'sort_order' => 1, 'is_required' => true, 'credit_hours' => 3]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l4->id, 'level_no' => 4, 'sort_order' => 1, 'is_required' => true, 'credit_hours' => 3]);
        $this->require($l3, $l5);

        $this->pass($this->register($student, $l1));

        $recommendations = app(ProgressionService::class)->recommend($student->id);
        $byName = collect($recommendations)->keyBy(fn (array $r): string => $r['course']->name);

        $this->assertCount(1, $recommendations, 'with only L1 passed, only L2 is within reach');
        $this->assertTrue($byName[$l2->name]['satisfied']);
        $this->assertSame([], $byName[$l2->name]['missing']);
        $this->assertFalse($byName->has($l3->name), 'L3 is out of reach until L2 passes');
        $this->assertFalse($byName->has($l4->name));

        $this->pass($this->register($student, $l2));

        $recommendations = app(ProgressionService::class)->recommend($student->id);
        $byName = collect($recommendations)->keyBy(fn (array $r): string => $r['course']->name);

        $this->assertCount(1, $recommendations, 'now only L3 is suggested, blocked by Special 5');
        $this->assertFalse($byName[$l3->name]['satisfied']);
        $this->assertSame([$l5->name], $byName[$l3->name]['missing']);
        $this->assertFalse($byName->has($l2->name), 'a passed course is never suggested again');
        $this->assertFalse($byName->has($l4->name), 'L4 is still out of reach');
    }

    public function test_best_total_takes_the_highest_attempt(): void
    {
        $program = $this->makeProgram();
        $course = $this->makeCourse($program, 'Attempts');
        $student = $this->makeStudent();

        $this->attempt($student, $course, 'fail', 40);
        $this->attempt($student, $course, 'pass', 83);

        $this->assertEqualsWithDelta(83, app(ProgressionService::class)->bestTotal($student->id, $course->id), 0.001);
    }

    public function test_recommendations_widget_renders_ready_and_blocked_rows(): void
    {
        $program = $this->makeProgram();
        $l1 = $this->makeCourse($program, 'Widget L1');
        $l2 = $this->makeCourse($program, 'Widget L2');
        $l3 = $this->makeCourse($program, 'Widget L3');
        $l5 = $this->makeCourse($program, 'Widget Special 5');
        $student = $this->makeStudent();

        $entry3 = ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l3->id, 'level_no' => 3, 'sort_order' => 1, 'is_required' => true]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l2->id, 'level_no' => 2, 'sort_order' => 1, 'is_required' => true]);
        ProgramCourse::create(['program_id' => $program->id, 'course_id' => $l1->id, 'level_no' => 1, 'sort_order' => 1, 'is_required' => true]);
        $this->require($l3, $l5);
        $this->pass($this->register($student, $l1));
        $this->pass($this->register($student, $l2));

        $component = Livewire::test(RecommendationsWidget::class, ['record' => $student])
            ->assertOk()
            ->assertCountTableRecords(1);

        $column = $component->instance()->getTable()->getColumn('course.id');
        $column->record($entry3);
        $this->assertSame(__('general.recommendation_blocked'), $column->formatState($column->getState()));
        $this->assertSame([$l5->name], app(ProgressionService::class)->missingRequiredPrerequisites($student->id, $l3));
    }

    public function test_prerequisites_relation_manager_renders_rows(): void
    {
        $program = $this->makeProgram();
        $prereq = $this->makeCourse($program, 'RM Basics');
        $target = $this->makeCourse($program, 'RM Advanced');
        $this->require($target, $prereq);

        Livewire::test(PrerequisitesRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => EditCourse::class,
        ])
            ->assertOk()
            ->assertCountTableRecords(1);
    }
}