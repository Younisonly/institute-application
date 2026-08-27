<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\DatePicker;

class MonthPicker extends DatePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->format('Y-m')
            ->displayFormat('m/Y')
            ->closeOnDateSelection();
    }
}