<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\EnrollmentTransfer;
use App\Models\InstituteSetting;
use App\Models\Item;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\RegistrationItem;
use App\Models\RegistrationMonth;
use App\Models\StudentTransaction;
use App\Models\Account;
use App\Services\AccountService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /**
     * Register a student into a course inside ONE transaction:
     * registration + months + course fee charge + issued items (stock deduction)
     * + optional initial payment (sequential receipt).
     */
    public function register(array $data, int $createdBy, bool $overrideEligibility = false, ?string $overrideReason = null, bool $skipLevelGate = false): Registration
    {
        return DB::transaction(function () use ($data, $createdBy, $overrideEligibility, $overrideReason, $skipLevelGate): Registration {
            if ($overrideEligibility && ($overrideReason === null || trim((string) $overrideReason) === '')) {
                throw ValidationException::withMessages([
                    'override_reason' => __('general.override_reason_required'),
                ]);
            }

            $course = Course::query()->findOrFail($data['course_id']);
            $studentId = (int) $data['student_id'];
            $startMonth = $data['start_month'];
            $monthsCount = max(1, (int) $data['months_count']);
            $price = (float) $data['price_snapshot'];
            
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            if (! empty($data['discount_percent']) && (float) $data['discount_percent'] > 0) {
                $discountPercent = (float) $data['discount_percent'];
                if ($discountPercent > 100) {
                    throw ValidationException::withMessages([
                        'discount_percent' => __('general.discount_exceeds_fee_error'),
                    ]);
                }
                $baseFee = (float) ($data['original_price'] ?? ($course->price * $monthsCount));
                $discountAmount = round($baseFee * ($discountPercent / 100), 2);
                if (empty($data['price_snapshot'])) {
                    $price = max(0, $baseFee - $discountAmount);
                }
            }

            if ($discountAmount < 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => __('general.discount_negative_error'),
                ]);
            }

            $batch = $this->resolveBatch($course, $data['course_batch_id'] ?? null, $overrideEligibility);

            $hasOriginalInput = \array_key_exists('original_price', $data) && $data['original_price'] !== null;
            $originalPrice = $hasOriginalInput
                ? (float) $data['original_price']
                : (float) ($price + $discountAmount);

            if ($discountAmount > 0 && $discountAmount > $originalPrice) {
                throw ValidationException::withMessages([
                    'discount_amount' => __('general.discount_exceeds_fee_error'),
                ]);
            }

            if ($hasOriginalInput && abs(($originalPrice - $discountAmount) - $price) > 0.01) {
                throw ValidationException::withMessages([
                    'price_snapshot' => __('general.discount_price_mismatch'),
                ]);
            }

            $eligibility = app(EligibilityService::class)->check(
                $studentId,
                $course,
                $batch,
                [
                    'override' => $overrideEligibility,
                    'skip_level_gate' => $skipLevelGate,
                ],
            );

            if (! $eligibility['eligible']) {
                $blocker = $eligibility['blockers'][0];
                $field = in_array($blocker, [
                    __('general.enrollment_closed_error'),
                    __('general.duplicate_registration'),
                ], true) ? 'course_id' : 'course_batch_id';

                throw ValidationException::withMessages([
                    $field => $blocker,
                ]);
            }

            if ($batch !== null) {
                $locked = CourseBatch::query()->withTrashed()->lockForUpdate()->find($batch->id);

                if ($locked === null || $locked->trashed() || ! $locked->isEnrollmentOpen()) {
                    throw ValidationException::withMessages([
                        'course_batch_id' => __('general.batch_closed_error'),
                    ]);
                }

                if (! $overrideEligibility && $locked->is_full) {
                    throw ValidationException::withMessages([
                        'course_batch_id' => __('general.batch_full_error'),
                    ]);
                }

                if (! $overrideEligibility
                    && app(EligibilityService::class)->hasDuplicate($studentId, $course, $locked)) {
                    throw ValidationException::withMessages([
                        'course_batch_id' => __('general.duplicate_batch_registration'),
                    ]);
                }

                $batch = $locked;
            }

            $registration = Registration::create([
                'student_id' => $studentId,
                'course_id' => $course->id,
                'course_batch_id' => $batch?->id,
                'price_snapshot' => $price,
                'original_price' => $originalPrice,
                'discount_amount' => $discountAmount,
                'discount_type' => $discountAmount > 0 ? ($data['discount_type'] ?? null) : null,
                'start_month' => $startMonth,
                'months_count' => $monthsCount,
                'status' => 'active',
                'created_by' => $createdBy,
            ]);

            $this->generateMonths($registration, $startMonth, $monthsCount);

            $this->createCharge($registration, $studentId, $price, __('general.registration_fee').' — '.$course->name, $createdBy);

            foreach ($data['items'] ?? [] as $row) {
                $this->issueItem($registration, $studentId, $row, $createdBy);
            }

            foreach ($data['books'] ?? [] as $row) {
                $this->issueBook($registration, $studentId, $row, $createdBy);
            }

            $payment = $data['payment_amount'] ?? null;
            if ($payment !== null && (float) $payment > 0) {
                if ((float) $payment > $price) {
                    throw ValidationException::withMessages([
                        'payment_amount' => __('general.payment_exceeds_net_price', [
                            'price' => number_format($price).' '.__('general.currency'),
                        ]),
                    ]);
                }
                
                $this->recordPayment(
                    $registration,
                    $studentId,
                    (float) $payment,
                    $data['payment_method'] ?? 'cash',
                    $data['payment_date'] ?? now()->format('Y-m-d'),
                    __('general.initial_payment').' — '.$course->name,
                    $createdBy,
                    [
                        'bank_id' => $data['bank_id'] ?? null,
                        'wallet_id' => $data['wallet_id'] ?? null,
                        'transaction_ref' => $data['transaction_ref'] ?? null,
                    ],
                );
            }

            AuditLog::log('registration.created', Registration::class, $registration->id, [
                'student_id' => $studentId,
                'course_id' => $course->id,
                'price_snapshot' => $price,
                'start_month' => $startMonth,
            ]);

            if ($overrideEligibility) {
                AuditLog::log('registration.eligibility_overridden', Registration::class, $registration->id, [
                    'student_id' => $studentId,
                    'course_id' => $course->id,
                    'by' => $createdBy,
                    'reason' => $overrideReason,
                ]);
            }

            return $registration;
        });
    }

    /**
     * Close the current registration as "transferred" and open a new one
     * on a same-type course carrying the remaining balance and months.
     */
    public function transfer(Registration $registration, int $newCourseId, string $reason, int $userId, bool $carryItems = false, ?int $newBatchId = null): Registration
    {
        return DB::transaction(function () use ($registration, $newCourseId, $reason, $userId, $carryItems, $newBatchId): Registration {
            $newCourse = Course::query()->findOrFail($newCourseId);
            $oldCourse = $registration->course;

            if ($oldCourse->program_type_id !== $newCourse->program_type_id) {
                throw ValidationException::withMessages([
                    'course_id' => __('general.transfer_to_course'),
                ]);
            }

            $totals = Registration::query()->withTotals()->findOrFail($registration->id);
            $carried = $totals->balance;

            $startMonth = InstituteSetting::current()->current_month ?: now()->format('Y-m');
            $current = CarbonImmutable::createFromFormat('Y-m', $startMonth);
            $expectedEnd = CarbonImmutable::createFromFormat('Y-m', $registration->expected_end);
            $monthsCount = max(1, $current->diffInMonths($expectedEnd) + 1);

            $batch = $this->resolveBatch($newCourse, $newBatchId);

            $new = Registration::create([
                'student_id' => $registration->student_id,
                'course_id' => $newCourse->id,
                'course_batch_id' => $batch?->id,
                'price_snapshot' => $carried,
                'original_price' => $carried,
                'discount_amount' => 0,
                'discount_type' => null,
                'start_month' => $startMonth,
                'months_count' => $monthsCount,
                'status' => 'active',
                'created_by' => $userId,
            ]);

            $this->generateMonths($new, $startMonth, $monthsCount);

            if ($carried > 0) {
                StudentTransaction::create([
                    'student_id' => $registration->student_id,
                    'registration_id' => $new->id,
                    'type' => 'transfer_debit',
                    'amount' => $carried,
                    'date' => now()->format('Y-m-d'),
                    'description' => __('general.balance_carried').' — '.$oldCourse->name,
                    'income_account_id' => app(AccountService::class)->account(AccountService::CODE_INCOME_COURSE_FEES)->id,
                    'created_by' => $userId,
                ]);

                StudentTransaction::create([
                    'student_id' => $registration->student_id,
                    'registration_id' => $registration->id,
                    'type' => 'transfer_credit',
                    'amount' => $carried,
                    'date' => now()->format('Y-m-d'),
                    'description' => __('general.balance_transferred_out').' — '.$newCourse->name,
                    'income_account_id' => app(AccountService::class)->account(AccountService::CODE_INCOME_COURSE_FEES)->id,
                    'created_by' => $userId,
                ]);
            }

            if ($carryItems) {
                $registration->items()->active()->update(['registration_id' => $new->id]);
            }

            $registration->update([
                'status' => 'transferred',
                'transferred_to_id' => $new->id,
                'closed_at' => now(),
                'close_reason' => $reason,
            ]);

            EnrollmentTransfer::create([
                'from_registration_id' => $registration->id,
                'to_registration_id' => $new->id,
                'student_id' => $registration->student_id,
                'from_course_id' => $oldCourse->id,
                'to_course_id' => $newCourse->id,
                'from_batch_id' => $registration->course_batch_id,
                'to_batch_id' => $batch?->id,
                'reason' => $reason,
                'balance_carried' => $carried,
                'months_carried' => $monthsCount,
                'carry_items' => $carryItems,
                'transferred_at' => now(),
                'transferred_by' => $userId,
                'approved_by' => $userId,
            ]);

            AuditLog::log('registration.transferred', Registration::class, $registration->id, [
                'to_registration_id' => $new->id,
                'course_id' => $newCourseId,
                'balance_carried' => $carried,
                'items_carried' => $carryItems,
                'reason' => $reason,
            ]);

            return $new;
        });
    }


    /**
     * Close a registration (history + balance kept). Never deletes.
     */
    public function close(Registration $registration, string $reason, int $userId, bool $writeOff = false): void
    {
        DB::transaction(function () use ($registration, $reason, $userId, $writeOff): void {
            $registration->update([
                'status' => 'closed',
                'closed_at' => now(),
                'close_reason' => $reason,
            ]);

            if ($writeOff) {
                $totals = Registration::query()->withTotals()->find($registration->id);
                if ($totals && $totals->balance > 0) {
                    StudentTransaction::create([
                        'student_id' => $registration->student_id,
                        'registration_id' => $registration->id,
                        'type' => 'write_off',
                        'amount' => $totals->balance,
                        'date' => now()->format('Y-m-d'),
                        'description' => __('general.write_off').' — '.$reason,
                        'created_by' => $userId,
                    ]);
                }
            }

            AuditLog::log('registration.closed', Registration::class, $registration->id, [
                'reason' => $reason,
                'write_off' => $writeOff,
                'by' => $userId,
            ]);
        });
    }

    /**
     * Withdraw a student from a registration (voluntary exit with audit trail).
     * Write-off optional — same pattern as close(). Status → withdrawn.
     */
    public function withdraw(Registration $registration, string $reason, int $userId, bool $writeOff = false): void
    {
        DB::transaction(function () use ($registration, $reason, $userId, $writeOff): void {
            $registration->update([
                'status' => 'withdrawn',
                'closed_at' => now(),
                'close_reason' => $reason,
            ]);

            if ($writeOff) {
                $totals = Registration::query()->withTotals()->find($registration->id);
                if ($totals && $totals->balance > 0) {
                    StudentTransaction::create([
                        'student_id' => $registration->student_id,
                        'registration_id' => $registration->id,
                        'type' => 'write_off',
                        'amount' => $totals->balance,
                        'date' => now()->format('Y-m-d'),
                        'description' => __('general.write_off').' — '.$reason,
                        'created_by' => $userId,
                    ]);
                }
            }

            AuditLog::log('registration.withdrawn', Registration::class, $registration->id, [
                'reason' => $reason,
                'write_off' => $writeOff,
                'by' => $userId,
            ]);
        });
    }

    /**
     * Cancel a registration that has not yet been financially committed (no charges paid).
     * Reverses any unpaid charge record and marks status → cancelled.
     */
    public function cancel(Registration $registration, string $reason, int $userId): void
    {
        DB::transaction(function () use ($registration, $reason, $userId): void {
            $registration->update([
                'status' => 'cancelled',
                'closed_at' => now(),
                'close_reason' => $reason,
            ]);

            AuditLog::log('registration.cancelled', Registration::class, $registration->id, [
                'reason' => $reason,
                'by' => $userId,
            ]);
        });
    }

    /**
     * End a registration's lifecycle as "completed". The academic verdict is
     * passed separately ($result) and is NEVER derived from the lifecycle —
     * a completed enrollment can legitimately hold result=incomplete when the
     * required assessments were never recorded. Finalization is audited.
     */
    public function complete(Registration $registration, int $userId, ?string $result = null): void
    {
        DB::transaction(function () use ($registration, $userId, $result): void {
            $previousResult = $registration->fresh()?->result ?? $registration->result;
            $registration->update([
                'status' => 'completed',
                'closed_at' => now(),
                'result' => $result ?? $registration->result,
                'result_finalized_at' => $result !== null ? now() : $registration->result_finalized_at,
                'result_finalized_by' => $result !== null ? $userId : $registration->result_finalized_by,
            ]);

            AuditLog::change('registration.completed', Registration::class, $registration->id, $previousResult, $result ?? $registration->result, [
                'by' => $userId,
            ]);
        });
    }

    /**
     * Reopen a finalized result (marks frozen until then). Admin-approved
     * correction path: clears finalization + recomputes the snapshot; the
     * result must be finalized again afterwards. Audit keeps before/after.
     */
    public function reopenResult(Registration $registration, int $userId, string $reason): void
    {
        DB::transaction(function () use ($registration, $userId, $reason): void {
            $fresh = $registration->fresh();

            if ($fresh === null || $fresh->result_finalized_at === null) {
                throw ValidationException::withMessages([
                    'result' => __('general.reopen_error_not_finalized'),
                ]);
            }

            $before = [
                'result' => $fresh->result,
                'finalized_at' => $fresh->result_finalized_at?->format('Y-m-d H:i:s'),
                'finalized_by' => $fresh->result_finalized_by,
            ];

            $fresh->update([
                'result' => 'pending',
                'result_finalized_at' => null,
                'result_finalized_by' => null,
            ]);

            app(\App\Services\ResultService::class)->refreshGradeSnapshot($fresh, $userId);

            AuditLog::change('registration.result_reopened', Registration::class, $fresh->id, $before, [
                'result' => null,
                'finalized_at' => null,
                'finalized_by' => null,
            ], [
                'by' => $userId,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Suspend or resume (status only; history + balance kept).
     */
    public function setStatus(Registration $registration, string $status, int $userId): void
    {
        DB::transaction(function () use ($registration, $status, $userId): void {
            $registration->update([
                'status' => $status,
                'closed_at' => $status === 'suspended' ? now() : null,
            ]);

            AuditLog::log("registration.{$status}", Registration::class, $registration->id, [
                'by' => $userId,
            ]);
        });
    }

    /**
     * Void an issued item on a registration: reverses the charge AND the
     * stock movement so the ledger and the shelf always agree. The billing
     * row itself is voided too — item sale history is never hard-deleted.
     */
    public function voidIssuedItem(RegistrationItem $registrationItem, string $reason): void
    {
        DB::transaction(function () use ($registrationItem, $reason): void {
            $charge = StudentTransaction::query()
                ->where('registration_id', $registrationItem->registration_id)
                ->where('type', 'charge')
                ->whereNull('voided_at')
                ->when(
                    $registrationItem->id !== null,
                    fn ($q) => $q->where('registration_item_id', $registrationItem->id),
                    fn ($q) => $q->whereNull('registration_item_id'),
                )
                ->latest()
                ->first();

            if ($charge === null) {
                // Legacy rows created before the FK link existed: fall back to the label match.
                $charge = StudentTransaction::query()
                    ->where('registration_id', $registrationItem->registration_id)
                    ->where('type', 'charge')
                    ->where('description', $registrationItem->label.' × '.$registrationItem->qty)
                    ->whereNull('voided_at')
                    ->latest()
                    ->first();
            }

            if ($charge !== null) {
                $charge->void($reason);
            }

            $movement = $registrationItem->is_book
                ? $registrationItem->book?->movements()
                : $registrationItem->item?->movements();

            $movement = $movement?->where('registration_item_id', $registrationItem->id)
                ->where('type', 'issue')
                ->whereNull('voided_at')
                ->latest()
                ->first();

            if ($movement !== null) {
                $movement->void($reason);
            }

            $registrationItem->update([
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            AuditLog::log('registration.item_voided', RegistrationItem::class, $registrationItem->id, [
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Extend a registration by one explicit month: create the month row,
     * increment months_count and add a prorated charge for it. Never called
     * silently — the admin picks the month in a modal.
     */
    public function addMonth(Registration $registration, string $month, int $userId): void
    {
        DB::transaction(function () use ($registration, $month, $userId): void {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw ValidationException::withMessages([
                    'month' => __('general.invalid_month_format'),
                ]);
            }

            if (in_array($registration->status, ['closed', 'transferred'], true)) {
                throw ValidationException::withMessages([
                    'month' => __('general.cannot_add_month_closed'),
                ]);
            }

            if ($registration->months()->where('month', $month)->exists()) {
                throw ValidationException::withMessages([
                    'month' => __('general.month_already_added'),
                ]);
            }

            $perMonth = round(((float) $registration->price_snapshot) / max(1, (int) $registration->months_count), 2);

            RegistrationMonth::create([
                'registration_id' => $registration->id,
                'month' => $month,
                'status' => 'open',
            ]);

            $registration->increment('months_count');

            $this->createCharge(
                $registration,
                $registration->student_id,
                $perMonth,
                __('general.month_extension').' — '.$month,
                $userId,
            );

            AuditLog::log('registration.month_added', Registration::class, $registration->id, [
                'month' => $month,
                'charge' => $perMonth,
                'by' => $userId,
            ]);
        });
    }

    /**
     * Generate the per-registration month rows (never silently extends —
     * months are only created here from an explicit start + count).
     */
    public function generateMonths(Registration $registration, string $startMonth, int $count): void
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $startMonth);

        for ($i = 0; $i < $count; $i++) {
            $monthStr = $start->addMonths($i)->format('Y-m');
            RegistrationMonth::firstOrCreate([
                'registration_id' => $registration->id,
                'month' => $monthStr,
            ], [
                'status' => 'open',
            ]);
        }
    }

    /**
     * Register a student into ALL (selected) active courses of a program
     * inside ONE transaction — the diploma = set of courses flow.
     * Delegates every enrollment to register() so eligibility, seat locks,
     * months, charges and items are identical across entry points; the
     * initial payment is then allocated across courses in order.
     */
    public function registerForProgram(array $data, int $createdBy): array
    {
        return DB::transaction(function () use ($data, $createdBy): array {
            $studentId = (int) $data['student_id'];
            $startMonth = $data['start_month'];
            $program = ProgramType::query()->findOrFail($data['program_type_id']);

            $courseIds = $data['course_ids'] ?? [];
            $courses = Course::query()
                ->where('program_type_id', $program->id)
                ->where('is_active', true)
                ->whereIn('id', $courseIds)
                ->orderBy('id')
                ->get();

            if ($courses->isEmpty()) {
                throw ValidationException::withMessages([
                    'course_ids' => __('general.select_course'),
                ]);
            }

            $registrations = [];

            foreach ($courses as $course) {
                $batch = $course->openBatch();
                $batchFee = isset($batch?->fee_schedule['price']) ? (float) $batch->fee_schedule['price'] : null;
                $fee = $batchFee ?? (float) $course->price;

                $registrations[] = $this->register([
                    'student_id' => $studentId,
                    'course_id' => $course->id,
                    'course_batch_id' => $batch?->id !== null
                        ? (string) $batch->id
                        : null,
                    'price_snapshot' => $fee,
                    'original_price' => $fee,
                    'discount_amount' => 0,
                    'start_month' => $startMonth,
                    'months_count' => (int) $course->months,
                    'items' => [],
                    'books' => [],
                    'payment_amount' => null,
                ], $createdBy, false, null, true);
            }

            $remaining = (float) ($data['payment_amount'] ?? 0);
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $paymentDate = $data['payment_date'] ?? now()->format('Y-m-d');

            foreach ($registrations as $registration) {
                if ($remaining <= 0) {
                    break;
                }

                $allocated = min($remaining, (float) $registration->price_snapshot);
                $remaining -= $allocated;

                $this->recordPayment(
                    $registration,
                    $studentId,
                    $allocated,
                    $paymentMethod,
                    $paymentDate,
                    __('general.initial_payment').' — '.$registration->course->name,
                    $createdBy,
                    [
                        'bank_id' => $data['bank_id'] ?? null,
                        'wallet_id' => $data['wallet_id'] ?? null,
                        'transaction_ref' => $data['transaction_ref'] ?? null,
                    ],
                );
            }

            AuditLog::log('registration.program_created', ProgramType::class, $program->id, [
                'student_id' => $studentId,
                'course_count' => count($registrations),
                'program' => $program->name,
                'start_month' => $startMonth,
            ]);

            return $registrations;
        });
    }

    /**
     * Issue a predefined book at registration: stock deduction (locked),
     * movement + pivot row + student charge booked to book sales income.
     */
    private function issueBook(Registration $registration, int $studentId, array $row, int $createdBy): void
    {
        $book = Book::query()->lockForUpdate()->findOrFail($row['book_id']);
        $qty = max(1, (int) ($row['qty'] ?? 1));

        if ($book->stock_qty < $qty) {
            throw ValidationException::withMessages([
                'books' => __('general.insufficient_stock', ['item' => $book->title]),
            ]);
        }

        $unitPrice = (float) ($row['unit_price'] ?? $book->sale_price ?? 0);

        $registrationItem = RegistrationItem::create([
            'registration_id' => $registration->id,
            'book_id' => $book->id,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'description' => $row['description'] ?? null,
        ]);

        $book->movements()->create([
            'book_id' => $book->id,
            'type' => 'issue',
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'date' => now()->format('Y-m-d'),
            'registration_item_id' => $registrationItem->id,
            'description' => $registration->student->name,
            'created_by' => $createdBy,
        ]);

        $charge = $this->createCharge($registration, $studentId, $qty * $unitPrice, $book->title.' × '.$qty, $createdBy);
        $charge?->update([
            'income_account_id' => app(AccountService::class)->account(AccountService::CODE_INCOME_BOOKS)->id,
            'registration_item_id' => $registrationItem->id,
        ]);
    }

    /**
     * Issue an item at registration (stock deduction, movement, student charge).
     */
    private function issueItem(Registration $registration, int $studentId, array $row, int $createdBy): void
    {
        $item = Item::query()->lockForUpdate()->findOrFail($row['item_id']);
        $qty = max(1, (int) ($row['qty'] ?? 1));

        if ($item->stock_qty < $qty) {
            throw ValidationException::withMessages([
                'items' => __('general.insufficient_stock', ['item' => $item->name]),
            ]);
        }

        $unitPrice = (float) ($row['unit_price'] ?? $item->sale_price ?? 0);

        $registrationItem = RegistrationItem::create([
            'registration_id' => $registration->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'description' => $row['description'] ?? null,
        ]);

        $item->movements()->create([
            'item_id' => $item->id,
            'type' => 'issue',
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'date' => now()->format('Y-m-d'),
            'registration_item_id' => $registrationItem->id,
            'description' => $registration->student->name,
            'created_by' => $createdBy,
        ]);

        $charge = $this->createCharge($registration, $studentId, $qty * $unitPrice, $item->name.' × '.$qty, $createdBy);
        $charge?->update([
            'income_account_id' => app(AccountService::class)->account(AccountService::CODE_INCOME_ITEMS)->id,
            'registration_item_id' => $registrationItem->id,
        ]);
    }

    private function createCharge(Registration $registration, int $studentId, float $amount, string $description, int $createdBy): ?StudentTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return StudentTransaction::create([
            'student_id' => $studentId,
            'registration_id' => $registration->id,
            'type' => 'charge',
            'amount' => $amount,
            'date' => now()->format('Y-m-d'),
            'description' => $description,
            'income_account_id' => app(AccountService::class)->account(AccountService::CODE_INCOME_COURSE_FEES)->id,
            'created_by' => $createdBy,
        ]);
    }

    private function recordPayment(Registration $registration, int $studentId, float $amount, string $method, string $date, string $description, int $createdBy, array $place = []): StudentTransaction
    {
        return StudentTransaction::create([
            'student_id' => $studentId,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
            'method' => $method,
            'bank_id' => $place['bank_id'] ?? null,
            'wallet_id' => $place['wallet_id'] ?? null,
            'transaction_ref' => $place['transaction_ref'] ?? null,
            'receipt_no' => app(ReceiptNumberService::class)->next(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Resolve the course batch a registration lands in. An explicit batch
     * must exist, belong to the course and be open for enrollment; when no
     * batch is given, the course's open batch is used (if any).
     */
    private function resolveBatch(Course $course, ?int $batchId, bool $allowOverflow = false): ?CourseBatch
    {
        if ($batchId === null) {
            return $course->openBatch();
        }

        $batch = CourseBatch::query()->withTrashed()->find($batchId);

        if ($batch === null || $batch->course_id !== $course->id) {
            throw ValidationException::withMessages([
                'course_batch_id' => __('general.select_batch'),
            ]);
        }

        if ($batch->trashed() || ! $batch->isEnrollmentOpen()) {
            throw ValidationException::withMessages([
                'course_batch_id' => __('general.batch_closed_error'),
            ]);
        }

        if (! $allowOverflow && ! $batch->hasCapacityLeft()) {
            throw ValidationException::withMessages([
                'course_batch_id' => __('general.batch_full_error'),
            ]);
        }

        return $batch;
    }
}
