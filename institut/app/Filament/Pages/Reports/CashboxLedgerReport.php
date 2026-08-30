<?php

namespace App\Filament\Pages\Reports;

use App\Models\Cashbox;
use App\Models\StudentTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CashboxLedgerReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.pages.reports.cashbox-ledger-report';

    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.cashbox_ledger_report');
    }

    public function getTitle(): string
    {
        return __('general.cashbox_ledger_report');
    }

    public function mount(): void
    {
        $defaultCashboxId = Cashbox::query()->where('is_default', true)->value('id') ?? Cashbox::query()->value('id');
        $this->form->fill([
            'cashbox_id' => $defaultCashboxId,
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cashbox_id')
                    ->label(__('general.cashbox'))
                    ->options(fn (): array => Cashbox::query()->get()->mapWithKeys(fn (Cashbox $c): array => [$c->id => $c->name])->all())
                    ->required()
                    ->native(false)
                    ->live(),
                DatePicker::make('from')
                    ->label(__('general.from'))
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live(),
                DatePicker::make('to')
                    ->label(__('general.to'))
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        $cashboxId = $this->data['cashbox_id'] ?? null;
        $from = $this->data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->data['to'] ?? now()->toDateString();

        return $table
            ->query(function () use ($cashboxId, $from, $to) {
                if (! $cashboxId) {
                    return StudentTransaction::query()->whereRaw('1 = 0');
                }

                return StudentTransaction::query()
                    ->where('cashbox_id', $cashboxId)
                    ->where('method', 'cash')
                    ->whereNull('voided_at')
                    ->whereBetween('date', [$from, $to]);
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
