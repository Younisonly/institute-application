<?php

namespace Tests\Feature;

use App\Filament\Pages\OpeningBalances;
use App\Filament\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\Book;
use App\Models\Course;
use App\Models\Item;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RepeaterCrashTest extends TestCase
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

    public function test_opening_balances_page_survives_row_add_edit_and_remove(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(OpeningBalances::class)
            ->assertOk()
            ->callFormComponentAction('entries', 'add')
            ->assertOk()
            ->fillForm([
                'entries' => [
                    ['account_id' => \App\Models\Account::query()->first()->id, 'amount' => 1000],
                    ['account_id' => \App\Models\Account::query()->first()->id, 'amount' => 2000],
                ],
            ])
            ->assertOk()
            ->call('postBalances')
            ->assertOk();
    }

    public function test_opening_balances_survives_field_by_field_updates_and_invalid_post(): void
    {
        $this->actingAs($this->admin());

        $account = \App\Models\Account::query()->first();

        Livewire::test(OpeningBalances::class)
            ->assertOk()
            ->set('data.entries.0.amount', '5000')
            ->assertOk()
            ->set('data.entries.0.account_id', (string) $account->id)
            ->assertOk()
            ->call('postBalances')
            ->assertOk();

        Livewire::test(OpeningBalances::class)
            ->assertOk()
            ->set('data.entries.0.amount', '')
            ->assertOk()
            ->call('postBalances')
            ->assertOk();

        Livewire::test(OpeningBalances::class)
            ->assertOk()
            ->set('data.entries', [])
            ->assertOk()
            ->set('data.entries.0.amount', '100')
            ->assertOk()
            ->call('postBalances')
            ->assertOk();
    }

    public function test_registration_create_survives_book_row_add_and_submit(): void
    {
        $this->actingAs($this->admin());

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $supplier = Supplier::create(['name' => 'Dar Al-Kitab']);
        $book = Book::create([
            'title' => 'English Starter',
            'course_id' => $course->id,
            'supplier_id' => $supplier->id,
            'buy_price' => 1500,
            'sale_price' => 2000,
            'stock_qty' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        Livewire::test(CreateRegistration::class)
            ->assertOk()
            ->fillForm([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'start_month' => '2026-08',
                'price_snapshot' => 35000,
                'months_count' => 6,
                'books' => [
                    ['book_id' => $book->id, 'qty' => 1, 'unit_price' => 2000],
                ],
                'payment_amount' => 10000,
                'payment_method' => 'cash',
            ])
            ->assertOk()
            ->call('create')
            ->assertOk();

        $this->assertDatabaseCount('registrations', 1);
        $this->assertSame(9, $book->fresh()->stock_qty);
    }

    public function test_live_select_in_item_row_keeps_unit_price_in_same_row(): void
    {
        $this->actingAs($this->admin());

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $item = Item::create(['name' => 'Notebook', 'sale_price' => 2500, 'is_active' => true]);

        $component = Livewire::test(CreateRegistration::class)
            ->assertOk()
            ->set('data.student_id', $student->id)
            ->set('data.course_id', $course->id)
            ->set('data.months_count', 6)
            ->set('data.price_snapshot', 35000)
            ->set('data.course_batch_id', null);

        $component->callFormComponentAction('items', 'add');

        $state = $component->get('data.items');
        $this->assertIsArray($state);
        $firstKey = array_key_first($state);

        $component
            ->set("data.items.{$firstKey}.item_id", (string) $item->id)
            ->assertOk();

        $stateAfter = $component->get('data.items');
        $this->assertIsArray($stateAfter);
        $this->assertSame('2500.00', $stateAfter[$firstKey]['unit_price'] ?? null);
    }

    public function test_corrupted_string_row_in_items_state_does_not_crash_and_is_pruned(): void
    {
        $this->actingAs($this->admin());

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $item = Item::create(['name' => 'Notebook', 'sale_price' => 2500, 'is_active' => true]);

        $component = Livewire::test(CreateRegistration::class)
            ->assertOk()
            ->set('data.student_id', $student->id)
            ->set('data.course_id', $course->id)
            ->set('data.months_count', 6)
            ->set('data.price_snapshot', 35000);

        $component->callFormComponentAction('items', 'add');
        $firstKey = array_key_first($component->get('data.items'));

        $component
            ->set("data.items.{$firstKey}.item_id", (string) $item->id)
            ->set('data.items.unit_price', '2500.00')
            ->assertOk();

        $stateAfter = $component->get('data.items');
        $this->assertIsArray($stateAfter);
        $this->assertArrayNotHasKey('unit_price', $stateAfter);
        $this->assertSame('2500.00', $stateAfter[$firstKey]['unit_price'] ?? null);
    }
}
