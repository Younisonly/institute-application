<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\TransferResource\Pages;
use App\Models\Account;
use App\Models\Transfer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransferResource extends Resource
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

    protected static ?string $model = Transfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.transfers');
    }

    public static function getModelLabel(): string
    {
        return __('general.transfer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.transfers');
    }

    private static function placeAccounts(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('code', ['1100'])->orWhereNotNull('place_type'))
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => $account->name.' ('.$account->code.')'])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('from_account_id')->native(false)
                    ->label(__('general.from_account'))
                    ->options(fn (): array => self::placeAccounts())
                    ->searchable()
                    ->required(),
                Select::make('to_account_id')->native(false)
                    ->label(__('general.to_account'))
                    ->options(fn (): array => self::placeAccounts())
                    ->searchable()
                    ->required()
                    ->rule(fn (callable $get): \Illuminate\Validation\Rules\NotIn => \Illuminate\Validation\Rule::notIn([$get('from_account_id')])),
                MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y')->required(),
                TextInput::make('reference')->label(__('general.reference'))->maxLength(100),
                TextInput::make('description')->label(__('general.description'))->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('fromAccount.name')->label(__('general.from_account'))->color('danger'),
                TextColumn::make('toAccount.name')->label(__('general.to_account'))->color('success'),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('voided_at')->label(__('general.status'))->badge()->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')->color('gray'),
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
                        TextInput::make('void_reason')->label(__('general.void_reason'))->required()->maxLength(255),
                    ])
                    ->action(function (Transfer $record, array $data): void {
                        $record->update(['voided_at' => now(), 'void_reason' => $data['void_reason']]);
                    })
                    ->visible(fn (?Transfer $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'view' => Pages\ViewTransfer::route('/{record}'),
        ];
    }
}
