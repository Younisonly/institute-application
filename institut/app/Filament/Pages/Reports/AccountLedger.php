<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Filament\Concerns\HasRbac;
use App\Models\JournalEntryLine;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class AccountLedger extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.account_ledger');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports.account-ledger';

    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.account_ledger');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('account_id')->native(false)
                    ->label(__('general.account'))
                    ->options(fn (): array => Account::query()->active()->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->code . ' — ' . $a->name])->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->resetTable();
                    }),
                DatePicker::make('from')->label(__('general.from'))->displayFormat('d/m/Y')->live()->afterStateUpdated(function (): void {
                    $this->resetTable();
                }),
                DatePicker::make('to')->label(__('general.to'))->displayFormat('d/m/Y')->live()->afterStateUpdated(function (): void {
                    $this->resetTable();
                }),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('filter')->label(__('general.apply'))->submit('applyFilters'),
        ];
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    public function getReport(): array
    {
        $account = Account::find($this->data['account_id'] ?? null);
        if ($account === null) {
            return ['account' => null, 'rows' => collect(), 'total' => 0];
        }

        $from = ! empty($this->data['from']) ? \Illuminate\Support\Carbon::parse($this->data['from']) : null;
        $to = ! empty($this->data['to']) ? \Illuminate\Support\Carbon::parse($this->data['to']) : null;

        return app(ReportService::class)->accountLedger($account, $from, $to);
    }

    private function runningBalances(): Collection
    {
        $rows = $this->getReport()['rows'] ?? collect();

        return $rows->mapWithKeys(fn (array $row): array => [$row['line_id'] => $row['balance']]);
    }

    public function table(Table $table): Table
    {
        $account = Account::find($this->data['account_id'] ?? null);
        $from = ! empty($this->data['from']) ? \Illuminate\Support\Carbon::parse($this->data['from']) : null;
        $to = ! empty($this->data['to']) ? \Illuminate\Support\Carbon::parse($this->data['to']) : null;

        $balances = $this->runningBalances();

        return $table
            ->query(JournalEntryLine::query()
                ->select('journal_entry_lines.*')
                ->where('account_id', $account?->id ?? 0)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->whereNull('journal_entries.voided_at')
                ->when($from, fn ($q, $value) => $q->whereDate('journal_entries.date', '>=', $value->toDateString()))
                ->when($to, fn ($q, $value) => $q->whereDate('journal_entries.date', '<=', $value->toDateString()))
                ->with(['entry', 'party'])
                ->orderBy('journal_entries.date')
                ->orderBy('journal_entries.id'))
            ->columns([
                TextColumn::make('entry.date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('entry.entry_no')->label(__('general.entry_no'))->prefix('#')->color('gray'),
                TextColumn::make('entry.description')->label(__('general.description'))->limit(50)->wrap()->placeholder('—'),
                TextColumn::make('party.name')->label(__('general.party'))->placeholder('—'),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state))
                    ->summarize(Sum::make()->label(__('general.total'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state))
                    ->summarize(Sum::make()->label(__('general.total'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (JournalEntryLine $record): string => \App\Helpers\MoneyFormatter::formatAccountBalance((float) ($balances->get($record->id) ?? 0), $account?->type ?? 'asset'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($balances->last() ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, $account?->type ?? 'asset'))
                    ),
            ])
            ->paginated(false)
            ->emptyStateHeading(fn (): string => $account ? __('general.no_records') : __('general.select_account'))
            ->actions([
                \Filament\Tables\Actions\Action::make('view_entry')
                    ->label(__('general.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (JournalEntryLine $record): string => \App\Filament\Resources\JournalResource::getUrl('view', ['record' => $record->entry->id]))
                    ->openUrlInNewTab(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url('javascript:window.print()'),
        ];
    }
}
