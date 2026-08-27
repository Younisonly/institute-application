<?php

namespace App\Services;

use App\Models\FiscalYearClosing;
use App\Models\InstituteSetting;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalService
{
    /**
     * @throws ValidationException when the entry date falls in a closed fiscal year
     * @throws \RuntimeException when debits !== credits
     */
    public function post(array $lines, string $date, ?string $description = null, ?string $reference = null, ?string $documentType = null, ?int $documentId = null, ?int $userId = null): JournalEntry
    {
        $this->assertYearOpen((int) substr($date, 0, 4), $documentType);
        $this->assertPeriodOpen($date, $documentType);

        if (count($lines) < 2) {
            throw new \RuntimeException(__('general.journal_at_least_two_lines'));
        }

        $totalDebit = round((float) collect($lines)->sum('debit'), 2);
        $totalCredit = round((float) collect($lines)->sum('credit'), 2);
        if ($totalDebit !== $totalCredit) {
            throw new \RuntimeException(__('general.journal_not_balanced', ['debit' => $totalDebit, 'credit' => $totalCredit]));
        }

        return DB::transaction(function () use ($lines, $date, $description, $reference, $documentType, $documentId, $userId) {
            $entry = JournalEntry::query()->create([
                'entry_no' => $this->nextEntryNo(),
                'date' => $date,
                'description' => $description,
                'reference' => $reference,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'created_by' => $userId ?? auth()->id(),
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'party_type' => $line['party_type'] ?? null,
                    'party_id' => $line['party_id'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            if ($documentType !== null && $documentId !== null) {
                $document = $documentType::find($documentId);
                if ($document !== null && ! $document->isVoided()) {
                    $document->update(['journal_entry_id' => $entry->id]);
                }
            }

            return $entry;
        });
    }

    /**
     * Reverse a journal entry: create an exact reversal, mark the original voided.
     */
    public function reverse(JournalEntry $entry, string $reason, ?int $userId = null): JournalEntry
    {
        if ($entry->isVoided()) {
            return $entry;
        }

        $this->assertYearOpen((int) substr($entry->date, 0, 4), $entry->document_type);

        return DB::transaction(function () use ($entry, $reason, $userId) {
            $reversal = JournalEntry::query()->create([
                'entry_no' => $this->nextEntryNo(),
                'date' => now()->toDateString(),
                'description' => __('general.reverse_of').' #'.$entry->entry_no,
                'reference' => $entry->reference,
                'document_type' => $entry->document_type,
                'document_id' => $entry->document_id,
                'created_by' => $userId ?? auth()->id(),
                'voided_at' => now(),
                'void_reason' => __('general.reverse_of').' #'.$entry->entry_no,
            ]);

            foreach ($entry->lines as $line) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                    'notes' => $line->notes,
                ]);
            }

            $entry->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'reversed_entry_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }

    public function nextEntryNo(): int
    {
        $setting = InstituteSetting::query()->lockForUpdate()->firstOrFail();
        $next = $setting->journal_next_no;
        $setting->update(['journal_next_no' => $next + 1]);

        return $next;
    }

    /**
     * Fiscal-year lock: no journal entry may be posted or reversed into a closed year.
     * Closing entries and their reversals bypass the lock (they ARE the closing action).
     */
    private function assertYearOpen(int $year, ?string $documentType = null): void
    {
        if ($documentType === FiscalYearClosing::class) {
            return;
        }

        if (FiscalYearClosing::query()->where('year', $year)->exists()) {
            throw ValidationException::withMessages([
                'date' => __('general.year_closed', ['year' => $year]),
            ]);
        }
    }

    private function assertPeriodOpen(string $date, ?string $documentType = null): void
    {
        if ($documentType === FiscalYearClosing::class) {
            return;
        }

        $lockDate = InstituteSetting::current()->financial_lock_date;
        if ($lockDate !== null && $date < $lockDate->toDateString()) {
            throw ValidationException::withMessages([
                'date' => __('general.period_closed_before_lock_date', ['date' => $lockDate->format('d/m/Y')]),
            ]);
        }
    }

    /**
     * Void a journal entry from the UI: reverse it and void the source document
     * if it is still linked and not already voided.
     */
    public static function reverseIfVoidable(\App\Models\JournalEntry $entry, string $reason): void
    {
        if ($entry->isVoided()) {
            return;
        }

        app(self::class)->reverse($entry, $reason);

        $document = $entry->document;
        if ($document !== null && method_exists($document, 'void') && ! $document->isVoided()) {
            $document->void($reason);
        }
    }
}
