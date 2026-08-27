<?php

namespace App\Filament\Resources\JournalResource\Pages;

use App\Filament\Pages\Reports\AccountStatement;
use App\Filament\Resources\JournalResource;
use App\Models\JournalEntryLine;
use App\Services\JournalDocumentLinker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('entry_no')->label(__('general.entry_no')),
                TextEntry::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextEntry::make('description')->label(__('general.description'))->placeholder('—'),
                TextEntry::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextEntry::make('createdBy.name')->label(__('general.created_by'))->placeholder('—'),
                TextEntry::make('voided_at')->label(__('general.status'))->formatStateUsing(fn ($state) => $state ? __('general.voided') : __('general.active')),
                TextEntry::make('document')
                    ->label(__('general.source_document'))
                    ->formatStateUsing(function (object $record): string {
                        $linker = app(JournalDocumentLinker::class);
                        $url = $linker->urlFor($record->document_type, $record->document_id);

                        return $url !== null
                            ? __('general.open_document')
                            : ($record->document_type !== null
                                ? class_basename($record->document_type).' #'.$record->document_id
                                : '—');
                    })
                    ->url(function (object $record): ?string {
                        return app(JournalDocumentLinker::class)->urlFor($record->document_type, $record->document_id);
                    })
                    ->placeholder('—'),
                RepeatableEntry::make('lines')
                    ->label(__('general.lines'))
                    ->schema([
                        TextEntry::make('account.name')
                            ->label(__('general.account'))
                            ->url(fn (?JournalEntryLine $record): ?string => $record
                                ? AccountStatement::getUrl(['account_id' => $record->account_id, 'to' => now()->format('Y-m-d')])
                                : null),
                        TextEntry::make('debit')->label(__('general.debit'))->formatStateUsing(fn (string $state): string => (float) $state > 0 ? number_format((float) $state) : '—'),
                        TextEntry::make('credit')->label(__('general.credit'))->formatStateUsing(fn (string $state): string => (float) $state > 0 ? number_format((float) $state) : '—'),
                        TextEntry::make('party_name')
                            ->label(__('general.party'))
                            ->formatStateUsing(function (?JournalEntryLine $record): string {
                                if ($record === null || $record->party_id === null) {
                                    return '—';
                                }
                                $party = $record->party()->first();

                                return $party?->name ?? '—';
                            }),
                    ])
                    ->columns(4),
            ]);
    }
}