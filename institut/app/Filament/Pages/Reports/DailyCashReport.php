<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\OtherPeopleTransaction;
use App\Models\StaffTransaction;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class DailyCashReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.daily_cash_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports.daily-cash';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['date' => now()->format('Y-m-d')]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.daily_cash_report');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date')
                    ->label(__('general.date'))
                    ->required()
                    ->default(now())
                    ->displayFormat('d/m/Y'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('filter')
                ->label(__('general.apply'))
                ->submit('applyFilters'),
        ];
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    public function getReport(): array
    {
        return app(ReportService::class)->dailyCash($this->data['date'] ?? now()->format('Y-m-d'));
    }

    public function table(Table $table): Table
    {
        $date = $this->data['date'] ?? now()->format('Y-m-d');

        return $table
            ->query(JournalEntry::query()
                ->whereNull('voided_at')
                ->whereDate('date', $date)
                ->whereIn('document_type', [
                    StudentTransaction::class,
                    Expense::class,
                    StaffTransaction::class,
                    SupplierTransaction::class,
                    OtherPeopleTransaction::class,
                ])
                ->with(['document', 'lines.account'])
                ->latest('id'))
            ->columns([
                TextColumn::make('entry_no')->label(__('general.entry_no'))->prefix('#')->color('gray'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('description')->label(__('general.description'))->limit(60)->wrap(),
                TextColumn::make('document_type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (JournalEntry $record): string => $this->typeLabel($record))
                    ->color(fn (JournalEntry $record): string => $this->typeColor($record)),
                TextColumn::make('flow')
                    ->label(__('general.flow'))
                    ->state(function (\App\Models\JournalEntry $record) {
                        $debits = $record->lines->where('debit', '>', 0)->map->account->pluck('name')->unique()->implode(', ');
                        $credits = $record->lines->where('credit', '>', 0)->map->account->pluck('name')->unique()->implode(', ');
                        if ($debits && $credits) {
                            return "$debits ➔ $credits";
                        }
                        return '—';
                    }),
                TextColumn::make('debit_total')
                    ->label(__('general.amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('semibold')
                    ->state(fn (JournalEntry $record): string => number_format((float) $record->debit_total).' '.__('general.currency'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('debit_total'))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ),
            ])
            ->paginated(false);
    }

    private function typeLabel(JournalEntry $entry): string
    {
        $document = $entry->document;

        if ($document instanceof StudentTransaction) {
            return $document->type === 'refund' ? __('general.refund') : __('general.payment');
        }

        if ($document instanceof OtherPeopleTransaction) {
            return __("general.{$document->type}");
        }

        if ($document instanceof StaffTransaction) {
            return __("general.{$document->type}");
        }

        return match ($entry->document_type) {
            Expense::class => __('general.expense'),
            SupplierTransaction::class => __('general.supplier_payment'),
            default => __('general.other'),
        };
    }

    private function typeColor(JournalEntry $entry): string
    {
        $document = $entry->document;

        if ($document instanceof StudentTransaction) {
            return $document->type === 'refund' ? 'danger' : 'success';
        }

        if ($document instanceof OtherPeopleTransaction) {
            return $document->type === 'in' ? 'success' : 'danger';
        }

        return 'danger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.daily-cash.print', ['date' => $this->data['date'] ?? now()->format('Y-m-d')]))
                ->openUrlInNewTab(),
        ];
    }
}
