<?php

namespace App\Filament\Resources\CashboxShiftResource\Pages;

use App\Filament\Resources\CashboxShiftResource;
use App\Models\Cashbox;
use App\Services\CashboxShiftService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCashboxShifts extends ListRecords
{
    protected static string $resource = CashboxShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_shift')
                ->label(__('general.open_shift'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Select::make('cashbox_id')
                        ->label(__('general.cashbox'))
                        ->options(fn (): array => Cashbox::query()->where('is_active', true)->get()->mapWithKeys(fn (Cashbox $c): array => [$c->id => $c->name])->all())
                        ->default(fn () => Auth::user()?->default_cashbox_id ?? Cashbox::query()->where('is_default', true)->value('id'))
                        ->required()
                        ->native(false),
                    TextInput::make('opening_balance')
                        ->label(__('general.opening_balance'))
                        ->numeric()
                        ->default(0)
                        ->prefix('YER')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(CashboxShiftService::class)->openShift(
                        (int) $data['cashbox_id'],
                        Auth::id(),
                        (float) $data['opening_balance']
                    );
                    Notification::make()->title(__('general.open_shift'))->success()->send();
                }),
        ];
    }
}
