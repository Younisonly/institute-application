<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGuardedDeletes;
use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierResource extends Resource
{
    use HasGuardedDeletes;
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_parties');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.suppliers');
    }

    public static function getModelLabel(): string
    {
        return __('general.supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.suppliers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(20),
                TextInput::make('address')->label(__('general.address'))->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('phone')->label(__('general.phone'))->searchable()->icon('heroicon-m-phone'),
                TextColumn::make('address')->label(__('general.address'))->limit(30)->toggleable(),
                TextColumn::make('items_count')
                    ->label(__('general.items'))
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('debt')
                    ->label(__('general.total_charge'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->color('warning')
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('debt'))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ),
                TextColumn::make('paid')
                    ->label(__('general.paid'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->color('success')
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('paid'))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->formatStateUsing(fn ($state): string => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) $state))
                    ->weight('semibold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : ((float) $state < 0 ? 'success' : 'gray'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatSupplierBalance($state, true))
                    ),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_debt')
                    ->label(__('general.has_debt'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('purchases')),
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
                static::guardedForceDeleteAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    static::guardedForceDeleteBulkAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
