<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpChartDumpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump_dashboard(): void
    {
        $this->seed();
        $this->actingAs(User::first());

        $response = $this->get('/admin');
        file_put_contents('/tmp/opencode/dashboard_live.html', $response->getContent());
        $this->assertTrue($response->status() === 200);
    }
}
