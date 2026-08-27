<?php

namespace App\Services;

class MoneyWordsService
{
    private const UNITS = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];

    private const TENS = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مئة', 'مئتان', 'ثلاثمئة', 'أربعمئة', 'خمسمئة', 'ستمئة', 'سبعمئة', 'ثمانمئة', 'تسعمئة'];

    public function toArabicWords(float|int $amount): string
    {
        if ($amount == 0) {
            return 'صفر';
        }

        if ((int) floor(abs($amount)) > 999999999999) {
            return __('general.number_too_large');
        }

        $isNegative = $amount < 0;
        $amount = abs($amount);

        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        $words = $this->convertWhole((int) $whole);

        if ($fraction > 0) {
            $words .= ' و'.$this->convertWhole($fraction).' قرشًا';
        }

        return $isNegative ? 'سالب '.$words : $words;
    }

    public function toArabicRials(float|int $amount, string $currency = 'ريال'): string
    {
        if ($amount == 0) {
            return 'صفر '.$currency;
        }

        if ((int) floor(abs($amount)) > 999999999999) {
            return __('general.number_too_large');
        }

        $isNegative = $amount < 0;
        $amount = abs($amount);

        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        $words = $this->convertWhole($whole);
        $words .= $whole >= 3 && $whole <= 10 ? ' '.$currency.'ات' : ' '.$currency;

        if ($fraction > 0) {
            $words .= ' و'.$this->convertWhole($fraction).' قرشًا';
        }

        return $isNegative ? 'سالب '.$words : $words;
    }

    private function convertWhole(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        if ($number < 0) {
            return 'سالب '.$this->convertWhole(abs($number));
        }

        $billions = intdiv($number, 1000000000);
        $remaining = $number % 1000000000;

        $millions = intdiv($remaining, 1000000);
        $remaining %= 1000000;

        $thousands = intdiv($remaining, 1000);
        $remaining %= 1000;

        $parts = [];

        if ($billions > 0) {
            $parts[] = $this->convertGroupWithNoun($billions, 'مليار', 'ملياران', 'مليارات', 'مليارًا');
        }

        if ($millions > 0) {
            $parts[] = $this->convertGroupWithNoun($millions, 'مليون', 'مليونان', 'ملايين', 'مليونًا');
        }

        if ($thousands > 0) {
            $parts[] = $this->convertGroupWithNoun($thousands, 'ألف', 'ألفان', 'آلاف', 'ألفًا');
        }

        if ($remaining > 0) {
            $parts[] = $this->convertHundreds($remaining);
        }

        return implode(' و', $parts);
    }

    private function convertGroupWithNoun(int $group, string $one, string $two, string $threeToTen, string $elevenPlus): string
    {
        if ($group === 1) {
            return $one;
        }

        if ($group === 2) {
            return $two;
        }

        if ($group === 200) {
            return 'مئتا '.$one;
        }

        $words = $this->convertHundreds($group);

        if ($group >= 3 && $group <= 10) {
            return $words.' '.$threeToTen;
        }

        if ($group < 100) {
            return $words.' '.$elevenPlus;
        }

        return $words.' '.$one;
    }

    private function convertHundreds(int $number): string
    {
        if ($number < 0 || $number > 999) {
            throw new \InvalidArgumentException(__('money_words.hundreds_out_of_range', ['number' => $number]));
        }

        if ($number === 0) {
            return '';
        }

        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        $hundredsWords = $hundreds > 0 ? self::HUNDREDS[$hundreds] : '';

        if ($rest === 0) {
            return $hundredsWords;
        }

        $restWords = $this->convertTensAndUnits($rest);

        if ($hundredsWords === '') {
            return $restWords;
        }

        return trim($hundredsWords.' و'.$restWords);
    }

    private function convertTensAndUnits(int $number): string
    {
        if ($number < 0 || $number > 99) {
            throw new \InvalidArgumentException(__('money_words.tens_and_units_out_of_range', ['number' => $number]));
        }

        if ($number === 0) {
            return '';
        }

        if ($number === 1) {
            return self::UNITS[1];
        }

        if ($number === 2) {
            return self::UNITS[2];
        }

        if ($number <= 10) {
            return $number === 10 ? self::TENS[1] : self::UNITS[$number];
        }

        if ($number <= 19) {
            return match ($number) {
                11 => 'أحد عشر',
                12 => 'اثنا عشر',
                default => self::UNITS[$number % 10].' عشر',
            };
        }

        $tens = intdiv($number, 10);
        $units = $number % 10;

        if ($units === 0) {
            return self::TENS[$tens];
        }

        return self::UNITS[$units].' و'.self::TENS[$tens];
    }
}
