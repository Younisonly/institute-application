<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\JobTitleResource\Pages;
use App\Models\JobTitle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobTitleResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function createRoles(): array
    {
        return ['admin'];
    }

    protected static function editRoles(): array
    {
        return ['admin'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = JobTitle::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.job_titles');
    }

    public static function getModelLabel(): string
    {
        return __('general.job_title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.job_titles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->unique(ignoreRecord: true)->maxLength(255),
                \Filament\Forms\Components\Textarea::make('notes')
                    ->label(__('general.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('staff_count')
                    ->label(__('general.staff_count'))
                    ->counts('staff')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, JobTitle $record) {
                        if ($record->staff()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title(__('general.error'))
                                ->body(__('general.cannot_delete_job_title_in_use'))
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            if ($records->contains(fn ($record) => $record->staff()->exists())) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title(__('general.error'))
                                    ->body(__('general.cannot_delete_job_title_in_use'))
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobTitles::route('/'),
            'create' => Pages\CreateJobTitle::route('/create'),
            'edit' => Pages\EditJobTitle::route('/{record}/edit'),
        ];
    }
}
