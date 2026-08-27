<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\Certificate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.certificates');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('certificate_no')
                    ->label(__('general.certificate_no'))
                    ->weight('bold'),
                TextColumn::make('program.name')
                    ->label(__('general.program'))
                    ->placeholder('—'),
                TextColumn::make('issue_date')
                    ->label(__('general.issue_date'))
                    ->date('d/m/Y'),
                TextColumn::make('verification_code')
                    ->label(__('general.verification_code'))
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.certificate_status_{$state}"))
                    ->color(fn (string $state): string => $state === 'voided' ? 'danger' : 'success'),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label(__('general.print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Certificate $record): string => route('certificates.register.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (?Certificate $record): bool => ! $record?->isVoided()),
            ])
            ->headerActions([])
            ->emptyStateHeading(__('general.certificates_empty'))
            ->emptyStateDescription(__('general.certificates_empty_hint_link'));
    }
}