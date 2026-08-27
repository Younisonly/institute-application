<?php

namespace App\Observers;

use App\Models\OtherPerson;
use RuntimeException;

class OtherPersonObserver
{
    public function forceDeleting(OtherPerson $person): void
    {
        if ($person->transactions()->exists()) {
            throw new RuntimeException(__('general.cannot_delete_with_financial_history'));
        }
    }
}
