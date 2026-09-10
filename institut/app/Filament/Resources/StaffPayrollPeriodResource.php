<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffPayrollPeriodResource\Pages;
use App\Models\JournalEntry;
use App\Models\StaffPayrollPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffPayrollPeriodResource extends Resource
{
    protected static ?string $model = StaffPayrollPeriod::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.payroll_records');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.payroll_records');
    }

    public static function getModelLabel(): string
    {
        return __('general.payroll_voucher');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.payroll_voucher'))
                    ->columns(2)
                    ->schema([
                        Placeholder::make('staff_name')
                            ->label(__('general.staff_member'))
                            ->content(fn (StaffPayrollPeriod $record): string => $record->staff?->name ?? '—'),
                        Placeholder::make('salary_month')
                            ->label(__('general.salary_month'))
                            ->content(fn (StaffPayrollPeriod $record): string => $record->salary_month),
                        Placeholder::make('base_salary')
                            ->label(__('general.base_salary'))
                            ->content(fn (StaffPayrollPeriod $record): string => number_format((float) $record->base_salary) . ' ' . __('general.currency')),
                        Placeholder::make('gross_salary')
                            ->label(__('general.gross_salary'))
                            ->content(fn (StaffPayrollPeriod $record): string => number_format((float) $record->gross_salary) . ' ' . __('general.currency')),
                        Placeholder::make('advance_deduction')
                            ->label(__('general.deduct_from_advance'))
                            ->content(fn (StaffPayrollPeriod $record): string => number_format((float) $record->advance_deduction_amount) . ' ' . __('general.currency')),
                        Placeholder::make('net_salary')
                            ->label(__('general.net_salary'))
                            ->content(fn (StaffPayrollPeriod $record): string => number_format((float) $record->net_salary) . ' ' . __('general.currency')),
                        Placeholder::make('status')
                            ->label(__('general.status'))
                            ->content(fn (StaffPayrollPeriod $record): string => match ($record->status) {
                                'approved' => __('general.posted_to_employee_account'),
                                'partially_paid' => __('general.partially_paid'),
                                'paid' => __('general.paid'),
                                'cancelled' => __('general.cancelled'),
                                default => __('general.pending_payroll_approval'),
                            }),
                        Placeholder::make('approved_by')
                            ->label(__('general.approved_by'))
                            ->content(fn (StaffPayrollPeriod $record): string => $record->approvedBy?->name ?? '—'),
                        Placeholder::make('approved_at')
                            ->label(__('general.approved_at'))
                            ->content(fn (StaffPayrollPeriod $record): string => $record->approved_at ? $record->approved_at->format('d/m/Y H:i') : '—'),
                        Placeholder::make('notes')
                            ->label(__('general.notes'))
                            ->content(fn (StaffPayrollPeriod $record): string => $record->notes ?? '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label(__('general.staff_member'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('salary_month')
                    ->label(__('general.salary_month'))
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('base_salary')
                    ->label(__('general.base_salary'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gross_salary')
                    ->label(__('general.gross_salary'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('advance_deduction_amount')
                    ->label(__('general.deduct_from_advance'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_salary')
                    ->label(__('general.net_salary'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('bold')
                    ->color('success'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => __('general.posted_to_employee_account'),
                        'partially_paid' => __('general.partially_paid'),
                        'paid' => __('general.paid'),
                        'cancelled' => __('general.cancelled'),
                        default => __('general.pending_payroll_approval'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'info',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('approvedBy.name')
                    ->label(__('general.approved_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label(__('general.approved_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->label(__('general.staff_member'))
                    ->relationship('staff', 'name'),
                Tables\Filters\SelectFilter::make('salary_month')
                    ->label(__('general.salary_month'))
                    ->options(fn (): array => StaffPayrollPeriod::query()->pluck('salary_month', 'salary_month')->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'approved' => __('general.posted_to_employee_account'),
                        'partially_paid' => __('general.partially_paid'),
                        'paid' => __('general.paid'),
                        'cancelled' => __('general.cancelled'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancelAccrual')
                    ->label(__('general.cancel_payroll_accrual'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.cancel_payroll_accrual'))
                    ->modalDescription(__('general.cancel_payroll_confirm'))
                    ->form([
                        TextInput::make('reason')
                            ->label(__('general.void_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (StaffPayrollPeriod $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $period = StaffPayrollPeriod::query()->lockForUpdate()->find($record->id);
                            if (!$period || !in_array($period->status, ['approved', 'partially_paid'], true)) {
                                return;
                            }

                            app(\App\Services\FinancePostingService::class)->reverseForDocument(
                                $period,
                                $data['reason'] ?? __('general.cancel_payroll_accrual'),
                                auth()->id()
                            );

                            $period->update([
                                'status' => 'cancelled',
                                'notes' => trim(($period->notes ?? '') . ' [' . __('general.cancelled') . ': ' . ($data['reason'] ?? '') . ']'),
                            ]);
                        });

                        Notification::make()->title(__('general.accrual_cancelled_successfully'))->success()->send();
                    })
                    ->visible(fn (StaffPayrollPeriod $record): bool => in_array($record->status, ['approved', 'partially_paid'], true) && $record->total_paid <= 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffPayrollPeriods::route('/'),
            'view' => Pages\ViewStaffPayrollPeriod::route('/{record}'),
        ];
    }
}
