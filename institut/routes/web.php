<?php

use App\Http\Controllers\PrintController;
use App\Http\Controllers\StaffDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::post('/locale/switch', function (\Illuminate\Http\Request $request) {
    $locale = $request->input('locale');

    if (in_array($locale, ['ar', 'en'], true)) {
        session(['locale' => $locale]);
        app()->setLocale($locale);

        return back()->withCookie(cookie()->forever('locale', $locale));
    }

    return back();
})->name('locale.switch');

Route::get('/certificates/verify', [PrintController::class, 'certificateVerify'])
    ->name('certificates.verify');

Route::middleware(['auth', 'role:admin|accountant'])->group(function (): void {
    Route::get('/staff-documents/{document}/download', [StaffDocumentController::class, 'download'])
        ->name('staff-documents.download');

    Route::get('/certificates/register/{certificate}/print', [PrintController::class, 'programCertificate'])
        ->name('certificates.register.print');

    Route::get('/reports/daily-cash/print', [PrintController::class, 'dailyCash'])
        ->name('reports.daily-cash.print');

    Route::get('/reports/profit/print', [PrintController::class, 'profit'])
        ->name('reports.profit.print');

    Route::get('/reports/arrears/print', [PrintController::class, 'arrears'])
        ->name('reports.arrears.print');

    Route::get('/reports/salary-sheet/print', [PrintController::class, 'salarySheet'])
        ->name('reports.salary-sheet.print');

    Route::get('/reports/payment-history/print', [PrintController::class, 'paymentHistory'])
        ->name('reports.payment-history.print');

    Route::get('/reports/payment-history/export', [PrintController::class, 'paymentHistoryExcel'])
        ->name('reports.payment-history.export');

    Route::get('/reports/stock-inventory/print', [PrintController::class, 'stockInventory'])
        ->name('reports.stock-inventory.print');

    Route::get('/reports/enrollment/print', [PrintController::class, 'enrollment'])
        ->name('reports.enrollment.print');

    Route::get('/reports/account-statement/print', [PrintController::class, 'accountStatement'])
        ->name('reports.account-statement.print');
});

Route::middleware(['auth', 'role:admin|registrar'])->group(function (): void {
    Route::get('/enrollment-transfers/{transfer}/print', [PrintController::class, 'enrollmentTransfer'])
        ->name('enrollment-transfers.print');
});

Route::middleware(['auth', 'role:admin|accountant|registrar'])->group(function (): void {
    Route::get('/receipts/{transaction}/print', [PrintController::class, 'receipt'])
        ->name('receipts.print');

    Route::get('/students/{student}/statement', [PrintController::class, 'statement'])
        ->name('students.statement');

    Route::get('/id-cards/{registration}/print', [PrintController::class, 'idCard'])
        ->name('id-cards.print');

    Route::get('/id-cards/course/{course}/print', [PrintController::class, 'idCardsCourse'])
        ->name('id-cards.course.print');

    Route::get('/other-vouchers/{transaction}/print', [PrintController::class, 'otherVoucher'])
        ->name('vouchers.other.print');

    Route::get('/supplier-vouchers/{transaction}/print', [PrintController::class, 'supplierVoucher'])
        ->name('vouchers.supplier.print');

    Route::get('/shifts/{shift}/print', [PrintController::class, 'shiftVoucher'])
        ->name('shifts.print');
});

Route::middleware(['auth', 'role:admin|accountant|registrar|teacher'])->group(function (): void {
    Route::get('/reports/registrations/print', [PrintController::class, 'registrationList'])
        ->name('reports.registrations.print');

    Route::get('/reports/registrations/export', [PrintController::class, 'registrationListExcel'])
        ->name('reports.registrations.export');

    Route::get('/certificates/{registration}/print', [PrintController::class, 'certificate'])
        ->name('certificates.print');

    Route::get('/certificates/course-batches/{batch}/print', [PrintController::class, 'certificatesBatch'])
        ->name('certificates.batch.print');

    Route::get('/marks/course-batches/{batch}/print', [PrintController::class, 'batchMarks'])
        ->name('marks.batch.print');

    Route::get('/marks/course-batches/{batch}/export', [PrintController::class, 'batchMarksExcel'])
        ->name('marks.batch.export');
});