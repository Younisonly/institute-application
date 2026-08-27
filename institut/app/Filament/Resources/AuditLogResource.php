<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;

class AuditLogResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin'];
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

    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.audit_log');
    }

    public static function getModelLabel(): string
    {
        return __('general.audit_log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.audit_log');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('general.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('general.name'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('action')
                    ->label(__('general.actions'))
                    ->formatStateUsing(function (string $state): string {
                        $key = 'general.' . $state;
                        return Lang::has($key) ? __($key) : $state;
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'created') => 'success',
                        str_contains($state, 'voided') => 'danger',
                        str_contains($state, 'suspended') => 'warning',
                        str_contains($state, 'closed') => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('entity_type')
                    ->label(__('general.type'))
                    ->formatStateUsing(function (?string $state): string {
                        if (!$state) return '—';
                        $base = class_basename($state);
                        $key = 'general.entity_' . $base;
                        return Lang::has($key) ? __($key) : $base;
                    })
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('entity_id')
                    ->label('#')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ip')
                    ->label(__('general.ip'))
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('user_id')->native(false)
                    ->label(__('general.name'))
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->searchable(),
                Tables\Filters\Filter::make('date')
                    ->label(__('general.date'))
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('general.from'))
                            ->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('general.to'))
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->where('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->where('created_at', '<=', $data['until'].' 23:59:59'));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
