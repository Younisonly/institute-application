<?php

namespace App\Filament\Resources\StaffAttendanceResource\Pages;

use App\Filament\Resources\StaffAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffAttendances extends ListRecords
{
    protected static string $resource = StaffAttendanceResource::class;

    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();
        if ($user && $user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'accountant', 'registrar'])) {
            $staff = $user->staff ?? \App\Models\Staff::query()->where('phone', $user->email)->orWhere('name', $user->name)->first();
            if ($staff) {
                $this->redirect(StaffAttendanceResource::getUrl('employee-history', ['record' => $staff->id]));
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('record_attendance')
                ->label(__('general.record_attendance'))
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->slideOver()
                ->modalHeading(__('general.record_attendance'))
                ->modalSubmitActionLabel(__('general.save'))
                ->form(StaffAttendanceResource::getAttendanceFormSchema())
                ->action(function (array $data): void {
                    try {
                        $att = app(\App\Services\AttendanceManagementService::class)->saveAttendance($data);
                        if ($att->getAttribute('is_closed_payroll_edit')) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('general.attendance_saved_retroactive_notice'))
                                ->warning()
                                ->send();
                        } elseif ($att->getAttribute('was_already_recorded')) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('general.attendance_already_recorded_updated'))
                                ->info()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title(__('general.attendance_recorded'))
                                ->success()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
