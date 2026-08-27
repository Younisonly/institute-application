<?php

namespace App\Filament\Forms\Components;

use App\Models\InstituteSetting;
use App\Services\MoneyWordsService;
use Filament\Forms\Components\TextInput;

class MoneyInput extends TextInput
{
    private const MAX_VALUE = 999999999999.99;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inputMode('decimal')->numeric()->type('text')->minValue(0)->maxValue(self::MAX_VALUE);
        $this->live(debounce: 500);
        $this->afterStateHydrated(fn (MoneyInput $component, mixed $state): mixed => $component->state(
            is_string($state) ? str_replace(',', '', $state) : $state,
        ));
        $this->afterStateUpdated(function (MoneyInput $component, mixed $state, \Filament\Forms\Set $set): mixed {
            $val = is_string($state) ? str_replace(',', '', $state) : $state;
            if ((float) $val > self::MAX_VALUE) {
                \Filament\Notifications\Notification::make()
                    ->title(__('general.number_too_large'))
                    ->warning()
                    ->send();
                
                $set($component->getName(), (string) self::MAX_VALUE);
                return $component->state((string) self::MAX_VALUE);
            }
            return $component->state($val);
        });
        $this->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state)
            ? (float) str_replace(',', '', $state)
            : $state);
        $this->helperText(fn (mixed $state): string => self::words($state));
    }

    public static function words(mixed $state, ?string $currency = null): string
    {
        $amount = is_string($state) ? (float) str_replace(',', '', $state) : (float) ($state ?? 0);

        return app(MoneyWordsService::class)->toArabicRials($amount, $currency ?? (string) InstituteSetting::current()->currency_label);
    }
}
