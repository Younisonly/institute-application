<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\Account;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class TrialBalance extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.trial_balance');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reports.trial-balance';

    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.trial_balance');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label(__('general.from'))->displayFormat('d/m/Y'),
                DatePicker::make('to')->label(__('general.to'))->displayFormat('d/m/Y'),
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
        $from = ! empty($this->data['from']) ? \Illuminate\Support\Carbon::parse($this->data['from']) : null;
        $to = ! empty($this->data['to']) ? \Illuminate\Support\Carbon::parse($this->data['to']) : null;

        return app(ReportService::class)->trialBalance($from, $to);
    }

    private function getRows(): Collection
    {
        return collect($this->getReport()['rows'])->mapWithKeys(fn (array $row): array => [$row['account']->id => $row]);
    }

    public function table(Table $table): Table
    {
        $rows = $this->getRows();

        return $table
            ->query(Account::query()->whereIn('id', $rows->keys())->orderBy('code'))
            ->columns([
                TextColumn::make('name')->label(__('general.account'))->weight('semibold'),
                TextColumn::make('code')->label(__('general.code'))->placeholder('—'),
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (Account $record): string => __("general.account_type_{$record->type}"))
                    ->color(fn (Account $record): string => match ($record->type) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'gray',
                        'income' => 'success',
                        'expense' => 'danger',
                    }),
                TextColumn::make('debit')
                    ->label(__('general.debit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (Account $record): string => number_format((float) ($rows->get($record->id)['debit'] ?? 0))),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (Account $record): string => number_format((float) ($rows->get($record->id)['credit'] ?? 0))),
                TextColumn::make('account_balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (Account $record): string => number_format((float) ($rows->get($record->id)['balance'] ?? 0))),
            ])
            ->paginated(false);
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
