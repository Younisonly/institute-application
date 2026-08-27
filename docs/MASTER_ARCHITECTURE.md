# MASTER_ARCHITECTURE.md — Institute Management System (Yemen / YER)

Last verified: 2026-08-14 (full suite 99 tests / 484 assertions green).

## 1. Purpose & Scope

Single local web app for a Yemen institute: students, dynamic courses (short course / diploma),
registrations with month tracking, payments + sequential receipts, staff (salaries, advances),
books/supplies stock, expenses, profit reports, student ID cards, and a DOUBLE-ENTRY finance core
(accounts, banks/wallets, suppliers, other people, transfers, opening balances, fiscal-year closing,
account-ledger / trial-balance / income-statement / balance-sheet reports).

NOT in scope: homework, librarian, transport, multi-currency, exams, timetables.

## 2. Stack (verified)

- Laravel 12 (PHP 8.3+, run with `/usr/bin/php` — LAMPP php 8.2 fails the platform check)
- Filament 3 panel (single panel `app`, mounted at `/admin`)
- MySQL 8 on localhost (dev `institute`, tests `institute_test`)
- Spatie permissions — roles: `admin` (all), `accountant` (money), `registrar` (students/regs), `teacher` (limited)
- Bilingual `ar` (default, RTL) / `en`; all user-facing strings via `__()` with keys in both `lang/en` and `lang/ar`
- YER only; money = `decimal(10,2)` + `cast: 'decimal:2'`; integer math, no floats
- Scheduled `mysqldump` daily + `finance:audit` weekly + `model:prune` daily (audit log > 1 year)

## 3. Non-negotiable financial rules

1. Price snapshot — copy the price into the child record at creation; never read the live price later.
2. Balance via transactions only — charges − payments (voided excluded); never mutate a balance column.
3. No hard delete of financial records — void + reason + audit log instead.
4. Sequential receipts — `ReceiptNumberService::next()` allocates atomically (`lockForUpdate` inside a transaction), never reused.
5. Money = `decimal(10,2)` + `cast: 'decimal:2'`.
6. Suspend/close/transfer keeps history; transfer carries the balance to a same-type class.
7. Month tracking — start → expected end; months open/close individually; never silently extend.
8. Every money event journals itself — payments, expenses, supplier purchases, other-people in/out,
   transfers, stock issues post balanced double-entry entries via `FinancePostingService`.
   Charges/billing rows are cash-basis (NOT journaled).
9. Void = reversing entry — `JournalService::reverse()` creates the exact reversal (marked `voided_at`),
   keeps the audit trail; never delete an entry or line.
10. `JournalEntryLine::entry()` uses the explicit FK `journal_entry_id`.
11. Account `name` is an accessor (`name_ar`/`name_en`) — never `pluck('name')` on `accounts`.
12. Native Filament tables everywhere (never raw HTML `<table>` in page views).

## 4. Money-flow architecture (who posts what)

All posting funnels through three services:

- `AccountService` — chart codes, place accounts (`ensureForPlace`, code = 1200+id bank / 1300+id wallet,
  auto-steps on collision), category accounts (4400+income id / 5900+expense id).
- `JournalService` — `post()` (validates balance + open year, allocates `entry_no`, links document),
  `reverse()` (reversal entry + voided original), `reverseIfVoidable()` (reverse + `$document->void()`).
- `FinancePostingService` — typed post methods below; `placeAccount()` resolves cash/bank/cheque/wallet.

| Event | Where triggered | Method | Debit / Credit |
|---|---|---|---|
| Student payment/refund | `StudentTransactionObserver` | `postStudentTransaction` | place ↔ course fees / other income, party tracking |
| Staff salary/advance/repayment/deduction | `StaffTransactionObserver` | `postStaffTransaction` | salaries (5100) / staff advances (1420) ↔ place |
| Expense create (and edit via reverse+repost) | `ExpenseObserver` | `postExpense` | category account (5900+id) ↔ place |
| Stock purchase (`in` with supplier) | `StockMovementObserver` | `postStockPurchase` | inventory (1510/1520) ↔ supplier payable (2110) |
| Stock sale (`sold`) | `StockMovementObserver` | `postStockSale` | place ↔ income books (4200) / items (4300) |
| Supplier payment | `SupplierTransactionObserver` | `postSupplierPayment` | supplier payable (2110) ↔ place |
| Other-person in/out | `OtherPeopleTransactionObserver` | `postOtherPersonTransaction` | place ↔ income (4400+id) / expense (5900+id) |
| Transfer between accounts | `TransferObserver` | `postTransfer` | to ↔ from |
| Opening balance | Admin UI | `postOpeningBalance` | place ↔ capital (3100) |

