<?php

namespace Tests\Feature;

use App\Filament\Pages\OpeningBalances;
use App\Filament\Pages\Payments;
use App\Filament\Resources\RegistrationResource\RelationManagers\TransactionsRelationManager;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialAuditPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    private function createStudentAndRegistration(float $fee = 10000): array
    {
        $student = Student::create([
            'name' => 'Test Audit Student',
            'phone' => '771234567',
            'gender' => 'male',
        ]);

        $course = Course::query()->first() ?? Course::create([
            'name_ar' => 'دورة اختبار',
            'name_en' => 'Test Course',
            'code' => 'TC01',
            'price' => $fee,
        ]);

        $registration = Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'price_snapshot' => $fee,
            'original_price' => $fee,
            'discount_amount' => 0,
            'start_month' => now()->format('Y-m'),
            'months_count' => 1,
            'status' => 'active',
        ]);

        // Add charge transaction
        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'charge',
            'amount' => $fee,
            'date' => now()->format('Y-m-d'),
            'description' => 'Course Fee Charge',
            'created_by' => $this->adminUser()->id,
        ]);

        return [$student, $registration];
    }

    public function test_refund_requires_original_payment_and_cannot_exceed_available_amount(): void
    {
        $this->actingAs($this->adminUser());
        [$student, $registration] = $this->createStudentAndRegistration(10000);

        // Record a 10,000 payment
        $payment = StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => 10000,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'receipt_no' => 9001,
            'created_by' => $this->adminUser()->id,
        ]);

        $pageClass = \App\Filament\Resources\RegistrationResource\Pages\EditRegistration::class;

        // Test Livewire relation manager refund action with amount > payment
        Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $registration, 'pageClass' => $pageClass])
            ->callTableAction('recordRefund', null, [
                'original_transaction_id' => $payment->id,
                'amount' => 15000,
                'date' => now()->format('Y-m-d'),
                'method' => 'cash',
            ])
            ->assertHasErrors(['amount']);

        // First refund of 6,000 should succeed
        Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $registration, 'pageClass' => $pageClass])
            ->callTableAction('recordRefund', null, [
                'original_transaction_id' => $payment->id,
                'amount' => 6000,
                'date' => now()->format('Y-m-d'),
                'method' => 'cash',
            ])
            ->assertHasNoErrors();

        // Second refund of 5,000 should fail because max remaining refundable is 4,000
        Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $registration, 'pageClass' => $pageClass])
            ->callTableAction('recordRefund', null, [
                'original_transaction_id' => $payment->id,
                'amount' => 5000,
                'date' => now()->format('Y-m-d'),
                'method' => 'cash',
            ])
            ->assertHasErrors(['amount']);

        // Second refund of 4,000 should succeed
        Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $registration, 'pageClass' => $pageClass])
            ->callTableAction('recordRefund', null, [
                'original_transaction_id' => $payment->id,
                'amount' => 4000,
                'date' => now()->format('Y-m-d'),
                'method' => 'cash',
            ])
            ->assertHasNoErrors();

        $this->assertEquals(2, $payment->refunds()->count());
        $this->assertEquals(10000, $payment->refunds()->sum('amount'));
    }

    public function test_payment_cannot_exceed_outstanding_balance(): void
    {
        $this->actingAs($this->adminUser());
        [$student, $registration] = $this->createStudentAndRegistration(10000);

        // Attempt overpayment via Payments page
        Livewire::test(Payments::class)
            ->set('data.party_type', 'student')
            ->set('data.student_id', $student->id)
            ->set('data.registration_id', $registration->id)
            ->set('data.amount', 12000)
            ->set('data.date', now()->format('Y-m-d'))
            ->set('data.method', 'cash')
            ->call('savePayment')
            ->assertHasErrors(['amount']);

        // Exact balance payment of 10,000 succeeds
        Livewire::test(Payments::class)
            ->set('data.party_type', 'student')
            ->set('data.student_id', $student->id)
            ->set('data.registration_id', $registration->id)
            ->set('data.amount', 10000)
            ->set('data.date', now()->format('Y-m-d'))
            ->set('data.method', 'cash')
            ->call('savePayment')
            ->assertHasNoErrors();

        // Additional payment when balance is 0 fails
        Livewire::test(Payments::class)
            ->set('data.party_type', 'student')
            ->set('data.student_id', $student->id)
            ->set('data.registration_id', $registration->id)
            ->set('data.amount', 1000)
            ->set('data.date', now()->format('Y-m-d'))
            ->set('data.method', 'cash')
            ->call('savePayment')
            ->assertHasErrors(['amount']);
    }

    public function test_account_service_handles_concurrent_code_generation(): void
    {
        $this->actingAs($this->adminUser());
        $service = app(AccountService::class);

        $bank = Bank::create([
            'name' => 'Tadhamon Bank',
            'is_active' => true,
        ]);

        $account1 = $service->ensureForPlace($bank);
        $account2 = $service->ensureForPlace($bank);

        $this->assertEquals($account1->id, $account2->id);
        $this->assertNotNull($account1->code);
    }

    public function test_opening_balances_rejects_duplicate_accounts(): void
    {
        $this->actingAs($this->adminUser());
        $cashAccount = app(AccountService::class)->cashAccount();

        Livewire::test(OpeningBalances::class)
            ->set('data.entries', [
                ['account_id' => $cashAccount->id, 'amount' => 50000],
                ['account_id' => $cashAccount->id, 'amount' => 30000],
            ])
            ->call('postBalances')
            ->assertHasErrors();
    }

    public function test_transfer_void_method_executes_journal_reversal(): void
    {
        $this->actingAs($this->adminUser());
        $cashAccount = app(AccountService::class)->cashAccount();
        $capitalAccount = Account::query()->where('code', '3100')->firstOrFail();

        $transfer = Transfer::create([
            'from_account_id' => $capitalAccount->id,
            'to_account_id' => $cashAccount->id,
            'amount' => 25000,
            'date' => now()->format('Y-m-d'),
            'description' => 'Capital injection transfer',
            'created_by' => $this->adminUser()->id,
        ]);

        $this->assertFalse($transfer->isVoided());

        $transfer->void('Testing transfer void method');

        $this->assertTrue($transfer->fresh()->isVoided());
        $this->assertEquals('Testing transfer void method', $transfer->fresh()->void_reason);
    }
}
