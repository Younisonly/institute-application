<?php

namespace App\Observers;

use App\Models\Staff;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StaffObserver
{
    public function forceDeleting(Staff $staff): void
    {
        if ($staff->transactions()->exists()) {
            throw new RuntimeException(__('general.cannot_delete_with_financial_history'));
        }
    }

    public function saved(Staff $staff): void
    {
        if ($staff->isDirty('photo_path') && ($old = $staff->getOriginal('photo_path'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function forceDeleted(Staff $staff): void
    {
        if ($staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
        }
    }
}
