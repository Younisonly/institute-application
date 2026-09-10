<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RestoreDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_restore_command_fails_if_file_does_not_exist(): void
    {
        $this->artisan('finance:restore', ['--file' => '/path/to/non_existent_file.sql'])
            ->expectsOutputToContain('Backup file not found')
            ->assertExitCode(1);
    }

    public function test_admin_can_access_institute_settings_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/institute-settings');

        $response->assertSuccessful();
    }
}
