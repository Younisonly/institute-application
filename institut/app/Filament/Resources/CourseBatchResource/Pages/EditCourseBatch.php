<?php

namespace App\Filament\Resources\CourseBatchResource\Pages;

use App\Filament\Resources\CourseBatchResource;
use App\Models\CourseBatch;
use App\Services\CourseBatchService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCourseBatch extends EditRecord
{
    protected static string $resource = CourseBatchResource::class;

    protected ?string $requestedStatus = null;

    protected ?string $periodId = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Status changes go through CourseBatchService::transition() (guards,
        // is_active sync, audit, cancellation rules) — never saved raw here.
        if (isset($data['status']) && (string) $data['status'] !== $this->record->status) {
            $this->requestedStatus = (string) $data['status'];
        }

        $data['status'] = $this->record->status;

        $this->periodId = isset($data['periods']) && $data['periods'] !== '' ? (string) $data['periods'] : null;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->periodId !== null) {
            $this->record->periods()->sync([(int) $this->periodId]);
        }

        if ($this->requestedStatus === null) {
            return;
        }

        app(CourseBatchService::class)->transition(
            $this->record->refresh(),
            $this->requestedStatus,
            (int) Auth::id(),
        );

        $this->record = $this->record->fresh();
        $this->requestedStatus = null;

        $this->redirect($this->getUrl());
    }

    protected function getHeaderActions(): array
    {
        $record = $this->record;

        $transitionActions = collect(CourseBatch::TRANSITIONS[$record->status] ?? [])
            ->map(function (string $to) use ($record): Actions\Action {
                $action = Actions\Action::make('to_'.$to)
                    ->label(__("general.batch_status_{$to}"))
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => $to === 'cancelled'
                        ? \App\Filament\Resources\CourseBatchResource::canDo('batch.cancel')
                        : auth()->user()?->hasAnyRole(['admin', 'registrar']) ?? false)
                    ->action(function () use ($record, $to): void {
                        $reason = $to === 'cancelled'
                            ? (string) ($this->mountedActionsData[0]['cancelled_reason'] ?? '')
                            : null;

                        app(CourseBatchService::class)->transition(
                            $record->refresh(),
                            $to,
                            (int) Auth::id(),
                            $reason,
                        );

                        $this->record = $record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title(__('general.batch_status_changed'))
                            ->success()
                            ->send();
                    });

                if ($to === 'cancelled') {
                    $action->color('danger')
                        ->icon('heroicon-m-x-circle')
                        ->modalHeading(__('general.batch_cancel_confirm'))
                        ->form([
                            Textarea::make('cancelled_reason')
                                ->label(__('general.batch_cancel_reason'))
                                ->required()
                                ->rows(3),
                        ])
                        ->modalSubmitActionLabel(__('general.confirm'));
                } else {
                    $action->color(match ($to) {
                        'open' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })->icon('heroicon-m-arrow-path');
                }

                return $action;
            })
            ->all();

        return [
            ...$transitionActions,
            Actions\Action::make('enterMarks')
                ->label(__('general.enter_marks'))
                ->icon('heroicon-m-pencil-square')
                ->url(fn () => \App\Filament\Pages\BatchMarks::getUrl([
                    'course_id' => $this->record->course_id,
                    'course_batch_id' => $this->record->id,
                ])),
            Actions\Action::make('printMarksSheet')
                ->label(__('general.marks_sheet'))
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->url(fn () => route('marks.batch.print', $this->record->id)),
            Actions\Action::make('certificatesBatch')
                ->label(__('general.certificates'))
                ->icon('heroicon-m-academic-cap')
                ->color('success')
                ->url(fn () => route('certificates.batch.print', $this->record->id)),
            Actions\DeleteAction::make(),
        ];
    }
}