<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Filament\Resources\CourseBatchResource;
use App\Models\CourseBatch;
use App\Models\Registration;
use App\Services\RegistrationService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $recordTitleAttribute = 'name';

    private function syncPeriod(?CourseBatch $record, array $data): void
    {
        if ($record === null) {
            return;
        }

        $periodId = $data['periods'] ?? null;

        if ($periodId === null || $periodId === '') {
            return;
        }

        $record->periods()->sync([(int) $periodId]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.batches');
    }

    protected function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema(CourseBatchResource::fields(false, $this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.batch_name'))
                    ->searchable()
                    ->weight('semibold')
                    ->wrap(),
                TextColumn::make('year')->label(__('general.batch_year'))->badge()->color('info')->placeholder('—'),
                TextColumn::make('lifecycle_status')
                    ->label(__('general.enrollment_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.lifecycle_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        CourseBatch::LIFECYCLE_OPEN     => 'success',
                        CourseBatch::LIFECYCLE_RUNNING  => 'info',
                        CourseBatch::LIFECYCLE_FULL     => 'warning',
                        CourseBatch::LIFECYCLE_FINISHED => 'danger',
                        default                         => 'gray',
                    }),
                TextColumn::make('periods.name')
                    ->label(__('general.periods'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('start_date')->label(__('general.course_start_month'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('end_date')->label(__('general.course_end_month'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('registrations_count')
                    ->label(__('general.students'))
                    ->counts('registrations')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('general.create_batch'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['course_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    })
                    ->after(function ($record, array $data): void {
                        $this->syncPeriod($record, $data);

                        Notification::make()->title(__('general.batch_created'))->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record, array $data): void {
                        $this->syncPeriod($record, $data);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}