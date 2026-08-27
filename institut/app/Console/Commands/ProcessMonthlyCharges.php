<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessMonthlyCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process-monthly';

    protected $description = 'Process monthly charges for all active registrations';

    public function handle(\App\Services\RegistrationService $service): int
    {
        $currentMonth = \App\Models\InstituteSetting::current()->current_month ?: now()->format('Y-m');
        $activeRegistrations = \App\Models\Registration::query()->where('status', 'active')->get();
        $processed = 0;
        $totalCharged = 0;
        
        $adminUserId = \App\Models\User::role('admin')->first()?->id ?? 1;

        foreach ($activeRegistrations as $registration) {
            if (! $registration->months()->where('month', $currentMonth)->exists()) {
                try {
                    $service->addMonth($registration, $currentMonth, $adminUserId);
                    $processed++;
                    $totalCharged += $registration->price_snapshot;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to process monthly charge for Registration {$registration->id}: {$e->getMessage()}");
                }
            }
        }

        $message = "Processed monthly charges for {$currentMonth}. Registrations charged: {$processed}. Total amount: " . number_format($totalCharged);
        
        $this->info($message);
        
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            \Filament\Notifications\Notification::make()
                ->title(__('general.monthly_billing_processed'))
                ->body($message)
                ->success()
                ->sendToDatabase($admin);
        }

        return self::SUCCESS;
    }
}
