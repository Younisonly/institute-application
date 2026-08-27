<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\OtherPersonResource\Pages;
use App\Filament\Resources\OtherPersonResource\RelationManagers\TransactionsRelationManager;
use App\Models\OtherPerson;
use App\Models\PartyType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OtherPersonResource extends Resource
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

    protected static ?string $model = OtherPerson::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_parties');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.other_people');
    }

    public static function getModelLabel(): string
    {
        return __('general.other_person');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.other_people');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.full_name'))->required()->maxLength(255),
                Select::make('party_type_id')->native(false)
                    ->label(__('general.party_type'))
                    ->options(fn (): array => PartyType::query()->where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('general.name'))->required()->unique(),
                    ])
                    ->nullable(),
                TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(50),
                TextInput::make('address')->label(__('general.address'))->maxLength(255),
                TextInput::make('notes')->label(__('general.notes'))->maxLength(255),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.full_name'))->searchable()->weight('semibold'),
                TextColumn::make('partyType.name')->label(__('general.party_type'))->badge()->color('info'),
                TextColumn::make('phone')->label(__('general.phone'))->placeholder('—'),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatOtherPersonBalance($state))
                    ->badge()
                    ->color(fn (float $state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatOtherPersonBalance($state, true))
                    ),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('party_type_id')->native(false)
                    ->label(__('general.party_type'))
                    ->options(fn (): array => PartyType::query()->pluck('name', 'id')->all()),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtherPeople::route('/'),
            'create' => Pages\CreateOtherPerson::route('/create'),
            'view' => Pages\ViewOtherPerson::route('/{record}'),
            'edit' => Pages\EditOtherPerson::route('/{record}/edit'),
        ];
    }
}
