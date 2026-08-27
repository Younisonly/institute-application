<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\IncomeCategoryResource\Pages;
use App\Models\IncomeCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncomeCategoryResource extends Resource
{
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

    protected static ?string $model = IncomeCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_parties');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.income_categories');
    }

    public static function getModelLabel(): string
    {
        return __('general.income_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.income_categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->unique(ignoreRecord: true)->maxLength(255),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('account.name')
                    ->label(__('general.account'))
                    ->badge()
                    ->color('success')
                    ->toggleable(),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomeCategories::route('/'),
            'create' => Pages\CreateIncomeCategory::route('/create'),
            'edit' => Pages\EditIncomeCategory::route('/{record}/edit'),
        ];
    }
}
