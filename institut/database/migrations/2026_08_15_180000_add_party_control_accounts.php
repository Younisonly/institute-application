<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Party subsidiary-ledger control accounts (ACC-012).
 *
 * Students, staff, suppliers and other parties are NOT accounts — each is a
 * row in a SUBSIDIARY LEDGER whose aggregate appears in the chart of accounts
 * under a CONTROL account:
 *
 *   1410 Student Receivables   (asset)     <- student statements (NEW)
 *   1420 Staff Advances        (asset)     <- staff advances register (exists, journal-posted)
 *   1430 Other-People Balances (asset)     <- other-people in/out register (NEW)
 *   2110 Supplier Payable      (liability) <- supplier purchases vs payments (exists, journal-posted)
 *
 * 1420 and 2110 already receive journal lines, so their balances come from the
 * journal. 1410/1430 have NO journal lines (charges are cash-basis billing
 * rows), so their balance is derived from the subsidiary register via
 * ReportService::controlAccountBalance.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'code' => '1410',
                'name_ar' => 'ذمم الطلاب',
                'name_en' => 'Student Receivables',
                'type' => 'asset',
                'is_system' => true,
                'is_active' => true,
                'description' => "Control account — subsidiary ledger = student statements (charges − payments + refunds). Charges are cash-basis billing rows, so no journal lines post here.",
            ],
            [
                'code' => '1430',
                'name_ar' => 'أرصدة الأشخاص الآخرين',
                'name_en' => 'Other-People Balances',
                'type' => 'asset',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Control account — subsidiary ledger = other-people in/out register (out − in).',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('accounts')->updateOrInsert(['code' => $row['code']], $row);
        }

        DB::table('accounts')->where('code', '1420')->update([
            'description' => "Control account — subsidiary ledger = staff advances (advance − repayment − deduction).",
        ]);
        DB::table('accounts')->where('code', '2110')->update([
            'description' => 'Control account — subsidiary ledger = supplier debt (purchases − payments). Purchases post from stock-in movements.',
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['1410', '1430'])->delete();
    }
};