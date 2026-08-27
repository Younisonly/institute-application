<?php

namespace Tests\Feature;

use App\Filament\Resources\StaffResource\Pages\ListStaff;
use App\Filament\Resources\StaffResource\Pages\ViewStaff;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffSalaryNullStateAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_list_staff_renders_with_percentage_staff(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $this->actingAs($admin);

        Staff::create([
            'name' => 'Percentage Worker',
            'salary_type' => 'percentage',
            'percentage_value' => 20,
            'status' => 'active',
        ]);

        Livewire::test(ListStaff::class)->assertOk();
    }

    public function test_view_staff_renders_with_percentage_staff(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $this->actingAs($admin);

        $staff = Staff::create([
            'name' => 'Percentage Worker 2',
            'salary_type' => 'percentage',
            'percentage_value' => 20,
            'status' => 'active',
        ]);

        Livewire::test(ViewStaff::class, ['record' => $staff->getKey()])->assertOk();
    }
}
