<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use App\Services\ReportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountResource extends Resource
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
        return [];
    }

    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.chart_of_accounts');
    }

    public static function getModelLabel(): string
    {
        return __('general.account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.chart_of_accounts');
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label(__('general.account_code'))
                    ->required()
                    ->numeric()
                    ->minValue(1000)
                    ->maxValue(9999)
                    ->disabled(fn (string $operation, ?Account $record): bool => $record !== null && ($record->is_system || $record->lines()->exists())),
                TextInput::make('name_ar')
                    ->label(__('general.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label(__('general.name_en'))
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('general.account_type'))
                    ->required()
                    ->options([
                        Account::TYPE_ASSET => __('general.type_asset'),
                        Account::TYPE_LIABILITY => __('general.type_liability'),
                        Account::TYPE_EQUITY => __('general.type_equity'),
                        Account::TYPE_INCOME => __('general.type_income'),
                        Account::TYPE_EXPENSE => __('general.type_expense'),
                    ])
                    ->disabled(fn (string $operation, ?Account $record): bool => $record !== null && ($record->is_system || $record->lines()->exists())),
                Select::make('parent_id')
                    ->label(__('general.parent_account'))
                    ->native(false)
                    ->options(fn (?Account $record): array => Account::query()
                        ->where('id', '!=', $record?->id ?? 0)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $a) => [$a->id => $a->code.' — '.$a->name])
                        ->all())
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label(__('general.description'))
                    ->rows(2)
                    ->maxLength(500),
                Toggle::make('is_active')
                    ->label(__('general.active'))
                    ->default(true)
                    ->disabled(fn (?Account $record): bool => $record !== null && $record->is_system),
            ]);
    }

    public static function table(Table $table): Table
    {
        $totals = app(ReportService::class)->accountTotals();

        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label(__('general.account_code'))->weight('semibold')->sortable(),
                TextColumn::make('name')->label(__('general.account'))->searchable(),
                TextColumn::make('type')
                    ->label(__('general.account_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.account_type_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        Account::TYPE_ASSET => 'info',
                        Account::TYPE_LIABILITY => 'warning',
                        Account::TYPE_EQUITY => 'gray',
                        Account::TYPE_INCOME => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('parent.name')->label(__('general.parent_account'))->placeholder('—'),
                TextColumn::make('place_type')
                    ->label(__('general.place'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(function (?Account $record): string {
                        $place = $record?->place()->first();

                        return $place?->name ?? '—';
                    })
                    ->placeholder('—'),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (Account $record): float => self::accountBalance($record, $totals))
                    ->formatStateUsing(fn (float $state, Account $record): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, $record->type))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(function ($query) use ($totals): float {
                                return (float) $query->get()->sum(function ($acc) use ($totals): float {
                                    $id = is_object($acc) ? ($acc->id ?? 0) : 0;
                                    $type = is_object($acc) ? ($acc->type ?? 'asset') : 'asset';
                                    $deb = (float) ($totals[$id]['debit'] ?? 0);
                                    $crd = (float) ($totals[$id]['credit'] ?? 0);
                                    return in_array($type, ['asset', 'expense'], true) ? ($deb - $crd) : ($crd - $deb);
                                });
                            })
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, 'asset'))
                    ),
                IconColumn::make('is_system')
                    ->label(__('general.system'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon(''),
                IconColumn::make('is_active')
                    ->label(__('general.active'))
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'view' => Pages\ViewAccount::route('/{record}'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    private static function accountBalance(Account $account, \Illuminate\Support\Collection $totals): float
    {
        $subsidiary = app(ReportService::class)->controlAccountBalance($account);
        if ($subsidiary !== null) {
            return $subsidiary;
        }

        $total = $totals[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];

        return in_array($account->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true)
            ? (float) $total['debit'] - (float) $total['credit']
            : (float) $total['credit'] - (float) $total['debit'];
    }
}