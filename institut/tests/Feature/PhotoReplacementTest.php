<?php

namespace Tests\Feature;

use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Models\JobTitle;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoReplacementTest extends TestCase
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

    public function test_replacing_staff_photo_persists_the_new_image(): void
    {
        Storage::fake('public');
        $job = JobTitle::create(['name' => 'أستاذ لغة']);
        $staff = Staff::create([
            'name' => 'Khalid',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
            'photo_path' => 'staff/old-photo.png',
        ]);
        Storage::disk('public')->put('staff/old-photo.png', 'old-image');

        Livewire::actingAs($this->admin())
            ->test(EditStaff::class, ['record' => $staff->id])
            ->set('data.photo_path', UploadedFile::fake()->createWithContent('new-photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')))
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();

        $this->assertNotNull($staff->photo_path);
        $this->assertNotEquals('staff/old-photo.png', $staff->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($staff->photo_path));
    }

    public function test_replacing_student_photo_persists_the_new_image(): void
    {
        Storage::fake('public');
        $student = Student::create([
            'name' => 'Sara',
            'status' => 'active',
            'photo_path' => 'students/old-photo.png',
        ]);
        Storage::disk('public')->put('students/old-photo.png', 'old-image');

        Livewire::actingAs($this->admin())
            ->test(EditStudent::class, ['record' => $student->id])
            ->set('data.photo_path', UploadedFile::fake()->createWithContent('new-photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')))
            ->call('save')
            ->assertHasNoFormErrors();

        $student->refresh();

        $this->assertNotNull($student->photo_path);
        $this->assertNotEquals('students/old-photo.png', $student->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));
    }

    private function putTempUpload(string $filename, string $content): void
    {
        config()->set('filesystems.disks.tmp-for-tests', [
            'driver' => 'local',
            'root' => storage_path('app/livewire-tmp'),
        ]);

        Storage::disk('tmp-for-tests')->put("livewire-tmp/{$filename}", $content);
    }

    public function test_replacing_staff_photo_via_real_upload_flow_persists_new_image(): void
    {
        Storage::fake('public');
        $job = JobTitle::create(['name' => 'أستاذ لغة']);
        $staff = Staff::create([
            'name' => 'Khalid',
            'job_title_id' => $job->id,
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
            'photo_path' => 'staff/old-photo.png',
        ]);
        Storage::disk('public')->put('staff/old-photo.png', 'old-image');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $filename = 'abc123-size=1000-mimeType=image/png-hash=abc.png';
        $this->putTempUpload($filename, $png);

        Livewire::actingAs($this->admin())
            ->test(EditStaff::class, ['record' => $staff->id])
            ->call('_finishUpload', 'data.photo_path.9a2c1e3f-0000-4000-8000-000000000000', [$filename], false)
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();

        $this->assertNotNull($staff->photo_path);
        $this->assertNotEquals('staff/old-photo.png', $staff->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($staff->photo_path));
    }

    public function test_replacing_student_photo_via_real_upload_flow_persists_new_image(): void
    {
        Storage::fake('public');
        $student = Student::create([
            'name' => 'Sara',
            'status' => 'active',
            'photo_path' => 'students/old-photo.png',
        ]);
        Storage::disk('public')->put('students/old-photo.png', 'old-image');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $filename = 'abc123-size=1000-mimeType=image/png-hash=abc.png';
        $this->putTempUpload($filename, $png);

        Livewire::actingAs($this->admin())
            ->test(EditStudent::class, ['record' => $student->id])
            ->call('_finishUpload', 'data.photo_path.9a2c1e3f-0000-4000-8000-000000000000', [$filename], false)
            ->call('save')
            ->assertHasNoFormErrors();

        $student->refresh();

        $this->assertNotNull($student->photo_path);
        $this->assertNotEquals('students/old-photo.png', $student->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));
    }

    public function test_photo_field_blade_renders_src_helper(): void
    {
        Storage::fake('public');
        $staff = Staff::create([
            'name' => 'Khalid',
            'salary_type' => 'monthly',
            'salary_value' => 70000,
            'status' => 'active',
            'photo_path' => 'staff/old-photo.png',
        ]);
        Storage::disk('public')->put('staff/old-photo.png', 'old-image');

        $this->actingAs($this->admin())
            ->get("/admin/staff/{$staff->id}/edit")
            ->assertOk()
            ->assertSee('fileUploadFormComponent', false);
    }
}
