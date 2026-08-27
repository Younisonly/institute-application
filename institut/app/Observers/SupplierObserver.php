<?php

namespace App\Observers;

use App\Models\Supplier;
use RuntimeException;

class SupplierObserver
{
    public function forceDeleting(Supplier $supplier): void
    {
        if ($supplier->transactions()->exists()) {
            throw new RuntimeException(__('general.cannot_delete_with_financial_history'));
        }
    }
}
