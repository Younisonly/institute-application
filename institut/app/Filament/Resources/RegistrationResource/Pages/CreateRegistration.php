<?php

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\RegistrationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    public function mount(): void
    {
        parent::mount();

        if ($studentId = request('student_id')) {
            $this->form->fill(['student_id' => (int) $studentId]);
        }
    }

    protected function handleRecordCreation(array $data): Registration
    {
        $override = (bool) ($data['eligibility_override'] ?? false);
        $reason = isset($data['override_reason']) && trim((string) $data['override_reason']) !== ''
            ? trim((string) $data['override_reason'])
            : null;

        if ($override && $reason === null) {
            throw ValidationException::withMessages([
                'data.override_reason' => __('general.override_reason_required'),
            ]);
        }

        return app(RegistrationService::class)->register(
            $data,
            (int) Auth::id(),
            $override,
            $reason,
        );
    }

    protected function afterCreate(): void
    {
        $paid = (float) ($this->data['payment_amount'] ?? 0);
        $price = (float) ($this->data['price_snapshot'] ?? 0);

        if ($paid > 0 && $price > 0 && $paid < $price) {
            Notification::make()
                ->title(__('general.warning'))
                ->body(__('general.payment_less_than_price'))
                ->warning()
                ->send();
        }
    }

    public function hydrate(): void
    {
        $this->pruneRepeaterRows();
    }

    public function updated(string $path, mixed $value): void
    {
        if (str_starts_with($path, 'data.items') || str_starts_with($path, 'data.books')) {
            $this->pruneRepeaterRows();
        }
    }

    private function pruneRepeaterRows(): void
    {
        foreach (['items', 'books'] as $field) {
            if (! is_array($this->data[$field] ?? null)) {
                continue;
            }

            $this->data[$field] = collect($this->data[$field])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->all();
        }
    }

    protected function getRedirectUrl(): string
    {
        return RegistrationResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('general.cancel'))
                ->url(RegistrationResource::getUrl('index')),
        ];
    }
}
