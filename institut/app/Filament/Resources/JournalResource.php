<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\JournalResource\Pages;
use App\Models\JournalEntry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JournalResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function createRoles(): array
    {
        return [];
    }

    protected static function editRoles(): array
    {
        return [];
    }

    protected static function deleteRoles(): array
    {
        return [];
    }

    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_ledger');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.journal');
    }

    public static function getModelLabel(): string
    {
        return __('general.journal_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.journal');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('lines.account'))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('entry_no')->label(__('general.entry_no'))->weight('semibold'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('description')->label(__('general.description'))->limit(50),
                TextColumn::make('flow')
                    ->label(__('general.flow'))
                    ->state(function (\App\Models\JournalEntry $record) {
                        $debits = $record->lines->where('debit', '>', 0)->map->account->pluck('name')->unique()->implode(', ');
                        $credits = $record->lines->where('credit', '>', 0)->map->account->pluck('name')->unique()->implode(', ');
                        if ($debits && $credits) {
                            return "$debits ➔ $credits";
                        }
                        return '—';
                    }),
                TextColumn::make('debit_total')
                    ->label(__('general.total_debit'))
                    ->formatStateUsing(fn (float $state): string => number_format($state) . ' ' . __('general.currency')),
                TextColumn::make('credit_total')
                    ->label(__('general.total_credit'))
                    ->formatStateUsing(fn (float $state): string => number_format($state) . ' ' . __('general.currency')),
                TextColumn::make('createdBy.name')->label(__('general.created_by'))->placeholder('—'),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : __('general.active'))
                    ->color(fn ($state) => $state ? 'gray' : 'success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->label(__('general.date_range'))
                    ->columns(2)
                    ->form([
                        DatePicker::make('from')->label(__('general.from'))->displayFormat('d/m/Y'),
                        DatePicker::make('to')->label(__('general.to'))->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $from): Builder => $q->whereDate('date', '>=', $from))
                            ->when($data['to'] ?? null, fn (Builder $q, string $to): Builder => $q->whereDate('date', '<=', $to));
                    }),
                Tables\Filters\SelectFilter::make('document_type')->native(false)
                    ->label(__('general.document'))
                    ->options([
                        \App\Models\StudentTransaction::class => __('general.student_transaction'),
                        \App\Models\StaffTransaction::class => __('general.staff_transaction'),
                        \App\Models\Expense::class => __('general.expense'),
                        \App\Models\StockMovement::class => __('general.stock_movement'),
                        \App\Models\SupplierTransaction::class => __('general.supplier_payment'),
                        \App\Models\OtherPeopleTransaction::class => __('general.other_people'),
                        \App\Models\Transfer::class => __('general.transfer'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('void')
                    ->label(__('general.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.void'))
                    ->modalDescription(__('general.must_provide_void_reason'))
                    ->form([
                        TextInput::make('void_reason')->label(__('general.void_reason'))->required()->maxLength(255),
                    ])
                    ->action(function (JournalEntry $record, array $data): void {
                        \App\Services\JournalService::reverseIfVoidable($record, $data['void_reason']);

                        \App\Models\AuditLog::log(
                            'journal.voided',
                            $record->getMorphClass(),
                            $record->id,
                            ['reason' => $data['void_reason']],
                        );
                    })
                    ->visible(fn (?JournalEntry $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }
}
