<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Pages\Reports\AccountStatement;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Services\ReportService;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    /** Control account code => subsidiary (party) ledger the statement opens. */
    private const CONTROL_PARTY = [
        '1410' => 'student',
        '1420' => 'staff',
        '2110' => 'supplier',
        '1430' => 'other',
    ];

    public function infolist(Infolist $infolist): Infolist
    {
        $subsidiary = app(ReportService::class)->controlAccountBalance($this->record);

        return $infolist
            ->schema([
                TextEntry::make('code')->label(__('general.account_code')),
                TextEntry::make('name')->label(__('general.account')),
                TextEntry::make('type')
                    ->label(__('general.account_type'))
                    ->formatStateUsing(fn (string $state): string => __("general.account_type_{$state}")),
                TextEntry::make('parent.name')->label(__('general.parent_account'))->placeholder('—'),
                TextEntry::make('balance')
                    ->label($subsidiary !== null ? __('general.subsidiary_balance') : __('general.balance'))
                    ->state(\App\Helpers\MoneyFormatter::formatAccountBalance((float) ($subsidiary ?? self::journalBalance($this->record)), $this->record->type)),
                TextEntry::make('description')->label(__('general.description'))->placeholder('—'),
                IconEntry::make('is_active')->label(__('general.active'))->boolean(),
                IconEntry::make('is_system')->label(__('general.system'))->boolean(),
            ])
            ->columns(2);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\EditAction::make(),
            Actions\Action::make('statement')
                ->label(__('general.account_statement'))
                ->icon('heroicon-o-chart-bar')
                ->url(fn (): string => AccountStatement::getUrl(['account_id' => $this->record->id, 'to' => now()->format('Y-m-d')])),
        ];

        $partyType = self::CONTROL_PARTY[$this->record->code] ?? null;
        if ($partyType !== null) {
            $actions[] = Actions\Action::make('party_statement')
                ->label(__("general.party_statement_{$partyType}"))
                ->icon('heroicon-o-users')
                ->url(fn (): string => AccountStatement::getUrl([
                    'party_type' => $partyType,
                    'to' => now()->format('Y-m-d'),
                ]));
        }

        return $actions;
    }

    private static function journalBalance(Account $account): float
    {
        $totals = app(ReportService::class)->accountTotals();
        $total = $totals[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];

        return in_array($account->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true)
            ? (float) $total['debit'] - (float) $total['credit']
            : (float) $total['credit'] - (float) $total['debit'];
    }
}