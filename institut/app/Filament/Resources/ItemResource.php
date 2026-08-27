<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers\MovementsRelationManager;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.items');
    }

    public static function getModelLabel(): string
    {
        return __('general.item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.items');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.item_name'))->required()->maxLength(255),
                TextInput::make('unit')
                    ->label(__('general.unit'))
                    ->placeholder(__('general.unit_hint'))
                    ->maxLength(30),
                Select::make('category_id')->native(false)
                    ->label(__('general.item_category'))
                    ->options(fn (): array => ItemCategory::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('general.name'))->required(),
                    ]),
                Select::make('supplier_id')->native(false)
                    ->label(__('general.supplier'))
                    ->options(fn (): array => Supplier::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('general.name'))->required(),
                        TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(20),
                    ]),
                TextInput::make('stock_qty')
                    ->label(__('general.current_stock'))
                    ->numeric()->maxValue(999999999999)
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText(__('general.stock_movements')),
                TextInput::make('low_stock_threshold')
                    ->label(__('general.low_stock_threshold'))
                    ->numeric()->maxValue(999999999999)
                    ->required()
                    ->default(5)
                    ->minValue(0),
                MoneyInput::make('purchase_price')
                    ->label(__('general.purchase_price'))
                    ->minValue(0)
                    ->suffix(__('general.currency')),
                MoneyInput::make('sale_price')
                    ->label(__('general.sale_price'))
                    ->minValue(0)
                    ->suffix(__('general.currency')),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.item_name'))->searchable()->weight('semibold'),
                TextColumn::make('category.name')->label(__('general.item_category'))->badge()->color('gray')->toggleable(),
                TextColumn::make('supplier.name')->label(__('general.supplier'))->toggleable(),
                TextColumn::make('stock_qty')
                    ->label(__('general.current_stock'))
                    ->badge()
                    ->color(fn (Item $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state, Item $record): string => $record->isLowStock() ? $state.' • '.__('general.low_stock') : (string) $state),
                TextColumn::make('low_stock_threshold')->label(__('general.low_stock_threshold'))->toggleable(),
                TextColumn::make('purchase_price')
                    ->label(__('general.purchase_price'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? number_format((float) $state).' '.__('general.currency') : '—')
                    ->toggleable(),
                TextColumn::make('sale_price')
                    ->label(__('general.sale_price'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? number_format((float) $state).' '.__('general.currency') : '—')
                    ->toggleable(),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->native(false)
                    ->label(__('general.item_category'))
                    ->options(fn (): array => ItemCategory::query()->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'], fn (Builder $q) => $q->where('category_id', $data['value']))),
                Tables\Filters\SelectFilter::make('supplier_id')->native(false)
                    ->label(__('general.supplier'))
                    ->relationship('supplier', 'name'),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make()->label(__('general.restore')),
                Tables\Actions\ForceDeleteAction::make()
                    ->label(__('general.force_delete'))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label(__('general.force_delete'))
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view' => Pages\ViewItem::route('/{record}'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
