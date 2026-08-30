<?php

namespace App\Filament\Resources\CashboxShiftResource\Pages;

use App\Filament\Resources\CashboxShiftResource;
use App\Models\CashboxShift;
use App\Services\CashboxShiftService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCashboxShift extends ViewRecord
{
    protected static string $resource = CashboxShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reconcile')
                ->label(__('general.close_shift'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->isOpen())
                ->form([
                    TextInput::make('physical_cash_count')
                        ->label(__('general.physical_cash_count'))
                        ->numeric()
                        ->required()
                        ->prefix('YER'),
                    Textarea::make('variance_notes')
                        ->label(__('general.variance_notes')),
                    Toggle::make('transfer_to_main_safe')
                        ->label(__('general.transfer_to_main_safe'))
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    app(CashboxShiftService::class)->closeAndReconcile(
                        $this->record,
                        (float) $data['physical_cash_count'],
                        $data['variance_notes'] ?? null,
                        (bool) ($data['transfer_to_main_safe'] ?? false)
                    );
                    Notification::make()->title(__('general.shift_closed_success'))->success()->send();
                    $this->refreshFormData();
                }),
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('shifts.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
