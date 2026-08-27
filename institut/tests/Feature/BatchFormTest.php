<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseBatchResource\Pages\CreateCourseBatch;
use App\Filament\Resources\CourseBatchResource\Pages\EditCourseBatch;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BatchFormTest extends TestCase
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
        return Course::create([
            'name' => 'English L1',
            'program_type_id' => ProgramType::create(['name' => 'Short', 'months_count' => 1])->id,
            'months' => 1,
            'price' => 35000,
            'is_active' => true,
        ]);
    }

    private function makePeriod(string $suffix = 'صباحي'): Period
    {
        return Period::create([
            'name_ar' => $suffix,
            'name_en' => 'Morning',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'is_active' => true,
        ]);
    }

    public function test_auto_name_is_sequential_per_course_and_includes_course_id(): void
    {
        $courseA = $this->makeCourse();
        $courseB = $this->makeCourse();

        $this->assertSame(
            __('general.batch_name_auto', ['id' => $courseA->id, 'n' => 1]),
            CourseBatch::autoName($courseA->id),
        );

        CourseBatch::create(['course_id' => $courseA->id, 'name' => 'old', 'year' => '2026']);
        CourseBatch::create(['course_id' => $courseA->id, 'name' => 'old', 'year' => '2026']);
        CourseBatch::create(['course_id' => $courseA->id, 'name' => 'old', 'year' => '2026'])->delete();

        $this->assertSame(
            __('general.batch_name_auto', ['id' => $courseA->id, 'n' => 4]),
            CourseBatch::autoName($courseA->id),
        );
        $this->assertSame(
            __('general.batch_name_auto', ['id' => $courseB->id, 'n' => 1]),
            CourseBatch::autoName($courseB->id),
        );
        $this->assertStringContainsString((string) $courseA->id, CourseBatch::autoName($courseA->id));
    }

    public function test_batch_create_page_renders_single_period_radio_from_db(): void
    {
        $period = $this->makePeriod();

        $this->actingAs($this->admin());

        $this->get('/admin/course-batches/create')
            ->assertOk()
            ->assertSee($period->option_label, false);
    }

    public function test_create_batch_form_saves_exactly_one_period_and_single_year(): void
    {
        $course = $this->makeCourse();
        $period = $this->makePeriod();
        $this->makePeriod('مسائي');

        $this->actingAs($this->admin());

        Livewire::test(CreateCourseBatch::class)
            ->fillForm([
                'course_id' => $course->id,
                'name' => 'Custom Name',
                'periods' => $period->id,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $batch = CourseBatch::query()->where('name', 'Custom Name')->firstOrFail();

        $this->assertSame($course->id, $batch->course_id);
        $this->assertSame(now()->format('Y'), $batch->year);
        $this->assertCount(1, $batch->periods);
        $this->assertSame($period->id, $batch->periods->first()->id);
        $this->assertDatabaseHas('course_batch_period', [
            'course_batch_id' => $batch->id,
            'period_id' => $period->id,
        ]);
    }

    public function test_course_switch_always_regenerates_the_auto_name(): void
    {
        $courseA = $this->makeCourse();
        $courseB = $this->makeCourse();

        $this->actingAs($this->admin());

        Livewire::test(CreateCourseBatch::class)
            ->fillForm(['course_id' => $courseA->id])
            ->assertFormSet(['name' => __('general.batch_name_auto', ['id' => $courseA->id, 'n' => 1])]);

        Livewire::test(CreateCourseBatch::class)
            ->fillForm([
                'course_id' => $courseA->id,
                'name' => 'My Manual Name',
            ])
            ->fillForm(['course_id' => $courseB->id])
            ->assertFormSet(['name' => __('general.batch_name_auto', ['id' => $courseB->id, 'n' => 1])]);
    }

    public function test_course_pick_fills_identifier_and_name_with_the_same_code(): void
    {
        $course = $this->makeCourse();
        CourseBatch::create(['course_id' => $course->id, 'name' => 'B1', 'year' => '2026']);

        $this->actingAs($this->admin());

        $expected = __('general.batch_name_auto', ['id' => $course->id, 'n' => 2]);

        Livewire::test(CreateCourseBatch::class)
            ->fillForm(['course_id' => $course->id])
            ->assertFormSet([
                'identifier' => $expected,
                'name' => $expected,
            ])
            ->assertFormSet(['identifier' => "cou{$course->id}-2"]);
    }

    public function test_batch_period_is_required(): void
    {
        $course = $this->makeCourse();

        $this->actingAs($this->admin());

        Livewire::test(CreateCourseBatch::class)
            ->fillForm([
                'course_id' => $course->id,
                'name' => 'No Period',
            ])
            ->call('create')
            ->assertHasFormErrors(['periods' => 'required']);
    }

    public function test_edit_batch_form_replaces_the_period_with_exactly_one(): void
    {
        $course = $this->makeCourse();
        $morning = $this->makePeriod();
        $evening = $this->makePeriod('مسائي');
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'B', 'year' => '2026']);
        $batch->periods()->attach($morning->id);

        $this->actingAs($this->admin());

        Livewire::test(EditCourseBatch::class, ['record' => $batch->getRouteKey()])
            ->assertFormSet(['identifier' => __('general.batch_name_auto', ['id' => $course->id, 'n' => 1])])
            ->fillForm([
                'course_id' => $course->id,
                'name' => 'B',
                'periods' => $evening->id,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $batch->refresh();

        $this->assertCount(1, $batch->periods);
        $this->assertSame($evening->id, $batch->periods->first()->id);
        $this->assertDatabaseHas('course_batch_period', [
            'course_batch_id' => $batch->id,
            'period_id' => $evening->id,
        ]);
    }
}