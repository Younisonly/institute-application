<?php

namespace App\Helpers;

class MoneyFormatter
{
    public static function formatStudentBalance(float $balance, bool $detailed = false): string
    {
        if ($balance > 0) {
            $tag = $detailed ? __('general.student_for_label') : __('general.on_short');
            return number_format($balance).' '.__('general.currency').' ('.$tag.')';
        }

        if ($balance < 0) {
            $tag = $detailed ? __('general.student_on_label') : __('general.for_short');
            return number_format(abs($balance)).' '.__('general.currency').' ('.$tag.')';
        }

        return '0 '.__('general.currency');
    }

    public static function formatSupplierBalance(float $balance, bool $detailed = false): string
    {
        if ($balance > 0) {
            $tag = $detailed ? __('general.supplier_on_label') : __('general.for_short');
            return number_format($balance).' '.__('general.currency').' ('.$tag.')';
        }

        if ($balance < 0) {
            $tag = $detailed ? __('general.supplier_for_label') : __('general.on_short');
            return number_format(abs($balance)).' '.__('general.currency').' ('.$tag.')';
        }

        return '0 '.__('general.currency');
    }

    public static function formatOtherPersonBalance(float $balance, bool $detailed = false): string
    {
        if ($balance > 0) {
            $tag = $detailed ? __('general.other_person_on_label') : __('general.for_short');
            return number_format($balance).' '.__('general.currency').' ('.$tag.')';
        }

        if ($balance < 0) {
            $tag = $detailed ? __('general.other_person_for_label') : __('general.on_short');
            return number_format(abs($balance)).' '.__('general.currency').' ('.$tag.')';
        }

        return '0 '.__('general.currency');
    }

    public static function formatStaffAdvanceBalance(float $balance, bool $detailed = false): string
    {
        if ($balance > 0) {
            $tag = $detailed ? __('general.staff_advance_for_label') : __('general.on_short');
            return number_format($balance).' '.__('general.currency').' ('.$tag.')';
        }

        if ($balance < 0) {
            $tag = $detailed ? __('general.staff_advance_on_label') : __('general.for_short');
            return number_format(abs($balance)).' '.__('general.currency').' ('.$tag.')';
        }

        return '0 '.__('general.currency');
    }

    public static function formatAccountBalance(float $balance, string $type = 'asset'): string
    {
        if ($balance == 0) {
            return '0 '.__('general.currency');
        }

        $isDebitSide = in_array($type, ['asset', 'expense'], true);

        if ($balance > 0) {
            $label = $isDebitSide ? __('general.debit_short') : __('general.credit_short');
            return number_format($balance).' '.__('general.currency').' ('.$label.')';
        }

        $label = $isDebitSide ? __('general.credit_short') : __('general.debit_short');
        return number_format(abs($balance)).' '.__('general.currency').' ('.$label.')';
    }
}
