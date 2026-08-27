# AI_CONTINUATION.md — Handoff for the Next AI Session

Session date: 2026-08-15 (accounting system transformation — COMPLETED and verified).
All work lives in `institut/`; run every artisan/composer command with `/usr/bin/php`.
Load both skills (`financial_integrity`, `laravel_filament`) before touching accounting code.

## Current state

The accounting transformation planned in the previous session is **DONE and verified**:

- **121 tests / 988 assertions green** (`/usr/bin/php artisan test`), including the new
  `Tests\Feature\AccountingCoreTest` (9 tests) which caught and fixed 3 real bugs
  (`accountTotals()` private → 500 on the accounts list; missing `$sign` in the statement
  closure; `Collection::where('not in')` not supported → refund detection).
- `strings:audit` exit 0 (no unlocalized user-facing strings).
- `2026_08_15_170000_add_accounting_check_constraints` applied to the **live** `institute` DB;
  `finance:audit` passes (Debit = Credit = 627,710 YER with real data).
- `docs/ACCOUNTING_ARCHITECTURE.md` (source of truth), `docs/MASTER_RECOVERY_PLAN.md`
  (ACC-001..011 all `[x] 2026-08-15`) and `PLAN.md` (Phase 36) updated.

## What was delivered

1. **Real account statement** — `ReportService::accountStatement()` (windowed opening balance,
   running balance in normal-balance sign, counterparty narrative, totals, closing) + new
   `AccountStatement` page (nav "Ledger" → Account Statement) with per-row links: entry_no →
   journal view, document badge → source document, party → person/student/supplier page, account
   → its statement; print action + `prints/account-statement.blade.php`; URL prefill
   (`?account_id=&from=&to=`). `AccountLedger` delegates to the same service and gained a
   "view entry" row action. Joined queries select `journal_entry_lines.*` (id-collision fix).
2. **Chart of Accounts** — `AccountResource` (list: code/name/type/parent/place/balance/active;
   create; view with balance + statement shortcut; edit). `code`/`type` guarded (disabled and
   re-asserted in `mutateFormDataBeforeSave`) once lines exist or the account is system;
   `accounts.description` column.
3. **Journal view cross-links** — `ViewJournalEntry`: source-document link row
   (`JournalDocumentLinker`), per-line account links to AccountStatement.
4. **Journal-derived cash/profit** — `ReportService::journalCashFlow()` (place accounts:
   in=debits, out=credits, refunds=place-credit entries whose counterpart debits an income
   account; supplier payments are cash-out but NOT profit-spent), `dailyCash()` totals from it,
   `profit()` aggregated from income/expense line movements (closing excluded; refunds reduce
   revenue). ProfitReport table/print rewritten to account rows (= income statement). Daily-cash
   print rewritten to one entries table + totals (collected/refunded/spent/net).
5. **DB invariants** — CHECK constraints (lines non-negative + single side; transfers amount>0 +
   from<>to; accounts.type IN 5 values) — validated against the live DB.
6. **Journal void audit log** (ACC-011), stock `ValidationException` toast fixes (ACC-006),
   TodayCollections covers other-people `in` (ACC-008), fallback removal (ACC-007).
7. **Localization** — 13 new general keys (both en/ar) + 8 validation attributes
   (`view_entry, counterparty, parent_id, place_type, is_system, statement, document,
   salary_month, month_warning, base_salary, max_payable_placeholder, outstanding_advances`).

## Remaining / known caveats (deliberate, do NOT "fix" blindly)

- **Months are operational, not accounting periods** (documented decision): back-dated entries in
  an open year are allowed; `registration_months` is not an accounting lock. Do not add per-month
  posting locks without a new owner decision.
- **No bank reconciliation module** (documented out-of-scope: no external statement import, no
  multi-party treasury expectations). Reconfirmation = `finance:audit` (weekly) + place-balance
  widgets + print vouchers.
- **Cash basis**: charges/billing rows are NOT journaled; only money movements are (payments,
  refunds, expenses, salaries/advances, supplier cycle, other-people in/out, stock sales &
  purchases, transfers, opening balances, fiscal close). No receivables accrual — documented.
- **P3 backlog** exists for separate UX issues (`notes_to_fix.md`); not part of accounting.
- `MonthlyChartWidget` still reads transaction tables (a chart, not a money report — acceptable;
  if it must agree with daily cash, derive from `journalCashFlow()` per month).

## Verified-but-watch (near future)

- `Account::balance(?string $asOf)` (model helper) still returns `number_format` string — fine;
  do NOT turn it into a relation (Filament columns that need balance must use `->state(...)`
  with `ReportService::accountTotals()` like `AccountResource` does).
- `JournalDocumentLinker` returns null for documents without a view page (e.g., stock purchase,
  fiscal closing) — those badges show the class basename only (by design).
- Owner has NOT yet browser-verified (functional testing is the owner's job per workflow):
  /admin/accounts, /admin/account-statement (with account_id prefill), journal view links,
  daily-cash/profit print output. Don't block on it; mention in the next report.

## Commands

```bash
/usr/bin/php artisan test                    # full suite — currently 121 passed / 988 assertions
/usr/bin/php artisan finance:audit           # journal balanced scan (weekly schedule stays)
/usr/bin/php artisan strings:audit           # must exit 0 after touching user-facing strings
/usr/bin/php artisan serve --port=8001       # dev server (use /usr/bin/php)
```

## Plan bookkeeping

- `PLAN.md` Phase 36 appended (all items `[x] 2026-08-15`). Next session: re-audit before/after,
  then nothing outstanding for accounting besides owner browser verification.