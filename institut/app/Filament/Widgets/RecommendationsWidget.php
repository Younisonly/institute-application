<?php

namespace App\Filament\Widgets;

use App\Models\ProgramCourse;
use App\Services\ProgressionService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Student;

class RecommendationsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
    public ?Student $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return __('general.recommendations');
    }

    protected function recommendations(): array
    {
        return $this->record instanceof Student && $this->record->exists
            ? app(ProgressionService::class)->recommend((int) $this->record->id)
            : [];
    }

    protected function getTableQuery(): Builder
    {
        $ids = collect($this->recommendations())->map(fn (array $r): int => $r['entry_id'])->all();

        if ($ids === []) {
            return ProgramCourse::query()->whereRaw('1 = 0');
        }

        $order = implode(',', $ids);

        return ProgramCourse::query()
            ->with('course')
            ->whereIn('id', $ids)
            ->orderByRaw("FIELD(id, {$order})");
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('course.name')
                ->label(__('general.course'))
                ->weight('semibold'),
            TextColumn::make('level_no')
                ->label(__('general.level_no'))
                ->badge()
                ->color('info'),
            TextColumn::make('course.description')
                ->label(__('general.description'))
                ->limit(40)
                ->placeholder('—'),
            TextColumn::make('credit_hours')
                ->label(__('general.credit_hours'))
                ->placeholder('—'),
            TextColumn::make('course.id')
                ->label(__('general.status'))
                ->badge()
                ->formatStateUsing(fn (ProgramCourse $record): string => $this->status($record)['label'])
                ->color(fn (ProgramCourse $record): string => $this->status($record)['color'])
                ->tooltip(fn (ProgramCourse $record): ?string => $this->status($record)['tooltip']),
        ];
    }

    /** @return array{label: string, color: string, tooltip: ?string} */
    private function status(ProgramCourse $record): array
    {
        foreach ($this->recommendations() as $recommendation) {
            if ($recommendation['entry_id'] === (int) $record->id) {
                if ($recommendation['satisfied']) {
                    return [
                        'label' => __('general.recommendation_ready'),
                        'color' => 'success',
                        'tooltip' => __('general.recommendation_ready_hint'),
                    ];
                }

                return [
                    'label' => __('general.recommendation_blocked'),
                    'color' => 'danger',
                    'tooltip' => __('general.missing_prerequisites', ['courses' => implode(', ', $recommendation['missing'])]),
                ];
            }
        }

        return ['label' => '—', 'color' => 'gray', 'tooltip' => null];
    }
}