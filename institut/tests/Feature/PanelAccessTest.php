<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_open_dashboard(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->get('/admin/login')->assertOk();
    }

    public function test_dashboard_renders_in_arabic_rtl(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    public function test_settings_page_renders(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();

        $this->actingAs($user)->get('/admin/institute-settings')->assertOk();
    }

    public function test_users_page_renders(): void
    {
        $user = User::query()->where('email', 'admin@institute.local')->firstOrFail();

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }
}
