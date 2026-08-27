<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Filament\Widgets\ClosedYearsWidget;
use App\Models\Account;
use App\Models\FiscalYearClosing as FiscalYearClosingModel;
use App\Models\InstituteSetting;
use App\Models\JournalEntry;
use App\Services\FiscalYearClosingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FiscalYearClosing extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.fiscal-year-closing';

    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.fiscal_year_closing');
    }

    public static function getModelLabel(): string
    {
        return __('general.fiscal_year_closing');
    }

    public function mount(): void
    {
        $this->form->fill(['year' => $this->defaultYear()]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('year')->native(false)
                    ->label(__('general.fiscal_year'))
                    ->options(fn (): array => JournalEntry::query()
                        ->whereNull('voided_at')
                        ->selectRaw('YEAR(date) as y')
                        ->distinct()
                        ->orderByDesc('y')
                        ->pluck('y', 'y')
                        ->mapWithKeys(fn ($y): array => [(int) $y => (string) $y])
                        ->all())
                    ->searchable()
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

    public function defaultYear(): int
    {
        $latest = JournalEntry::query()->whereNull('voided_at')->max('date');
        if ($latest !== null) {
            return (int) substr($latest, 0, 4);
        }

        return (int) substr(InstituteSetting::query()->firstOrFail()->current_month, 0, 4);
    }

    public function selectedYear(): int
    {
        return (int) ($this->data['year'] ?? $this->defaultYear());
    }

    public function getPreview(): array
    {
        return app(FiscalYearClosingService::class)->preview($this->selectedYear());
    }

    public function isYearClosed(): bool
    {
        return FiscalYearClosingModel::query()->where('year', $this->selectedYear())->exists();
    }

    public function canCloseSelectedYear(): bool
    {
        if ($this->isYearClosed()) {
            return false;
        }

        $currentYear = (int) substr(InstituteSetting::query()->firstOrFail()->current_month, 0, 4);

        return $this->selectedYear() < $currentYear;
    }

    private function previewByAccount(): Collection
    {
        $preview = $this->getPreview();

        return collect($preview['income'])
            ->concat($preview['expenses'])
            ->mapWithKeys(fn (array $row): array => [$row['account']->id => $row['amount']]);
    }

    public function table(Table $table): Table
    {
        $amounts = $this->previewByAccount();

        return $table
            ->query(Account::query()
                ->whereIn('id', $amounts->keys())
                ->orderBy('code'))
            ->columns([
                TextColumn::make('name')->label(__('general.account'))->weight('semibold'),
                TextColumn::make('code')->label(__('general.code'))->placeholder('—'),
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (Account $record): string => __("general.account_type_{$record->type}"))
                    ->color(fn (Account $record): string => $record->type === 'income' ? 'success' : 'danger'),
                TextColumn::make('closing_amount')
                    ->label(__('general.closing_amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->formatStateUsing(fn (Account $record): string => number_format((float) ($amounts->get($record->id) ?? 0))),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('general.year_no_activity_short'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeYear')
                ->label(__('general.close_year'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('general.close_year'))
                ->modalDescription(fn (): string => $this->closeConfirmText())
                ->visible(function (): bool {
                    $preview = $this->getPreview();

                    return $this->canCloseSelectedYear()
                        && ($preview['totalIncome'] != 0 || $preview['totalExpenses'] != 0);
                })
                ->action(fn (): mixed => $this->closeYear()),
            Action::make('reopenYear')
                ->label(__('general.reopen_year'))
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('general.reopen_year'))
                ->modalDescription(__('general.reopen_year_confirm'))
                ->visible(fn (): bool => $this->isYearClosed())
                ->action(fn (): mixed => $this->reopenYear()),
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url('javascript:window.print()'),
        ];
    }

    private function closeConfirmText(): string
    {
        $preview = $this->getPreview();

        return __('general.close_year_confirm', [
            'year' => $this->selectedYear(),
            'income' => number_format($preview['totalIncome']),
            'expenses' => number_format($preview['totalExpenses']),
            'net' => number_format($preview['net']),
        ]);
    }

    private function closeYear(): void
    {
        try {
            app(FiscalYearClosingService::class)->close($this->selectedYear());
        } catch (ValidationException $e) {
            Notification::make()->title(__('general.error'))->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('general.year_closed_success', ['year' => $this->selectedYear()]))->success()->send();
        $this->cachedPreview = null;
    }

    private function reopenYear(): void
    {
        try {
            app(FiscalYearClosingService::class)->reopen($this->selectedYear());
        } catch (ValidationException $e) {
            Notification::make()->title(__('general.error'))->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('general.year_reopened_success', ['year' => $this->selectedYear()]))->success()->send();
        $this->cachedPreview = null;
    }

    protected function getFooterWidgets(): array
    {
        return [
            ClosedYearsWidget::class,
        ];
    }
}
