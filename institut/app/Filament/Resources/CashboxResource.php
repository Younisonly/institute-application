<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashboxResource\Pages;
use App\Helpers\MoneyFormatter;
use App\Models\Cashbox;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashboxResource extends Resource
{
    protected static ?string $model = Cashbox::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('general.nav_places');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.cashboxes');
    }

    public static function getModelLabel(): string
    {
        return __('general.cashbox');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.cashboxes');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.cashbox'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('general.cashbox_code'))
                            ->default(fn (): string => Cashbox::generateNextCode())
                            ->readOnly()
                            ->dehydrated()
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name_ar')
                            ->label(__('general.name_ar'))
                            ->required()
                            ->maxLength(191),
                        TextInput::make('name_en')
                            ->label(__('general.name_en'))
                            ->required()
                            ->maxLength(191),
                        Select::make('keeper_id')
                            ->label(__('general.keeper'))
                            ->options(fn (): array => User::pluck('name', 'id')->all())
                            ->searchable()
                            ->native(false)
                            ->nullable(),
                        TextInput::make('min_balance')
                            ->label(__('general.min_balance'))
                            ->numeric()
                            ->prefix('YER')
                            ->default(0),
                        TextInput::make('max_balance')
                            ->label(__('general.max_balance'))
                            ->numeric()
                            ->prefix('YER')
                            ->nullable(),
                        Toggle::make('is_default')
                            ->label(__('general.is_default'))
                            ->default(false),
                        Toggle::make('is_active')
                            ->label(__('general.active'))
                            ->default(true),
                        Textarea::make('notes')
                            ->label(__('general.notes'))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('general.cashbox_code'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('general.cashbox_name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('keeper.name')
                    ->label(__('general.keeper'))
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->state(fn (Cashbox $record): string => MoneyFormatter::formatAccountBalance($record->balance()))
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('general.is_default'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('general.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashboxes::route('/'),
            'create' => Pages\CreateCashbox::route('/create'),
            'edit' => Pages\EditCashbox::route('/{record}/edit'),
        ];
    }
}
