<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Book;
use App\Models\Course;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JournalEntry;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountService;
use App\Services\JournalService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditRecoveryTest extends TestCase
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

    private function makeCourse(): Course
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        return Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'is_active' => true,
        ]);
    }

    public function test_item_stock_sale_posts_balanced_income_entry_and_links_movement(): void
    {
        $category = ItemCategory::create(['name' => 'Gear']);
        $item = Item::create([
            'name' => 'Calculator',
            'category_id' => $category->id,
            'stock_qty' => 5,
            'sale_price' => 3000,
        ]);

        $item->movements()->create([
            'item_id' => $item->id,
            'type' => 'sold',
            'qty' => 2,
            'unit_price' => 3000,
            'method' => 'cash',
            'date' => '2026-08-10',
        ]);

        $movement = $item->movements()->firstOrFail();
        $entry = $movement->journalEntry;

        $this->assertNotNull($entry, 'sold movement must auto-post a journal entry');
        $this->assertEqualsWithDelta((float) $entry->debit_total, (float) $entry->credit_total, 0.001);
        $this->assertSame(3, $item->fresh()->stock_qty);

        $itemsIncome = Account::query()->where('code', AccountService::CODE_INCOME_ITEMS)->firstOrFail();
        $this->assertEquals(6000, (float) $itemsIncome->balance());
        $this->assertEquals(6000, (float) app(AccountService::class)->cashAccount()->balance());
    }

    public function test_book_stock_sale_paid_from_bank_credits_bank_account(): void
    {
        $course = $this->makeCourse();
        $bank = Bank::create(['name' => 'SAB', 'is_active' => true]);
        $book = Book::create([
            'title' => 'Reader',
            'course_id' => $course->id,
            'sale_price' => 1500,
            'stock_qty' => 4,
            'is_active' => true,
        ]);

        $book->movements()->create([
            'book_id' => $book->id,
            'type' => 'sold',
            'qty' => 2,
            'unit_price' => 1500,
            'method' => 'bank',
            'bank_id' => $bank->id,
            'transaction_ref' => 'CHQ-88',
            'date' => '2026-08-10',
        ]);

        $movement = $book->movements()->firstOrFail();
        $this->assertNotNull($movement->journalEntry);
        $this->assertNotNull($movement->journalEntry->lines()->where('account_id', app(AccountService::class)->ensureForPlace($bank)->id)->first());
        $this->assertEquals(3000, (float) app(AccountService::class)->ensureForPlace($bank)->balance());
    }

    public function test_voiding_a_journal_entry_restores_stock_on_the_linked_movement(): void
    {
        $category = ItemCategory::create(['name' => 'Gear']);
        $item = Item::create([
            'name' => 'Pen',
            'category_id' => $category->id,
            'stock_qty' => 0,
        ]);
        $supplier = \App\Models\Supplier::create(['name' => 'Al Noor']);

        $item->movements()->create([
            'item_id' => $item->id,
            'type' => 'in',
            'qty' => 10,
            'unit_price' => 100,
            'supplier_id' => $supplier->id,
            'date' => '2026-08-01',
        ]);

        $this->assertSame(10, $item->fresh()->stock_qty);

        $entry = JournalEntry::query()
            ->where('document_type', \App\Models\StockMovement::class)
            ->where('document_id', $item->movements()->first()->id)
            ->firstOrFail();

        JournalService::reverseIfVoidable($entry, 'wrong purchase');

        $this->assertSame(0, $item->fresh()->stock_qty);
        $this->assertNotNull($item->movements()->first()->fresh()->voided_at);
    }

    public function test_editing_an_expense_reposts_the_journal_entry(): void
    {
        $category = ExpenseCategory::create(['name' => 'Rent']);
        $expense = Expense::create([
            'amount' => 5000,
            'date' => '2026-08-05',
            'expense_category_id' => $category->id,
            'payment_method' => 'cash',
            'description' => 'Electricity',
            'created_by' => $this->admin()->id,
        ]);

        $expense->update(['amount' => 7000, 'description' => 'Electricity 2']);

        $expenseAccount = $category->fresh()->account;
        $this->assertEquals(7000, (float) $expenseAccount->balance());

        $entries = JournalEntry::query()
            ->where('document_type', Expense::class)
            ->where('document_id', $expense->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $entries);
        $this->assertNotNull($entries->first()->voided_at, 'original entry must be reversed');
        $this->assertNotNull($entries->get(1)->voided_at, 'reversal entry carries the audit trail');
        $this->assertNull($entries->last()->voided_at, 'reposted entry must be live');
    }

    public function test_registration_with_history_cannot_be_deleted(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 0,
        ], $this->admin()->id);

        try {
            $registration->delete();
            $this->fail('Expected RuntimeException for a registration with history.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('general.cannot_delete_registration_with_history'), $e->getMessage());
        }

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_staff_specialty_charge_link_survives_item_rename(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);
        $course = $this->makeCourse();
        $category = ItemCategory::create(['name' => 'Books']);
        $item = Item::create([
            'name' => 'Workbook',
            'category_id' => $category->id,
            'stock_qty' => 5,
            'sale_price' => 2000,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [
                ['item_id' => $item->id, 'qty' => 1, 'unit_price' => 2000],
            ],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $registrationItem = $registration->items()->firstOrFail();

        $item->update(['name' => 'Workbook 2nd Edition']);

        app(RegistrationService::class)->voidIssuedItem($registrationItem, 'renamed before return');

        $charge = $registration->transactions()
            ->where('type', 'charge')
            ->where('registration_item_id', $registrationItem->id)
            ->first();
        $this->assertNotNull($charge);
        $this->assertNotNull($charge->voided_at, 'charge must be voided even after a rename');
        $this->assertSame(5, $item->fresh()->stock_qty);
    }

    public function test_place_account_codes_never_collide_across_bank_and_wallet_series(): void
    {
        $accounting = app(AccountService::class);

        $bank = Bank::create(['name' => 'KBY', 'balance' => 0]);
        $wallet = Wallet::create(['name' => 'Mobile Money', 'balance' => 0]);
        $bankAccount = $accounting->ensureForPlace($bank);
        $walletAccount = $accounting->ensureForPlace($wallet);

        $this->assertSame((string) (1200 + $bank->id), $bankAccount->code);
        $this->assertSame((string) (1300 + $wallet->id), $walletAccount->code);
        $this->assertNotSame($bankAccount->code, $walletAccount->code);

        $bank2 = Bank::create(['name' => 'Yemen Bank', 'balance' => 0]);
        $bank2Account = $accounting->ensureForPlace($bank2);
        $this->assertSame((string) (1200 + $bank2->id), $bank2Account->code);
        $this->assertNotSame($bank2Account->code, $bankAccount->code);
    }

    public function test_bank_and_wallet_with_overlapping_series_get_distinct_codes(): void
    {
        $accounting = app(AccountService::class);

        $wallet = Wallet::create(['name' => 'Fawri', 'balance' => 0]);
        $walletAccount = $accounting->ensureForPlace($wallet);

        $bank = Bank::create(['name' => 'KBY', 'balance' => 0]);
        DB::table('banks')->where('id', $bank->id)->update(['id' => $wallet->id + 100]);
        $bankAccount = $accounting->ensureForPlace(Bank::findOrFail($wallet->id + 100));

        $this->assertSame((string) (1300 + $wallet->id), $walletAccount->code);
        $this->assertSame((string) (1300 + $wallet->id + 1), $bankAccount->code);
        $this->assertNotSame($bankAccount->code, $walletAccount->code);
    }
}