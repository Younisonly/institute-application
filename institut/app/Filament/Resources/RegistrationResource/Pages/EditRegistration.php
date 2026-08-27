<?php

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use App\Models\Registration;
use Filament\Actions;
use Filament\Forms\Components\Section;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.price_snapshot'))
                    ->description(fn (): string => $this->currentBalance() != 0
                        ? __('general.price_snapshot_locked')
                        : '')
                    ->schema([
                        MoneyInput::make('price_snapshot')
                            ->label(__('general.price_snapshot'))
                            ->required()
                            ->minValue(0)
                            ->suffix(__('general.currency'))
                            ->disabled(fn (): bool => $this->currentBalance() != 0),
                    ]),
                Section::make(__('general.notes'))
                    ->schema([
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label(__('general.registration_notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private function currentBalance(): float
    {
        return (float) Registration::query()
            ->withTotals()
            ->findOrFail($this->record->getKey())
            ->balance;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->currentBalance() != 0) {
            $data['price_snapshot'] = $this->record->price_snapshot;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return RegistrationResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
