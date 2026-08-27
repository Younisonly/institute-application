<?php

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Models\Book;
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
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.stock_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'issue' => 'info',
                        'sold' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('qty')->label(__('general.quantity'))->weight('semibold'),
                TextColumn::make('unit_price')
                    ->label(__('general.unit_price'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? number_format((float) $state).' '.__('general.currency') : '—'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('supplier.name')->label(__('general.supplier'))->placeholder('—')->toggleable(),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')->label(__('general.status'))->badge()->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('stockIn')
                    ->label(__('general.stock_in'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->minValue(1),
                        MoneyInput::make('unit_price')->label(__('general.unit_price'))->minValue(0)->required(),
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
                        TextInput::make('reference')->label(__('general.reference'))->maxLength(100),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->adjustStock('in', $data);
                    }),
                Tables\Actions\Action::make('stockSold')
                    ->label(__('general.stock_sold'))
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->minValue(1),
                        MoneyInput::make('unit_price')->label(__('general.unit_price'))->minValue(0),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        ...PaymentDetails::fields(),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->adjustStock('sold', $data);
                    }),
                Tables\Actions\Action::make('stockDamaged')
                    ->label(__('general.stock_damaged'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->minValue(1),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->adjustStock('damaged', $data);
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
                        TextInput::make('void_reason')->label(__('general.void_reason'))->required()->maxLength(255),
                    ])
                    ->action(function (StockMovement $record, array $data): void {
                        $this->voidMovement($record, $data['void_reason']);
                    })
                    ->visible(fn (?StockMovement $record): bool => $record !== null && $record->voided_at === null && $record->type !== 'issue'),
            ]);
    }

    private function adjustStock(string $type, array $data): void
    {
        DB::transaction(function () use ($type, $data): void {
            /** @var Book $book */
            $book = Book::query()->lockForUpdate()->findOrFail($this->getOwnerRecord()->id);
            $qty = (int) $data['qty'];

            if ($type !== 'in' && $book->stock_qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => __('general.insufficient_stock', ['item' => $book->title]),
                ]);
            }

            $book->movements()->create([
                'book_id' => $book->id,
                'type' => $type,
                'qty' => $qty,
                'unit_price' => $type === 'in' ? ($data['unit_price'] ?? null) : ($data['unit_price'] ?? $book->sale_price),
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'date' => $data['date'] ?? now()->format('Y-m-d'),
                'supplier_id' => $type === 'in' ? ($data['supplier_id'] ?? null) : null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }

    private function voidMovement(StockMovement $record, string $reason): void
    {
        DB::transaction(function () use ($record, $reason): void {
            $record->void($reason);
        });

        Notification::make()->title(__('general.voided'))->success()->send();
    }
}
