<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingResultsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false;
    }

    protected function getTableHeading(): string
    {
        return __('general.pending_results');
    }

    protected function getTableQuery(): Builder
    {
        return Registration::query()
            ->with(['student', 'course', 'batch'])
            ->whereIn('status', ['active', 'suspended'])
            ->where('result', 'pending')
            ->whereHas('batch', fn (Builder $q): Builder => $q->whereNotNull('finished_at'))
            ->orderBy('student_id');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('student.name')
                ->label(__('general.student_name'))
                ->weight('semibold')
                ->searchable(),
            TextColumn::make('course.name')
                ->label(__('general.course')),
            TextColumn::make('batch.name')
                ->label(__('general.batch_name'))
                ->placeholder('—'),
            TextColumn::make('result')
                ->label(__('general.result'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                ->color(fn (string $state): string => match ($state) {
                    'passed' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),
        ];
    }
}
