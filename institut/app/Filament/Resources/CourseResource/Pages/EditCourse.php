<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Models\Course;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('open_enrollment')
                    ->label(__('general.open_enrollment'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Course $record) {
                        $record->update(['is_active' => true]);
                        Notification::make()->title(__('general.saved'))->success()->send();
                        $this->refreshFormData(['is_active']);
                    })
                    ->visible(fn (?Course $record) => $record?->lifecycle_status === Course::LIFECYCLE_INACTIVE),

                Actions\Action::make('suspend_course')
                    ->label(__('general.suspend_course'))
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Course $record) {
                        $record->update([
                            'is_active' => false,
                        ]);
                        Notification::make()->title(__('general.saved'))->success()->send();
                        $this->refreshFormData(['is_active']);
                    })
                    ->visible(fn (Course $record) => $record->is_active),
            ])->label(__('general.actions'))->icon('heroicon-m-ellipsis-vertical'),
            
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make()->label(__('general.restore')),
            Actions\ForceDeleteAction::make()
                ->label(__('general.force_delete'))
                ->requiresConfirmation(),
        ];
    }
}
