<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin'];
    }

    protected static function createRoles(): array
    {
        return ['admin'];
    }

    protected static function editRoles(): array
    {
        return ['admin'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('general.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.users');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.users');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required(),
                TextInput::make('email')->label(__('general.email'))->email()->unique(ignoreRecord: true)->required(),
                TextInput::make('password')
                    ->label(__('general.password'))
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(8)
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label(__('general.password_confirm'))
                    ->password()
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation !== 'view'),
                Select::make('roles')->native(false)
                    ->label(__('general.roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('general.name'))->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('general.email'))->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label(__('general.roles'))->badge(),
                Tables\Columns\TextColumn::make('created_at')->label(__('general.date'))->dateTime('d/m/Y'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
