<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\PaymentDetails;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
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
        return [];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.expenses');
    }

    public static function getModelLabel(): string
    {
        return __('general.expense');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.expenses');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('expense_category_id')->native(false)
                    ->label(__('general.expense_category'))
                    ->options(fn (): array => ExpenseCategory::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label(__('general.name'))->required(),
                    ]),
                MoneyInput::make('amount')
                    ->label(__('general.amount'))
                    ->required()
                    ->minValue(0.01)
                    ->suffix(__('general.currency')),
                DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y')->required(),
                ...PaymentDetails::fields('payment_method', 'bank_id', 'wallet_id', 'transaction_ref'),
                \Filament\Forms\Components\FileUpload::make('attachment_path')
                    ->label(__('general.attachment'))
                    ->directory('expenses')
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxSize(5120),
                TextInput::make('description')->label(__('general.description'))->maxLength(255),
                \Filament\Forms\Components\Select::make('approved_by')->native(false)
                    ->label(__('general.approved_by'))
                    ->options(fn (): array => \App\Models\User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->nullable()
                    ->helperText(__('general.expense_approved_by_hint')),
                \Filament\Forms\Components\DateTimePicker::make('approved_at')
                    ->label(__('general.approved_at'))
                    ->displayFormat('d/m/Y H:i')
                    ->nullable()
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('approved_by') !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y')->sortable(),
                TextColumn::make('category.name')->label(__('general.expense_category'))->badge()->color('danger'),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state).' '.__('general.currency'))
                    ->weight('semibold')
                    ->color('danger'),
                TextColumn::make('payment_method')
                    ->label(__('general.payment_method'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => __("general.method_{$state}")),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('approvedBy.name')
                    ->label(__('general.approved_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('expense_category_id')->native(false)
                    ->label(__('general.expense_category'))
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('date')
                    ->label(__('general.date'))
                    ->form([
                        DatePicker::make('from')->label(__('general.start_month'))->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('general.end_month'))->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q) => $q->where('date', '>=', $data['from']))
                            ->when($data['until'], fn (Builder $q) => $q->where('date', '<=', $data['until']));
                    }),
                Tables\Filters\TernaryFilter::make('voided')
                    ->label(__('general.show_voided'))
                    ->placeholder(__('general.hide_voided'))
                    ->trueLabel(__('general.show_voided'))
                    ->falseLabel(__('general.hide_voided'))
                    ->queries(
                        true: fn (Builder $q): Builder => $q->whereNotNull('voided_at'),
                        false: fn (Builder $q): Builder => $q->whereNull('voided_at'),
                        blank: fn (Builder $q): Builder => $q->whereNull('voided_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('void')
                    ->label(__('general.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.void'))
                    ->modalDescription(__('general.must_provide_void_reason'))
                    ->form([
                        TextInput::make('void_reason')
                            ->label(__('general.void_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        $record->void($data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?Expense $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
        ];
    }
}
