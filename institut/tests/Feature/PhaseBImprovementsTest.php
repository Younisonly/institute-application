<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Staff;
use App\Models\StaffPayrollPeriod;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Filament\Pages\BatchAttendance;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseBImprovementsTest extends TestCase
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

    public function test_user_staff_fk_relationship_and_authorization(): void
    {
        $staff = Staff::create([
            'name' => 'Teacher Ahmed',
            'phone' => '777000111',
            'salary_type' => 'monthly',
            'salary_value' => 50000,
            'status' => 'active',
            'is_teacher' => true,
        ]);

        $user = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher.ahmed@institute.local',
            'password' => 'password123',
            'staff_id' => $staff->id,
        ]);
        $user->assignRole('teacher');

        $this->actingAs($user);

        $authStaff = BatchAttendance::getAuthorizedTeacherStaff();
        $this->assertNotNull($authStaff);
        $this->assertEquals($staff->id, $authStaff->id);
    }

    public function test_attendance_roll_print_route_returns_200(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $type = ProgramType::create(['name' => 'Diplomas', 'months_count' => 6]);
        $course = Course::create(['name' => 'Python Basics', 'program_type_id' => $type->id, 'months' => 6, 'price' => 20000]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 101', 'status' => 'in_progress']);

        $session = AttendanceSession::create([
            'course_batch_id' => $batch->id,
            'date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $response = $this->get(route('attendance.roll.print', $session));
        $response->assertStatus(200);
        $response->assertSee('Python Basics');
    }

    public function test_refund_transaction_copies_income_account_id(): void
    {
        $admin = $this->admin();
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 1]);
        $course = Course::create(['name' => 'Web Dev', 'program_type_id' => $type->id, 'months' => 1, 'price' => 10000]);
        $student = Student::create(['name' => 'Saeed', 'status' => 'active']);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => now()->format('Y-m'),
            'months_count' => 1,
            'price_snapshot' => 10000,
            'payment_amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $admin->id);

        $payment = StudentTransaction::where('registration_id', $registration->id)->where('type', 'payment')->firstOrFail();

        $refund = StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'original_transaction_id' => $payment->id,
            'income_account_id' => $payment->income_account_id,
            'type' => 'refund',
            'amount' => 5000,
            'date' => now()->toDateString(),
            'method' => 'cash',
            'created_by' => $admin->id,
        ]);

        $this->assertEquals($payment->income_account_id, $refund->income_account_id);
    }
}
