<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewStaff extends ViewRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make()->label(__('general.restore')),
            Actions\ForceDeleteAction::make()
                ->label(__('general.force_delete'))
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.profile'))
                    ->columns(6)
                    ->schema([
                        ImageEntry::make('photo_path')
                            ->label('')
                            ->circular()
                            ->height(88)
                            ->width(88)
                            ->placeholder(__('general.no_photo'))
                            ->columnSpan(1),
                        TextEntry::make('name')
                            ->label(__('general.name'))
                            ->size(TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->columnSpan(2),
                        TextEntry::make('jobTitle.name')
                            ->label(__('general.job_title'))
                            ->badge()
                            ->color('info')
                            ->columnSpan(1),
                        TextEntry::make('status')
                            ->label(__('general.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
                            ->columnSpan(1),
                        TextEntry::make('created_at')
                            ->label(__('general.joined'))
                            ->icon('heroicon-m-calendar')
                            ->color('gray')
                            ->since()
                            ->columnSpan(1),
                    ]),
                Section::make(__('general.salary'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('salary_type')
                            ->label(__('general.salary_type'))
                            ->badge()
                            ->color('primary')
                            ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                        TextEntry::make('salary_value')
                            ->label(__('general.salary_value'))
                            ->formatStateUsing(fn (string $state, $record): string => $record->salary_type === 'percentage'
                                ? ($record->percentage_value !== null ? number_format((float) $record->percentage_value, 0) . '%' : '—')
                                : number_format((float) $state) . ' ' . __('general.currency')),
                        TextEntry::make('contract_no')->label(__('general.contract_no'))->placeholder('—'),
                    ]),
                Section::make(__('general.contact'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')->label(__('general.phone'))->icon('heroicon-m-phone')->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('general.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
