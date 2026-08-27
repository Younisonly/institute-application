<?php

namespace App\Services;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\OtherPersonResource;
use App\Filament\Resources\StaffResource;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\TransferResource;
use App\Models\Expense;
use App\Models\OtherPeopleTransaction;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Models\Transfer;

/**
 * Maps a journal entry's source document (morph) to its Filament page URL so
 * statements/ledgers/journal views can navigate to the originating business
 * document (and back) instead of dead-ending.
 */
class JournalDocumentLinker
{
    public function urlFor(?string $documentType, ?int $documentId): ?string
    {
        if ($documentType === null || $documentId === null) {
            return null;
        }

        return match ($documentType) {
            StudentTransaction::class => $this->studentTransactionUrl($documentId),
            StaffTransaction::class => $this->staffTransactionUrl($documentId),
            StockMovement::class => $this->stockMovementUrl($documentId),
            Expense::class => ExpenseResource::getUrl('view', ['record' => $documentId]),
            SupplierTransaction::class => SupplierResource::getUrl('view', ['record' => $this->supplierFor($documentId)]),
            OtherPeopleTransaction::class => OtherPersonResource::getUrl('view', ['record' => $this->personFor($documentId)]),
            Transfer::class => TransferResource::getUrl('view', ['record' => $documentId]),
            default => null,
        };
    }

    private function studentTransactionUrl(int $id): ?string
    {
        $transaction = StudentTransaction::query()->find($id);

        return $transaction?->student_id !== null
            ? StudentResource::getUrl('view', ['record' => $transaction->student_id])
            : null;
    }

    private function staffTransactionUrl(int $id): ?string
    {
        $transaction = StaffTransaction::query()->find($id);

        return $transaction?->staff_id !== null
            ? StaffResource::getUrl('view', ['record' => $transaction->staff_id])
            : null;
    }

    private function stockMovementUrl(int $id): ?string
    {
        $movement = StockMovement::query()->find($id);

        if ($movement === null) {
            return null;
        }

        return match (true) {
            $movement->book_id !== null => BookResource::getUrl('view', ['record' => $movement->book_id]),
            $movement->item_id !== null => ItemResource::getUrl('view', ['record' => $movement->item_id]),
            default => null,
        };
    }

    private function supplierFor(int $transactionId): ?int
    {
        return SupplierTransaction::query()->find($transactionId)?->supplier_id;
    }

    private function personFor(int $transactionId): ?int
    {
        return OtherPeopleTransaction::query()->find($transactionId)?->other_person_id;
    }
}