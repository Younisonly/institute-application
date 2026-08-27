<?php

namespace Tests\Feature;

use App\Models\InstituteSetting;
use App\Models\Course;
use App\Models\JobTitle;
use App\Models\ProgramType;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\StaffTransaction;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\ReceiptNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentsStaffTest extends TestCase
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

    public function test_students_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/students')->assertOk();
    }

    public function test_staff_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/staff')->assertOk();
    }

    public function test_job_titles_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/job-titles')->assertOk();
    }

    public function test_staff_view_page_renders(): void
    {
        $job = JobTitle::create(['name' => 'أستاذ لغة']);
        $staff = Staff::create([
            'name' => 'Khalid',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())->get("/admin/staff/{$staff->id}")->assertOk();
    }

    public function test_staff_edit_page_renders(): void
    {
        $job = JobTitle::create(['name' => 'أستاذ لغة']);
        $staff = Staff::create([
            'name' => 'Khalid',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/staff/{$staff->id}/edit")
            ->assertOk()
            ->assertSee('fileUploadFormComponent', false);
    }

    public function test_staff_specialties_pivot_sync_and_edit_page_render(): void
    {
        $course = Course::create([
            'name' => 'برمجة',
            'program_type_id' => ProgramType::create(['name' => 'دورة قصيرة', 'months_count' => 3])->id,
            'months' => 3,
            'price' => 50000,
        ]);
        $job = JobTitle::create(['name' => 'أستاذ لغة']);
        $staff = Staff::create([
            'name' => 'Nada',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
        ]);

        $staff->courses()->sync([$course->id]);
        $this->assertEquals(1, $staff->courses()->count());
        $this->assertNotNull($staff->courses()->first()->pivot->updated_at);

        $this->actingAs($this->admin())
            ->get("/admin/staff/{$staff->id}/edit")
            ->assertOk()
            ->assertSee('fileUploadFormComponent', false);
    }

    public function test_student_edit_page_renders(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->get("/admin/students/{$student->id}/edit")
            ->assertOk()
            ->assertSee('fileUploadFormComponent', false);
    }

    public function test_student_view_page_renders(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);

        $this->actingAs($this->admin())->get("/admin/students/{$student->id}")->assertOk();
    }

    public function test_balance_is_derived_from_transactions(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);

        StudentTransaction::create(['student_id' => $student->id, 'type' => 'charge', 'amount' => 10000, 'date' => now()]);
        StudentTransaction::create(['student_id' => $student->id, 'type' => 'payment', 'amount' => 3000, 'date' => now()]);
        StudentTransaction::create(['student_id' => $student->id, 'type' => 'refund', 'amount' => 500, 'date' => now()]);

        $fresh = Student::query()->withBalance()->findOrFail($student->id);

        $this->assertEquals(7500, $fresh->balance);
    }

    public function test_voiding_a_payment_restores_balance(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);

        StudentTransaction::create(['student_id' => $student->id, 'type' => 'charge', 'amount' => 10000, 'date' => now()]);
        $payment = StudentTransaction::create(['student_id' => $student->id, 'type' => 'payment', 'amount' => 3000, 'date' => now()]);

        $payment->void('wrong amount');

        $fresh = Student::query()->withBalance()->findOrFail($student->id);

        $this->assertEquals(10000, $fresh->balance);
        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertEquals('wrong amount', $payment->fresh()->void_reason);
    }

    public function test_receipt_numbers_are_sequential_and_unique(): void
    {
        $service = app(ReceiptNumberService::class);

        $first = $service->next();
        $second = $service->next();
        $third = $service->next();

        $this->assertEquals(1, $first);
        $this->assertEquals(2, $second);
        $this->assertEquals(3, $third);
        $this->assertEquals(4, InstituteSetting::current()->receipt_next_no);
    }

    public function test_staff_outstanding_advance_is_derived(): void
    {
        $job = JobTitle::create(['name' => 'أستاذ رياضيات']);
        $staff = Staff::create([
            'name' => 'Ahmed',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 80000,
            'status' => 'active',
        ]);

        StaffTransaction::create(['staff_id' => $staff->id, 'type' => 'advance', 'amount' => 20000, 'date' => now()]);
        StaffTransaction::create(['staff_id' => $staff->id, 'type' => 'advance', 'amount' => 15000, 'date' => now()]);
        StaffTransaction::create(['staff_id' => $staff->id, 'type' => 'repayment', 'amount' => 10000, 'date' => now()]);
        StaffTransaction::create(['staff_id' => $staff->id, 'type' => 'salary', 'amount' => 50000, 'date' => now()]);

        $fresh = Staff::query()->withAccount()->findOrFail($staff->id);

        $this->assertEquals(25000, $fresh->outstanding_advance);
        $this->assertEquals(50000, $fresh->total_salary_paid);
    }

    public function test_voiding_staff_advance_updates_outstanding(): void
    {
        $job = JobTitle::create(['name' => 'أستاذ فيزياء']);
        $staff = Staff::create([
            'name' => 'Ahmed',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 80000,
            'status' => 'active',
        ]);

        $advance = StaffTransaction::create(['staff_id' => $staff->id, 'type' => 'advance', 'amount' => 20000, 'date' => now()]);
        $advance->void('entered twice');

        $fresh = Staff::query()->withAccount()->findOrFail($staff->id);

        $this->assertEquals(0, $fresh->outstanding_advance);
    }

    public function test_student_status_enum_values_work(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'suspended']);

        $this->assertEquals('suspended', $student->fresh()->status);
    }

    public function test_student_guardian_fields_persist(): void
    {
        $student = Student::create([
            'name' => 'Ali',
            'gender' => 'male',
            'birth_date' => '2005-03-15',
            'guardian_name' => 'Abdullah',
            'guardian_relation' => 'father',
            'guardian_phone' => '770123456',
            'education_level' => 'secondary',
            'status' => 'active',
        ]);

        $fresh = $student->fresh();
        $this->assertEquals('Abdullah', $fresh->guardian_name);
        $this->assertEquals('father', $fresh->guardian_relation);
        $this->assertNull($fresh->father_name ?? null);
    }

    public function test_job_title_seeder_runs(): void
    {
        $this->assertTrue(JobTitle::query()->where('name', 'معلم')->exists());
    }

    public function test_public_storage_url_uses_app_url(): void
    {
        $url = config('filesystems.disks.public.url');
        $appUrl = rtrim(config('app.url'), '/');

        $this->assertStringStartsWith($appUrl . '/storage', $url);
    }

    public function test_student_delete_blocked_when_active_registration_or_balance(): void
    {
        $student = Student::create(['name' => 'Sam', 'status' => 'active']);

        StudentTransaction::create([
            'student_id' => $student->id,
            'type' => 'charge',
            'amount' => 5000,
            'date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        try {
            $student->delete();
            $this->fail('Expected RuntimeException for student with balance.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('general.cannot_delete_student_with_balance'), $e->getMessage());
        }

        $student->transactions()->delete();
        $student->delete();
        $this->assertNotNull($student->fresh()->deleted_at);
    }

    public function test_staff_delete_is_soft_and_restorable(): void
    {
        $job = JobTitle::create(['name' => 'أستاذ أحياء']);
        $staff = Staff::create([
            'name' => 'Mona',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 60000,
            'status' => 'active',
        ]);

        $staff->delete();
        $this->assertNotNull($staff->fresh()->deleted_at);
        $this->assertDatabaseHas('staff', ['name' => 'Mona']);

        $staff->restore();
        $this->assertNull($staff->fresh()->deleted_at);
    }

    public function test_staff_document_download_forces_attachment(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('staff-documents/sample.pdf', '%PDF-1.4 fake');

        $job = JobTitle::create(['name' => 'أستاذ كيمياء']);
        $staff = Staff::create([
            'name' => 'Omar',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
        ]);
        $doc = StaffDocument::create([
            'staff_id' => $staff->id,
            'label' => 'CV',
            'file_path' => 'staff-documents/sample.pdf',
        ]);

        $response = $this->actingAs($this->admin())->get(route('staff-documents.download', $doc));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=CV.pdf');
        $this->assertSame('%PDF-1.4 fake', $response->streamedContent());
    }

    public function test_trashed_document_download_returns_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('staff-documents/old.pdf', 'data');

        $job = JobTitle::create(['name' => 'أستاذ فيزياء']);
        $staff = Staff::create([
            'name' => 'Lina',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
        ]);
        $doc = StaffDocument::create([
            'staff_id' => $staff->id,
            'file_path' => 'staff-documents/old.pdf',
        ]);
        $doc->delete();

        $this->actingAs($this->admin())
            ->get(route('staff-documents.download', $doc))
            ->assertNotFound();
    }
}
