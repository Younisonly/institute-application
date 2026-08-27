<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\CreateBook;
use App\Models\Book;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookCreateStockQtyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_create_book_form_does_not_dehydrate_null_stock_qty(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Audit Test Book',
                'low_stock_threshold' => 5,
                'sale_price' => 100,
                'buy_price' => 50,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $book = Book::query()->where('title', 'Audit Test Book')->first();
        $this->assertNotNull($book);
        $this->assertSame(0, $book->stock_qty);
    }
}
