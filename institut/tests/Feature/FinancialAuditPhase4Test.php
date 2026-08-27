<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Student;
use App\Models\StudentTransaction;

use App\Models\User;
use App\Services\DashboardCacheService;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use Tests\TestCase;

class FinancialAuditPhase4Test extends TestCase
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

    public function test_dashboard_cache_flushes_on_student_transaction(): void
    {
        Cache::put('dashboard_stats_refined', ['cached' => true], 300);
        $this->assertTrue(Cache::has('dashboard_stats_refined'));

        $student = Student::create([
            'name' => 'طالب تجربة الذاكرة',
            'phone' => '770000001',
            'gender' => 'male',
        ]);

        StudentTransaction::create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => 5000,
            'date' => now()->format('Y-m-d'),
            'receipt_no' => 88881,
        ]);

        $this->assertFalse(Cache::has('dashboard_stats_refined'));
    }

    public function test_journal_service_enforces_strict_two_decimal_balancing(): void
    {
        $this->actingAs($this->adminUser());
        $journalService = app(JournalService::class);

        $acc1 = Account::query()->first();
        $acc2 = Account::query()->skip(1)->first();

        $this->expectException(\RuntimeException::class);

        $journalService->post([
            ['account_id' => $acc1->id, 'debit' => 100.00, 'credit' => 0.00],
            ['account_id' => $acc2->id, 'debit' => 0.00, 'credit' => 99.99],
        ], now()->format('Y-m-d'), 'قيد غير متوازن برمزين عشريين');
    }

    public function test_account_balance_returns_float_value(): void
    {
        $account = Account::query()->first();
        $balance = $account->balance();

        $this->assertIsFloat($balance);
    }
}
