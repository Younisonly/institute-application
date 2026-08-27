<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseBatchResource\Pages\EditCourseBatch;
use App\Filament\Resources\CourseBatchResource\Pages\ListCourseBatches;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function makeBatch(): CourseBatch
    {
        $type = ProgramType::create(['name' => 'Dip '.uniqid(), 'months_count' => 24]);
        $course = Course::create([
            'name' => 'English L1 '.uniqid(),
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'full_mark' => 100,
            'success_marks' => 50,
            'is_active' => true,
        ]);

        return CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'year' => '2026', 'is_active' => true]);
    }

    public function test_teacher_can_view_students_but_not_manage_them_or_reach_finance(): void
    {
        $this->actingAs($this->userWithRole('teacher'));

        $this->get('/admin/students')->assertOk();
        $this->get('/admin/students/create')->assertForbidden();
        $this->get('/admin/payments')->assertForbidden();
        $this->get('/admin/expenses')->assertForbidden();
        $this->get('/admin/finances')->assertForbidden();
        $this->get('/admin/institute-settings')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/enrollment-transfers')->assertForbidden();
    }

    public function test_registrar_can_register_and_collect_payments_but_not_edit_money(): void
    {
        $this->actingAs($this->userWithRole('registrar'));

        $this->get('/admin/students/create')->assertOk();
        $this->get('/admin/registrations/create')->assertOk();
        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/expenses')->assertForbidden();
        $this->get('/admin/finances')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/enrollment-transfers')->assertOk();
    }

    public function test_accountant_has_finance_access_but_not_security_pages(): void
    {
        $this->actingAs($this->userWithRole('accountant'));

        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/expenses')->assertOk();
        $this->get('/admin/finances')->assertOk();
        $this->get('/admin/institute-settings')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/students/create')->assertForbidden();
        $this->get('/admin/enrollment-transfers')->assertForbidden();
    }

    public function test_admin_has_full_access(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->get('/admin/students/create')->assertOk();
        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/expenses')->assertOk();
        $this->get('/admin/finances')->assertOk();
        $this->get('/admin/institute-settings')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/enrollment-transfers')->assertOk();
    }

    public function test_teacher_and_registrar_can_reach_academic_pages_but_accountant_cannot(): void
    {
        $this->actingAs($this->userWithRole('teacher'));

        $this->get('/admin/batch-marks')->assertOk();
        $this->get('/admin/batch-attendance')->assertOk();

        $this->actingAs($this->userWithRole('registrar'));

        $this->get('/admin/batch-marks')->assertOk();
        $this->get('/admin/batch-attendance')->assertOk();

        $this->actingAs($this->userWithRole('accountant'));

        $this->get('/admin/batch-marks')->assertForbidden();
        $this->get('/admin/batch-attendance')->assertForbidden();
    }

    public function test_only_admin_can_finalize_results_and_cancel_batches_on_the_list(): void
    {
        $batch = $this->makeBatch();

        $this->actingAs($this->userWithRole('admin'));
        Livewire::test(ListCourseBatches::class)
            ->assertTableActionVisible('completeBatch');

        $this->actingAs($this->userWithRole('registrar'));
        Livewire::test(ListCourseBatches::class)
            ->assertTableActionHidden('completeBatch');

        $this->actingAs($this->userWithRole('accountant'));
        Livewire::test(ListCourseBatches::class)
            ->assertTableActionHidden('completeBatch');
    }

    public function test_only_admin_can_cancel_a_batch_from_the_edit_page(): void
    {
        $batch = $this->makeBatch();

        $this->actingAs($this->userWithRole('registrar'));
        Livewire::test(EditCourseBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('to_cancelled')
            ->assertActionVisible('to_in_progress');

        $this->actingAs($this->userWithRole('admin'));
        Livewire::test(EditCourseBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionVisible('to_cancelled');
    }
}