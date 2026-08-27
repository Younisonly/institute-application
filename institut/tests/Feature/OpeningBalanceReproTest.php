<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpeningBalanceReproTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_opening_balances_page_render_and_submit(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $this->actingAs($admin);

        $account = \App\Models\Account::query()->firstOrFail();

        Livewire::test(\App\Filament\Pages\OpeningBalances::class)
            ->assertOk()
            ->fillForm([
                'entries' => [
                    ['account_id' => $account->id, 'amount' => 5000],
                ],
            ])
            ->call('postBalances')
            ->assertHasNoErrors();
    }
}
