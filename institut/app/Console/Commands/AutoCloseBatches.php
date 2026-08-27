<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CourseBatch;
use App\Models\User;
use App\Services\CourseBatchService;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AutoCloseBatches extends Command
{
    protected $signature   = 'batches:auto-close';
    protected $description = 'Auto-complete batches whose end date has passed and send 5-day expiry warnings';

    public function handle(CourseBatchService $service): int
    {
        $today = now()->startOfDay();

        $this->autoComplete($service, $today);
        $this->warnExpiring($today);

        return self::SUCCESS;
    }

    /**
     * Complete any batch whose end_date is in the past and that hasn't been
     * finished yet. Batches stuck in 'scheduled' (never launched) get cancelled
     * instead of completed — they have no registrations to finalize.
     */
    private function autoComplete(CourseBatchService $service, \Carbon\Carbon $today): void
    {
        $expiredBatches = CourseBatch::query()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->whereNull('finished_at')
            ->whereIn('status', ['open', 'in_progress', 'scheduled'])
            ->get();

        if ($expiredBatches->isEmpty()) {
            $this->line(__('general.batch_auto_close_none_found'));

            return;
        }

        /** @var User|null $systemActor */
        $systemActor = User::query()->role('admin')->first();
        $actorId     = $systemActor?->id ?? 0;

        $adminsAndRegistrars = User::query()
            ->role(['admin', 'registrar'])
            ->get();

        foreach ($expiredBatches as $batch) {
            try {
                DB::transaction(function () use ($batch, $service, $actorId, $adminsAndRegistrars): void {
                    if ($batch->status === 'scheduled') {
                        // Never ran — cancel cleanly
                        $batch->update([
                            'status'          => 'cancelled',
                            'is_active'       => false,
                            'cancelled_at'    => now(),
                            'cancelled_reason' => __('general.batch_auto_cancelled_reason'),
                            'cancelled_by'    => $actorId ?: null,
                        ]);

                        AuditLog::log('course_batch.auto_cancelled', CourseBatch::class, $batch->id, [
                            'end_date' => $batch->end_date?->toDateString(),
                            'via'      => 'system',
                        ]);

                        $this->warn(__('general.batch_auto_cancelled_log', ['name' => $batch->name]));
                    } else {
                        // Has (or had) registrations — complete properly
                        $result = $service->complete($batch, $actorId);

                        $this->info(__('general.batch_auto_closed_log', [
                            'name'      => $batch->name,
                            'completed' => $result['completed'],
                            'remaining' => $result['remaining'],
                        ]));
                    }

                    // Notify admins & registrars via database notification
                    if ($adminsAndRegistrars->isNotEmpty()) {
                        Notification::make()
                            ->title(__('general.batch_auto_closed'))
                            ->body(__('general.batch_auto_closed_body', [
                                'name' => $batch->name,
                                'date' => $batch->end_date?->format('d/m/Y') ?? '—',
                            ]))
                            ->warning()
                            ->sendToDatabase($adminsAndRegistrars);
                    }
                });
            } catch (\Throwable $e) {
                $this->error("Failed to auto-close batch [{$batch->id}] {$batch->name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Send a daily DB notification to admins/registrars for every batch that
     * will expire within the next 5 days, so they can act before the auto-close
     * runs overnight. Deduplication: one cache key per batch per calendar day.
     */
    private function warnExpiring(\Carbon\Carbon $today): void
    {
        $horizon = now()->addDays(5)->endOfDay();

        $expiringSoon = CourseBatch::query()
            ->with('course')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $horizon->toDateString()])
            ->whereNull('finished_at')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('end_date')
            ->get();

        if ($expiringSoon->isEmpty()) {
            return;
        }

        $recipients = User::query()->role(['admin', 'registrar'])->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($expiringSoon as $batch) {
            $cacheKey = "batch_warn_{$batch->id}_" . $today->toDateString();

            if (Cache::has($cacheKey)) {
                continue; // Already warned today for this batch
            }

            $daysLeft = (int) $today->diffInDays($batch->end_date, false);

            Notification::make()
                ->title(__('general.batch_expiring_soon'))
                ->body(__('general.batch_expiring_soon_body', [
                    'name'   => $batch->name,
                    'course' => $batch->course?->name ?? '—',
                    'days'   => $daysLeft,
                    'date'   => $batch->end_date->format('d/m/Y'),
                ]))
                ->danger()
                ->sendToDatabase($recipients);

            // Cache for 25 hours so the daily job never double-fires
            Cache::put($cacheKey, true, now()->addHours(25));

            $this->line(__('general.batch_expiry_warning_sent', [
                'name' => $batch->name,
                'days' => $daysLeft,
            ]));
        }
    }
}
