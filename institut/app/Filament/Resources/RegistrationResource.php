<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\PaymentDetails;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Filament\Resources\RegistrationResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RegistrationResource\RelationManagers\MonthsRelationManager;
use App\Filament\Resources\RegistrationResource\RelationManagers\TransactionsRelationManager;
use App\Models\Book;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\DiscountType;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use App\Models\InstituteSetting;
use App\Models\Item;
use App\Models\Registration;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RegistrationResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Registration::class;
    
    public static function getGloballySearchableAttributes(): array
    {
        return ['student.name', 'student.phone', 'course.name'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.registrations');
    }

    public static function getModelLabel(): string
    {
        return __('general.registration');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.registrations');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.student_registration'))
                    ->columns(3)
                    ->schema([
                        Select::make('student_id')->native(false)
                            ->label(__('general.select_student'))
                            ->options(fn (): array => Student::query()
                                ->where('status', 'active')
                                ->whereNull('deleted_at')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('course_id')->native(false)
                            ->label(__('general.select_course'))
                            ->options(function (Get $get): array {
                                $studentId = (int) ($get('student_id') ?? 0);

                                return Course::query()
                                    ->enrollable()
                                    ->get()
                                    ->mapWithKeys(function (Course $course) use ($studentId): array {
                                        $seats = $course->seats_remaining !== null
                                            ? ' — ('.$course->seats_remaining.' '.__('general.seats_remaining').')'
                                            : '';
                                        $level = $course->curriculumEntries()->first()?->level_no;
                                        $levelPrefix = $level !== null
                                            ? __('general.level_label', ['level' => $level]).' — '
                                            : '';
                                        $blocked = $studentId > 0
                                            && self::courseEligibilityBlockers($studentId, $course) !== [];

                                        return [
                                            $course->id => $levelPrefix.$course->name.' — '.number_format((float) $course->price).' '.__('general.currency').$seats
                                                .($blocked ? ' — ('.__('general.course_blocked').')' : ''),
                                        ];
                                    })
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                $course = $state !== null ? Course::find($state) : null;
                                $batch = $course?->openBatch();

                                $set('original_price', $course?->price ?? 0);
                                $set('price_snapshot', $course?->price ?? 0);
                                $set('discount_amount', 0);
                                $set('discount_type', null);
                                $set('months_count', $course?->months ?? 1);
                                $set('course_batch_id', $batch?->id ?? null);
                                $set('start_month', $batch?->start_date !== null
                                    ? $batch->start_date->format('Y-m')
                                    : (InstituteSetting::current()->current_month ?? now()->format('Y-m')));

                                // Auto-populate required supplies from course definition
                                if ($course !== null && is_array($course->required_supplies) && $course->required_supplies !== []) {
                                    $items = [];
                                    $books = [];

                                    foreach ($course->required_supplies as $supply) {
                                        $supplyId = $supply['supply_id'] ?? null;
                                        $qty = (int) ($supply['qty'] ?? 1);

                                        if ($supplyId === null) {
                                            continue;
                                        }

                                        if (str_starts_with((string) $supplyId, 'book_')) {
                                            $bookId = (int) str_replace('book_', '', $supplyId);
                                            $book = Book::find($bookId);
                                            if ($book !== null) {
                                                $books[] = [
                                                    'book_id' => $bookId,
                                                    'qty' => $qty,
                                                    'unit_price' => (string) $book->sale_price,
                                                    'description' => '',
                                                ];
                                            }
                                        } elseif (str_starts_with((string) $supplyId, 'item_')) {
                                            $itemId = (int) str_replace('item_', '', $supplyId);
                                            $item = Item::find($itemId);
                                            if ($item !== null) {
                                                $items[] = [
                                                    'item_id' => $itemId,
                                                    'qty' => $qty,
                                                    'unit_price' => (string) $item->sale_price,
                                                    'description' => '',
                                                ];
                                            }
                                        }
                                    }

                                    $set('items', $items);
                                    $set('books', $books);
                                } else {
                                    $set('items', []);
                                    $set('books', []);
                                }

                                $studentId = (int) ($get('student_id') ?? 0);

                                if ($studentId > 0 && $course !== null) {
                                    $blockers = self::courseEligibilityBlockers($studentId, $course);

                                    if ($blockers !== []) {
                                        // Reset the field to null so re-selecting the same blocked
                                        // course always fires afterStateUpdated again.
                                        $set('course_id', null);
                                        $set('course_batch_id', null);
                                        $set('original_price', 0);
                                        $set('price_snapshot', 0);

                                        Notification::make()
                                            ->danger()
                                            ->title(__('general.eligibility_blocked_title'))
                                            ->body(implode(' • ', $blockers))
                                            ->persistent()
                                            ->send();
                                    }
                                }
                            })
                            ->helperText(function (Get $get): ?string {
                                $studentId = (int) ($get('student_id') ?? 0);
                                $courseId = (int) ($get('course_id') ?? 0);

                                if ($studentId <= 0 || $courseId <= 0) {
                                    return null;
                                }

                                $course = Course::find($courseId);

                                if ($course === null) {
                                    return null;
                                }

                                $blockers = self::courseEligibilityBlockers($studentId, $course);

                                return $blockers !== [] ? implode(' • ', $blockers) : null;
                            }),
                        Select::make('course_batch_id')->native(false)
                            ->label(__('general.batch'))
                            ->placeholder(__('general.no_batch_selected'))
                            ->searchable()
                            ->options(function (Get $get): array {
                                $courseId = $get('course_id');

                                if ($courseId === null) {
                                    return [];
                                }

                                return CourseBatch::query()
                                    ->where('course_id', $courseId)
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(function (CourseBatch $batch): array {
                                        $seats = $batch->seats_remaining !== null
                                            ? ' — '.$batch->seats_remaining.' '.__('general.seats_remaining')
                                            : '';
                                        $statusLabel = $batch->status !== 'open'
                                            ? ' ('.__("general.batch_status_{$batch->status}").')'
                                            : '';
                                        $period = '';
                                        if ($batch->periods_label !== '—' && $batch->periods_label !== '') {
                                            $period = ' — '.$batch->periods_label;
                                        }

                                        return [
                                            $batch->id => $batch->option_label.$period.$statusLabel.$seats,
                                        ];
                                    })
                                    ->all();
                            })
                            ->required(fn (Get $get): bool => (int) ($get('course_id') ?? 0) > 0
                                && CourseBatch::query()->where('course_id', $get('course_id'))->exists())
                            ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {
                                $courseId = (int) ($get('course_id') ?? 0);
                                $batch = $state !== null
                                    ? CourseBatch::find($state)
                                    : null;
                                $course = $courseId > 0 ? Course::find($courseId) : null;

                                if ($batch !== null && $batch->start_date !== null) {
                                    $set('start_month', $batch->start_date->format('Y-m'));
                                } elseif ($batch === null && $course !== null) {
                                    $openBatch = $course->openBatch();
                                    $set('start_month', $openBatch?->start_date !== null
                                        ? $openBatch->start_date->format('Y-m')
                                        : (InstituteSetting::current()->current_month ?? now()->format('Y-m')));
                                }

                                if ($batch !== null && isset($batch->fee_schedule['price'])) {
                                    $fee = (float) $batch->fee_schedule['price'];
                                    $set('original_price', $fee);
                                    $set('price_snapshot', $fee);
                                    $set('discount_amount', 0);
                                    $set('discount_type', null);
                                } elseif ($course !== null && ! isset($batch?->fee_schedule['price'])) {
                                    $set('original_price', $course->price ?? 0);
                                    $set('price_snapshot', $course->price ?? 0);
                                }

                                if ($batch !== null) {
                                    $blockers = [];

                                    $blocker = self::batchBlockReason($batch);

                                    if ($blocker !== null) {
                                        $blockers[] = $blocker;
                                    }

                                    $studentId = (int) ($get('student_id') ?? 0);

                                    if ($studentId > 0 && $course !== null) {
                                        $verdict = app(\App\Services\EligibilityService::class)->check($studentId, $course, $batch);

                                        $blockers = array_merge($blockers, array_values(array_filter(
                                            $verdict['blockers'],
                                            fn (string $message): bool => ! in_array($message, [
                                                __('general.batch_cancelled_error'),
                                                __('general.batch_closed_error'),
                                                __('general.batch_full_error'),
                                            ], true),
                                        )));
                                    }

                                    $blockers = array_values(array_unique($blockers));

                                    if ($blockers !== []) {
                                        Notification::make()
                                            ->id('batch_block_'.uniqid('', true))
                                            ->danger()
                                            ->title(__('general.batch_registration_blocked_title'))
                                            ->body(implode(' • ', $blockers))
                                            ->send();
                                    }
                                }
                            })
                            ->helperText(function (Get $get): ?string {
                                $courseId = (int) ($get('course_id') ?? 0);

                                if ($courseId <= 0) {
                                    return null;
                                }

                                if (! CourseBatch::query()->where('course_id', $courseId)->exists()) {
                                    return __('general.no_batches_hint');
                                }

                                return self::enrollmentWindowWarning($get);
                            }),
                        TextInput::make('start_month')
                            ->label(__('general.start_month'))
                            ->hidden()
                            ->dehydratedWhenHidden()
                            ->required()
                            ->default(fn (): string => InstituteSetting::current()->current_month ?: now()->format('Y-m')),
                        TextInput::make('months_count')
                            ->label(__('general.months_count'))
                            ->hidden()
                            ->dehydratedWhenHidden()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->required()
                            ->default(1),
                        Placeholder::make('study_period')
                            ->label(__('general.study_period'))
                            ->content(function (Get $get): string {
                                $batchId = (int) ($get('course_batch_id') ?? 0);
                                $month = (string) ($get('start_month') ?? '');
                                $months = max(1, (int) ($get('months_count') ?? 1));
                                $fromBatch = $batchId > 0;

                                $start = null;
                                $end = null;

                                if ($fromBatch) {
                                    $batch = CourseBatch::find($batchId);

                                    if ($batch !== null) {
                                        $start = $batch->start_date?->format('d/m/Y');
                                        $end = $batch->end_date?->format('d/m/Y')
                                            ?? ($batch->expected_end !== null
                                                ? Carbon::parse($batch->expected_end)->format('d/m/Y')
                                                : null);
                                    }
                                }

                                if ($start === null && preg_match('/^\d{4}-\d{2}$/', $month)) {
                                    $start = Carbon::createFromFormat('Y-m', $month)->format('d/m/Y');
                                    $end = Carbon::createFromFormat('Y-m', $month)
                                        ->addMonths($months - 1)
                                        ->format('d/m/Y');
                                }

                                if ($start === null) {
                                    return __('general.study_period_unknown');
                                }

                                $period = __('general.study_period_info', [
                                    'start' => $start,
                                    'end' => $end ?? '—',
                                ]);

                                return $period;
                            })
                            ->columnSpan(1),
                        Textarea::make('notes')->label(__('general.registration_notes'))->columnSpanFull(),
                        Toggle::make('eligibility_override')
                            ->label(__('general.eligibility_override'))
                            ->helperText(__('general.eligibility_override_hint'))
                            ->live()
                            ->columnSpan(1)
                            ->visible(fn (string $operation): bool => $operation === 'create'
                                && (auth()->user()?->hasAnyRole(['admin', 'registrar']) ?? false)),
                        TextInput::make('override_reason')
                            ->label(__('general.override_reason'))
                            ->placeholder(fn (): string => __('general.required'))
                            ->required(fn (Get $get): bool => (bool) $get('eligibility_override'))
                            ->columnSpan(2)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                                && (bool) $get('eligibility_override')
                                && (auth()->user()?->hasAnyRole(['admin', 'registrar']) ?? false)),
                    ]),
                Section::make(__('general.price_snapshot'))
                    ->columns(4)
                    ->schema([
                        MoneyInput::make('original_price')
                            ->label(__('general.price'))
                            ->required()
                            ->minValue(0)
                            ->suffix(__('general.currency'))
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                $original = (float) ($get('original_price') ?? 0);
                                $discount = (float) ($get('discount_amount') ?? 0);
                                $set('price_snapshot', (string) max(0, $original - $discount));
                            })
                            ->helperText(function (Get $get): string {
                                $batchId = (int) ($get('course_batch_id') ?? 0);
                                $courseId = (int) ($get('course_id') ?? 0);

                                if ($batchId > 0) {
                                    $batch = CourseBatch::find($batchId);

                                    if ($batch !== null && isset($batch->fee_schedule['price'])) {
                                        return __('general.price_source_batch', [
                                            'batch' => number_format((float) $batch->fee_schedule['price']).' '.__('general.currency'),
                                            'course' => number_format((float) ($batch->course?->price ?? 0)).' '.__('general.currency'),
                                        ]);
                                    }
                                }

                                $course = $courseId > 0 ? Course::find($courseId) : null;

                                return $course !== null
                                    ? __('general.price_source_course', [
                                        'course' => number_format((float) $course->price).' '.__('general.currency'),
                                    ])
                                    : '';
                            }),
                        Select::make('discount_type')->native(false)
                            ->label(__('general.discount_type'))
                            ->placeholder(__('general.no_discount'))
                            ->options(fn (): array => DiscountType::query()
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (DiscountType $dt): array => [$dt->name => $dt->label])
                                ->all())
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('general.discount_type_name'))
                                    ->required()
                                    ->maxLength(100)
                                    ->unique('discount_types', 'name'),
                            ])
                            ->createOptionModalHeading(__('general.new_discount_type'))
                            ->createOptionUsing(function (array $data): string {
                                $dt = DiscountType::create([
                                    'name' => $data['name'],
                                    'is_active' => true,
                                ]);

                                return $dt->name;
                            })
                            ->live(),
                        MoneyInput::make('discount_amount')
                            ->label(__('general.discount_amount'))
                            ->default(0)
                            ->minValue(0)
                            ->suffix(__('general.currency'))
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                $original = (float) ($get('original_price') ?? 0);
                                $discount = (float) ($get('discount_amount') ?? 0);
                                $set('price_snapshot', (string) max(0, $original - $discount));
                            })
                            ->helperText(fn (Get $get): string => __('general.net_fee').': '.number_format((float) ($get('price_snapshot') ?? 0)).' '.__('general.currency')),
                        MoneyInput::make('price_snapshot')
                            ->label(__('general.price_net'))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->minValue(0)
                            ->suffix(__('general.currency')),
                    ]),
                Section::make(__('general.issue_items'))
                    ->schema([
                        Repeater::make('items')
                            ->label(__('general.issue_items'))
                            ->schema([
                                Select::make('item_id')->native(false)
                                    ->label(__('general.item_name'))
                                    ->options(fn (): array => Item::query()->where('is_active', true)->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        $set('unit_price', $state !== null ? (string) (Item::find($state)?->sale_price ?? 0) : '0');
                                    }),
                                TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->default(1)->minValue(1)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                MoneyInput::make('unit_price')->label(__('general.price'))->required()->minValue(0)->default(0)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                TextInput::make('total')
                                    ->label(__('general.total'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                TextInput::make('description')->label(__('general.description'))->maxLength(255),
                            ])
                            ->columns(4)
                            ->default([])
                            ->reorderable(false)
                            ->addActionLabel(__('general.add')),
                        Repeater::make('books')
                            ->label(__('general.books'))
                            ->schema([
                                Select::make('book_id')->native(false)
                                    ->label(__('general.book_title'))
                                    ->options(fn (): array => Book::query()
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(function (Book $book): array {
                                            $course = $book->course?->name;
                                            $suffix = $course !== null ? ' — '.$course : '';

                                            return [
                                                $book->id => $book->title.$suffix.' — '.number_format((float) $book->sale_price).' '.__('general.currency'),
                                            ];
                                        })
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        $set('unit_price', $state !== null ? (string) (Book::find($state)?->sale_price ?? 0) : '0');
                                    }),
                                TextInput::make('qty')->label(__('general.quantity'))->numeric()->maxValue(999999999999)->required()->default(1)->minValue(1)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                MoneyInput::make('unit_price')->label(__('general.price'))->required()->minValue(0)->default(0)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                TextInput::make('total')
                                    ->label(__('general.total'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Set $set, Get $get): void {
                                        $qty = (float) ($get('qty') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('total', (string) ($qty * $price));
                                    }),
                                TextInput::make('description')->label(__('general.description'))->maxLength(255),
                            ])
                            ->columns(4)
                            ->default([])
                            ->reorderable(false)
                            ->addActionLabel(__('general.add')),
                    ]),
                Section::make(__('general.initial_payment'))
                    ->columns(3)
                    ->schema([
                        MoneyInput::make('payment_amount')
                            ->label(__('general.initial_payment'))
                            ->minValue(0)
                            ->default(0)
                            ->suffix(__('general.currency'))
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $netPrice = (float) ($get('price_snapshot') ?? 0);
                                    $paid = is_string($value) ? (float) str_replace(',', '', $value) : (float) ($value ?? 0);

                                    if ($netPrice > 0 && $paid > $netPrice) {
                                        $fail(__('general.payment_exceeds_net_price', [
                                            'price' => number_format($netPrice).' '.__('general.currency'),
                                        ]));
                                    }
                                },
                            ])
                            ->helperText(function (Get $get, mixed $state): string {
                                $price = (float) ($get('price_snapshot') ?? 0);
                                $paid = is_string($state) ? (float) str_replace(',', '', $state) : (float) ($state ?? 0);

                                $words = MoneyInput::words($state);

                                if ($price > 0 && $paid > 0 && $paid < $price) {
                                    return $words.' — '.__('general.payment_less_than_price');
                                }

                                return $words;
                            }),
                        ...PaymentDetails::fields('payment_method'),
                        DatePicker::make('payment_date')
                            ->label(__('general.date'))
                            ->default(now())
                            ->displayFormat('d/m/Y'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Registration::query()->withTotals())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('student.name')->label(__('general.student'))->searchable()->weight('semibold'),
                TextColumn::make('course.name')->label(__('general.course'))->searchable()->limit(30),
                TextColumn::make('batch.name')
                    ->label(__('general.batch'))
                    ->badge()
                    ->color('gray')
                    ->limit(20)
                    ->tooltip(fn (?Registration $record): ?string => $record?->batch?->name)
                    ->toggleable(),
                TextColumn::make('start_month')->label(__('general.start_month'))->toggleable(),
                TextColumn::make('expected_end')->label(__('general.end_month'))->toggleable(),
                TextColumn::make('months_count')->label(__('general.months'))->toggleable(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        'transferred' => 'info',
                        'completed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('result')
                    ->label(__('general.result'))
                    ->badge()
                    ->formatStateUsing(fn (?Registration $record, ?string $state): string => __("general.result_{$state}"))
                    ->color(fn (?Registration $record, ?string $state): string => match ($state) {
                        'pass' => 'success',
                        'fail' => 'danger',
                        'incomplete' => 'warning',
                        'absent' => 'warning',
                        'withdrawn' => 'info',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('charged')
                    ->label(__('general.total_charge'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency'))
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('discount_amount')
                    ->label(__('general.discount_amount'))
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(function (Registration $record): string {
                        if ((float) $record->discount_amount <= 0) {
                            return '—';
                        }

                        $type = $record->discount_type !== null ? ' ('.__("general.discount_type_{$record->discount_type}").')' : '';

                        return number_format((float) $record->discount_amount).' '.__('general.currency').$type;
                    })
                    ->toggleable(),
                TextColumn::make('paid')
                    ->label(__('general.paid'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (?string $state): string => number_format((float) ($state ?? 0)).' '.__('general.currency'))
                    ->color('success')
                    ->toggleable()
                    ->summarize(Sum::make()->label(__('general.total'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (?string $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($state ?? 0)))
                    ->color(fn (?string $state): string => (float) ($state ?? 0) > 0 ? 'danger' : ((float) ($state ?? 0) < 0 ? 'success' : 'gray'))
                    ->weight('bold')
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance($state, true))
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->native(false)
                    ->label(__('general.status'))
                    ->options([
                        'active' => __('general.active'),
                        'suspended' => __('general.suspended'),
                        'completed' => __('general.completed'),
                        'closed' => __('general.closed'),
                        'transferred' => __('general.transfer'),
                    ]),
                Tables\Filters\SelectFilter::make('result')->native(false)
                    ->label(__('general.result'))
                    ->options([
                        'pending' => __('general.result_pending'),
                        'pass' => __('general.result_pass'),
                        'fail' => __('general.result_fail'),
                        'incomplete' => __('general.result_incomplete'),
                        'absent' => __('general.result_absent'),
                        'withdrawn' => __('general.result_withdrawn'),
                    ]),
                Tables\Filters\SelectFilter::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('course_batch_id')->native(false)
                    ->label(__('general.batch'))
                    ->options(fn (): array => CourseBatch::query()
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (CourseBatch $batch): array => [$batch->id => $batch->option_label])
                        ->all())
                    ->searchable(),
                Tables\Filters\Filter::make('student_phone')
                    ->label(__('general.student_phone'))
                    ->form([
                        TextInput::make('phone')
                            ->label(__('general.phone'))
                            ->tel()
                            ->placeholder('7…')
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $phone = $data['phone'] ?? null;
                        if (! is_string($phone) || $phone === '') {
                            return $query;
                        }

                        return $query->whereHas('student', fn (Builder $q): Builder => $q->where('phone', 'like', "%{$phone}%"));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reopenResult')
                    ->label(__('general.reopen_result'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.reopen_result'))
                    ->modalDescription(__('general.reopen_result_confirm'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label(__('general.reopen_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->authorize(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                    ->action(function (Registration $record, array $data): void {
                        try {
                            app(\App\Services\RegistrationService::class)->reopenResult($record, (int) Auth::id(), $data['reason']);
                            \Filament\Notifications\Notification::make()
                                ->title(__('general.reopen_result_done'))
                                ->success()
                                ->send();
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            \Filament\Notifications\Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Registration $record): bool => $record->result_finalized_at !== null),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MonthsRelationManager::class,
            TransactionsRelationManager::class,
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'view' => Pages\ViewRegistration::route('/{record}'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }

    private static function courseEligibilityBlockers(int $studentId, Course $course): array
    {
        return app(\App\Services\EligibilityService::class)->check($studentId, $course, null)['blockers'];
    }

    private static function batchBlockReason(CourseBatch $batch): ?string
    {
        if ($batch->status === 'completed') {
            return __('general.batch_completed_error');
        }

        if ($batch->status === 'cancelled') {
            return __('general.batch_cancelled_error');
        }

        if (! in_array($batch->status, ['open', 'scheduled'], true)) {
            return __('general.batch_not_open_error');
        }

        $today = now()->startOfDay();

        if ($batch->enrollment_start !== null && $today->lt($batch->enrollment_start)) {
            return __('general.batch_window_not_open_error');
        }

        if ($batch->enrollment_end !== null && $today->gt($batch->enrollment_end)) {
            return __('general.batch_window_closed_error');
        }

        if ($batch->end_date !== null && $batch->end_date->isBefore($today)) {
            return __('general.batch_study_ended_error');
        }

        if ($batch->is_full) {
            return __('general.batch_full_error');
        }

        return null;
    }

    private static function enrollmentWindowWarning(Get $get): ?string
    {
        $batchId = (int) ($get('course_batch_id') ?? 0);
        $month = (string) ($get('start_month') ?? '');

        if ($batchId <= 0 || $month === '' || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        $batch = CourseBatch::find($batchId);

        if ($batch === null || ($batch->enrollment_start === null && $batch->enrollment_end === null)) {
            return null;
        }

        $monthStart = Carbon::parse($month.'-01')->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $outsideBefore = $batch->enrollment_start !== null && $monthEnd->lt($batch->enrollment_start);
        $outsideAfter = $batch->enrollment_end !== null && $monthStart->gt($batch->enrollment_end);

        if (! $outsideBefore && ! $outsideAfter) {
            return null;
        }

        $window = $batch->enrollment_start?->format('d/m/Y') ?? __('general.enrollment_window_open');
        $window .= ' — ';
        $window .= $batch->enrollment_end?->format('d/m/Y') ?? __('general.enrollment_window_open');

        return __('general.enrollment_window_warning', ['window' => $window]);
    }
}
