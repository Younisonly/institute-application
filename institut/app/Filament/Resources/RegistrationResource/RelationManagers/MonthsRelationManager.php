<?php

namespace App\Filament\Resources\RegistrationResource\RelationManagers;

use App\Models\RegistrationMonth;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MonthsRelationManager extends RelationManager
{
    protected static string $relationship = 'months';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.registration_months');
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    protected function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    protected function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('month')
            ->defaultSort('month', 'asc')
            ->columns([
                TextColumn::make('month')
                    ->label(__('general.month'))
                    ->formatStateUsing(fn (string $state): string => CarbonImmutable::createFromFormat('Y-m', $state)->translatedFormat('F Y'))
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => $state === 'open' ? 'success' : 'gray'),
                TextColumn::make('closed_at')
                    ->label(__('general.date'))
                    ->date('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('closeMonth')
                    ->label(__('general.close_month'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.close_month'))
                    ->modalDescription(__('general.close_month_confirm'))
                    ->action(function (RegistrationMonth $record): void {
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                        Notification::make()->title(__('general.closed'))->success()->send();
                    })
                    ->visible(fn (?RegistrationMonth $record): bool => $record !== null && $record->status === 'open'),
                Tables\Actions\Action::make('openMonth')
                    ->label(__('general.open_month'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->action(function (RegistrationMonth $record): void {
                        $record->update([
                            'status' => 'open',
                            'closed_at' => null,
                        ]);

                        Notification::make()->title(__('general.open'))->success()->send();
                    })
                    ->visible(fn (?RegistrationMonth $record): bool => $record !== null && $record->status === 'closed'),
            ]);
    }
}
