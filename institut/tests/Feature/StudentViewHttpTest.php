<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentViewHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_student_page(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin'));

        $student = Student::create([
            'name' => 'Test Student',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get('/admin/students/' . $student->id);

        $response->assertStatus(200);
    }
}
