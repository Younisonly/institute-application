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
use Tests\TestCase;

class MarksCertificatesTest extends TestCase
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
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        return Course::create([
            'name' => 'Computer L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 30000,
            'full_mark' => 100,
            'success_marks' => 50,
            'grading_schema' => [
                ['max' => 50, 'label' => 'مقبول'],
                ['max' => 70, 'label' => 'جيد'],
                ['max' => 90, 'label' => 'جيد جداً'],
                ['max' => 100, 'label' => 'امتياز'],
            ],
            'is_active' => true,
        ]);
    }

    private function register(Student $student, Course $course): Registration
    {
        return app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 30000,
            'items' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);
    }

    public function test_save_grade_snapshots_total_grade_and_pass_flag(): void
    {
        $student = Student::create(['name' => 'Sami', 'status' => 'active']);
        $registration = $this->register($student, $this->makeCourse());

        $this->assertNull($registration->grade_total);

        $registration->saveGrade(85, (int) $this->admin()->id);

        $fresh = $registration->fresh();
        $this->assertSame(85.0, (float) $fresh->grade_total);
        $this->assertSame('جيد جداً', $fresh->grades['grade']);
        $this->assertTrue($fresh->grades['passed']);
        $this->assertSame(100, $fresh->grades['full_mark']);
        $this->assertNotNull($fresh->graded_at);

        $this->assertDatabaseHas((new AuditLog())->getTable(), [
            'action' => 'registration.graded',
            'entity_type' => Registration::class,
            'entity_id' => $registration->id,
        ]);
    }

    public function test_mark_below_pass_threshold_is_failed(): void
    {
        $student = Student::create(['name' => 'Khalid', 'status' => 'active']);
        $registration = $this->register($student, $this->makeCourse());

        $registration->saveGrade(30, (int) $this->admin()->id);

        $fresh = $registration->fresh();
        $this->assertSame('مقبول', $fresh->grades['grade']);
        $this->assertFalse($fresh->grades['passed']);
    }

    public function test_certificate_print_route_requires_passed_grade(): void
    {
        $student = Student::create(['name' => 'Ahmed', 'status' => 'active']);
        $registration = $this->register($student, $this->makeCourse());

        $this->actingAs($this->admin())
            ->get(route('certificates.print', $registration))
            ->assertNotFound();

        $registration->saveGrade(51, (int) $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('certificates.print', $registration))
            ->assertOk()
            ->assertSee($student->name);
    }

    public function test_marks_sheet_print_route_renders_batch_rows(): void
    {
        $course = $this->makeCourse();
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => '2026-A',
            'year' => '2026',
        ]);
        $student = Student::create(['name' => 'Nabil', 'status' => 'active']);
        $registration = $this->register($student, $course);
        $registration->update(['course_batch_id' => $batch->id]);
        $registration->saveGrade(88, (int) $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('marks.batch.print', $batch))
            ->assertOk()
            ->assertSee($student->name);
    }
}