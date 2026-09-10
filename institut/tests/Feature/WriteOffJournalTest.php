<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Course;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for P0-1 write-off journal entry.
 *
 * Verifies that closing/withdrawing a registration with write_off=true
 * creates a balanced journal entry:
 *   DR 5150 (Debt Write-Off Expense) / CR 4100 (Course Fees Income)
 * And that voiding the write_off transaction creates a reversing entry.
 */
class WriteOffJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    private function makeRegistration(int $paymentAmount = 0): Registration
    {
        $type   = ProgramType::create(['name' => 'Short', 'months_count' => 3]);
        $course = Course::create([
            'name'            => 'Test Course',
            'program_type_id' => $type->id,
            'months'          => 3,
            'price'           => 30000,
            'is_active'       => true,
        ]);
        $student = Student::create(['name' => 'Test Student', 'status' => 'active']);

        return app(RegistrationService::class)->register([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'start_month'    => '2026-08',
            'months_count'   => 3,
            'price_snapshot' => 30000,
            'items'          => [],
            'payment_amount' => $paymentAmount,
            'payment_method' => 'cash',
            'payment_date'   => '2026-08-01',
        ], $this->admin()->id);
    }

    public function test_write_off_on_close_creates_journal_entry(): void
    {
        // Register with partial payment of 10,000; remaining balance = 20,000
        $registration = $this->makeRegistration(10000);

        $writeOffCountBefore = JournalEntry::count();

        app(RegistrationService::class)->close(
            $registration,
            'غير قادر على الدفع',
            $this->admin()->id,
            writeOff: true,
        );

        // One new JournalEntry should have been created for the write_off
        $this->assertGreaterThan($writeOffCountBefore, JournalEntry::count());

        // The write_off StudentTransaction must exist
        $writeOffTx = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'write_off')
            ->firstOrFail();

        $this->assertEquals(20000.00, (float) $writeOffTx->amount);

        // Find the journal entry for this specific document
        $entry = JournalEntry::query()
            ->where('document_type', StudentTransaction::class)
            ->where('document_id', $writeOffTx->id)
            ->firstOrFail();

        $this->assertNull($entry->voided_at, 'Write-off journal entry must not be voided');

        // Lines: exactly 2 — a debit and a credit
        $lines = $entry->lines()->get();
        $this->assertCount(2, $lines);

        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals((float) $totalDebit, (float) $totalCredit, 'Journal entry must be balanced');
        $this->assertEquals(20000.00, (float) $totalDebit);

        // Debit line must be account 5150 (Write-Off Expense)
        $debitLine = $lines->firstWhere('debit', '>', 0);
        $writeOffAccount = Account::query()->where('code', AccountService::CODE_EXPENSE_WRITE_OFF)->first();
        $this->assertNotNull($writeOffAccount, 'Account 5150 must exist');
        $this->assertEquals($writeOffAccount->id, $debitLine->account_id);
        $this->assertNull($debitLine->party_type, 'Write-off expense line must have no party (pure expense entry)');

        // Credit line must be account 4100 (Course Fees Income)
        $creditLine = $lines->firstWhere('credit', '>', 0);
        $incomeAccount = Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->first();
        $this->assertEquals($incomeAccount->id, $creditLine->account_id);
        $this->assertNull($creditLine->party_type, 'Write-off income line must have no party (not a cash receipt)');
    }

    public function test_write_off_on_zero_payment_registration_still_creates_journal(): void
    {
        // Register with no payment at all
        $registration = $this->makeRegistration(0);

        app(RegistrationService::class)->close(
            $registration,
            'سبب الإغلاق',
            $this->admin()->id,
            writeOff: true,
        );

        $writeOffTx = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'write_off')
            ->firstOrFail();

        $this->assertEquals(30000.00, (float) $writeOffTx->amount);

        // Journal entry must still be created
        $entry = JournalEntry::query()
            ->where('document_type', StudentTransaction::class)
            ->where('document_id', $writeOffTx->id)
            ->first();

        $this->assertNotNull($entry, 'Journal entry must be created even with no prior payment');
        $lines = $entry->lines()->get();
        $this->assertEquals(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit'),
            'Zero-payment write-off journal must be balanced'
        );
    }

    public function test_voiding_write_off_transaction_creates_reversing_journal(): void
    {
        $registration = $this->makeRegistration(5000);

        app(RegistrationService::class)->close(
            $registration,
            'مغلق',
            $this->admin()->id,
            writeOff: true,
        );

        $writeOffTx = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'write_off')
            ->firstOrFail();

        $journalCountBefore = JournalEntry::count();

        // Void the write-off transaction
        $writeOffTx->update([
            'voided_at'     => now(),
            'void_reason'   => 'اختبار الإلغاء',
            'voided_by'     => $this->admin()->id,
        ]);

        // The observer must have created a reversing entry
        $this->assertGreaterThan($journalCountBefore, JournalEntry::count(), 'Void must create a reversing journal entry');

        $original = JournalEntry::query()
            ->where('document_type', StudentTransaction::class)
            ->where('document_id', $writeOffTx->id)
            ->oldest('id')
            ->first();

        $this->assertNotNull($original->voided_at, 'Original write-off journal entry must be marked voided');
    }

    public function test_withdraw_with_write_off_creates_journal_entry(): void
    {
        $registration = $this->makeRegistration(8000);

        app(RegistrationService::class)->withdraw(
            $registration,
            'انسحب الطالب',
            $this->admin()->id,
            writeOff: true,
        );

        $writeOffTx = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'write_off')
            ->firstOrFail();

        $entry = JournalEntry::query()
            ->where('document_type', StudentTransaction::class)
            ->where('document_id', $writeOffTx->id)
            ->first();

        $this->assertNotNull($entry, 'Withdraw with write_off must also create a journal entry');

        $lines = $entry->lines()->get();
        $this->assertEquals(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit'),
        );
    }

    public function test_finance_reconcile_command_passes_with_no_discrepancies(): void
    {
        // Register and close with write-off; reconcile should still pass
        $registration = $this->makeRegistration(15000);

        app(RegistrationService::class)->close(
            $registration,
            'إغلاق للاختبار',
            $this->admin()->id,
            writeOff: true,
        );

        $this->artisan('finance:reconcile --all')
            ->assertExitCode(0);
    }
}
