<?php

namespace Tests\Feature;

use App\Filament\Pages\Payments;
use App\Filament\Resources\ExpenseResource;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OtherPerson;

use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialAuditPhase2Test extends TestCase
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

    public function test_posted_expense_cannot_be_edited_directly(): void
    {
        $category = ExpenseCategory::create(['name' => 'مستلزمات مكتبية']);
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'amount' => 15000,
            'date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'created_by' => $this->adminUser()->id,
        ]);

        $this->assertFalse(ExpenseResource::canEdit($expense));
    }

    public function test_student_payment_requires_registration_id(): void
    {
        $this->actingAs($this->adminUser());
        $student = Student::create([
            'name' => 'طالب ربط التسجيل',
            'phone' => '773333333',
            'gender' => 'male',
        ]);

        Livewire::test(Payments::class)
            ->set('data.party_type', 'student')
            ->set('data.student_id', $student->id)
            ->set('data.registration_id', null)
            ->set('data.amount', 5000)
            ->set('data.date', now()->format('Y-m-d'))
            ->set('data.method', 'cash')
            ->call('savePayment')
            ->assertHasErrors(['data.registration_id']);
    }

    public function test_payment_method_wallet_requires_wallet_id(): void
    {
        $this->actingAs($this->adminUser());
        $person = OtherPerson::create(['name' => 'مورد خارجي', 'phone' => '770000000', 'is_active' => true]);

        Livewire::test(Payments::class)
            ->set('data.party_type', 'other')
            ->set('data.other_person_id', $person->id)
            ->set('data.amount', 8000)
            ->set('data.date', now()->format('Y-m-d'))
            ->set('data.method', 'wallet')
            ->set('data.wallet_id', null)
            ->call('savePayment')
            ->assertHasErrors(['data.wallet_id']);
    }

    public function test_salary_payment_prevents_duplicate_disbursement_under_lock(): void
    {
        $this->actingAs($this->adminUser());
        $staff = Staff::create([
            'name' => 'استاذ المعالجة',
            'phone' => '775555555',
            'gender' => 'male',
            'salary_type' => 'monthly',
            'salary_value' => 100000,
            'is_active' => true,
        ]);

        $month = now()->format('Y-m');

        Livewire::test(\App\Filament\Pages\Reports\SalarySheetReport::class)
            ->set('data.month', $month)
            ->callAction('recordSalaries', data: [
                'method' => 'cash',
            ]);

        $txCount = \App\Models\StaffTransaction::query()
            ->where('staff_id', $staff->id)
            ->where('salary_month', $month)
            ->count();

        $this->assertEquals(1, $txCount);

        // Second call for the same month must skip disbursement
        Livewire::test(\App\Filament\Pages\Reports\SalarySheetReport::class)
            ->set('data.month', $month)
            ->callAction('recordSalaries', data: [
                'method' => 'cash',
            ]);

        $txCountAfterSecondRun = \App\Models\StaffTransaction::query()
            ->where('staff_id', $staff->id)
            ->where('salary_month', $month)
            ->count();

        $this->assertEquals(1, $txCountAfterSecondRun);
    }

    public function test_void_action_populates_voided_by_user_id(): void
    {
        $this->actingAs($this->adminUser());
        $student = Student::create([
            'name' => 'طالب تجربة الإلغاء',
            'phone' => '774444444',
            'gender' => 'male',
        ]);

        $tx = StudentTransaction::create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => 5000,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'receipt_no' => 8888,
            'created_by' => $this->adminUser()->id,
        ]);

        $tx->void('سبب إلغاء لاختبار تتبع المستخدم');

        $tx->refresh();
        $this->assertTrue($tx->isVoided());
        $this->assertEquals($this->adminUser()->id, $tx->voided_by);
        $this->assertEquals('سبب إلغاء لاختبار تتبع المستخدم', $tx->void_reason);
    }
}
