<?php

namespace App\Observers;

use App\Models\Registration;

class RegistrationObserver
{
    /**
     * Registrations are historical financial records — never hard-deleted
     * once they carry months, billing rows or transactions.
     */
    public function deleting(Registration $registration): void
    {
        if ($registration->months()->exists() || $registration->items()->exists() || $registration->transactions()->exists()) {
            throw new \RuntimeException(__('general.cannot_delete_registration_with_history'));
        }
    }
}