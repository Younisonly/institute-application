<?php

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Actions\SellBookAction;
use App\Filament\Forms\Components\PaymentDetails;
use App\Filament\Resources\RegistrationResource;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Registration;
use App\Models\StudentTransaction;
use App\Services\ReceiptNumberService;
use App\Services\RegistrationService;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use App\Filament\Forms\Components\MonthPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.registration_details'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('student.name')
                            ->label(__('general.student'))
                            ->size(TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->url(fn (Registration $record): string => \App\Filament\Resources\StudentResource::getUrl('view', ['record' => $record->student_id])),
                        TextEntry::make('course.name')->label(__('general.course'))->badge()->color('info'),
                        TextEntry::make('batch.name')
                            ->label(__('general.batch'))
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),
                        TextEntry::make('period')
                            ->label(__('general.period'))
                            ->state(fn (Registration $record): ?string => $record->batch?->periods_label)
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('general.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                            ->color(fn (string $state): string => match ($state) {
                                'active'      => 'success',
                                'suspended'   => 'warning',
                                'closed'      => 'danger',
                                'transferred' => 'info',
                                default       => 'gray',
                            }),
                        TextEntry::make('start_month')->label(__('general.start_month'))->icon('heroicon-m-calendar'),
                        TextEntry::make('expected_end')->label(__('general.end_month'))->icon('heroicon-m-calendar'),
                        TextEntry::make('months_count')->label(__('general.months_count')),
                        TextEntry::make('price_snapshot')
                            ->label(__('general.price_snapshot'))
                            ->formatStateUsing(fn (?Registration $record): string => $record !== null && (float) $record->discount_amount > 0
                                ? number_format((float) $record->original_price).' '.__('general.currency')
                                    .' — '.__('general.discount_amount').' '.number_format((float) $record->discount_amount).' '.__('general.currency')
                                    .' = '.number_format((float) $record->price_snapshot).' '.__('general.currency')
                                : number_format((float) ($record?->price_snapshot ?? 0)).' '.__('general.currency')),
                        TextEntry::make('discount_type')
                            ->label(__('general.discount_type'))
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?Registration $record): string => $record !== null && (float) $record->discount_amount > 0 && $record->discount_type !== null
                                ? __("general.discount_type_{$record->discount_type}")
                                : __('general.no_discount'))
                            ->placeholder('—')
                            ->visible(fn (?Registration $record): bool => $record !== null && (float) $record->discount_amount > 0),
                        TextEntry::make('grade_total')
                            ->label(__('general.mark'))
                            ->placeholder('—')
                            ->formatStateUsing(function (?Registration $record): string {
                                if ($record === null || $record->grade_total === null) {
                                    return '—';
                                }

                                $grade = $record->grades['grade'] ?? null;

                                return number_format($record->grade_total)
                                    .($record->grades['full_mark'] !== null && $record->grades['full_mark'] !== '' ? ' / '.$record->grades['full_mark'] : '')
                                    .($grade !== null && $grade !== '' ? ' — '.$grade : '');
                            }),
                        TextEntry::make('grade_result')
                            ->label(__('general.result'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                            ->color(fn (string $state): string => match ($state) {
                                'passed' => 'success',
                                'failed' => 'danger',
                                default  => 'gray',
                            }),
                    ]),
                Section::make(__('general.balance'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('charged')
                            ->label(__('general.total_charge'))
                            ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency')),
                        TextEntry::make('paid')
                            ->label(__('general.paid'))
                            ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency')),
                        TextEntry::make('balance')
                            ->label(fn (?Registration $record): string => match (true) {
                                $record === null                      => __('general.balance'),
                                (float) ($record->balance ?? 0) > 0  => __('general.balance_owed_by_student'),
                                (float) ($record->balance ?? 0) < 0  => __('general.balance_credit_to_student'),
                                default                              => __('general.balance_settled'),
                            })
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn (?string $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($state ?? 0)))
                            ->color(fn (?string $state): string => (float) ($state ?? 0) > 0 ? 'danger' : 'success'),
                    ]),
                Section::make(__('general.notes'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('notes')->label(__('general.registration_notes'))->placeholder('—'),
                        TextEntry::make('close_reason')->label(__('general.close_reason'))->placeholder('—'),
                        TextEntry::make('closed_at')->label(__('general.date'))->date('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('eligibility_override')
                            ->label(__('general.eligibility_override'))
                            ->badge()
                            ->color('warning')
                            ->visible(fn (?Registration $record): bool => $record !== null && $record->eligibilityOverrideAudit() !== null)
                            ->formatStateUsing(function (?Registration $record): string {
                                $audit = $record?->eligibilityOverrideAudit();

                                if ($audit === null) {
                                    return '';
                                }

                                $reason = (string) ($audit->details['reason'] ?? '');
                                $by     = $audit->user?->name ?? (string) ($audit->details['by'] ?? '');

                                return $reason !== '' ? $reason.' — '.$by : ($by !== '' ? $by : __('general.override_applied'));
                            }),
                        TextEntry::make('created_at')->label(__('general.created_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('createdBy.name')->label(__('general.created_by'))->placeholder('—'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            // ── Primary: Record Payment ──────────────────────────────────────
            Actions\Action::make('recordPayment')
                ->label(__('general.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                ->modalHeading(__('general.record_payment'))
                ->form([
                    MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                    DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                    ...PaymentDetails::fields(),
                    TextInput::make('description')->label(__('general.description'))->maxLength(255),
                ])
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        StudentTransaction::create([
                            'student_id'      => $this->getRecord()->student_id,
                            'registration_id' => $this->getRecord()->id,
                            'type'            => 'payment',
                            'amount'          => $data['amount'],
                            'date'            => $data['date'],
                            'method'          => $data['method'] ?? 'cash',
                            'bank_id'         => $data['bank_id'] ?? null,
                            'wallet_id'       => $data['wallet_id'] ?? null,
                            'transaction_ref' => $data['transaction_ref'] ?? null,
                            'receipt_no'      => app(ReceiptNumberService::class)->next(),
                            'description'     => $data['description'] ?? null,
                            'created_by'      => Auth::id(),
                        ]);
                    });

                    Notification::make()->title(__('general.saved'))->success()->send();
                    $this->record->refresh();
                })
                ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

            // ── Sell Book ────────────────────────────────────────────────────
            SellBookAction::forRegistration($record)
                ->visible(fn (): bool => $record !== null && $record->status === 'active'),

            // ── Manage group (status + transfer + month) ─────────────────────
            ActionGroup::make([
                Actions\Action::make('suspend')
                    ->label(__('general.suspend'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->action(function (): void {
                        app(RegistrationService::class)->setStatus($this->getRecord(), 'suspended', (int) Auth::id());
                        Notification::make()->title(__('general.suspended'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && $record->status === 'active'),

                Actions\Action::make('resume')
                    ->label(__('general.resume'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->action(function (): void {
                        app(RegistrationService::class)->setStatus($this->getRecord(), 'active', (int) Auth::id());
                        Notification::make()->title(__('general.active'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && $record->status === 'suspended'),

                Actions\Action::make('addMonth')
                    ->label(__('general.add_month'))
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.add_month'))
                    ->modalDescription(__('general.add_month_confirm'))
                    ->form([
                        MonthPicker::make('month')
                            ->label(__('general.add_month'))
                            ->required()
                            ->helperText(__('general.month_format_hint')),
                    ])
                    ->action(function (array $data): void {
                        app(RegistrationService::class)->addMonth($this->getRecord(), $data['month'], (int) Auth::id());
                        Notification::make()->title(__('general.saved'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

                Actions\Action::make('transfer')
                    ->label(__('general.transfer'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.transfer_registration'))
                    ->modalDescription(__('general.transfer_hint'))
                    ->form([
                        Select::make('course_id')->native(false)
                            ->label(__('general.transfer_to_course'))
                            ->options(function () use ($record): array {
                                if ($record === null) {
                                    return [];
                                }

                                return Course::query()
                                    ->enrollable()
                                    ->where('id', '!=', $record->course_id)
                                    ->where('program_type_id', $record->course->program_type_id)
                                    ->get()
                                    ->mapWithKeys(fn (Course $course): array => [
                                        $course->id => $course->name,
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Set $set, ?int $state): void {
                                $set('course_batch_id', $state !== null ? Course::find($state)?->openBatch()?->id : null);
                            }),
                        Select::make('course_batch_id')->native(false)
                            ->label(__('general.batch'))
                            ->placeholder(__('general.no_batch_selected'))
                            ->searchable()
                            ->options(function (\Filament\Forms\Get $get): array {
                                $courseId = (int) ($get('course_id') ?? 0);

                                if ($courseId <= 0) {
                                    return [];
                                }

                                return CourseBatch::query()
                                    ->where('course_id', $courseId)
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (CourseBatch $batch): array => [
                                        $batch->id => $batch->option_label,
                                    ])
                                    ->all();
                            })
                            ->helperText(__('general.select_course_and_batch')),
                        TextInput::make('reason')->label(__('general.close_reason'))->required()->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('carry_items')
                            ->label(__('general.carry_items'))
                            ->helperText(__('general.carry_items_hint'))
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        $new = app(RegistrationService::class)->transfer(
                            $this->getRecord(),
                            (int) $data['course_id'],
                            $data['reason'],
                            (int) Auth::id(),
                            (bool) ($data['carry_items'] ?? false),
                            $data['course_batch_id'] !== null ? (int) $data['course_batch_id'] : null,
                        );

                        Notification::make()->title(__('general.transfer'))->success()->send();
                        $this->redirect(RegistrationResource::getUrl('view', ['record' => $new]));
                    })
                    ->visible(fn (): bool => $record !== null && $record->status === 'active'),
            ])
            ->label(__('general.manage'))
            ->icon('heroicon-m-cog-6-tooth')
            ->color('gray')
            ->button()
            ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

            // ── Print group ──────────────────────────────────────────────────
            ActionGroup::make([
                Actions\Action::make('printReceipt')
                    ->label(__('general.print_receipt'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(function () use ($record): ?string {
                        if ($record === null) {
                            return null;
                        }

                        $latest = $record->transactions()
                            ->where('type', 'payment')
                            ->whereNotNull('receipt_no')
                            ->whereNull('voided_at')
                            ->latest('id')
                            ->first();

                        return $latest !== null ? route('receipts.print', $latest) : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(function () use ($record): bool {
                        if ($record === null) {
                            return false;
                        }

                        return $record->transactions()
                            ->where('type', 'payment')
                            ->whereNotNull('receipt_no')
                            ->whereNull('voided_at')
                            ->exists();
                    }),

                Actions\Action::make('printStatement')
                    ->label(__('general.print_statement'))
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Registration $registration): string => route('students.statement', $registration->student_id))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => $record !== null),

                Actions\Action::make('printCertificate')
                    ->label(__('general.print_certificates'))
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->url(fn (): string => route('certificates.print', $this->getRecord()))
                    ->openUrlInNewTab()
                    ->visible(function () use ($record): bool {
                        if ($record === null) {
                            return false;
                        }

                        return ($record->grades['passed'] ?? false) === true;
                    }),
            ])
            ->label(__('general.print'))
            ->icon('heroicon-m-printer')
            ->color('gray')
            ->button()
            ->visible(fn (): bool => $record !== null),

            // ── Destructive / admin actions group ────────────────────────────
            ActionGroup::make([
                Actions\Action::make('close')
                    ->label(__('general.close_registration'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.close_registration'))
                    ->modalDescription(__('general.close_registration_confirm'))
                    ->form([
                        TextInput::make('reason')->label(__('general.close_reason'))->required()->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('write_off')
                            ->label(__('general.write_off_balance'))
                            ->helperText(__('general.write_off_balance_hint'))
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        app(RegistrationService::class)->close($this->getRecord(), $data['reason'], (int) Auth::id(), $data['write_off'] ?? false);
                        Notification::make()->title(__('general.closed'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

                Actions\Action::make('withdraw')
                    ->label(__('general.withdraw_registration'))
                    ->icon('heroicon-o-arrow-left-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.withdraw_registration'))
                    ->modalDescription(__('general.withdraw_registration_confirm'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label(__('general.withdraw_reason'))
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('write_off')
                            ->label(__('general.write_off_balance'))
                            ->helperText(__('general.write_off_balance_hint'))
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        app(RegistrationService::class)->withdraw($this->getRecord(), $data['reason'], (int) Auth::id(), $data['write_off'] ?? false);
                        Notification::make()->title(__('general.withdrawn'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

                Actions\Action::make('cancel')
                    ->label(__('general.cancel_registration'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.cancel_registration'))
                    ->modalDescription(__('general.cancel_registration_confirm'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label(__('general.cancel_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $record = $this->getRecord();
                        $totals = \App\Models\Registration::query()->withTotals()->find($record->id);
                        if ($totals && $totals->paid > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('general.cancel_has_payments_error'))
                                ->danger()
                                ->send();
                            return;
                        }
                        app(RegistrationService::class)->cancel($record, $data['reason'], (int) Auth::id());
                        Notification::make()->title(__('general.cancelled'))->success()->send();
                        $this->record->refresh();
                    })
                    ->visible(fn (): bool => $record !== null && in_array($record->status, ['active', 'suspended'], true)),

                Actions\Action::make('reopenResult')
                    ->label(__('general.reopen_result'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                    ->modalHeading(__('general.reopen_result'))
                    ->modalDescription(__('general.reopen_result_confirm'))
                    ->form([
                        TextInput::make('reason')
                            ->label(__('general.reopen_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(RegistrationService::class)->reopenResult($this->getRecord(), (int) Auth::id(), $data['reason']);
                            Notification::make()->title(__('general.reopen_result_done'))->success()->send();
                            $this->record->refresh();
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn (): bool => $record !== null && $record->result_finalized_at !== null),
            ])
            ->label(__('general.more_actions'))
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->button()
            ->visible(fn (): bool => $record !== null),
        ];
    }
}
