<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\FinancePostingService;
use Illuminate\Database\Eloquent\Model;

class StockMovementObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(StockMovement $movement): void
    {
        $this->adjustStock($movement);
        $this->posting->postStockPurchase($movement);
        $this->posting->postStockSale($movement);
    }

    public function updating(StockMovement $movement): void
    {
        if ($movement->isDirty('voided_at') && $movement->voided_at !== null) {
            $this->posting->reverseForDocument($movement, $movement->void_reason ?? __('general.void'));
        }
    }

    /**
     * Every movement adjusts the physical stock by its sign (in adds,
     * issue/sold/damaged remove). Single source of truth so the shelf and
     * the ledger can never diverge, no matter which UI path created it.
     */
    private function adjustStock(StockMovement $movement): void
    {
        /** @var Model|null $stock */
        $stock = $movement->book_id !== null
            ? Book::query()->lockForUpdate()->find($movement->book_id)
            : ($movement->item_id !== null ? Item::query()->lockForUpdate()->find($movement->item_id) : null);

        if ($stock === null) {
            return;
        }

        $sign = $movement->type === 'in' ? 1 : -1;
        $updates = ['stock_qty' => max(0, $stock->stock_qty + ($sign * $movement->qty))];

        if ($movement->type === 'in' && $movement->unit_price > 0) {
            if ($stock instanceof \App\Models\Item) {
                $updates['purchase_price'] = $movement->unit_price;
            } elseif ($stock instanceof \App\Models\Book) {
                $updates['buy_price'] = $movement->unit_price;
            }
        }

        $stock->update($updates);
    }
}