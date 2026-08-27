<?php

namespace Tests\Feature;

use App\Filament\Pages\Finances;
use App\Filament\Widgets\MoneyPlacesWidget;
use App\Filament\Widgets\PartyBalancesWidget;
use App\Filament\Widgets\TrialBalanceWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireFinancesTest extends TestCase
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

    public function test_finances_page_and_widgets_livewire_hydration(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Finances::class)->call('$refresh')->assertStatus(200);
        Livewire::test(MoneyPlacesWidget::class)->call('$refresh')->assertStatus(200);
        Livewire::test(PartyBalancesWidget::class)->call('$refresh')->assertStatus(200);
        Livewire::test(TrialBalanceWidget::class)->call('$refresh')->assertStatus(200);
    }

    public function test_finances_widgets_are_not_lazy_loaded(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/finances');
        $response->assertOk();

        $content = $response->getContent();
        
        // Assert that the page contains the rendered content of all 3 widgets directly (non-lazy)
        $this->assertStringContainsString(__('general.money_places'), $content);
        $this->assertStringContainsString(__('general.party_balances'), $content);
        $this->assertStringContainsString(__('general.trial_balance_short'), $content);
    }

    public function test_registrar_receives_403_on_finances(): void
    {
        $registrar = User::factory()->create();
        $registrar->assignRole('registrar');

        $response = $this->actingAs($registrar)->get('/admin/finances');
        $response->assertStatus(403);
    }
}
