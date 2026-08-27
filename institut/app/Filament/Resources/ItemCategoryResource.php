<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\ItemCategoryResource\Pages;
use App\Models\ItemCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemCategoryResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
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

    protected static ?string $model = ItemCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.item_categories');
    }

    public static function getModelLabel(): string
    {
        return __('general.item_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.item_categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->unique(ignoreRecord: true)->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('items_count')
                    ->label(__('general.items'))
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItemCategories::route('/'),
            'create' => Pages\CreateItemCategory::route('/create'),
            'edit' => Pages\EditItemCategory::route('/{record}/edit'),
        ];
    }
}
