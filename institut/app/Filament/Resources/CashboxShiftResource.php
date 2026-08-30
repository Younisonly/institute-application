<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashboxShiftResource\Pages;
use App\Models\Cashbox;
use App\Models\CashboxShift;
use App\Models\User;
use App\Services\CashboxShiftService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashboxShiftResource extends Resource
{
    protected static ?string $model = CashboxShift::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('general.nav_places');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.cashbox_shifts');
    }

    public static function getModelLabel(): string
    {
        return __('general.cashbox_shift');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.cashbox_shifts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.cashbox_shift'))
                    ->schema([
                        TextInput::make('shift_no')
                            ->label(__('general.shift_no'))
                            ->disabled(),
                        Select::make('cashbox_id')
                            ->label(__('general.cashbox'))
                            ->options(fn (): array => Cashbox::query()->get()->mapWithKeys(fn (Cashbox $c): array => [$c->id => $c->name])->all())
                            ->disabled(),
                        Select::make('user_id')
                            ->label(__('general.keeper'))
                            ->options(fn (): array => User::pluck('name', 'id')->all())
                            ->disabled(),
                        DateTimePicker::make('opened_at')
                            ->label(__('general.opened_at'))
                            ->disabled(),
                        DateTimePicker::make('closed_at')
                            ->label(__('general.closed_at'))
                            ->disabled(),
                        TextInput::make('opening_balance')
                            ->label(__('general.opening_balance'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('system_cash_in')
                            ->label(__('general.system_cash_in'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('system_cash_out')
                            ->label(__('general.system_cash_out'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('expected_closing_balance')
                            ->label(__('general.expected_closing_balance'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('physical_cash_count')
                            ->label(__('general.physical_cash_count'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('variance_amount')
                            ->label(__('general.variance_amount'))
                            ->numeric()
                            ->disabled(),
                        Textarea::make('variance_notes')
                            ->label(__('general.variance_notes'))
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shift_no')
                    ->label(__('general.shift_no'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cashbox.name')
                    ->label(__('general.cashbox'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('general.keeper'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('opened_at')
                    ->label(__('general.opened_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('general.closed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('general.shift_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.shift_status_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'reconciled' => 'success',
                        'closed' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('expected_closing_balance')
                    ->label(__('general.expected_closing_balance'))
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('physical_cash_count')
                    ->label(__('general.physical_cash_count'))
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('variance_amount')
                    ->label(__('general.variance_amount'))
                    ->numeric(2)
                    ->badge()
                    ->color(fn (CashboxShift $record): string => match ($record->variance_type) {
                        'surplus' => 'success',
                        'shortage' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('reconcile')
                    ->label(__('general.close_shift'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (?CashboxShift $record): bool => $record !== null && $record->isOpen())
                    ->form([
                        TextInput::make('physical_cash_count')
                            ->label(__('general.physical_cash_count'))
                            ->numeric()
                            ->required()
                            ->prefix('YER'),
                        Textarea::make('variance_notes')
                            ->label(__('general.variance_notes')),
                        Toggle::make('transfer_to_main_safe')
                            ->label(__('general.transfer_to_main_safe'))
                            ->default(true),
                    ])
                    ->action(function (CashboxShift $record, array $data): void {
                        app(CashboxShiftService::class)->closeAndReconcile(
                            $record,
                            (float) $data['physical_cash_count'],
                            $data['variance_notes'] ?? null,
                            (bool) ($data['transfer_to_main_safe'] ?? false)
                        );
                        Notification::make()->title(__('general.shift_closed_success'))->success()->send();
                    }),
                Action::make('print')
                    ->label(__('general.print'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (CashboxShift $record): string => route('shifts.print', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashboxShifts::route('/'),
            'view' => Pages\ViewCashboxShift::route('/{record}'),
        ];
    }
}
