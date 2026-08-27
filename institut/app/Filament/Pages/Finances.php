<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Filament\Widgets\MoneyPlacesWidget;
use App\Filament\Widgets\PartyBalancesWidget;
use App\Filament\Widgets\TrialBalanceWidget;
use App\Services\ReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class Finances extends Page implements HasForms
{
    use HasRbac, InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.finances';

    public static function getNavigationGroup(): string
    {
        return __('general.nav_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.finances');
    }

    public static function getModelLabel(): string
    {
        return __('general.finances');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')
                    ->label(__('general.from'))
                    ->displayFormat('d/m/Y')
                    ->live(),
                DatePicker::make('to')
                    ->label(__('general.to'))
                    ->displayFormat('d/m/Y')
                    ->live(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function getIncomeStatement(): array
    {
        $from = ! empty($this->data['from']) ? Carbon::parse($this->data['from'])->startOfDay() : null;
        $to = ! empty($this->data['to']) ? Carbon::parse($this->data['to'])->endOfDay() : null;

        return app(ReportService::class)->incomeStatement($from, $to);
    }

    public function getIncomeTotal(): float
    {
        return (float) ($this->getIncomeStatement()['totalIncome'] ?? 0);
    }

    public function getExpenseTotal(): float
    {
        return (float) ($this->getIncomeStatement()['totalExpenses'] ?? 0);
    }

    protected function getFooterWidgets(): array
    {
        return [
            MoneyPlacesWidget::class,
            PartyBalancesWidget::class,
            TrialBalanceWidget::class,
        ];
    }
}

