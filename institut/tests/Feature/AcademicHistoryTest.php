<?php

namespace Tests\Feature;

use App\Filament\Resources\StudentResource;
use App\Filament\Resources\StudentResource\Pages\ViewStudent;
use App\Filament\Resources\StudentResource\RelationManagers\AcademicHistoryRelationManager;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicHistoryTest extends TestCase
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

    private function makeBatch(Course $course): CourseBatch
    {
        return CourseBatch::create([
            'course_id' => $course->id,
            'name' => '2026-A '.uniqid(),
            'year' => '2026',
            'is_active' => true,
            'status' => 'open',
        ]);
    }

    private function makeStudent(): Student
    {
        return Student::create(['name' => 'History '.uniqid(), 'status' => 'active']);
    }

    private function register(Student $student, Course $course, ?CourseBatch $batch): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => $student->id,
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
        ], (int) $this->admin()->id);
    }

    private function historyComponent(Student $student): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(AcademicHistoryRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => ViewStudent::class,
        ]);
    }

    public function test_view_page_shows_academic_history_tab(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = $this->makeStudent();
        $this->register($student, $course, $batch);

        $this->actingAs($this->admin())
            ->get(StudentResource::getUrl('view', ['record' => $student->id]))
            ->assertSuccessful()
            ->assertSee(__('general.academic_history'));
    }

    public function test_history_row_shows_course_batch_status_result_and_duration(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = $this->makeStudent();
        $registration = $this->register($student, $course, $batch);

        $this->historyComponent($student)
            ->assertOk()
            ->assertCountTableRecords(1)
            ->assertTableColumnFormattedStateSet('course.name', $course->name, $registration)
            ->assertTableColumnFormattedStateSet('status', __('general.active'), $registration)
            ->assertTableColumnFormattedStateSet('result', __('general.result_pending'), $registration)
            ->assertTableColumnFormattedStateSet('start_month', '2026-08 → 2027-01', $registration);
    }

    public function test_mark_column_shows_percent_and_standard_grade_band(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = $this->makeStudent();
        $registration = $this->register($student, $course, $batch);

        $registration->saveGrade(78, (int) $this->admin()->id);

        $this->historyComponent($student)
            ->assertOk()
            ->assertTableColumnFormattedStateSet('grade_total', '78%', $registration);
    }

    public function test_result_badge_shows_pass_fail_from_simple_marks(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = $this->makeStudent();
        $registration = $this->register($student, $course, $batch);

        $registration->saveGrade(78, (int) $this->admin()->id);
        app(RegistrationService::class)->complete($registration, (int) $this->admin()->id, 'pass');

        $this->historyComponent($student)
            ->assertOk()
            ->assertTableColumnFormattedStateSet('result', __('general.result_pass'), $registration);
    }

    public function test_attendance_percentage_badge_shows_rate_and_barring(): void
    {
        $course = $this->makeCourse();
        $batch = $this->makeBatch($course);
        $student = $this->makeStudent();
        $registration = $this->register($student, $course, $batch);

        $s1 = app(AttendanceService::class)->createSession($batch, '2026-09-01', null, null, (int) $this->admin()->id);
        $s2 = app(AttendanceService::class)->createSession($batch, '2026-09-02', null, null, (int) $this->admin()->id);

        app(AttendanceService::class)->recordStatus($s1, $registration, 'present', (int) $this->admin()->id);
        app(AttendanceService::class)->recordStatus($s2, $registration, 'absent', (int) $this->admin()->id);

        $this->historyComponent($student)
            ->assertOk()
            ->assertTableColumnFormattedStateSet('attendance_rate', '50%', $registration);
    }

    public function test_batchless_registration_renders_placeholder(): void
    {
        $course = $this->makeCourse();
        $student = $this->makeStudent();
        $registration = $this->register($student, $course, null);

        $this->historyComponent($student)
            ->assertOk()
            ->assertTableColumnFormattedStateSet('course.name', $course->name, $registration)
            ->assertTableColumnFormattedStateSet('start_month', '2026-08 → 2027-01', $registration);
    }

    public function test_empty_history_shows_empty_state(): void
    {
        $student = $this->makeStudent();

        $this->historyComponent($student)
            ->assertOk()
            ->assertCountTableRecords(0);
    }
}