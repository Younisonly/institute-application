<?php

namespace App\Filament\Resources\RegistrationResource\RelationManagers;

use App\Models\RegistrationItem;
use App\Services\RegistrationService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.items');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->modifyQueryUsing(fn ($query) => $query->whereNull('voided_at'))
            ->columns([
                TextColumn::make('label')->label(__('general.name'))->weight('semibold'),
                TextColumn::make('is_book')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('general.book') : __('general.item'))
                    ->color(fn (bool $state): string => $state ? 'info' : 'warning'),
                TextColumn::make('qty')->label(__('general.quantity'))->badge(),
                TextColumn::make('unit_price')
                    ->label(__('general.price'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state).' '.__('general.currency')),
                TextColumn::make('total')
                    ->label(__('general.total'))
                    ->state(fn (RegistrationItem $record): float => (float) $record->qty * (float) $record->unit_price)
                    ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('description')->label(__('general.description'))->limit(40)->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('voidItem')
                    ->label(__('general.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.void'))
                    ->modalDescription(__('general.must_provide_void_reason'))
                    ->form([
                        TextInput::make('void_reason')
                            ->label(__('general.void_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (RegistrationItem $record, array $data): void {
                        app(RegistrationService::class)->voidIssuedItem($record, $data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    }),
            ]);
    }
}
