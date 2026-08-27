<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Services\ReportService;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

class MoneyPlacesWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected function getTableHeading(): string
    {
        return __('general.money_places');
    }

    private function getBalances(): Collection
    {
        return collect(app(ReportService::class)->placeBalances())
            ->mapWithKeys(fn (array $row): array => [$row['account']->id => (float) $row['balance']]);
    }

    public function table(Table $table): Table
    {
        $balances = $this->getBalances();

        return $table
            ->query(Account::query()
                ->whereIn('id', $balances->keys())
                ->orderBy('code'))
            ->columns([
                TextColumn::make('name')->label(__('general.account'))->weight('semibold'),
                TextColumn::make('code')->label(__('general.code'))->placeholder('—'),
                TextColumn::make('account_balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->state(fn (Account $record): string => \App\Helpers\MoneyFormatter::formatAccountBalance((float) $balances->get($record->id, 0.0), $record->type))
                    ->color(fn (Account $record): string => $balances->get($record->id, 0.0) < 0 ? 'danger' : 'gray')
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn (): float => (float) $balances->sum())
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatAccountBalance($state, 'asset'))
                    ),
            ])
            ->paginated(false);
    }
}
