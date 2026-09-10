<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\TeachingSessionResource\Pages;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Staff;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeachingSessionResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return [];
    }

    protected static function editRoles(): array
    {
        return [];
    }

    protected static function deleteRoles(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    protected static ?string $model = CourseBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.teaching_sessions');
    }

    public static function getModelLabel(): string
    {
        return __('general.teaching_session');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.teaching_sessions');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.course_batch'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('course.name')
                    ->label(__('general.course'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teacher.name')
                    ->label(__('general.primary_teacher'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('start_date')
                    ->label(__('general.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('general.end_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.batch_status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'in_progress', 'active' => 'success',
                        'completed' => 'info',
                        'scheduled', 'open' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('teaching_sessions_count')
                    ->label(__('general.number_of_sessions'))
                    ->counts('teachingSessions')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('total_actual_hours')
                    ->label(__('general.total_taught_hours'))
                    ->state(fn (CourseBatch $record): string => number_format((float) $record->teachingSessions()->sum('actual_hours'), 1).' '.__('general.hours_short'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->withSum('teachingSessions as total_hours', 'actual_hours')->orderBy('total_hours', $direction)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->label(__('general.course'))
                    ->options(Course::query()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('teacher_id')
                    ->label(__('general.primary_teacher'))
                    ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'draft' => __('general.batch_status_draft'),
                        'open' => __('general.batch_status_open'),
                        'in_progress' => __('general.batch_status_in_progress'),
                        'completed' => __('general.batch_status_completed'),
                        'cancelled' => __('general.batch_status_cancelled'),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_sessions')
                    ->label(__('general.sessions_detail'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->modalHeading(fn (CourseBatch $record): string => __('general.sessions_report').' - '.$record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('general.close'))
                    ->infolist([
                        InfoSection::make()
                            ->schema([
                                ViewEntry::make('sessions_modal')
                                    ->view('filament.resources.teaching-sessions.batch-sessions-modal'),
                            ]),
                    ]),
                Tables\Actions\Action::make('print_report')
                    ->label(__('general.print_report'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (CourseBatch $record): string => route('reports.teaching-sessions.batch.print', ['batch' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachingSessions::route('/'),
        ];
    }
}
