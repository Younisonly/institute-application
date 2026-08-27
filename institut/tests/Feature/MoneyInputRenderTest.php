<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyInputRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_payments_page_money_fields_render_without_dead_mask_or_number_type(): void
    {
        $this->actingAs(User::first());

        $response = $this->get('/admin/payments');
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('data.amount', $html);
        $this->assertStringNotContainsString('x-mask', $html, 'Money fields must not render Alpine masks ($money() is undefined in Filament 3.3 and number+mask deletes keystrokes)');
        $this->assertStringNotContainsString('$money($input)', $html);
        $this->assertStringNotContainsString('type="number"', $html, 'Money fields must not be type=number (native spinners reject formatted values)');
        $this->assertStringContainsString('inputmode="decimal"', $html);
    }

    public function test_money_input_component_is_text_with_decimal_inputmode(): void
    {
        $component = MoneyInput::make('amount');

        $this->assertSame('text', $component->getType());
        $this->assertSame('decimal', $component->getInputMode());
    }

    public function test_money_input_accepts_up_to_999_billion_without_error(): void
    {
        $component = MoneyInput::make('amount');

        $this->assertGreaterThanOrEqual(999000000000, $component->getMaxValue());
    }
}