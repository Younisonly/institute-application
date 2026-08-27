<?php

namespace Tests\Feature;

use App\Filament\Resources\PeriodResource\Pages\CreatePeriod;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PeriodFeatureTest extends TestCase
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

    public function test_periods_resource_renders_and_creates(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/periods')->assertOk();
        $this->get('/admin/periods/create')->assertOk();

        Livewire::test(CreatePeriod::class)
            ->fillForm([
                'name_ar' => 'ليلي',
                'name_en' => 'Night',
                'start_time' => '19:00',
                'end_time' => '21:00',
                'days' => ['sat', 'sun', 'tue'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('periods', ['name_ar' => 'ليلي', 'name_en' => 'Night']);

        $period = Period::query()->where('name_ar', 'ليلي')->firstOrFail();
        $this->assertSame(['sat', 'sun', 'tue'], $period->days);
        $this->assertSame('19:00 – 21:00', $period->times_label);
    }

    public function test_period_time_fields_render_clock_panel_picker(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/periods/create')
            ->assertOk()
            ->assertSee('start_time')
            ->assertSee('end_time');

        $this->assertStringContainsString(
            __('general.am_abbr'),
            $response->getContent()
        );
        $this->assertStringContainsString(
            __('general.pm_abbr'),
            $response->getContent()
        );
    }

    public function test_batch_periods_attach_and_registration_uses_batch(): void
    {
        $admin = $this->admin();
        $program = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create([
            'name' => 'English L1', 'program_type_id' => $program->id,
            'months' => 6, 'price' => 35000, 'is_active' => true,
        ]);
        $batch = CourseBatch::create([
            'course_id' => $course->id, 'name' => 'Batch 2026', 'year' => '2026', 'is_active' => true,
        ]);
        $morning = Period::create([
            'name_ar' => 'صباحي', 'name_en' => 'Morning',
            'start_time' => '08:00:00', 'end_time' => '10:00:00',
        ]);
        $evening = Period::create([
            'name_ar' => 'مسائي', 'name_en' => 'Evening',
            'start_time' => '16:00:00', 'end_time' => '18:00:00',
        ]);
        $batch->periods()->attach([$morning->id, $evening->id]);

        $this->assertSame(2, $batch->periods()->count());
        $this->assertStringContainsString('صباحي', $batch->periods_label);

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch->id,
            'start_month' => now()->format('Y-m'),
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
        ], $admin->id);

        $this->assertSame($batch->id, $registration->course_batch_id);
        $this->assertSame('مسائي', $registration->batch->periods->last()->name);
    }

    public function test_registration_list_report_filters_by_batch(): void
    {
        $admin = $this->admin();
        $program = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        $courseA = Course::create(['name' => 'English', 'program_type_id' => $program->id, 'months' => 6, 'price' => 35000, 'is_active' => true]);
        $courseB = Course::create(['name' => 'Excel', 'program_type_id' => $program->id, 'months' => 3, 'price' => 15000, 'is_active' => true]);
        $batchA = CourseBatch::create(['course_id' => $courseA->id, 'name' => 'English Batch', 'is_active' => true]);
        $batchB = CourseBatch::create(['course_id' => $courseB->id, 'name' => 'Excel Batch', 'is_active' => true]);

        $studentA = Student::create(['name' => 'Ali', 'status' => 'active']);
        $studentB = Student::create(['name' => 'Noor', 'status' => 'active']);

        app(RegistrationService::class)->register([
            'student_id' => $studentA->id, 'course_id' => $courseA->id, 'course_batch_id' => $batchA->id,
            'start_month' => now()->format('Y-m'), 'months_count' => 6, 'price_snapshot' => 35000,
            'items' => [], 'books' => [], 'payment_amount' => 0,
        ], $admin->id);
        app(RegistrationService::class)->register([
            'student_id' => $studentB->id, 'course_id' => $courseB->id, 'course_batch_id' => $batchB->id,
            'start_month' => now()->format('Y-m'), 'months_count' => 3, 'price_snapshot' => 15000,
            'items' => [], 'books' => [], 'payment_amount' => 0,
        ], $admin->id);

        $morningRows = app(ReportService::class)->registrationList(null, $batchA->id, null);

        $this->assertCount(1, $morningRows);
        $this->assertSame('Ali', $morningRows->first()->student->name);

        $this->actingAs($admin);
        $this->get('/admin/registration-lists-report?course_batch_id='.$batchA->id)->assertOk();
    }

    public function test_period_survives_batch_deletion_but_pivot_is_cleaned(): void
    {
        $admin = $this->admin();
        $program = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create(['name' => 'English', 'program_type_id' => $program->id, 'months' => 6, 'price' => 35000, 'is_active' => true]);
        $period = Period::create(['name_ar' => 'صباحي', 'name_en' => 'Morning']);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch', 'is_active' => true]);
        $batch->periods()->attach($period->id);

        $batch->forceDelete();

        $this->assertDatabaseHas('periods', ['id' => $period->id]);
        $this->assertDatabaseMissing('course_batch_period', ['period_id' => $period->id]);
    }
}
