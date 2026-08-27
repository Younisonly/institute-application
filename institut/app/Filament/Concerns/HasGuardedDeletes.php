<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Force-delete actions that never crash the page: the model observers
 * (financial-history guards) throw RuntimeException — here we surface the
 * localized reason as a persistent dialog-style notification instead of
 * letting Livewire render the 500 error page.
 */
trait HasGuardedDeletes
{
    public static function guardedForceDeleteAction(): Action
    {
        return Action::make('forceDelete')
            ->label(__('general.force_delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (object $record): void {
                try {
                    $record->forceDelete();
                    Notification::make()
                        ->title(__('general.deleted'))
                        ->success()
                        ->send();
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function guardedForceDeleteBulkAction(): BulkAction
    {
        return BulkAction::make('forceDeleteStudents')
            ->label(__('general.force_delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $deleted = 0;
                $skipped = 0;
                $reason = null;

                foreach ($records as $record) {
                    try {
                        $record->forceDelete();
                        $deleted++;
                    } catch (RuntimeException $e) {
                        $skipped++;
                        $reason = $reason ?? $e->getMessage();
                    }
                }

                Notification::make()
                    ->title(__('general.bulk_delete_result', ['deleted' => $deleted, 'skipped' => $skipped]))
                    ->body($skipped > 0 ? $reason : null)
                    ->color($skipped > 0 ? 'warning' : 'success')
                    ->persistent()
                    ->send();
            });
    }
}