<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Book;
use App\Models\Course;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\JournalEntry;
use App\Models\OtherPeopleTransaction;
use App\Models\OtherPerson;
use App\Models\PartyType;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
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

    public function test_student_payment_posts_balanced_entry_and_void_reverses_it(): void
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
            'books' => [],
            'payment_amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);

        $payment = $registration->transactions()->where('type', 'payment')->firstOrFail();
        $entry = $payment->journalEntry;

        $this->assertNotNull($entry, 'payment must auto-post a journal entry');
        $this->assertEqualsWithDelta($entry->debit_total, $entry->credit_total, 0.001);
        $this->assertSame(2, $entry->lines()->count());

        $cash = Account::query()->where('code', AccountService::CODE_CASH)->firstOrFail();
        $fees = Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail();
        $this->assertEquals(10000, (float) $cash->balance());
        $this->assertEquals(10000, (float) $fees->balance());

        $payment->void('duplicate entry');

        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertTrue($entry->fresh()->isVoided());
        $this->assertEquals(0, (float) $cash->balance());
        $this->assertEquals(0, (float) $fees->balance());
    }

    public function test_bank_payment_lands_in_bank_account_with_reference(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);
        $course = $this->makeCourse();
        $bank = Bank::create(['name' => 'Kuraimi', 'is_active' => true]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 5000,
            'payment_method' => 'bank',
            'bank_id' => $bank->id,
            'transaction_ref' => 'TX-991',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);

        $payment = $registration->transactions()->where('type', 'payment')->firstOrFail();
        $this->assertSame('bank', $payment->method);
        $this->assertSame($bank->id, $payment->bank_id);
        $this->assertSame('TX-991', $payment->transaction_ref);

        $bankAccount = app(AccountService::class)->ensureForPlace($bank);
        $this->assertEquals(5000, (float) $bankAccount->fresh()->balance());
        $this->assertEquals(0, (float) app(AccountService::class)->cashAccount()->balance());
    }

    public function test_supplier_cycle_posts_inventory_payable_and_payment(): void
    {
        $supplier = Supplier::create(['name' => 'Dar Al-Kutub']);
        $book = Book::create([
            'title' => 'Grammar 1',
            'supplier_id' => $supplier->id,
            'buy_price' => 1500,
            'sale_price' => 2000,
            'stock_qty' => 0,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $movement = $book->movements()->create([
            'book_id' => $book->id,
            'type' => 'in',
            'qty' => 10,
            'unit_price' => 1500,
            'date' => '2026-08-01',
            'supplier_id' => $supplier->id,
            'description' => 'initial stock',
            'created_by' => $this->admin()->id,
        ]);
        $book->increment('stock_qty', 10);

        $this->assertEquals(15000, (float) $supplier->fresh()->debt);
        $this->assertEquals(0, (float) $supplier->fresh()->paid);

        $inventory = Account::query()->where('code', AccountService::CODE_INVENTORY_BOOKS)->firstOrFail();
        $payable = Account::query()->where('code', AccountService::CODE_SUPPLIER_PAYABLE)->firstOrFail();
        $this->assertEquals(15000, (float) $inventory->balance());
        $this->assertEquals(15000, (float) $payable->balance());

        $payment = SupplierTransaction::create([
            'supplier_id' => $supplier->id,
            'type' => 'payment',
            'amount' => 10000,
            'date' => '2026-08-05',
            'method' => 'cash',
            'receipt_no' => 1001,
            'created_by' => $this->admin()->id,
        ]);

        $this->assertEquals(10000, (float) $supplier->fresh()->paid);
        $this->assertEquals(5000, (float) $supplier->fresh()->balance);
        $this->assertEquals(5000, (float) $payable->fresh()->balance());

        $payment->void('wrong amount');
        $this->assertEquals(0, (float) $supplier->fresh()->paid);
        $this->assertEquals(15000, (float) $payable->fresh()->balance());
    }

    public function test_expense_posts_to_category_account_and_void_reverses(): void
    {
        $category = ExpenseCategory::create(['name' => 'Electricity']);
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'amount' => 3000,
            'date' => '2026-08-07',
            'payment_method' => 'wallet',
            'description' => 'monthly bill',
            'created_by' => $this->admin()->id,
        ]);

        $wallet = Wallet::create(['name' => 'YMoney', 'is_active' => true]);
        $expense->update(['wallet_id' => $wallet->id]);

        $this->assertNotNull($expense->fresh()->journalEntry);
        $this->assertEquals(3000, (float) app(AccountService::class)->ensureForExpenseCategory($category)->balance());

        $expense->void('paid twice');
        $this->assertTrue($expense->fresh()->journalEntry->isVoided());
        $this->assertEquals(0, (float) app(AccountService::class)->ensureForExpenseCategory($category)->fresh()->balance());
    }

    public function test_other_person_income_and_expense_are_journaled(): void
    {
        $partyType = PartyType::create(['name' => 'Teacher']);
        $person = OtherPerson::create(['name' => 'Ustaz Omar', 'party_type_id' => $partyType->id, 'phone' => '777']);
        $incomeCategory = IncomeCategory::create(['name' => 'Donation', 'is_active' => true]);

        $income = OtherPeopleTransaction::create([
            'other_person_id' => $person->id,
            'type' => 'in',
            'amount' => 20000,
            'date' => '2026-08-02',
            'method' => 'cash',
            'income_category_id' => $incomeCategory->id,
            'receipt_no' => 2001,
            'created_by' => $this->admin()->id,
        ]);

        $this->assertNotNull($income->fresh()->journalEntry);
        $this->assertEquals(20000, (float) $person->fresh()->balance);
        $this->assertEquals(20000, (float) app(AccountService::class)->ensureForIncomeCategory($incomeCategory)->balance());

        $out = OtherPeopleTransaction::create([
            'other_person_id' => $person->id,
            'type' => 'out',
            'amount' => 5000,
            'date' => '2026-08-03',
            'method' => 'cash',
            'receipt_no' => 2002,
            'created_by' => $this->admin()->id,
        ]);

        $this->assertEquals(15000, (float) $person->fresh()->balance);
        $out->void('mistake');
        $this->assertEquals(20000, (float) $person->fresh()->balance);
    }

    public function test_registration_with_book_deducts_stock_and_posts_balanced_entries(): void
    {
        $student = Student::create(['name' => 'Huda', 'status' => 'active']);
        $course = $this->makeCourse();
        $book = Book::create([
            'title' => 'Workbook A',
            'course_id' => $course->id,
            'buy_price' => 1000,
            'sale_price' => 2000,
            'stock_qty' => 5,
            'low_stock_threshold' => 1,
            'is_active' => true,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [
                ['book_id' => $book->id, 'qty' => 1, 'unit_price' => 2000],
            ],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $this->assertSame(4, $book->fresh()->stock_qty);

        $pivot = $registration->items()->firstOrFail();
        $this->assertSame($book->id, $pivot->book_id);
        $this->assertTrue($pivot->is_book);

        $bookCharge = $registration->transactions()
            ->where('type', 'charge')
            ->where('description', 'Workbook A × 1')
            ->firstOrFail();
        $booksIncome = Account::query()->where('code', AccountService::CODE_INCOME_BOOKS)->firstOrFail();
        $this->assertSame($booksIncome->id, $bookCharge->income_account_id);
        $this->assertEquals(2000, (float) $bookCharge->amount);
    }

    public function test_bulk_program_registration_allocates_payment_across_courses(): void
    {
        $student = Student::create(['name' => 'Noor', 'status' => 'active']);
        $type = ProgramType::create(['name' => 'Diploma', 'months_count' => 12]);
        $c1 = Course::create(['name' => 'Accounting', 'program_type_id' => $type->id, 'months' => 6, 'price' => 30000, 'is_active' => true]);
        $c2 = Course::create(['name' => 'Excel', 'program_type_id' => $type->id, 'months' => 3, 'price' => 15000, 'is_active' => true]);

        $registrations = app(RegistrationService::class)->registerForProgram([
            'student_id' => $student->id,
            'program_type_id' => $type->id,
            'course_ids' => [$c1->id, $c2->id],
            'start_month' => '2026-09',
            'payment_amount' => 40000,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-01',
        ], $this->admin()->id);

        $this->assertCount(2, $registrations);

        $payments = Student::query()->find($student->id)->transactions()->where('type', 'payment')->get();
        $this->assertCount(2, $payments);
        $this->assertEquals(40000, (float) $payments->sum('amount'));

        $totals = \App\Models\Registration::query()->withTotals()->get()->keyBy('id');
        $this->assertEquals(0, (float) $totals[$registrations[0]->id]->balance);
        $this->assertEquals(5000, (float) $totals[$registrations[1]->id]->balance);

        $entries = JournalEntry::query()->where('document_type', \App\Models\StudentTransaction::class)->get();
        $this->assertCount(2, $entries);
        $this->assertEquals(40000, (float) app(AccountService::class)->cashAccount()->balance());
    }

    public function test_finance_pages_render(): void
    {
        $this->actingAs($this->admin())->get('/admin/banks')->assertOk();
        $this->actingAs($this->admin())->get('/admin/banks/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/wallets')->assertOk();
        $this->actingAs($this->admin())->get('/admin/wallets/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/income-categories')->assertOk();
        $this->actingAs($this->admin())->get('/admin/party-types')->assertOk();
        $this->actingAs($this->admin())->get('/admin/other-people')->assertOk();
        $this->actingAs($this->admin())->get('/admin/other-people/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/transfers')->assertOk();
        $this->actingAs($this->admin())->get('/admin/transfers/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/journals')->assertOk();
        $this->actingAs($this->admin())->get('/admin/books')->assertOk();
        $this->actingAs($this->admin())->get('/admin/books/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/finances')->assertOk();
        $this->actingAs($this->admin())->get('/admin/payments')->assertOk();
        $this->actingAs($this->admin())->get('/admin/opening-balances')->assertOk();
        $this->actingAs($this->admin())->get('/admin/trial-balance')->assertOk();
        $this->actingAs($this->admin())->get('/admin/account-ledger')->assertOk();
        $this->actingAs($this->admin())->get('/admin/income-statement')->assertOk();
        $this->actingAs($this->admin())->get('/admin/balance-sheet')->assertOk();
        $this->actingAs($this->admin())->get('/admin/suppliers')->assertOk();
        $this->actingAs($this->admin())->get('/admin/expense-categories')->assertOk();
    }

    public function test_standalone_book_sale_charges_pays_with_receipt_and_posts_entries(): void
    {
        $student = Student::create(['name' => 'Layla', 'status' => 'active']);
        $course = $this->makeCourse();
        $book = Book::create([
            'title' => 'Grammar B',
            'course_id' => $course->id,
            'buy_price' => 1000,
            'sale_price' => 2500,
            'stock_qty' => 3,
            'low_stock_threshold' => 1,
            'is_active' => true,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'payment_amount' => 35000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-01',
        ], $this->admin()->id);

        app(\App\Filament\Actions\SellBookAction::class)::forRegistration($registration)
            ->call(['data' => [
                'book_id' => $book->id,
                'qty' => '1',
                'unit_price' => '2500',
                'date' => '2026-08-05',
                'pay_now' => true,
                'method' => 'cash',
            ]]);

        $this->assertSame(2, $book->fresh()->stock_qty);

        $charge = $registration->transactions()->where('type', 'charge')->where('description', 'Grammar B × 1')->firstOrFail();
        $payment = $registration->transactions()->where('type', 'payment')->where('description', 'Grammar B × 1')->firstOrFail();
        $this->assertNotNull($payment->receipt_no);
        $this->assertEquals(2500, (float) $charge->amount);

        $entries = JournalEntry::query()->where('document_type', \App\Models\StudentTransaction::class)->get();
        $paymentEntry = $entries->where('document_id', $payment->id)->firstOrFail();
        $this->assertEqualsWithDelta((float) $paymentEntry->lines->sum('debit'), (float) $paymentEntry->lines->sum('credit'), 0.01);
        $booksIncome = Account::query()->where('code', AccountService::CODE_INCOME_BOOKS)->firstOrFail();
        $consolidated = \Illuminate\Support\Facades\DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.document_type', \App\Models\StudentTransaction::class)
            ->where('journal_entries.document_id', $payment->id)
            ->get();
        $this->assertSame('2500.00', (string) $consolidated->first(fn ($l) => $l->account_id === $booksIncome->id)->credit);
        $paymentEntry->lines->where('account_id', $booksIncome->id)->firstOrFail();
        $this->assertEquals(37500, (float) app(AccountService::class)->cashAccount()->balance());

        \App\Models\AuditLog::query()->where('action', 'book.sold')->where('entity_type', \App\Models\Registration::class)->firstOrFail();
    }

    public function test_walk_in_book_sale_posts_place_credit_income_and_deducts_stock(): void
    {
        $course = $this->makeCourse();
        $book = Book::create([
            'title' => 'Reader C',
            'course_id' => $course->id,
            'buy_price' => 500,
            'sale_price' => 1500,
            'stock_qty' => 4,
            'low_stock_threshold' => 1,
            'is_active' => true,
        ]);

        app(\App\Filament\Actions\SellBookAction::class)::walkIn($book)
            ->call(['data' => [
                'qty' => '2',
                'unit_price' => '1500',
                'date' => '2026-08-06',
                'method' => 'cash',
            ]]);

        $this->assertSame(2, $book->fresh()->stock_qty);

        $entry = JournalEntry::query()->where('document_type', \App\Models\StockMovement::class)->firstOrFail();
        $this->assertEqualsWithDelta((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.01);

        $booksIncome = Account::query()->where('code', AccountService::CODE_INCOME_BOOKS)->firstOrFail();
        $this->assertEquals(3000, (float) app(AccountService::class)->cashAccount()->balance());
        \App\Models\AuditLog::query()->where('action', 'book.sold_walkin')->where('entity_type', \App\Models\Book::class)->firstOrFail();
    }

    public function test_ledger_reports_agree_with_ledger_after_payment_and_cheque_lands_in_bank(): void
    {
        $bank = Bank::create(['name' => 'SAB', 'is_active' => true]);
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'payment_amount' => 35000,
            'payment_method' => 'cheque',
            'bank_id' => $bank->id,
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);

        $this->assertEquals(0, (float) $registration->fresh()->balance);

        $report = app(\App\Services\ReportService::class);
        $trial = $report->trialBalance();
        $this->assertGreaterThan(0, (float) $trial['totalDebit']);
        $this->assertEqualsWithDelta((float) $trial['totalDebit'], (float) $trial['totalCredit'], 0.01);

        $bankAccount = app(AccountService::class)->ensureForPlace($bank);
        $this->assertSame('35000.00', $bankAccount->balanceFormatted());

        $statement = $report->incomeStatement();
        $this->assertEquals(35000, (float) $statement['totalIncome']);

        $sheet = $report->balanceSheet();
        $this->assertEqualsWithDelta((float) $sheet['totalAssets'], (float) $sheet['totalLiabilities'], 0.01);

        $places = collect($report->placeBalances())->firstWhere('account.id', $bankAccount->id);
        $this->assertEquals(35000, (float) $places['balance']);

        $ledger = $report->accountLedger($bankAccount);
        $this->assertSame('35000.00', number_format((float) $ledger['total'], 2, '.', ''));
        $this->assertNotEmpty($ledger['rows']);
    }

    public function test_payment_of_999_billion_posts_without_error_and_journal_stays_balanced(): void
    {
        $student = Student::create(['name' => 'Big', 'status' => 'active']);
        $course = $this->makeCourse();

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 999000000000,
            'items' => [],
            'books' => [],
            'payment_amount' => 999000000000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);

        $payment = $registration->transactions()->where('type', 'payment')->firstOrFail();
        $this->assertSame('999000000000.00', $payment->fresh()->amount);

        $entry = $payment->journalEntry;
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->debit_total, (float) $entry->credit_total, 0.01);

        $cash = Account::query()->where('code', AccountService::CODE_CASH)->firstOrFail();
        $fees = Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail();
        $this->assertSame('999000000000.00', $cash->balanceFormatted());
        $this->assertSame('999000000000.00', $fees->balanceFormatted());

        $words = app(\App\Services\MoneyWordsService::class)->toArabicRials(999000000000.0);
        $this->assertStringContainsString('مليار', $words);
        $this->assertStringNotContainsString(__('general.number_too_large'), $words);
    }
}
