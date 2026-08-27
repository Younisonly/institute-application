<?php

namespace App\Filament\Forms\Components;

use App\Models\Bank;
use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

class PaymentDetails
{
    /**
     * Reusable method + bank/wallet + transaction reference fields.
     * Method field name is parameterized because expenses use `payment_method`.
     */
    public static function fields(string $methodName = 'method', string $bankName = 'bank_id', string $walletName = 'wallet_id', string $refName = 'transaction_ref'): array
    {
        return [
            Select::make($methodName)->native(false)
                ->label(__('general.payment_method'))
                ->options([
                    'cash' => __('general.cash'),
                    'bank' => __('general.bank'),
                    'wallet' => __('general.wallet'),
                    'cheque' => __('general.cheque'),
                    'other' => __('general.method_other'),
                ])
                ->default('cash')
                ->live()
                ->required(),
            Select::make($bankName)->native(false)
                ->label(__('general.bank'))
                ->options(fn (): array => Bank::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => in_array($get($methodName), ['bank', 'transfer', 'cheque'], true))
                ->visible(fn (Get $get): bool => in_array($get($methodName), ['bank', 'transfer', 'cheque'], true)),
            Select::make($walletName)->native(false)
                ->label(__('general.wallet'))
                ->options(fn (): array => Wallet::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => $get($methodName) === 'wallet')
                ->visible(fn (Get $get): bool => $get($methodName) === 'wallet'),
            TextInput::make($refName)
                ->label(__('general.transaction_ref'))
                ->placeholder(__('general.transaction_ref_hint'))
                ->maxLength(100)
                ->visible(fn (Get $get): bool => in_array($get($methodName), ['bank', 'transfer', 'wallet', 'cheque'], true)),
        ];
    }
}
