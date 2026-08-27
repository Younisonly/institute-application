<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\EnrollmentTransfer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EnrollmentTransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollmentTransfers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.transfers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('transferred_at', 'desc')
            ->columns([
                TextColumn::make('transferred_at')
                    ->label(__('general.transferred_at'))
                    ->dateTime('d/m/Y H:i')
                    ->weight('semibold'),
                TextColumn::make('fromCourse.name')
                    ->label(__('general.transfer_from'))
                    ->description(fn (EnrollmentTransfer $record): ?string => $record->fromBatch?->name)
                    ->placeholder('—'),
                TextColumn::make('toCourse.name')
                    ->label(__('general.transfer_to'))
                    ->description(fn (EnrollmentTransfer $record): ?string => $record->toBatch?->name)
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('general.reason'))
                    ->limit(28)
                    ->tooltip(fn (EnrollmentTransfer $record): string => $record->reason),
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
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label(__('general.print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (EnrollmentTransfer $record): string => route('enrollment-transfers.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->emptyStateHeading(__('general.transfers_empty'))
            ->emptyStateDescription(__('general.transfers_empty_hint_link'));
    }
}
