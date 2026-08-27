<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;

use App\Models\Registration;
use App\Models\Student;
use App\Models\User;

use App\Services\CertificateService;
use App\Services\CourseBatchService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsFlowTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::findByName($role));

        return $user;
    }

    private function makeProgramWithCurriculum(): ProgramType
    {
        $program = ProgramType::create(['name' => 'Diploma '.uniqid(), 'months_count' => 24]);
        $course = Course::create([
            'name' => 'Core '.uniqid(),
            'program_type_id' => $program->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'is_active' => true,
        ]);

        \App\Models\ProgramCourse::create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'level_no' => 1,
            'sort_order' => 1,
            'is_required' => true,
        ]);

        return $program;
    }

    private function passProgram(User $admin, ProgramType $program): Student
    {
        $student = Student::create(['name' => 'Cert '.uniqid(), 'status' => 'active']);

        foreach ($program->curriculum()->get() as $entry) {
            $registration = app(RegistrationService::class)->register([
                'student_id' => $student->id,
                'course_id' => $entry->course_id,
                'start_month' => '2026-08',
                'months_count' => 6,
                'price_snapshot' => 35000,
                'items' => [],
                'payment_amount' => 0,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-10',
            ], (int) $admin->id);

            app(RegistrationService::class)->complete($registration, (int) $admin->id, 'pass');
        }

        return $student;
    }

    public function test_certificate_issuance_notifies_other_admin_and_accountant_users(): void
    {
        $this->actingAs($this->admin());
        $accountant = $this->userWithRole('accountant');
        $otherAdmin = $this->userWithRole('admin');
        $program = $this->makeProgramWithCurriculum();
        $student = $this->passProgram($this->admin(), $program);

        app(CertificateService::class)->issue($student, $program);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $accountant->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $otherAdmin->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->admin()->id]);
    }

    public function test_certificate_void_notifies_other_users(): void
    {
        $this->actingAs($this->admin());
        $accountant = $this->userWithRole('accountant');
        $program = $this->makeProgramWithCurriculum();
        $student = $this->passProgram($this->admin(), $program);
        $certificate = app(CertificateService::class)->issue($student, $program);

        app(CertificateService::class)->void($certificate, 'printed with a typo');

        $count = \Illuminate\Support\Facades\DB::table('notifications')->where('notifiable_id', $accountant->id)->count();
        $this->assertSame(2, $count);
    }

    public function test_batch_completion_notifies_registrar_and_accountant(): void
    {
        $this->actingAs($this->admin());
        $registrar = $this->userWithRole('registrar');
        $accountant = $this->userWithRole('accountant');
        $course = $this->makeCourse();

        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        app(CourseBatchService::class)->complete($batch, (int) $this->admin()->id);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $registrar->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $accountant->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->admin()->id]);
    }

    public function test_batch_cancellation_notifies_accountant(): void
    {
        $this->actingAs($this->admin());
        $accountant = $this->userWithRole('accountant');
        $course = $this->makeCourse();
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);

        app(CourseBatchService::class)->transition($batch, 'cancelled', (int) $this->admin()->id, 'no demand');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $accountant->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->admin()->id]);
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
}