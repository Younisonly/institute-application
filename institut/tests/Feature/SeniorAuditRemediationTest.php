<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeniorAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_write_off_reduces_registration_and_student_balance_to_zero(): void
    {
        $student = Student::create(['name' => 'Ahmed Ali', 'status' => 'active']);
        $program = ProgramType::create(['name' => 'Program A', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course A', 'price' => 50000, 'months' => 1]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'status' => 'open']);

        $registration = app(RegistrationService::class)->register(
            data: [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => now()->format('Y-m'),
                'months_count' => 1,
                'price_snapshot' => 50000,
            ],
            createdBy: $this->user->id,
        );

        $this->assertEquals(50000, $registration->fresh()->balance);
        $this->assertEquals(50000, $student->fresh()->balance);

        app(RegistrationService::class)->close(
            registration: $registration,
            reason: 'Uncollectible balance',
            userId: $this->user->id,
            writeOff: true,
        );

        $freshRegistration = Registration::query()->withTotals()->find($registration->id);
        $freshStudent = Student::query()->withBalance()->find($student->id);

        $this->assertEquals(0, $freshRegistration->balance);
        $this->assertEquals(0, $freshStudent->balance);
    }

    public function test_registration_allows_initial_payment_up_to_total_charges_including_books(): void
    {
        $student = Student::create(['name' => 'Sara Omar', 'status' => 'active']);
        $program = ProgramType::create(['name' => 'Program B', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course B', 'price' => 50000, 'months' => 1]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'status' => 'open']);
        $book = Book::create(['title' => 'Book 1', 'sale_price' => 10000, 'purchase_price' => 5000, 'stock_qty' => 10]);

        $registration = app(RegistrationService::class)->register(
            data: [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => now()->format('Y-m'),
                'months_count' => 1,
                'price_snapshot' => 50000,
                'books' => [
                    ['book_id' => $book->id, 'qty' => 1, 'unit_price' => 10000],
                ],
                'payment_amount' => 60000,
                'payment_method' => 'cash',
                'payment_date' => now()->format('Y-m-d'),
            ],
            createdBy: $this->user->id,
        );

        $this->assertNotNull($registration);
        $this->assertEquals(0, Registration::query()->withTotals()->find($registration->id)->balance);
    }

    public function test_percentage_salary_includes_batch_assigned_teacher_collections(): void
    {
        $teacher = Staff::create([
            'name' => 'Ustadh Hassan',
            'phone' => '777000111',
            'salary_type' => 'percentage',
            'percentage_value' => 20.0,
            'is_teacher' => true,
            'status' => 'active',
        ]);

        $program = ProgramType::create(['name' => 'Program C', 'months_count' => 1]);
        $course = Course::create(['program_type_id' => $program->id, 'name' => 'Course C', 'price' => 100000, 'months' => 1, 'teacher_id' => null]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch A', 'status' => 'open', 'teacher_id' => $teacher->id]);
        $student = Student::create(['name' => 'Tariq Khaled', 'status' => 'active']);

        $registration = app(RegistrationService::class)->register(
            data: [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'course_batch_id' => $batch->id,
                'start_month' => now()->format('Y-m'),
                'months_count' => 1,
                'price_snapshot' => 100000,
                'payment_amount' => 100000,
                'payment_method' => 'cash',
                'payment_date' => now()->format('Y-m-d'),
            ],
            createdBy: $this->user->id,
        );

        $month = now()->format('Y-m');
        $sheet = app(ReportService::class)->salarySheet($month);

        $teacherRow = collect($sheet['rows'])->firstWhere('staff.id', $teacher->id);

        $this->assertNotNull($teacherRow);
        $this->assertEquals(20000.0, $teacherRow['amount']);
    }
}
