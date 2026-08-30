<?php

namespace App\Filament\Forms\Components;

use App\Models\Bank;
use App\Models\Cashbox;
use App\Models\Wallet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Auth;

class PaymentDetails
{
    /**
     * Reusable method + cashbox/bank/wallet + transaction reference fields.
     * Method field name is parameterized because expenses use `payment_method`.
     */
    public static function fields(
        string $methodName = 'method',
        string $bankName = 'bank_id',
        string $walletName = 'wallet_id',
        string $refName = 'transaction_ref',
        string $cashboxName = 'cashbox_id'
    ): array {
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
            Select::make($cashboxName)->native(false)
                ->label(__('general.cashbox'))
                ->options(fn (): array => Cashbox::query()->where('is_active', true)->get()->mapWithKeys(fn (Cashbox $c) => [$c->id => $c->name])->all())
                ->default(function () {
                    $user = Auth::user();
                    if (! $user) {
                        return Cashbox::query()->where('is_default', true)->value('id');
                    }

                    $activeShiftCashboxId = \App\Models\CashboxShift::query()
                        ->where('user_id', $user->id)
                        ->where('status', \App\Models\CashboxShift::STATUS_OPEN)
                        ->value('cashbox_id');

                    if ($activeShiftCashboxId) {
                        return $activeShiftCashboxId;
                    }

                    if ($user->default_cashbox_id) {
                        return $user->default_cashbox_id;
                    }

                    return Cashbox::query()->where('is_default', true)->value('id') ?? Cashbox::query()->where('is_active', true)->value('id');
                })
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get($methodName) === 'cash' && Cashbox::query()->where('is_active', true)->exists()),
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
