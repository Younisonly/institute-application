<?php

namespace App\Filament\Resources\CertificateResource\Pages;

use App\Filament\Resources\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewCertificate extends ViewRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printCertificate')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('certificates.register.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! $this->getRecord()->isVoided()),
            Actions\Action::make('voidCertificate')
                ->label(__('general.void_certificate'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('general.void_certificate_confirm'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('void_reason')
                        ->label(__('general.void_reason'))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        app(CertificateService::class)->void($this->getRecord(), $data['void_reason']);

                        Notification::make()
                            ->title(__('general.certificate_voided_notice'))
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->hidden(fn (): bool => $this->getRecord()->isVoided()),
        ];
    }
}