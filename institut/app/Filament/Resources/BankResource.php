<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\BankResource\Pages;
use App\Models\Bank;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankResource extends Resource
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

    protected static ?string $model = Bank::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_places');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.banks');
    }

    public static function getModelLabel(): string
    {
        return __('general.bank');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.banks');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->maxLength(255),
                TextInput::make('account_no')->label(__('general.account_no'))->maxLength(255),
                TextInput::make('branch')->label(__('general.branch'))->maxLength(255),
                TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(50),
                TextInput::make('notes')->label(__('general.notes'))->maxLength(255),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('account_no')->label(__('general.account_no'))->placeholder('—'),
                TextColumn::make('branch')->label(__('general.branch'))->placeholder('—'),
                TextColumn::make('phone')->label(__('general.phone'))->placeholder('—'),
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
                Tables\Actions\ForceDeleteAction::make()
                    ->label(__('general.force_delete'))
                    ->requiresConfirmation(),
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
            'index' => Pages\ListBanks::route('/'),
            'create' => Pages\CreateBank::route('/create'),
            'edit' => Pages\EditBank::route('/{record}/edit'),
        ];
    }
}
