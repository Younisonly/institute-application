<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use App\Models\StaffDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.documents');
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    protected function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    protected function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    private static function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('file_path')
                    ->disk('public')
                    ->label(__('general.preview'))
                    ->height(40)
                    ->width(40)
                    ->square()
                    ->visible(fn (?StaffDocument $record): bool => $record !== null && static::isImage($record->file_path)),
                IconColumn::make('file_path')
                    ->label(__('general.document_file'))
                    ->icon(fn (string $state): string => static::isImage($state)
                        ? 'heroicon-o-photo'
                        : 'heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (?StaffDocument $record): bool => $record !== null && ! static::isImage($record->file_path)),
                TextColumn::make('file_path')
                    ->label(__('general.document_file'))
                    ->formatStateUsing(fn (string $state): string => basename($state))
                    ->url(fn (?StaffDocument $record): string => $record !== null
                        ? Storage::disk('public')->url($record->file_path)
                        : '#')
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->limit(40),
                TextColumn::make('label')->label(__('general.label'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('deleted_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.deleted') : '')
                    ->color('gray')
                    ->visible(fn (?StaffDocument $record): bool => $record?->trashed() ?? false),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('general.upload_document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading(__('general.upload_document'))
                    ->modalDescription(__('general.upload_document_hint'))
                    ->form([
                        TextInput::make('label')->label(__('general.label'))->maxLength(255),
                        FileUpload::make('file_path')
                            ->label(__('general.document_file'))
                            ->required()
                            ->directory('staff-documents')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/png',
                                'image/jpeg',
                                'image/webp',
                            ])
                            ->maxSize(10240)
                            ->previewable()
                            ->openable(),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('general.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (?StaffDocument $record): string => $record !== null
                        ? Storage::disk('public')->url($record->file_path)
                        : '#')
                    ->openUrlInNewTab()
                    ->visible(fn (?StaffDocument $record): bool => ! ($record?->trashed() ?? true)),
                Tables\Actions\Action::make('download')
                    ->label(__('general.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (?StaffDocument $record): string => $record !== null
                        ? route('staff-documents.download', $record)
                        : '#')
                    ->visible(fn (?StaffDocument $record): bool => ! ($record?->trashed() ?? true)),
                Tables\Actions\DeleteAction::make()
                    ->label(__('general.delete'))
                    ->visible(fn (?StaffDocument $record): bool => ! ($record?->trashed() ?? true)),
                Tables\Actions\RestoreAction::make()
                    ->label(__('general.restore'))
                    ->visible(fn (?StaffDocument $record): bool => $record?->trashed() ?? false),
                Tables\Actions\ForceDeleteAction::make()
                    ->label(__('general.force_delete'))
                    ->requiresConfirmation()
                    ->visible(fn (?StaffDocument $record): bool => $record?->trashed() ?? false)
                    ->action(function (StaffDocument $record): void {
                        Storage::disk('public')->delete($record->file_path);
                        $record->forceDelete();
                        Notification::make()->title(__('general.deleted'))->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label(__('general.delete')),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label(__('general.force_delete'))
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (StaffDocument $record): void {
                                Storage::disk('public')->delete($record->file_path);
                                $record->forceDelete();
                            });
                            Notification::make()->title(__('general.deleted'))->success()->send();
                        }),
                ]),
            ]);
    }
}
