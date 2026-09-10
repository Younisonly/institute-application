<?php

namespace App\Filament\Widgets;

use App\Models\Certificate;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringCertificatesWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return __('general.expiring_certificates');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Certificate::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->where('status', 'issued')
                    ->with(['student', 'program'])
                    ->orderBy('expires_at', 'asc')
            )
            ->columns([
                TextColumn::make('certificate_no')->label(__('general.certificate_no'))->weight('bold'),
                TextColumn::make('student.name')->label(__('general.student'))->weight('semibold'),
                TextColumn::make('program.name')->label(__('general.program'))->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label(__('general.expires_at'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Certificate $record): string => $record->expires_at->isPast() ? 'danger' : 'warning'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('general.view'))
                    ->url(fn (Certificate $record): string => \App\Filament\Resources\CertificateResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
