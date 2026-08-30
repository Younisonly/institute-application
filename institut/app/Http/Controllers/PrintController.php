<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\EnrollmentTransfer;
use App\Models\OtherPeopleTransaction;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Services\ReportService;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class PrintController extends Controller
{
    public function receipt(StudentTransaction $transaction)
    {
        abort_unless(in_array($transaction->type, ['payment', 'refund']) && $transaction->receipt_no !== null, 404);

        $registration = $transaction->registration;
        $balance = null;
        if ($registration !== null) {
            $balance = (float) $registration->transactions()
                ->whereNull('voided_at')
                ->selectRaw("SUM(CASE WHEN type='charge' THEN amount WHEN type IN ('payment','refund') THEN -amount ELSE 0 END) as bal")
                ->value('bal');
        }

        return view('prints.receipt', [
            'transaction' => $transaction->load(['student', 'registration.course', 'registration.batch.periods']),
            'balance'     => $balance,
            'autoPrint'   => true,
        ]);
    }

    public function statement(Student $student)
    {
        $transactions = $student->transactions()
            ->with('registration.course')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $rows = $transactions->map(function (StudentTransaction $transaction) use (&$running): array {
            $running += $transaction->voided_at !== null
                ? 0
                : (in_array($transaction->type, ['payment', 'transfer_credit']) ? -1 * (float) $transaction->amount : (float) $transaction->amount);

            return [
                'transaction' => $transaction,
                'running'     => $running,
            ];
        });

        return view('prints.statement', [
            'student'  => $student->load('registrations.course'),
            'rows'     => $rows,
            'balance'  => (float) $transactions->whereNull('voided_at')->sum(fn ($t) => in_array($t->type, ['payment', 'transfer_credit']) ? -1 * (float) $t->amount : (float) $t->amount),
        ]);
    }

    public function accountStatement(Request $request)
    {
        $partyType = $request->string('party_type')->toString();

        if ($partyType !== '') {
            $data = $request->validate([
                'party_type' => 'required|in:student,staff,supplier,other',
                'party_id' => 'required|integer',
                'staff_statement_mode' => 'nullable|string|in:advances,comprehensive',
                'from' => 'nullable|date',
                'to' => 'nullable|date',
            ]);

            $statement = app(ReportService::class)->partyLedger(
                $data['party_type'],
                (int) $data['party_id'],
                ! empty($data['from']) ? \Illuminate\Support\Carbon::parse($data['from']) : null,
                ! empty($data['to']) ? \Illuminate\Support\Carbon::parse($data['to']) : null,
                $data['staff_statement_mode'] ?? 'advances',
            );

            return view('prints.party-statement', [
                'statement' => $statement,
            ]);
        }

        $data = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $statement = app(ReportService::class)->accountStatement(
            \App\Models\Account::findOrFail($data['account_id']),
            ! empty($data['from']) ? \Illuminate\Support\Carbon::parse($data['from']) : null,
            ! empty($data['to']) ? \Illuminate\Support\Carbon::parse($data['to']) : null,
        );

        return view('prints.account-statement', [
            'statement' => $statement,
        ]);
    }

    public function dailyCash(Request $request)
    {
        $date = $request->validate(['date' => 'required|date'])['date'];

        return view('prints.daily-cash', [
            'report' => app(ReportService::class)->dailyCash($date),
        ]);
    }

    public function profit(Request $request)
    {
        $month = $request->validate(['month' => 'required|date_format:Y-m'])['month'];

        return view('prints.profit', [
            'report' => app(ReportService::class)->profit($month),
        ]);
    }

    public function arrears()
    {
        $rows = app(ReportService::class)->arrears();

        return view('prints.arrears', [
            'rows' => $rows,
            'total' => (float) $rows->sum(fn ($student): float => $student->balance),
        ]);
    }

    public function registrationList(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'nullable|integer',
            'course_batch_id' => 'nullable|integer|exists:course_batches,id',
            'status' => 'nullable|in:active,suspended,closed,transferred',
        ]);

        $rows = app(ReportService::class)->registrationList(
            $data['course_id'] ?? null,
            $data['course_batch_id'] ?? null,
            $data['status'] ?? null,
        );

        return view('prints.registration-list', [
            'rows' => $rows,
            'totalBalance' => (float) $rows->sum(fn ($r): float => $r->balance),
        ]);
    }

    public function registrationListExcel(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'nullable|integer',
            'course_batch_id' => 'nullable|integer|exists:course_batches,id',
            'status' => 'nullable|in:active,suspended,closed,transferred',
        ]);

        $rows = app(ReportService::class)->registrationList(
            $data['course_id'] ?? null,
            $data['course_batch_id'] ?? null,
            $data['status'] ?? null,
        );

        $currency = __('general.currency');
        $headerStyle = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToBrowser('registrations.xlsx');
        $writer->addRow(Row::fromValues([
            __('general.student'),
            __('general.phone'),
            __('general.course'),
            __('general.period'),
            __('general.start_month'),
            __('general.end_month'),
            __('general.status'),
            __('general.balance'),
        ], $headerStyle));

        foreach ($rows as $registration) {
            $writer->addRow(Row::fromValues([
                $registration->student->name,
                $registration->student->phone ?? '',
                $registration->course->name,
                $registration->batch?->periods_label ?? '—',
                $registration->start_month,
                $registration->expected_end,
                __("general.{$registration->status}"),
                number_format($registration->balance)." {$currency}",
            ]));
        }

        $writer->close();

        return response('');
    }

    public function idCard(Registration $registration)
    {
        $registration->load(['student', 'course', 'batch.periods']);

        return view('prints.id-card', [
            'registration' => $registration,
        ]);
    }

    public function idCardsCourse(Course $course)
    {
        $registrations = $course->registrations()
            ->whereIn('status', ['active', 'suspended'])
            ->with(['student', 'course', 'batch.periods'])
            ->orderBy('student_id')
            ->get();

        return view('prints.id-cards-bulk', [
            'course' => $course,
            'registrations' => $registrations->chunk(4),
        ]);
    }

    public function paymentHistory(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'student_id' => 'nullable|integer|exists:students,id',
            'registration_id' => 'nullable|integer|exists:registrations,id',
        ]);

        $rows = app(ReportService::class)->paymentHistory(
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['student_id'] ?? null,
            $data['registration_id'] ?? null,
        );

        return view('prints.payment-history', [
            'rows' => $rows,
            'total' => (float) $rows->sum('amount'),
            'from' => $data['from'] ?? '—',
            'to' => $data['to'] ?? '—',
        ]);
    }

    public function paymentHistoryExcel(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'student_id' => 'nullable|integer|exists:students,id',
            'registration_id' => 'nullable|integer|exists:registrations,id',
        ]);

        $rows = app(ReportService::class)->paymentHistory(
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['student_id'] ?? null,
            $data['registration_id'] ?? null,
        );

        $currency = __('general.currency');
        $headerStyle = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToBrowser('payment-history.xlsx');
        $writer->addRow(Row::fromValues([
            __('general.date'),
            __('general.student'),
            __('general.course'),
            __('general.receipt_no'),
            __('general.method'),
            __('general.amount'),
        ], $headerStyle));

        foreach ($rows as $transaction) {
            $writer->addRow(Row::fromValues([
                $transaction->date->format('d/m/Y'),
                $transaction->student?->name ?? '',
                $transaction->registration?->course?->name ?? '',
                $transaction->receipt_no ?? '',
                __("general.method_{$transaction->method}"),
                number_format((float) $transaction->amount)." {$currency}",
            ]));
        }

        $writer->close();

        return response('');
    }

    public function stockInventory(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|in:all,items,books',
            'category_id' => 'nullable|integer|exists:item_categories,id',
            'low_stock_only' => 'nullable|boolean',
        ]);

        $rows = app(ReportService::class)->inventory(
            $data['type'] ?? 'all',
            $data['category_id'] ?? null,
            (bool) ($data['low_stock_only'] ?? false),
        );

        return view('prints.stock-inventory', [
            'rows' => $rows,
            'totalValue' => (float) $rows->sum('buy_value'),
        ]);
    }

    public function enrollment(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'course_id' => 'nullable|integer|exists:courses,id',
            'course_batch_id' => 'nullable|integer|exists:course_batches,id',
            'status' => 'nullable|in:active,suspended,closed,transferred',
        ]);

        $report = app(ReportService::class)->enrollment(
            $data['month'] ?? null,
            $data['course_id'] ?? null,
            $data['course_batch_id'] ?? null,
            $data['status'] ?? null,
        );

        return view('prints.enrollment', [
            'report' => $report,
        ]);
    }

    public function salarySheet(Request $request)
    {
        $month = $request->validate(['month' => 'required|date_format:Y-m'])['month'];

        return view('prints.salary-sheet', [
            'report' => app(ReportService::class)->salarySheet($month),
        ]);
    }

    public function otherVoucher(OtherPeopleTransaction $transaction)
    {
        abort_unless($transaction->receipt_no !== null, 404);

        return view('prints.other-voucher', [
            'transaction' => $transaction->load(['person', 'incomeCategory', 'expenseCategory']),
            'autoPrint' => true,
        ]);
    }

    public function supplierVoucher(SupplierTransaction $transaction)
    {
        abort_unless($transaction->receipt_no !== null, 404);

        return view('prints.supplier-voucher', [
            'transaction' => $transaction->load(['supplier', 'bank', 'wallet']),
            'autoPrint' => true,
        ]);
    }

    public function certificate(Registration $registration)
    {
        $registration->load(['student', 'course', 'batch', 'course.teacher']);

        abort_unless($registration->grades !== [] && ($registration->grades['passed'] ?? false), 404);

        return view('prints.certificate', [
            'registration' => $registration,
            'autoPrint' => true,
        ]);
    }

    public function certificatesBatch(CourseBatch $batch)
    {
        $registrations = $batch->registrations()
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(grades, "$.passed")) = "1"')
            ->with(['student', 'course', 'batch', 'course.teacher'])
            ->orderBy('student_id')
            ->get();

        return view('prints.certificates-bulk', [
            'batch' => $batch,
            'registrations' => $registrations,
            'autoPrint' => true,
        ]);
    }

    public function programCertificate(\App\Models\Certificate $certificate)
    {
        abort_if($certificate->isVoided(), 404);

        return view('prints.certificate-program', [
            'certificate' => $certificate->load(['student', 'program', 'issuedBy']),
            'autoPrint' => true,
        ]);
    }

    public function enrollmentTransfer(EnrollmentTransfer $transfer)
    {
        return view('prints.enrollment-transfer', [
            'transfer' => $transfer->load([
                'student',
                'fromCourse',
                'toCourse',
                'fromBatch',
                'toBatch',
                'transferredBy',
                'approvedBy',
            ]),
            'autoPrint' => true,
        ]);
    }

    public function certificateVerify(Request $request)
    {
        $code = trim((string) $request->input('code', ''));

        $certificate = $code === ''
            ? null
            : \App\Models\Certificate::query()
                ->with(['student', 'program'])
                ->where('verification_code', $code)
                ->first();

        return view('prints.certificate-verify', [
            'certificate' => $certificate,
            'code' => $code,
            'autoPrint' => false,
        ]);
    }

    public function batchMarks(CourseBatch $batch)
    {
        $registrations = $batch->registrations()
            ->with(['student', 'course'])
            ->orderBy('student_id')
            ->get();

        return view('prints.batch-marks', [
            'batch' => $batch,
            'registrations' => $registrations,
        ]);
    }

    public function batchMarksExcel(CourseBatch $batch)
    {
        $registrations = $batch->registrations()
            ->with(['student'])
            ->orderBy('student_id')
            ->get();

        $currency = __('general.currency');
        $headerStyle = (new Style())->setFontBold();
        $writer = new Writer();
        $writer->openToBrowser('marks-'.($batch->name ?? $batch->id).'.xlsx');
        $writer->addRow(Row::fromValues([
            __('general.student'),
            __('general.phone'),
            __('general.mark'),
            __('general.result'),
        ], $headerStyle));

        foreach ($registrations as $registration) {
            $writer->addRow(Row::fromValues([
                $registration->student->name,
                $registration->student->phone ?? '',
                $registration->grade_total === null ? '' : number_format($registration->grade_total),
                $registration->grades['grade'] ?? (__('general.not_graded')),
            ]));
        }

        $writer->close();

        return response('');
    }

    public function shiftVoucher(\App\Models\CashboxShift $shift)
    {
        return view('prints.shift-closing-voucher', [
            'shift' => $shift->load(['cashbox', 'user', 'closedBy']),
            'autoPrint' => true,
        ]);
    }
}
