<?php

namespace App\Filament\Pages;

use App\Models\Cashbox;
use App\Models\CashboxShift;
use App\Models\StudentTransaction;
use App\Services\CashboxShiftService;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MyCashboxShift extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.my-cashbox-shift';

    public static function getNavigationGroup(): string
    {
        return __('general.nav_places');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.my_shift');
    }

    public function getTitle(): string
    {
        return __('general.my_shift');
    }

    public function getActiveShift(): ?CashboxShift
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $cashboxId = $user->default_cashbox_id ?? Cashbox::query()->where('keeper_id', $user->id)->value('id') ?? Cashbox::query()->where('is_default', true)->value('id');

        if (! $cashboxId) {
            return null;
        }

        return CashboxShift::query()
            ->where('cashbox_id', $cashboxId)
            ->where('status', CashboxShift::STATUS_OPEN)
            ->first();
    }

    public function getShiftTotals(): array
    {
        $shift = $this->getActiveShift();
        if (! $shift) {
            return ['cash_in' => 0.0, 'cash_out' => 0.0, 'expected' => 0.0];
        }

        return app(CashboxShiftService::class)->calculateShiftTotals($shift);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_shift')
                ->label(__('general.open_shift'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->getActiveShift() === null)
                ->form([
                    Select::make('cashbox_id')
                        ->label(__('general.cashbox'))
                        ->options(fn (): array => Cashbox::query()->where('is_active', true)->get()->mapWithKeys(fn (Cashbox $c): array => [$c->id => $c->name])->all())
                        ->default(fn () => Auth::user()?->default_cashbox_id ?? Cashbox::query()->where('is_default', true)->value('id'))
                        ->required()
                        ->native(false),
                    TextInput::make('opening_balance')
                        ->label(__('general.opening_balance'))
                        ->numeric()
                        ->default(0)
                        ->prefix('YER')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(CashboxShiftService::class)->openShift(
                        (int) $data['cashbox_id'],
                        Auth::id(),
                        (float) $data['opening_balance']
                    );
                    Notification::make()->title(__('general.open_shift'))->success()->send();
                }),
            Action::make('reconcile')
                ->label(__('general.close_shift'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getActiveShift() !== null)
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
                ->action(function (array $data): void {
                    $shift = $this->getActiveShift();
                    if (! $shift) {
                        return;
                    }
                    app(CashboxShiftService::class)->closeAndReconcile(
                        $shift,
                        (float) $data['physical_cash_count'],
                        $data['variance_notes'] ?? null,
                        (bool) ($data['transfer_to_main_safe'] ?? false)
                    );
                    Notification::make()->title(__('general.shift_closed_success'))->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $shift = $this->getActiveShift();

        return $table
            ->query(function () use ($shift) {
                if (! $shift) {
                    return StudentTransaction::query()->whereRaw('1 = 0');
                }

                return StudentTransaction::query()
                    ->where('cashbox_id', $shift->cashbox_id)
                    ->where('method', 'cash')
                    ->whereNull('voided_at')
                    ->where('created_at', '>=', $shift->opened_at);
            })
            ->columns([
                TextColumn::make('date')
                    ->label(__('general.date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('receipt_no')
                    ->label(__('general.receipt_no'))
                    ->prefix('#')
                    ->searchable(),
                TextColumn::make('student.name')
                    ->label(__('general.student'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.type_'.$state)),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->numeric(2)
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }
}
