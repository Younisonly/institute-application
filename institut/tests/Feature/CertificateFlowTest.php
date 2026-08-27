<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateResource;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ProgramCourse;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\ProgressionService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CertificateFlowTest extends TestCase
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
        return Student::create(['name' => 'Cert '.uniqid(), 'status' => 'active']);
    }

    private function passCourse(Student $student, Course $course): Registration
    {
        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-01',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 0,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-10',
        ], (int) $this->admin()->id);
        $registration->saveGrade(88, (int) $this->admin()->id);
        $registration->update(['result' => 'pass']);

        return $registration;
    }

    private function entry(ProgramType $program, Course $course, int $level, bool $required = true): ProgramCourse
    {
        return ProgramCourse::create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'level_no' => $level,
            'sort_order' => 1,
            'is_required' => $required,
        ]);
    }

    public function test_issue_blocked_until_all_required_courses_are_passed(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Core A');
        $b = $this->makeCourse($program, 'Core B');
        $this->entry($program, $a, 1);
        $this->entry($program, $b, 2);
        $student = $this->makeStudent();

        $this->passCourse($student, $a);

        try {
            app(CertificateService::class)->issue($student, $program);
            $this->fail('issue must be blocked while Core B is missing');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Core B', $exception->getMessage());
        }

        $this->assertDatabaseMissing('certificates', ['student_id' => $student->id]);
    }

    public function test_issue_after_completing_all_required_courses(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Core A');
        $b = $this->makeCourse($program, 'Core B');
        $optional = $this->makeCourse($program, 'Optional');
        $this->entry($program, $a, 1);
        $this->entry($program, $b, 2);
        $this->entry($program, $optional, 3, required: false);
        $student = $this->makeStudent();

        $this->passCourse($student, $a);
        $this->passCourse($student, $b);

        $certificate = app(CertificateService::class)->issue($student, $program);

        $this->assertTrue($certificate->status === Certificate::STATUS_ISSUED);
        $this->assertSame('00001', $certificate->certificate_no);
        $this->assertSame($program->name, $certificate->title_ar);
        $this->assertSame($program->name, $certificate->title_en);
        $this->assertNotEmpty($certificate->verification_code);

        $courses = $certificate->earned_courses;
        $this->assertCount(2, $courses, 'snapshot contains only the passed curriculum courses');
        $this->assertSame([$a->name, $b->name], array_column($courses, 'course'));
        $this->assertSame('88.00', $courses[0]['mark']);
    }

    public function test_issue_requires_curriculum_and_blocks_duplicates(): void
    {
        $program = $this->makeProgram();
        $course = $this->makeCourse($program, 'Solo');
        $this->entry($program, $course, 1);
        $student = $this->makeStudent();

        $this->expectException(ValidationException::class);
        app(CertificateService::class)->issue($student, $program);
    }

    public function test_second_issued_certificate_for_same_program_blocked(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);

        app(CertificateService::class)->issue($student, $program);

        try {
            app(CertificateService::class)->issue($student, $program);
            $this->fail('a second issued certificate must be blocked');
        } catch (ValidationException $exception) {
            $this->assertSame(__('general.certificate_already_issued'), $exception->getMessage());
        }

        $this->assertSame(1, Certificate::query()->where('student_id', $student->id)->count());
    }

    public function test_void_keeps_row_and_allows_reissue(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);

        $certificate = app(CertificateService::class)->issue($student, $program);
        app(CertificateService::class)->void($certificate, 'Wrong student name');

        $this->assertTrue($certificate->refresh()->isVoided());
        $this->assertSame('Wrong student name', $certificate->void_reason);
        $this->assertNotNull($certificate->voided_at);

        $reissued = app(CertificateService::class)->issue($student, $program);
        $this->assertSame('00002', $reissued->certificate_no, 'sequential numbers never reuse');
    }

    public function test_issue_and_void_are_audited(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);

        $this->actingAs($this->admin());

        $certificate = app(CertificateService::class)->issue($student, $program);
        app(CertificateService::class)->void($certificate, 'Issued by mistake');

        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.issued', 'entity_id' => $certificate->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.voided', 'entity_id' => $certificate->id]);
    }

    public function test_print_route_renders_program_certificate(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);

        $certificate = app(CertificateService::class)->issue($student, $program);

        $this->actingAs($this->admin())
            ->get(route('certificates.register.print', $certificate))
            ->assertSuccessful()
            ->assertSee($certificate->verification_code);

        $voided = app(CertificateService::class)->void($certificate, 'voided');
        $this->actingAs($this->admin())
            ->get(route('certificates.register.print', $voided))
            ->assertNotFound();
    }

    public function test_verification_page_checks_codes(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);
        $certificate = app(CertificateService::class)->issue($student, $program);

        $this->get(route('certificates.verify'))
            ->assertSuccessful();

        $this->get(route('certificates.verify', ['code' => $certificate->verification_code]))
            ->assertSuccessful()
            ->assertSee($certificate->student->name)
            ->assertSee(__('general.certificate_valid'));

        $this->get(route('certificates.verify', ['code' => 'ZZZZZZZZZZ']))
            ->assertSuccessful()
            ->assertSee(__('general.certificate_not_found'));
    }

    public function test_graduation_evaluation_counts_only_required(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Need');
        $b = $this->makeCourse($program, 'Skip');
        $this->entry($program, $a, 1);
        $this->entry($program, $b, 2, required: false);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);

        $evaluation = app(ProgressionService::class)->graduationEligible($student->id, $program);

        $this->assertTrue($evaluation['eligible']);
        $this->assertSame(1, $evaluation['required']);
        $this->assertSame(1, $evaluation['passed']);
        $this->assertSame([], $evaluation['missing']);
    }

    public function test_certificate_register_list_renders(): void
    {
        $program = $this->makeProgram();
        $a = $this->makeCourse($program, 'Only');
        $this->entry($program, $a, 1);
        $student = $this->makeStudent();
        $this->passCourse($student, $a);
        $certificate = app(CertificateService::class)->issue($student, $program);

        $this->actingAs($this->admin())
            ->get(CertificateResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee($certificate->certificate_no);

        $this->actingAs($this->admin())
            ->get(CertificateResource::getUrl('view', ['record' => $certificate->id]))
            ->assertSuccessful()
            ->assertSee($certificate->verification_code);
    }
}