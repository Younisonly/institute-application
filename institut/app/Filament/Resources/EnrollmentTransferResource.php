<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\EnrollmentTransferResource\Pages;
use App\Models\EnrollmentTransfer;
use App\Models\Student;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrollmentTransferResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'registrar'];
    }

    protected static ?string $model = EnrollmentTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.transfer_register');
    }

    public static function getModelLabel(): string
    {
        return __('general.transfer_record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.transfer_records');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('transferred_at', 'desc')
            ->columns([
                TextColumn::make('transferred_at')
                    ->label(__('general.transferred_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('student.name')
                    ->label(__('general.student'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('fromCourse.name')
                    ->label(__('general.transfer_from'))
                    ->searchable()
                    ->description(fn (EnrollmentTransfer $record): ?string => $record->fromBatch?->name)
                    ->placeholder('—'),
                TextColumn::make('toCourse.name')
                    ->label(__('general.transfer_to'))
                    ->description(fn (EnrollmentTransfer $record): ?string => $record->toBatch?->name)
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('general.reason'))
                    ->limit(28)
                    ->tooltip(fn (EnrollmentTransfer $record): string => $record->reason)
                    ->searchable(),
                TextColumn::make('balance_carried')
                    ->label(__('general.balance_carried'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency')),
                TextColumn::make('months_carried')
                    ->label(__('general.months_carried'))
                    ->badge()
                    ->color('info'),
                IconColumn::make('carry_items')
                    ->label(__('general.carry_items'))
                    ->boolean(),
                TextColumn::make('transferredBy.name')
                    ->label(__('general.transferred_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('student_id')
                    ->label(__('general.student'))
                    ->options(fn (): array => Student::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('carry_items')
                    ->label(__('general.carry_items'))
                    ->options([
                        '1' => __('general.yes'),
                        '0' => __('general.no'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')
                    ->label(__('general.print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (EnrollmentTransfer $record): string => route('enrollment-transfers.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('general.delete'))
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading(__('general.transfers_empty'))
            ->emptyStateDescription(__('general.transfers_empty_hint'));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.transfer_details'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('transferred_at')
                            ->label(__('general.transferred_at'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('student.name')
                            ->label(__('general.student')),
                        TextEntry::make('reason')
                            ->label(__('general.reason'))
                            ->columnSpanFull(),
                        TextEntry::make('fromCourse.name')
                            ->label(__('general.transfer_from'))
                            ->state(fn (EnrollmentTransfer $record): string => $record->fromCourse->name.($record->fromBatch ? ' — '.$record->fromBatch->name : '')),
                        TextEntry::make('toCourse.name')
                            ->label(__('general.transfer_to'))
                            ->state(fn (EnrollmentTransfer $record): string => $record->toCourse->name.($record->toBatch ? ' — '.$record->toBatch->name : '')),
                        TextEntry::make('balance_carried')
                            ->label(__('general.balance_carried'))
                            ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency'))
                            ->weight('bold'),
                        TextEntry::make('months_carried')
                            ->label(__('general.months_carried'))
                            ->badge()
                            ->color('info'),
                        TextEntry::make('carry_items')
                            ->label(__('general.carry_items'))
                            ->boolean(),
                        TextEntry::make('transferredBy.name')
                            ->label(__('general.transferred_by'))
                            ->placeholder('—'),
                        TextEntry::make('approvedBy.name')
                            ->label(__('general.approved_by'))
                            ->placeholder('—'),
                        TextEntry::make('from_registration_id')
                            ->label(__('general.registration_no'))
                            ->state(fn (EnrollmentTransfer $record): string => '#'.$record->from_registration_id.' → #'.$record->to_registration_id),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollmentTransfers::route('/'),
            'view' => Pages\ViewEnrollmentTransfer::route('/{record}'),
        ];
    }
}
