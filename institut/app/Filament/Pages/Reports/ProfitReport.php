<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MonthPicker;
use App\Models\Account;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ProfitReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.profit_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports.profit';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['month' => now()->format('Y-m')]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.profit_report');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                MonthPicker::make('month')
                    ->label(__('general.month'))
                    ->default(now()->format('Y-m'))
                    ->required(),
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
        return app(ReportService::class)->profit($this->selectedMonth());
    }

    public function selectedMonth(): string
    {
        return substr((string) ($this->data['month'] ?? now()->format('Y-m')), 0, 7);
    }

    public function table(Table $table): Table
    {
        $rows = collect($this->getReport()['rows']);

        return $table
            ->query(Account::query()->whereIn('id', $rows->pluck('account.id')->all()))
            ->columns([
                TextColumn::make('name')->label(__('general.account'))->weight('medium'),
                TextColumn::make('type')
                    ->label(__('general.account_type'))
                    ->badge()
                    ->color(fn (Account $record): string => $record->type === 'income' ? 'success' : 'danger')
                    ->formatStateUsing(fn (Account $record): string => __("general.account_type_{$record->type}")),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('semibold')
                    ->state(function (Account $record) use ($rows): string {
                        $row = $rows->firstWhere('account.id', $record->id);

                        return number_format((float) ($row['amount'] ?? 0)).' '.__('general.currency');
                    })
                    ->color(fn (Account $record): string => $record->type === 'income' ? 'success' : 'danger')
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.net_profit'))
                            ->using(fn (): float => (float) ($this->getReport()['netProfit'] ?? 0))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency').' ('.__('general.net_profit').')')
                    ),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.profit.print', ['month' => $this->selectedMonth()]))
                ->openUrlInNewTab(),
        ];
    }
}
