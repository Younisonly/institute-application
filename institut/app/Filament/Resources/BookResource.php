<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers\MovementsRelationManager;
use App\Models\Book;
use App\Models\Course;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookResource extends Resource
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

    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.books');
    }

    public static function getModelLabel(): string
    {
        return __('general.book');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.books');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')->label(__('general.book_title'))->required()->maxLength(255),
                TextInput::make('author')->label(__('general.author'))->maxLength(255),
                TextInput::make('edition')->label(__('general.edition'))->maxLength(50),
                TextInput::make('isbn')->label(__('general.isbn'))->maxLength(20),
                Select::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->relationship('course', 'name', modifyQueryUsing: fn ($query) => $query->withoutTrashed())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText(__('general.book_course_hint')),
                Select::make('supplier_id')->native(false)
                    ->label(__('general.supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('general.name'))->required(),
                        TextInput::make('phone')->label(__('general.phone'))->tel(),
                        TextInput::make('address')->label(__('general.address')),
                    ])
                    ->nullable(),
                MoneyInput::make('buy_price')->label(__('general.buy_price'))->minValue(0),
                MoneyInput::make('sale_price')->label(__('general.sale_price'))->minValue(0),
                TextInput::make('stock_qty')
                    ->label(__('general.current_stock'))
                    ->numeric()->maxValue(999999999999)
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText(__('general.stock_movements')),
                TextInput::make('low_stock_threshold')->label(__('general.low_stock_threshold'))->numeric()->maxValue(999999999999)->minValue(0)->default(5),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
                Textarea::make('details')->label(__('general.details'))->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('general.book_title'))->searchable()->weight('semibold'),
                TextColumn::make('author')->label(__('general.author'))->placeholder('—')->toggleable(),
                TextColumn::make('course.name')->label(__('general.course'))->badge()->color('info')->placeholder('—'),
                TextColumn::make('supplier.name')->label(__('general.supplier'))->placeholder('—')->toggleable(),
                TextColumn::make('stock_qty')
                    ->label(__('general.current_stock'))
                    ->badge()
                    ->color(fn (Book $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state, Book $record): string => $record->isLowStock() ? $state.' • '.__('general.low_stock') : (string) $state),
                TextColumn::make('sale_price')
                    ->label(__('general.sale_price'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? number_format((float) $state).' '.__('general.currency') : '—'),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->options(fn (): array => Course::query()->pluck('name', 'id')->all()),
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
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'view' => Pages\ViewBook::route('/{record}'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
