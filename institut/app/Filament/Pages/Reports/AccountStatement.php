<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\JournalResource;
use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntryLine;
use App\Models\OtherPerson;
use App\Models\OtherPeopleTransaction;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Transfer;
use App\Services\JournalDocumentLinker;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AccountStatement extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.account_statement');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reports.account-statement';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'account_id' => request()->integer('account_id') ?: null,
            'party_type' => request()->string('party_type')->toString() ?: '',
            'party_id' => request()->integer('party_id') ?: null,
            'from' => request('from'),
            'to' => request('to'),
        ]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.account_statement');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('party_type')->native(false)
                    ->label(__('general.statement_of'))
                    ->options([
                        '' => __('general.account'),
                        'student' => __('general.student'),
                        'staff' => __('general.staff_member'),
                        'supplier' => __('general.supplier'),
                        'other' => __('general.other_people'),
                    ])
                    ->default('')
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('party_id', null);
                        $this->resetTable();
                    }),
                Select::make('party_id')->native(false)
                    ->label(__('general.select_party'))
                    ->options(fn (Get $get): array => $this->partyOptions($get('party_type')))
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => ! blank($get('party_type')))
                    ->hidden(fn (Get $get): bool => blank($get('party_type')))
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->resetTable();
                    }),
                Select::make('account_id')->native(false)
                    ->label(__('general.account'))
                    ->options(fn (): array => Account::query()->orderBy('code')->get()
                        ->mapWithKeys(fn (Account $a) => [$a->id => $a->code.' — '.$a->name])->all())
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => blank($get('party_type')))
                    ->hidden(fn (Get $get): bool => ! blank($get('party_type')))
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

    private function partyOptions(?string $type): array
    {
        return match ($type) {
            'student' => Student::query()->orderBy('name')->pluck('name', 'id')->all(),
            'staff' => Staff::query()->orderBy('name')->pluck('name', 'id')->all(),
            'supplier' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'other' => OtherPerson::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
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
        if ($this->partyMode()) {
            return $this->partyReport();
        }

        $account = Account::find($this->data['account_id'] ?? null);
        if ($account === null) {
            return ['account' => null, 'party' => null, 'opening' => 0, 'rows' => collect(), 'balances' => [], 'counterparties' => [], 'totalDebit' => 0, 'totalCredit' => 0, 'closing' => 0];
        }

        return app(ReportService::class)->accountStatement(
            $account,
            $this->fromDate(),
            $this->toDate(),
        );
    }

    public function partyMode(): bool
    {
        return ! blank($this->data['party_type'] ?? '') && ! empty($this->data['party_id']);
    }

    private function partyReport(): array
    {
        return app(ReportService::class)->partyLedger(
            (string) $this->data['party_type'],
            (int) $this->data['party_id'],
            $this->fromDate(),
            $this->toDate(),
        );
    }

    public function reportHeading(): string
    {
        if ($this->partyMode()) {
            $report = $this->partyReport();
            $party = $report['party'];

            return $party?->name ?? __('general.select_party');
        }

        $report = $this->getReport();

        return $report['account'] !== null
            ? $report['account']->code.' — '.$report['account']->name
            : __('general.select_account');
    }

    private function fromDate(): ?\Illuminate\Support\Carbon
    {
        return ! empty($this->data['from']) ? \Illuminate\Support\Carbon::parse($this->data['from']) : null;
    }

    private function toDate(): ?\Illuminate\Support\Carbon
    {
        return ! empty($this->data['to']) ? \Illuminate\Support\Carbon::parse($this->data['to']) : null;
    }

    public function table(Table $table): Table
    {
        $account = Account::find($this->data['account_id'] ?? null);
        $from = $this->fromDate();
        $to = $this->toDate();
        $statements = $this->getReport();

        if ($this->partyMode()) {
            $partyType = (string) $this->data['party_type'];
            $partyId = (int) $this->data['party_id'];

            return match ($partyType) {
                'staff' => $this->staffTable($table, $partyId, $from, $to, $statements),
                'student' => $this->studentTable($table, $partyId, $from, $to, $statements),
                'supplier' => $this->supplierTable($table, $partyId, $from, $to, $statements),
                'other' => $this->otherPersonTable($table, $partyId, $from, $to, $statements),
                default => $table,
            };
        }

        return $this->accountTable($table, $account, $from, $to, $statements);
    }

    private function staffTable(Table $table, int $partyId, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, array $statements): Table
    {
        return $table
            ->query(StaffTransaction::query()
                ->where('staff_id', $partyId)
                ->whereNull('voided_at')
                ->when($from, fn ($q, $v) => $q->whereDate('date', '>=', $v->toDateString()))
                ->when($to, fn ($q, $v) => $q->whereDate('date', '<=', $v->toDateString()))
                ->orderBy('date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('description')
                    ->label(__('general.description'))
                    ->state(fn (StaffTransaction $r): string => $r->description ?: ($r->type === 'salary' ? __('general.salary').($r->salary_month ? " — {$r->salary_month}" : '') : __("general.{$r->type}")))
                    ->limit(45)->wrap(),
                TextColumn::make('type')
                    ->label(__('general.document'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                TextColumn::make('method')
                    ->label(__('general.counterparty'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bank' => __('general.bank'),
                        'wallet' => __('general.wallet'),
                        default => __('general.cash'),
                    }),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (StaffTransaction $r): string => $r->type === 'advance' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'advance')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (StaffTransaction $r): string => $r->type !== 'advance' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type !== 'advance')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (StaffTransaction $r): string => \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance((float) ($statements['balances'][$r->id] ?? 0), true))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($statements['closing'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance($state, true))
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('general.no_records'));
    }

    private function studentTable(Table $table, int $partyId, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, array $statements): Table
    {
        return $table
            ->query(StudentTransaction::query()
                ->where('student_id', $partyId)
                ->whereNull('voided_at')
                ->when($from, fn ($q, $v) => $q->whereDate('date', '>=', $v->toDateString()))
                ->when($to, fn ($q, $v) => $q->whereDate('date', '<=', $v->toDateString()))
                ->orderBy('date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('receipt_no')
                    ->label(__('general.receipt_no'))
                    ->state(fn (StudentTransaction $r): string => $r->receipt_no ?? $r->reference ?? '—'),
                TextColumn::make('description')
                    ->label(__('general.description'))
                    ->state(fn (StudentTransaction $r): string => $r->description ?: __("general.{$r->type}"))
                    ->limit(45)->wrap(),
                TextColumn::make('type')
                    ->label(__('general.document'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                TextColumn::make('method')
                    ->label(__('general.counterparty'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bank' => __('general.bank'),
                        'wallet' => __('general.wallet'),
                        default => __('general.cash'),
                    }),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (StudentTransaction $r): string => in_array($r->type, ['charge', 'refund'], true) ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => in_array($r->type, ['charge', 'refund'], true))->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (StudentTransaction $r): string => $r->type === 'payment' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'payment')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (StudentTransaction $r): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($statements['balances'][$r->id] ?? 0), true))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($statements['closing'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance($state, true))
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('general.no_records'));
    }

    private function supplierTable(Table $table, int $partyId, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, array $statements): Table
    {
        return $table
            ->query(SupplierTransaction::query()
                ->where('supplier_id', $partyId)
                ->whereNull('voided_at')
                ->when($from, fn ($q, $v) => $q->whereDate('date', '>=', $v->toDateString()))
                ->when($to, fn ($q, $v) => $q->whereDate('date', '<=', $v->toDateString()))
                ->orderBy('date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('description')
                    ->label(__('general.description'))
                    ->state(fn (SupplierTransaction $r): string => $r->description ?: __("general.{$r->type}"))
                    ->limit(45)->wrap(),
                TextColumn::make('type')
                    ->label(__('general.document'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                TextColumn::make('method')
                    ->label(__('general.counterparty'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bank' => __('general.bank'),
                        'wallet' => __('general.wallet'),
                        default => __('general.cash'),
                    }),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (SupplierTransaction $r): string => $r->type === 'payment' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'payment')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (SupplierTransaction $r): string => $r->type === 'purchase' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'purchase')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (SupplierTransaction $r): string => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) ($statements['balances'][$r->id] ?? 0), true))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($statements['closing'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatSupplierBalance($state, true))
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('general.no_records'));
    }

    private function otherPersonTable(Table $table, int $partyId, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, array $statements): Table
    {
        return $table
            ->query(OtherPeopleTransaction::query()
                ->where('other_person_id', $partyId)
                ->whereNull('voided_at')
                ->when($from, fn ($q, $v) => $q->whereDate('date', '>=', $v->toDateString()))
                ->when($to, fn ($q, $v) => $q->whereDate('date', '<=', $v->toDateString()))
                ->orderBy('date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextColumn::make('description')
                    ->label(__('general.description'))
                    ->state(fn (OtherPeopleTransaction $r): string => $r->description ?: __("general.{$r->type}"))
                    ->limit(45)->wrap(),
                TextColumn::make('type')
                    ->label(__('general.document'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                TextColumn::make('method')
                    ->label(__('general.counterparty'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bank' => __('general.bank'),
                        'wallet' => __('general.wallet'),
                        default => __('general.cash'),
                    }),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (OtherPeopleTransaction $r): string => $r->type === 'out' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'out')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (OtherPeopleTransaction $r): string => $r->type === 'in' ? number_format((float) $r->amount) : '0')
                    ->summarize(Summarizer::make()->label(__('general.total'))->using(fn ($query): float => (float) $query->get()->filter(fn ($r) => $r->type === 'in')->sum('amount'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (OtherPeopleTransaction $r): string => \App\Helpers\MoneyFormatter::formatOtherPersonBalance((float) ($statements['balances'][$r->id] ?? 0), true))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($statements['closing'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatOtherPersonBalance($state, true))
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('general.no_records'));
    }

    private function accountTable(Table $table, ?Account $account, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to, array $statements): Table
    {
        return $table
            ->query(JournalEntryLine::query()
                ->select('journal_entry_lines.*')
                ->where('account_id', $account?->id ?? 0)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->whereNull('journal_entries.voided_at')
                ->when($from, fn ($q, $v) => $q->whereDate('journal_entries.date', '>=', $v->toDateString()))
                ->when($to, fn ($q, $v) => $q->whereDate('journal_entries.date', '<=', $v->toDateString()))
                ->with(['entry.lines.account', 'party'])
                ->orderBy('journal_entries.date')
                ->orderBy('journal_entries.id')
                ->orderBy('journal_entry_lines.id'))
            ->columns([
                TextColumn::make('entry.date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('entry.entry_no')
                    ->label(__('general.entry_no'))
                    ->prefix('#')
                    ->color('primary')
                    ->url(fn (JournalEntryLine $record): ?string => JournalResource::getUrl('view', ['record' => $record->entry->id])),
                TextColumn::make('entry.description')->label(__('general.description'))->limit(45)->wrap()->placeholder('—'),
                TextColumn::make('entry.document_type')
                    ->label(__('general.document'))
                    ->badge()
                    ->formatStateUsing(fn (JournalEntryLine $record): string => $this->documentLabel($record))
                    ->url(fn (JournalEntryLine $record): ?string => app(JournalDocumentLinker::class)
                        ->urlFor($record->entry->document_type, $record->entry->document_id)),
                TextColumn::make('counterparty')
                    ->label(__('general.counterparty'))
                    ->state(fn (JournalEntryLine $record): string => $statements['counterparties'][$record->id] ?? '—'),
                TextColumn::make('party.name')
                    ->label(__('general.party'))
                    ->placeholder('—')
                    ->url(fn (JournalEntryLine $record): ?string => $this->partyUrl($record)),
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
                    ->state(fn (JournalEntryLine $record): string => \App\Helpers\MoneyFormatter::formatAccountBalance((float) ($statements['balances'][$record->id] ?? 0), $account?->type ?? 'asset'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($statements['closing'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, $account?->type ?? 'asset'))
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading($account ? __('general.no_records') : __('general.select_account'));
    }

    public function reportSummary(): ?string
    {
        $report = $this->getReport();

        if (($report['account'] ?? null) === null && ($report['party'] ?? null) === null) {
            return null;
        }

        $partyType = $report['party_type'] ?? null;
        $closingFormatted = match ($partyType) {
            'student' => \App\Helpers\MoneyFormatter::formatStudentBalance((float) $report['closing'], true),
            'staff' => \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance((float) $report['closing'], true),
            'supplier' => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) $report['closing'], true),
            'other' => \App\Helpers\MoneyFormatter::formatOtherPersonBalance((float) $report['closing'], true),
            default => \App\Helpers\MoneyFormatter::formatAccountBalance((float) $report['closing'], $report['account']->type ?? 'asset'),
        };

        return __('general.statement_summary', [
            'opening' => number_format((float) $report['opening']).' '.__('general.currency'),
            'debit' => number_format((float) $report['totalDebit']).' '.__('general.currency'),
            'credit' => number_format((float) $report['totalCredit']).' '.__('general.currency'),
            'closing' => $closingFormatted,
        ]);
    }

    private function documentLabel(JournalEntryLine $record): string
    {
        $type = $record->entry->document_type;

        return match ($type) {
            StudentTransaction::class => __('general.student_transaction'),
            StaffTransaction::class => __('general.staff_transaction'),
            Expense::class => __('general.expense'),
            StockMovement::class => __('general.stock_movement'),
            SupplierTransaction::class => __('general.supplier_payment'),
            OtherPeopleTransaction::class => __('general.other_people'),
            Transfer::class => __('general.transfer'),
            default => $type !== null ? class_basename($type) : __('general.other'),
        };
    }

    private function partyUrl(JournalEntryLine $record): ?string
    {
        if ($record->party_id === null) {
            return null;
        }

        return match ($record->party_type) {
            \App\Models\Student::class => \App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record->party_id]),
            \App\Models\Staff::class => \App\Filament\Resources\StaffResource::getUrl('view', ['record' => $record->party_id]),
            \App\Models\Supplier::class => \App\Filament\Resources\SupplierResource::getUrl('view', ['record' => $record->party_id]),
            \App\Models\OtherPerson::class => \App\Filament\Resources\OtherPersonResource::getUrl('view', ['record' => $record->party_id]),
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.account-statement.print', [
                    'account_id' => $this->data['account_id'] ?? '',
                    'party_type' => $this->data['party_type'] ?? '',
                    'party_id' => $this->data['party_id'] ?? '',
                    'from' => $this->data['from'] ?? '',
                    'to' => $this->data['to'] ?? '',
                ]))
                ->openUrlInNewTab(),
        ];
    }
}