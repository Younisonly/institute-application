<?php

namespace Tests\Feature;

use App\Filament\Widgets\BatchesEndingSoonWidget;
use App\Filament\Widgets\PendingResultsWidget;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicDashboardWidgetsTest extends TestCase
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

    public function test_batches_ending_soon_widget_shows_only_imminent_batches(): void
    {
        $this->actingAs($this->admin());
        $course = $this->makeCourse();

        $imminent = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Soon',
            'year' => '2026',
            'is_active' => true,
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $far = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Later',
            'year' => '2026',
            'is_active' => true,
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        Livewire::test(BatchesEndingSoonWidget::class)
            ->assertCanSeeTableRecords([$imminent])
            ->assertCanNotSeeTableRecords([$far]);
    }

    public function test_pending_results_widget_shows_only_students_of_finished_batches(): void
    {
        $this->actingAs($this->admin());
        $course = $this->makeCourse();

        $finished = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Done',
            'year' => '2026',
            'is_active' => true,
        ]);
        $stillRunning = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Running',
            'year' => '2026',
            'is_active' => true,
        ]);

        $pending = $this->makeRegistration($course, $finished);
        $running = $this->makeRegistration($course, $stillRunning);

        $finished->update(['is_active' => false, 'finished_at' => now(), 'status' => 'completed']);

        Livewire::test(PendingResultsWidget::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$running]);
    }

    public function test_pending_results_widget_excludes_finalized_results(): void
    {
        $this->actingAs($this->admin());
        $course = $this->makeCourse();

        $finished = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Done',
            'year' => '2026',
            'is_active' => true,
        ]);

        $finalized = $this->makeRegistration($course, $finished);
        $finished->update(['is_active' => false, 'finished_at' => now(), 'status' => 'completed']);
        $finalized->update(['result' => 'pass', 'result_finalized_at' => now()]);

        Livewire::test(PendingResultsWidget::class)
            ->assertCanNotSeeTableRecords([$finalized]);
    }

    public function test_pending_results_widget_shows_graded_students_of_finished_batches(): void
    {
        $this->actingAs($this->admin());
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
        $registration = $this->makeRegistration($course, $batch);

        $batch->update(['is_active' => false, 'finished_at' => now(), 'status' => 'completed']);
        $registration->saveGrade(45, (int) $this->admin()->id);

        Livewire::test(PendingResultsWidget::class)
            ->assertCanSeeTableRecords([$registration]);
    }
}