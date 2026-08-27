<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Services\ReportService;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

class TrialBalanceWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return __('general.trial_balance_short');
    }

    private function getRows(): Collection
    {
        return collect(app(ReportService::class)->trialBalance()['rows'])
            ->mapWithKeys(fn (array $row): array => [
                (int) $row['account']->id => [
                    'debit' => (float) $row['debit'],
                    'credit' => (float) $row['credit'],
                    'balance' => (float) $row['balance'],
                ],
            ]);
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
                    ->state(fn (Account $record): string => number_format((float) ($rows->get($record->id)['debit'] ?? 0)))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn (): float => (float) $rows->sum('debit'))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ),
                TextColumn::make('credit')
                    ->label(__('general.credit'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (Account $record): string => number_format((float) ($rows->get($record->id)['credit'] ?? 0)))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn (): float => (float) $rows->sum('credit'))
                            ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))
                    ),
                TextColumn::make('account_balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (Account $record): string => \App\Helpers\MoneyFormatter::formatAccountBalance((float) ($rows->get($record->id)['balance'] ?? 0), $record->type))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total_balance_summary'))
                            ->using(fn (): float => (float) ($rows->sum('debit') - $rows->sum('credit')))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, 'asset'))
                    ),
            ])
            ->paginated(false);
    }
}
