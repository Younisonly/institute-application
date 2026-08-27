<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return __('general.low_stock');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query()->withLowStock()->with('category'))
            ->columns([
                TextColumn::make('name')->label(__('general.item_name'))->searchable()->weight('semibold'),
                TextColumn::make('category.name')->label(__('general.item_category'))->badge()->color('gray'),
                TextColumn::make('stock_qty')
                    ->label(__('general.current_stock'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (int $state): string => (string) $state),
                TextColumn::make('low_stock_threshold')->label(__('general.low_stock_threshold')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('general.view'))
                    ->url(fn (Item $record): string => \App\Filament\Resources\ItemResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
