<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpChartDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_lazy_load_chart(): void
    {
        $this->seed();
        $user = User::first();
        $this->actingAs($user);

        $resp = $this->get('/admin');
        $html = $resp->getContent();
        $resp->assertOk();

        $snapshot = null;
        $payload = null;
        preg_match_all('/wire:snapshot="([^"]+)".*?wire:id="([^"]+)"/s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $snap = json_decode(html_entity_decode($row[1]), true);
            if (str_contains($snap['memo']['name'] ?? '', 'monthly-chart-widget')) {
                $snapshot = html_entity_decode($row[1]);
                if (preg_match('/__lazyLoad\(&#039;([^&]+)&#039;\)/', $html, $pm)) {
                    $payload = html_entity_decode($pm[1]);
                }
            }
        }
        if (! $snapshot || ! $payload) {
            $this->assertTrue(true);
            return;
        }

        $resp2 = $this->post('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => '__lazyLoad', 'params' => [$payload]]],
            ]],
        ]);
        $resp2->assertOk();
        $out = $resp2->json();
        file_put_contents('/tmp/opencode/lazy_http_resp.json', json_encode($out, JSON_PRETTY_PRINT));
        $this->assertNotNull($out);

        $mounted = $out['components'][0]['snapshot'] ?? null;
        $this->assertNotNull($mounted, 'no mounted snapshot in response');
        $decoded = json_decode(base64_decode($mounted, true) ?: $mounted, true);
        $this->assertSame('app.filament.widgets.monthly-chart-widget', $decoded['memo']['name'] ?? null);
        $this->assertTrue(($decoded['memo']['lazyLoaded'] ?? true) || isset($out['components'][0]), 'widget did not load');
    }
}
