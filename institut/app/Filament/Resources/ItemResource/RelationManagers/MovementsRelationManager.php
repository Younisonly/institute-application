<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Forms\Components\PaymentDetails;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.stock_movements');
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
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? __('general.stock_in') : __("general.stock_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'sold' => 'info',
                        'damaged' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('qty')->label(__('general.quantity'))->badge(),
                TextColumn::make('unit_price')
                    ->label(__('general.price'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? number_format((float) $state).' '.__('general.currency') : '—')
                    ->toggleable(),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('stockIn')
                    ->label(__('general.stock_in'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        ...$this->movementFields(),
                        Select::make('supplier_id')->native(false)
                            ->label(__('general.supplier'))
                            ->options(fn (): array => Supplier::query()->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label(__('general.name'))->required(),
                                TextInput::make('phone')->label(__('general.phone'))->tel(),
                                TextInput::make('address')->label(__('general.address')),
                            ])
                            ->required()
                            ->helperText(__('general.stock_in_supplier_hint')),
                    ])
                    ->action(function (array $data): void {
                        $this->createMovement('in', $data, +1);
                    }),
                Tables\Actions\Action::make('stockSold')
                    ->label(__('general.stock_sold'))
                    ->icon('heroicon-o-shopping-cart')
                    ->color('info')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        ...$this->movementFields(),
                        ...PaymentDetails::fields(),
                    ])
                    ->action(function (array $data): void {
                        $this->createMovement('sold', $data, -1);
                    }),
                Tables\Actions\Action::make('stockDamaged')
                    ->label(__('general.stock_damaged'))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form($this->movementFields())
                    ->action(function (array $data): void {
                        $this->createMovement('damaged', $data, -1);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('void')
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
                    ->action(function (StockMovement $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->void($data['void_reason']);
                        });

                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?StockMovement $record): bool => $record !== null && $record->voided_at === null && $record->type !== 'issue'),
            ]);
    }

    private function movementFields(): array
    {
        return [
            TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->minValue(1),
            MoneyInput::make('unit_price')
                ->label(__('general.price'))
                ->minValue(0)
                ->suffix(__('general.currency')),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            TextInput::make('reference')->label(__('general.reference'))->maxLength(100),
            TextInput::make('description')->label(__('general.description'))->maxLength(255),
        ];
    }

    private function createMovement(string $type, array $data, int $stockSign): void
    {
        DB::transaction(function () use ($type, $data, $stockSign): void {
            $item = Item::query()->lockForUpdate()->findOrFail($this->getOwnerRecord()->id);

            if ($stockSign < 0 && $item->stock_qty < $data['qty']) {
                throw ValidationException::withMessages([
                    'qty' => __('general.insufficient_stock', ['item' => $item->name]),
                ]);
            }

            $item->movements()->create([
                'item_id' => $item->id,
                'type' => $type,
                'qty' => $data['qty'],
                'unit_price' => $data['unit_price'] ?? null,
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'date' => $data['date'],
                'supplier_id' => $type === 'in' ? ($data['supplier_id'] ?? null) : null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }
}
