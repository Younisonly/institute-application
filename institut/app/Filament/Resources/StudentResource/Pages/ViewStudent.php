<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Actions\SellBookAction;
use App\Filament\Pages\Payments as PaymentsPage;
use App\Filament\Resources\RegistrationResource;
use App\Filament\Resources\StudentResource;
use App\Filament\Widgets\RecommendationsWidget;
use App\Models\Student;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function resolveRecord(int | string $key): Model
    {
        return Student::query()->withBalance()->findOrFail((int) $key);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RecommendationsWidget::make(['record' => $this->getRecord()]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newRegistration')
                ->label(__('general.new_registration'))
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                ->url(fn (): string => $this->getRecord() ? RegistrationResource::getUrl('create', ['student_id' => $this->getRecord()->id]) : '#'),
            Actions\Action::make('recordPayment')
                ->label(__('general.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                ->url(fn (): string => $this->getRecord() ? PaymentsPage::getUrl(['student_id' => $this->getRecord()->id]) : '#'),
            SellBookAction::forStudent($this->getRecord()),
            Actions\Action::make('printStatement')
                ->label(__('general.print_statement'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                ->url(fn (): string => $this->getRecord() ? route('students.statement', $this->getRecord()) : '#')
                ->openUrlInNewTab(),
            Actions\Action::make('issueCertificate')
                ->label(__('general.issue_certificate'))
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                ->form([
                    Select::make('program_id')
                        ->label(__('general.program'))
                        ->options(fn (): array => \App\Models\ProgramType::query()
                            ->has('curriculum')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->preload(),
                    Textarea::make('note')
                        ->label(__('general.certificate_issue_note'))
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $program = \App\Models\ProgramType::find((int) $data['program_id']);
                    $record = $this->getRecord();
                    if (! $record) {
                        return;
                    }

                    try {
                        $certificate = app(\App\Services\CertificateService::class)->issue(
                            $record,
                            $program,
                            ['note' => $data['note'] ?? null],
                        );

                        Notification::make()
                            ->title(__('general.certificate_issued_notice', ['no' => $certificate->certificate_no]))
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(__('general.certificate_issue_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make()->label(__('general.restore')),
            Actions\ForceDeleteAction::make()
                ->label(__('general.force_delete'))
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->columns(6)
                    ->schema([
                        ImageEntry::make('photo_path')
                            ->label('')
                            ->circular()
                            ->height(88)
                            ->width(88)
                            ->placeholder(__('general.no_photo'))
                            ->columnSpan(1),
                        TextEntry::make('name')
                            ->label(__('general.full_name'))
                            ->size(TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->columnSpan(2),
                        TextEntry::make('student_code')
                            ->label(__('general.student_code'))
                            ->badge()
                            ->color('gray')
                            ->columnSpan(1),
                        TextEntry::make('gender')
                            ->label(__('general.gender'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => $state ? __("general.{$state}") : '')
                            ->columnSpan(1),
                        TextEntry::make('status')
                            ->label(__('general.status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? __("general.{$state}") : '')
                            ->color(fn (?string $state): string => match ($state ?? '') {
                                'active' => 'success',
                                'suspended' => 'warning',
                                'closed' => 'danger',
                                default => 'gray',
                            })
                            ->columnSpan(1),
                        TextEntry::make('join_date')
                            ->label(__('general.join_date'))
                            ->icon('heroicon-m-calendar')
                            ->color('gray')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('phone')
                            ->label(__('general.phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('whatsapp_phone')
                            ->label(__('general.whatsapp_phone'))
                            ->icon('heroicon-m-chat-bubble-left-right')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('education_level')
                            ->label(__('general.education_level'))
                            ->formatStateUsing(fn ($state) => $state ? __("general.education_{$state}") : '')
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),
                Section::make(__('general.balance'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('charges')
                            ->label(__('general.total_charge'))
                            ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency')),
                        TextEntry::make('payments')
                            ->label(__('general.paid'))
                            ->color('success')
                            ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency')),
                        TextEntry::make('balance')
                            ->label(fn (?Student $record): string => match (true) {
                                $record === null => __('general.balance'),
                                (float) ($record->balance ?? 0) > 0 => __('general.balance_owed_by_student'),
                                (float) ($record->balance ?? 0) < 0 => __('general.balance_credit_to_student'),
                                default => __('general.balance_settled'),
                            })
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn (?string $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($state ?? 0)))
                            ->color(fn (?string $state): string => (float) ($state ?? 0) > 0 ? 'danger' : 'success'),
                        TextEntry::make('active_registrations')
                            ->label(__('general.active_registrations'))
                            ->formatStateUsing(fn (?Student $record): string => $record !== null
                                ? (string) $record->registrations()->where('status', 'active')->count()
                                : '0')
                            ->badge()
                            ->color('info'),
                    ]),
                Section::make(__('general.guardian'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('guardian_name')->label(__('general.guardian_name'))->placeholder('—'),
                        TextEntry::make('guardian_relation')
                            ->label(__('general.guardian_relation'))
                            ->formatStateUsing(fn ($state) => $state ? __("general.relation_{$state}") : '')
                            ->placeholder('—'),
                        TextEntry::make('guardian_phone')
                            ->label(__('general.guardian_phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder('—'),
                    ]),
                Section::make(__('general.contact'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('birth_date')
                            ->label(__('general.birth_date'))
                            ->date('d/m/Y')
                            ->placeholder('—'),
                        TextEntry::make('national_id')
                            ->label(__('general.national_id'))
                            ->icon('heroicon-m-identification')
                            ->placeholder('—'),
                        TextEntry::make('address')->label(__('general.address'))->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('general.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}