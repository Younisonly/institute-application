<?php

namespace App\Filament\Widgets;

use App\Models\FiscalYearClosing;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClosedYearsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return __('general.closed_financial_years');
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return FiscalYearClosing::query()
            ->with(['closer', 'journalEntry'])
            ->orderByDesc('year');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('year')->label(__('general.fiscal_year'))->weight('bold'),
            TextColumn::make('net')
                ->label(__('general.net_result'))
                ->alignment(\Filament\Support\Enums\Alignment::End)
                ->formatStateUsing(fn (FiscalYearClosing $record): string => number_format((float) $record->net)),
            TextColumn::make('journalEntry.entry_no')
                ->label(__('general.entry_no'))
                ->prefix('#')
                ->color('gray'),
            TextColumn::make('closed_at')->label(__('general.closed_at'))->dateTime('d/m/Y H:i'),
            TextColumn::make('closer.name')->label(__('general.closed_by'))->placeholder('—'),
        ];
    }
}
