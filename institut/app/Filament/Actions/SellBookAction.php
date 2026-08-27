<?php

namespace App\Filament\Actions;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Registration;
use App\Models\RegistrationItem;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Services\AccountService;
use App\Services\ReceiptNumberService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellBookAction
{
    public static function forRegistration(Registration $registration): Action
    {
        return Action::make('sellBook')
            ->label(__('general.sell_book'))
            ->icon('heroicon-o-shopping-cart')
            ->color('info')
            ->modalHeading(__('general.sell_book'))
            ->form(fn (): array => self::saleFields($registration, null, false))
            ->action(function (array $data) use ($registration): void {
                self::sell($registration, $data, $registration->student_id);
            })
            ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false);
    }

    public static function forStudent(?Student $student = null): Action
    {
        return Action::make('sellBook')
            ->label(__('general.sell_book'))
            ->icon('heroicon-o-shopping-cart')
            ->color('info')
            ->modalHeading(__('general.sell_book'))
            ->form(fn (?Student $record): array => [
                Select::make('registration_id')->native(false)
                    ->label(__('general.registration'))
                    ->options(function () use ($student, $record): array {
                        $target = $record ?? $student;
                        if (! $target) {
                            return [];
                        }
                        return $target->registrations()
                            ->where('status', 'active')
                            ->with('course')
                            ->get()
                            ->mapWithKeys(fn (Registration $r): array => [
                                $r->id => $r->course->name.' — '.$r->start_month,
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                ...self::saleFields(null, 'registration_id', false),
            ])
            ->action(function (array $data, ?Student $record) use ($student): void {
                $target = $record ?? $student;
                $registration = Registration::query()->findOrFail($data['registration_id']);
                self::sell($registration, $data, $target?->id ?? $registration->student_id);
            })
            ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false);
    }

    public static function walkIn(Book $book): Action
    {
        return Action::make('sellWalkIn')
            ->label(__('general.walk_in_sale'))
            ->icon('heroicon-o-shopping-cart')
            ->color('info')
            ->modalHeading(__('general.walk_in_sale'))
            ->form(fn (): array => [
                TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->default(1)->minValue(1),
                MoneyInput::make('unit_price')
                    ->label(__('general.price'))
                    ->required()
                    ->minValue(0)
                    ->default((string) $book->sale_price)
                    ->suffix(__('general.currency')),
                DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                ...PaymentDetails::fields(),
            ])
            ->action(function (array $data) use ($book): void {
                self::sellWalkIn($book, $data);
            })
            ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false);
    }

    private static function saleFields(?Registration $registration, ?string $registrationField, bool $walkIn): array
    {
        return [
            Select::make('book_id')->native(false)
                ->label(__('general.book'))
                ->options(function (Get $get) use ($registration, $registrationField): array {
                    $courseId = $registration?->course_id;
                    if ($courseId === null && $registrationField !== null && $get($registrationField) !== null) {
                        $courseId = Registration::query()->find($get($registrationField))?->course_id;
                    }

                    return Book::query()
                        ->where('is_active', true)
                        ->when($courseId !== null, fn ($q) => $q->where('course_id', $courseId))
                        ->get()
                        ->mapWithKeys(fn (Book $book): array => [
                            $book->id => $book->title.' — '.__('general.stock').': '.$book->stock_qty.' — '.number_format((float) $book->sale_price).' '.__('general.currency'),
                        ])
                        ->all();
                })
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set, ?int $state) => $set('unit_price', $state !== null ? (string) (Book::find($state)?->sale_price ?? 0) : '0')),
            TextInput::make('qty')
                ->label(__('general.quantity'))
                ->numeric()->maxValue(999999999999)
                ->required()
                ->default(1)
                ->minValue(1)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => $set('total', self::total($state, $get('unit_price')))),
            MoneyInput::make('unit_price')
                ->label(__('general.price'))
                ->required()
                ->minValue(0)
                ->default(0)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => $set('total', self::total($get('qty'), $state))),
            MoneyInput::make('total')
                ->label(__('general.total'))
                ->disabled()
                ->dehydrated(false)
                ->default('0'),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            Toggle::make('pay_now')
                ->label(__('general.pay_now'))
                ->default(true)
                ->live(),
            Select::make('method')->native(false)
                ->label(__('general.payment_method'))
                ->options([
                    'cash' => __('general.cash'),
                    'bank' => __('general.bank'),
                    'wallet' => __('general.wallet'),
                    'cheque' => __('general.cheque'),
                    'other' => __('general.method_other'),
                ])
                ->default('cash')
                ->live()
                ->required()
                ->visible(fn (Get $get): bool => $get('pay_now') === true),
            ...array_slice(PaymentDetails::fields('method'), 1),
        ];
    }

    private static function total(?string $qty, ?string $unitPrice): string
    {
        return (string) ((float) ($qty ?? 0) * (float) ($unitPrice ?? 0));
    }

    private static function sell(Registration $registration, array $data, int $studentId): void
    {
        DB::transaction(function () use ($registration, $data, $studentId): void {
            $book = Book::query()->lockForUpdate()->findOrFail($data['book_id']);
            $qty = max(1, (int) $data['qty']);
            $unitPrice = (float) ($data['unit_price'] ?? $book->sale_price ?? 0);

            if ($book->stock_qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => __('general.insufficient_stock', ['item' => $book->title]),
                ]);
            }

            $registrationItem = RegistrationItem::create([
                'registration_id' => $registration->id,
                'book_id' => $book->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'description' => $data['description'] ?? null,
            ]);

            $book->movements()->create([
                'book_id' => $book->id,
                'type' => 'issue',
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'date' => $data['date'],
                'registration_item_id' => $registrationItem->id,
                'description' => $registration->student?->name,
                'created_by' => Auth::id(),
            ]);

            $amount = $qty * $unitPrice;
            $income = app(AccountService::class)->account(AccountService::CODE_INCOME_BOOKS);

            StudentTransaction::create([
                'student_id' => $studentId,
                'registration_id' => $registration->id,
                'registration_item_id' => $registrationItem->id,
                'type' => 'charge',
                'amount' => $amount,
                'date' => $data['date'],
                'description' => $book->title.' × '.$qty,
                'income_account_id' => $income->id,
                'created_by' => Auth::id(),
            ]);

            if (($data['pay_now'] ?? false) === true) {
                StudentTransaction::create([
                    'student_id' => $studentId,
                    'registration_id' => $registration->id,
                    'type' => 'payment',
                    'amount' => $amount,
                    'date' => $data['date'],
                    'method' => $data['method'] ?? 'cash',
                    'bank_id' => $data['bank_id'] ?? null,
                    'wallet_id' => $data['wallet_id'] ?? null,
                    'transaction_ref' => $data['transaction_ref'] ?? null,
                    'income_account_id' => $income->id,
                    'receipt_no' => app(ReceiptNumberService::class)->next(),
                    'description' => $book->title.' × '.$qty,
                    'created_by' => Auth::id(),
                ]);
            }

            AuditLog::log('book.sold', Registration::class, $registration->id, [
                'book_id' => $book->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'pay_now' => ($data['pay_now'] ?? false) === true,
            ]);
        });

        Notification::make()->title(__('general.book_sold_success'))->success()->send();
    }

    private static function sellWalkIn(Book $book, array $data): void
    {
        DB::transaction(function () use ($book, $data): void {
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);
            $qty = max(1, (int) $data['qty']);
            $unitPrice = (float) ($data['unit_price'] ?? $book->sale_price ?? 0);

            if ($locked->stock_qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => __('general.insufficient_stock', ['item' => $book->title]),
                ]);
            }

            $movement = $locked->movements()->create([
                'book_id' => $locked->id,
                'type' => 'sold',
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'date' => $data['date'],
                'description' => __('general.walk_in_sale'),
                'created_by' => Auth::id(),
            ]);

            $amount = $qty * $unitPrice;

            AuditLog::log('book.sold_walkin', Book::class, $locked->id, [
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'total' => $amount,
            ]);
        });

        Notification::make()->title(__('general.book_sold_success'))->success()->send();
    }
}