Observers are registered in `AppServiceProvider::boot()` — every money entry goes through them,
including in tests and seeders (no silent bypass exists).

### Stock lifecycle (centralized since 2026-08-14)

`StockMovementObserver::created()`:
1. `adjustStock()` — `in` → +qty else → −qty (`lockForUpdate`, floored at 0).
2. `postStockPurchase()` (type `in` + supplier) and `postStockSale()` (type `sold`).

`StockMovement::void($reason)` — `restoreStock()` (reverse of the original sign) + `reverseForDocument()`.
Voiding the journal entry on the Journal page also restores stock via `JournalService::reverseIfVoidable()`.

Manual stock decrements at call sites were REMOVED; never re-add them (double adjustment otherwise).

## 5. Registration & items flow

- `RegistrationService::register()` — price snapshot → course; creates registration, months list,
  optional items, initial payment atomically.
- Month tracking: `registration_months` rows; `ProcessMonthlyCharges` opens charges per month;
  close/open individually; closing transfers the open balance one month forward.
- Items: `registration_items` (book/item + qty + unit_price snapshot, linked movement) →
  `issueBook()` / `issueItem()` post a charge via `registration_item_id` FK;
  `voidIssuedItem()` voids the charge AND the movement by FK (rename-proof, label only as fallback)
  and marks the pivot `voided_at` (never deletes).
- `transfer()` carries balance + optionally items to a same-type registration.
- `RegistrationObserver::deleting()` throws when months/items/transactions exist (FKs are RESTRICT,
  not cascade — enforced by migration 2026_08_13_000001 and 2026_08_14_000005).

## 6. UI conventions

- All 22 resources + 18 pages extend `HasRbac`-style role gating; money-sensitive actions
  `->authorize()` (e.g. `registerStudent`, `voidItem` = admin|accountant; refunds = admin|accountant).
- Custom report pages: `InteractsWithTable` + `{{ $this->table }}`; Finances page uses
  `TableWidget`s via `getFooterWidgets()`.
- Per-record table action closures type `?Model $record` (may be null) and use `$record`, never
  `->arguments(closure)` (rejected by this Filament version).
- Paper copies: print routes gated admin|accountant; receipts carry QR + auto-print.

## 7. Key constants (AccountService)

```
1100 cash on hand          1420 staff advances         1510 books inventory       1520 items inventory
2110 supplier payable      3100 capital                3200 retained earnings
4100 course fees           4200 book sales             4300 item sales            4400 other income (+4400+income_cat_id)
5100 salaries              5900 other expenses                                    (+5900+expense_cat_id)
banks 1200+id · wallets 1300+id (collision-safe auto-step)
```

## 8. Known limitations / deferred (accepted)

1. Stock `in` without supplier → not journaled (no inventory post). Inventory itself is never
   credited on issues/sales (no COGS) — profitability of stock is not tracked.
2. `dailyCash()` / `profit()` reports derive from transaction tables (cash-basis), not from the journal.
3. Journal/observer error messages at the UI boundary are raw English (no translation) for a few
   `RuntimeException`s (balance check, min 2 lines).
4. Audit logs are pruned after 1 year (deliberate - `Prunable`).
5. Staff per-hour salary double-pay (two records for the same staff+month) is not guarded — invoice-level.
6. Manually editing a journal entry's lines is possible only through the UI; the source document's
   derived data (e.g. an expense amount) is NOT re-derived (repost happens on document edit, not entry edit).

## 9. Operation

```
/usr/bin/php artisan serve --port=8001      # dev server (APP_URL must match the browser host)
/usr/bin/php artisan test                   # full suite (institute_test)
/usr/bin/php artisan migrate                # schema changes via migrations only
/usr/bin/php artisan finance:audit          # journal balance check (scheduled weekly)
/usr/bin/php artisan db:seed --force
```