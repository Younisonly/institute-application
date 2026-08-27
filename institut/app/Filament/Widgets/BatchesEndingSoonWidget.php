<?php

namespace App\Filament\Widgets;

use App\Models\CourseBatch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BatchesEndingSoonWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false;
    }

    protected function getTableHeading(): string
    {
        return __('general.batches_ending_soon');
    }

    protected function getTableQuery(): Builder
    {
        $horizon = now()->addDays(60)->toDateString();

        return CourseBatch::query()
            ->with(['course', 'periods'])
            ->withCount('registrations')
            ->whereIn('status', ['scheduled', 'open', 'in_progress'])
            ->whereNull('finished_at')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $horizon)
            ->orderBy('end_date');
    }

    protected function getTableColumns(): array
    {
        $today = now()->startOfDay();

        return [
            TextColumn::make('name')
                ->label(__('general.batch_name'))
                ->weight('semibold'),
            TextColumn::make('course.name')
                ->label(__('general.course')),
            TextColumn::make('periods_label')
                ->label(__('general.periods'))
                ->badge()
                ->color('info')
                ->placeholder('—'),
            TextColumn::make('year')
                ->label(__('general.batch_year'))
                ->badge()
                ->color('info')
                ->placeholder('—'),
            TextColumn::make('status')
                ->label(__('general.batch_status'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => __("general.batch_status_{$state}"))
                ->color(fn (string $state): string => match ($state) {
                    'open'        => 'success',
                    'scheduled'   => 'info',
                    'in_progress' => 'warning',
                    default       => 'gray',
                }),
            TextColumn::make('end_date')
                ->label(__('general.batch_end_date'))
                ->date('d/m/Y')
                ->badge()
                ->color(function (CourseBatch $record) use ($today): string {
                    $daysLeft = (int) $today->diffInDays($record->end_date, false);

                    return $daysLeft <= 5 ? 'danger' : 'warning';
                }),
            TextColumn::make('days_remaining')
                ->label(__('general.batch_days_remaining_label'))
                ->getStateUsing(function (CourseBatch $record) use ($today): string {
                    $daysLeft = (int) $today->diffInDays($record->end_date, false);

                    if ($daysLeft < 0) {
                        return __('general.batch_overdue');
                    }

                    if ($daysLeft === 0) {
                        return __('general.batch_ends_today');
                    }

                    return __('general.batch_days_remaining', ['days' => $daysLeft]);
                })
                ->badge()
                ->color(function (CourseBatch $record) use ($today): string {
                    $daysLeft = (int) $today->diffInDays($record->end_date, false);

                    return $daysLeft <= 5 ? 'danger' : 'warning';
                }),
            TextColumn::make('registrations_count')
                ->label(__('general.students'))
                ->badge()
                ->color('gray'),
        ];
    }
}