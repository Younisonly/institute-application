<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\CertificateResource\Pages;
use App\Models\Certificate;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CertificateResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $model = Certificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.certificate_register');
    }

    public static function getModelLabel(): string
    {
        return __('general.certificate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.certificates');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('certificate_no')
                    ->label(__('general.certificate_no'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('student.name')
                    ->label(__('general.student'))
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('program.name')
                    ->label(__('general.program'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('title_en')
                    ->label(__('general.certificate_title'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issue_date')
                    ->label(__('general.issue_date'))
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.certificate_status_{$state}"))
                    ->color(fn (string $state): string => $state === 'voided' ? 'danger' : 'success'),
                TextColumn::make('verification_code')
                    ->label(__('general.verification_code'))
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('issuedBy.name')
                    ->label(__('general.issued_by'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'issued' => __('general.certificate_status_issued'),
                        'voided' => __('general.certificate_status_voided'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('general.delete'))
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading(__('general.certificates_empty'))
            ->emptyStateDescription(__('general.certificates_empty_hint'));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.certificate_details'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('certificate_no')
                            ->label(__('general.certificate_no')),
                        TextEntry::make('verification_code')
                            ->label(__('general.verification_code'))
                            ->color('primary'),
                        TextEntry::make('status')
                            ->label(__('general.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("general.certificate_status_{$state}")),
                        TextEntry::make('student.name')
                            ->label(__('general.student')),
                        TextEntry::make('program.name')
                            ->label(__('general.program')),
                        TextEntry::make('title_ar')
                            ->label(__('general.certificate_title')),
                        TextEntry::make('issue_date')
                            ->label(__('general.issue_date'))
                            ->date('d/m/Y'),
                        TextEntry::make('completion_date')
                            ->label(__('general.completion_date'))
                            ->date('d/m/Y'),
                        TextEntry::make('issuedBy.name')
                            ->label(__('general.issued_by'))
                            ->placeholder('—'),
                        TextEntry::make('earned_courses_count')
                            ->label(__('general.earned_courses'))
                            ->state(fn (Certificate $record): int => count($record->earned_courses ?? [])),
                        TextEntry::make('void_reason')
                            ->label(__('general.void_reason'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificates::route('/'),
            'view' => Pages\ViewCertificate::route('/{record}'),
        ];
    }
}