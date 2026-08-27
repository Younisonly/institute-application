# ACCOUNTING_ARCHITECTURE.md — The Accounting Source of Truth

Last updated: 2026-08-15. Companion docs: `DATA_MODEL.md` (schema), `MASTER_RECOVERY_PLAN.md`
(remediation register), `AI_CONTINUATION.md` (session handoff), `MASTER_ARCHITECTURE.md` (app overview).

## 1. What kind of accounting system this is

This is **management accounting for a single private institute in Yemen, YER only** — not a
multi-entity ERP, not a bank-backed treasury system, and not a statutory/tax filing system.

- **Domain**: institute management (students, courses, registrations, staff, books/items stock) with
  a cash-basis double-entry accounting subsystem at its financial core.
- **Basis**: the journal records **cash events only** (money actually received or paid: payments,
  refunds, expenses, salaries, advances, supplier payments, stock purchases/sales, transfers,
  opening balances). Billing rows (registration charges) are cash-basis tracking rows that are NOT
  journaled; they exist to answer "what does this student owe".
- **Standard alignment**: the account classification follows the IFRS Conceptual Framework (2018)
  five elements — *assets, liabilities, equity, income, expenses* — for terminology and structure.
  This is **NOT an IFRS/GAAP compliance claim**: the entity keeps accounts on a cash basis, does not
  produce audited general-purpose financial statements, and there is no Yemeni statutory filing
  obligation implemented here. Accounting-system best practices (integrity, traceability, control)
  are treated as non-negotiable; jurisdiction-specific tax/legal compliance is **out of scope** and
  must never be inferred from this architecture.
- **Currency**: YER only, `decimal(10,2)` (widened to `decimal(16,2)` in 2026-08-15), integer math,
  no floats, no conversion. Multi-currency, FX, tax, bank reconciliation against external statements,
  and inventory COGS are explicitly NOT modeled (see §10).

## 2. The accounting model (one source of truth)

```
BUSINESS EVENT (payment, expense, salary, sale, purchase, transfer, opening balance, ...)
  →  FINANCIAL DOCUMENT (StudentTransaction, Expense, StaffTransaction, StockMovement,
      SupplierTransaction, OtherPeopleTransaction, Transfer, fiscal-year closing)
  →  JOURNAL ENTRY (head: date, entry_no, description, reference, document morph, created_by)
  →  JOURNAL LINES (account, debit, credit, party morph, notes)
  →  DERIVED VIEWS (no storage): account balances, account statement, general ledger,
      trial balance, income statement, balance sheet, cash statement, party ledgers
```

**Every money event journals itself, automatically, via observers** (`FinancePostingService`).
There is no hand-posted journal for normal operation; the journal is *written once* by the posting
service and is **immutable in operation** — corrections are reversals, never edits/overwrites.

## 3. Chart of accounts

- Table `accounts`: `code (unique)`, `name_ar/name_en` (locale accessor `name`), `type`
  (`asset|liability|equity|income|expense` — the IFRS five), `parent_id` (hierarchy),
  `place_type/place_id` (morph link to a bank/wallet when the account IS a payment place),
  `is_system` (never deactivatable/renamable), `is_active`, `description`.
- Normal balance by type: asset/expense = debit; liability/equity/income = credit.
- System codes (seeded; see `ChartOfAccountsSeeder`):

| Code | Type | Meaning |
|---|---|---|
| 1100 | asset | Cash on hand (the default place) |
| 1200+id | asset | Bank place account (auto-created on bank create, collision-safe) |
| 1300+id | asset | Wallet place account (auto-created on wallet create, collision-safe) |
| 1410 | asset | **Student receivables — CONTROL account** (subsidiary = student statements; no journal lines, balance derived from the register) |
| 1420 | asset | Staff advances — CONTROL account (journal-posted: advance/repayment/deduction) |
| 1430 | asset | **Other-people balances — CONTROL account** (subsidiary = other-people in/out register; no journal lines, balance derived from the register) |
| 1510 / 1520 | asset | Books / items inventory |
| 2110 | liability | Suppliers payable — CONTROL account (journal-posted: stock-in purchases credit, payments debit) |
| 3100 / 3200 | equity | Capital / retained earnings |
| 4100 / 4200 / 4300 / 4400 | income | Course fees / book sales / item sales / other income |
| 4400+id | income | Income-category accounts (auto-created per category) |
| 5100 / 5900 | expense | Salaries / other expenses |
| 5900+id | expense | Expense-category accounts (auto-created per category) |

- `AccountService::ensureForPlace/ensureFor{Income,Expense}Category` keep generated accounts in
  sync with their business source (bank/wallet/category); codes auto-step on collision.
- **Managed through the Chart of Accounts UI** (`AccountResource`, nav group "Ledger") since
  2026-08-15: create/edit custom accounts, deactivate broken ones, read balances. System accounts
  and accounts with posted lines cannot change their `type` (it drives the normal balance).

## 4. Journal model

`journal_entries` (head) + `journal_entry_lines` (body), both able to be **voided, never deleted**:

| Field | Meaning |
|---|---|
| `entry_no` | Sequential, unique, allocated atomically (`InstituteSetting::lockForUpdate`) |
| `date` | Entry date = business date of the event (document date) |
| `description` | Human-readable event label (includes party and place) |
| `reference` | Receipt/voucher number, invoice ref, `opening-balance`, `yearly-closing-YYYY` |
| `document_type/document_id` | Morph to the source financial document (per §2 chain) |
| `created_by` | User who caused the posting |
| `voided_at/void_reason` | Set on reversal entries and on reversed originals |
| `reversed_entry_id` | The entry that reversed this one |

Line: `account_id` (RESTRICT FK), `debit`, `credit` (both non-negative, exactly one side > 0 —
enforced by DB CHECK since 2026-08-15), `party_type/party_id` (morph to student/staff/supplier/
other person for receivable/payable traceability), `notes`.

## 5. Posting rules (invariants)

1. **Balance invariant**: for every journal entry, Σdebits = Σcredits. Enforced in
   `JournalService::post()` (tolerance ±0.009 due to decimal banking) AND by the
   `finance:audit` command (scheduled weekly) which re-scans all non-voided lines.
2. **Line validity**: a line can never have debit and credit both set, never a negative value,
   and never both zero (DB CHECK `chk_jel_single_side`, plus `chk_jel_debit_non_negative` / `chk_jel_credit_non_negative`).
3. **Transfer validity**: `from_account_id != to_account_id`, `amount > 0` (DB CHECK since
   2026-08-15; UI also blocks self-transfer).
4. **Min 2 lines** per entry (`JournalService`).
5. **Account must exist and be active** — FK RESTRICT + active-only account pickers.
6. **Closed-year lock**: no entry (other than year-closing work itself, which is the closing
   action) may be posted or reversed with a `date` inside a closed fiscal year.
7. **Atomicity**: posting happens inside `DB::transaction()`; document creation, receipt
   allocation, and the journal write either all commit or all roll back (e.g. a failed transfer
   leaves no trace — validated by test).
8. **Sequential receipts**: `ReceiptNumberService::next()` inside a `lockForUpdate` transaction;
   numbers are never reused; a receipt number is allocated only when the document actually commits.

## 6. Posting map (who debits/credits what)

Fully implemented in `FinancePostingService`; charges (billing rows) are deliberately NOT posted.

| Event | Debit | Credit | Party on line |
|---|---|---|---|
| Student payment | place | income account (course fees 4100, or per-transaction income account) | student |
| Student refund | income account | place | student |
| Staff salary | 5100 salaries | place | staff |
| Staff advance | 1420 advances | place | staff |
| Advance repayment / deduction | place / 5100 | 1420 | staff |
| Expense | expense category account (5900+id) | place | — |
| Stock purchase (in w/ supplier) | 1510/1520 inventory | 2110 payable | supplier |
| Stock sale (sold) | place | 4200/4300 | — |
| Supplier payment | 2110 payable | place | supplier |
| Other-person `in` | place | 4400(+cat) income | person |
| Other-person `out` | 5900(+cat) expense | place | person |
| Transfer | to-account | from-account | — |
| Opening balance | place | 3100 capital | — |
| Year closing | income accounts / 3200 | 3200 / expense accounts | — |

`placeAccount()` resolves the place: `method=bank|transfer|cheque` → bank account, `wallet` →
wallet account, else cash 1100. Sold stock movements carry their payment method.

## 7. Balances — derived, never stored

- Balances are **always computed from non-voided journal lines** (`Account::balance()`,
  `ReportService::accountTotals()`, widget sums). No balance column exists anywhere.
- The answer to *"why is this account balance X?"* is reconstructable: every movement is a journal
  line inside a balanced entry linked to a business document.
- The account statement/ledger runs a per-account balance in the account's normal-balance sign
  (asset/expense: debit−credit; liability/equity/income: credit−debit).
- No materialized/cached balance table: data volumes are small (a single institute) and the
  projection is one indexed aggregate — correctness over premature optimization.
- **Student/registration balances** (charges − payments + refunds, voided excluded) are derived
  from `student_transactions`, which mirrors the journal's party-side logic: a refund returns money
  to the student, so it *increases* the receivable. All surfaces (dashboards, report formulas,
  printed statements) use exactly this formula since 2026-08-15 (previously three different
  formulas disagreed — see MASTER_RECOVERY_PLAN TASK-015/016).

## 8. The account statement (real statement, since 2026-08-15)

`AccountStatement` page + `ReportService::accountStatement()` produce, per selected account and
date range, one row per journal line with: **date · entry_no · description · reference ·
counterparty (the other side(s) of the entry — "where the money came from / went to") · party
(student/staff/supplier/other) · debit · credit · running balance (normal sign) · journal link**.

The click-through chain is live:

```
ACCOUNT (Chart of Accounts / ledger nav)
  →  ACCOUNT STATEMENT (report page, per account + date range)
      →  JOURNAL ENTRY (view page: lines, party, document)
          →  SOURCE DOCUMENT (payment/expense/transfer/... resource page)
              →  back, context preserved (browser + Filament breadcrumbs)
```

No statement data is persisted — statements are derived views over the journal (the journal is the
single source of truth; a stored statement would be a second truth to keep in sync).

## 9. Reversals, corrections, lifecycle

- **Lifecycle is binary and by design**: an entry is either *live* or *voided*. There is no
  draft/pending/approved chain because every posting is a completed business event recorded at
  once by the posting service; a draft stage would create a window of un-audited finance with no
  business need in this system (documented decision, not an omission).
- **Void = exact reversal**: `JournalService::reverse()` creates a NEW entry with flipped
  debit/credit, marked `voided_at` (kept forever as the audit trail — excluded from balances by the
  `whereNull('voided_at')` convention), and marks the original voided with reason + link.
- **Document void** reverses the journal via `reverseForDocument()` and additionally restores
  business state (e.g. `StockMovement::void()` restores stock; `voidIssuedItem` voids charge +
  movement + pivot).
- **Expense edits** reverse the old entry and post a fresh one atomically (3-entry history).
- **Reopen** only exists for fiscal-year closing (`FiscalYearClosingService::reopen` voids the
  closing entry). Historical posted entries are otherwise never re-written.

## 10. Reporting model

All reports derive from **one** query family (`ReportService::linesQuery()` over non-voided lines,
with optional date window and closing-entry exclusion):

| Report | Source | Since |
|---|---|---|
| Account statement / general ledger | journal lines per account, running balance | ledger 2026-08-12; statement 2026-08-15 |
| Trial balance | Σdebit/Σcredit per account; totals always equal | 2026-08-12; re-verified 2026-08-15 |
| Income statement | income/expense accounts (closing excluded) | 2026-08-12 |
| Balance sheet | asset/liability/equity accounts + net income since last close | 2026-08-13 |
| **Profit (monthly)** | **journal-derived income/expense by account (closing excluded)** | **2026-08-15 (was transaction-derived)** |
| **Daily cash (per date)** | **journal lines on place accounts (in = debits, out = credits, counter-party per entry)** | **2026-08-15 (was transaction-derived)** |
| Daily-cash/profit print + page summary | same journal source (single path, no dual numbers) | 2026-08-15 |
| Arrears, payment history, salary sheet, enrollment, stock inventory, ID cards | operational tables (non-accounting registers) | original |

Report consistency invariant (tested): statement ⇔ ledger ⇔ trial balance ⇔ income statement for
the same events — all agree because they all read the same lines.

## 11. Fiscal years and posting restrictions

- `FiscalYearClosing` locks a calendar year after close; `JournalService::assertYearOpen` blocks
  any post/reverse whose date falls in a closed year (closing entries themselves bypass the lock).
- Closing zeroes income+expense into retained earnings 3200 with reference `yearly-closing-YYYY`
  and date 12-31 of the closed year; reports exclude closing entries.
- Months below the year level are NOT posting-controlled: back-dated entries in the same year are
  allowed by design (the institute may enter receipts late), and month tracking lives in
  `registration_months` (operational, not accounting-period) — documented decision.

## 12. Reconciliation

No bank reconciliation module: the system has no import of external bank/CSV statements and no
multi-party treasury expectations. Reconciliation here means (a) weekly `finance:audit`
debit=credit scan, (b) the account statement + place-balance widgets so the owner can eyeball a
place's balance at any date, and (c) print receipts/vouchers as supporting documents. Adding true
bank reconciliation would require an external-statement concept that does not exist in this
single-institute, cash-first environment — deliberately out of scope.

## 13. Security model (accounting-relevant controls)

- **Server-side enforcement everywhere**: Filament resources/pages gate via `HasRbac` role lists;
  web print routes use Spatie `role:` middleware; money actions additionally `->authorize()`
  (e.g. voids = admin|accountant, payments = admin|accountant|registrar).
- **No IDOR surface**: the only parameterized endpoints (print/voucher/download routes) are
  role-gated and rely on server-side checks; the panel itself runs Filament authorization per
  record action (OWASP ASVS V4.1.1/V4.2.1 — access control on the trusted service layer).
- **CSRF**: Laravel's default token protection on all POST routes, incl. locale switch and
  print forms (ASVS V4.2.2).
- **Validation**: Filament schema rules / Form-request validation on all money inputs (amount
  minValue, method enum, receipt reasons required) — ASVS V5.1.
- **Audit trail**: `audit_logs` for user-driven financial actions (voids, journal voids,
  settings) with user + IP + JSON delta; journal entries store `created_by`; reversals record
  reason. Pruned only after 1 year (documented decision).
- **Secrets**: none in code; `.env` excluded from the repo; DB backup via `backup:database`
  (mysqldump) to storage/app/backups kept 30 days.

## 14. Concurrency and integrity (verified by tests)

- Receipt and entry numbers: single-row `lockForUpdate` counters — safe against duplicate
  allocation (test: 3 sequential receipts).
- Posting: `DB::transaction` + balance validation before insert; concurrent double-submit of a
  transfer cannot produce a partial state or an unbalanced entry (test: failed/duplicate paths).
- Unique constraints: `receipt_no` per table, `entry_no`, `accounts.code`.
- Stock: `lockForUpdate` row lock when adjusting `stock_qty`, floored at 0.

## 15. What is deliberately NOT implemented (and why)

1. Draft/pending/approved posting workflow — every posted event is a completed business event.
2. Multi-currency / FX — YER only by domain decision.
3. Tax configuration — no statutory tax obligations implemented; the engine stays tax-neutral.
4. Bank reconciliation against external statements — no external statement concept (see §12).
5. Inventory COGS — stock is DR 1510/1520 on purchase and never credited on issues/sales;
   profitability of stock is not tracked. Documented P3 limitation.
6. Hard deletes, balance columns, hand-editable posted entries — contradicted by the rules above.
7. Accruals/receivable journalization: charges stay cash-basis; only actual cash events post.
## 16. Party subsidiary ledgers & control accounts (since 2026-08-16)

Students, staff, suppliers and other parties are NOT accounts. Each is a row in a
SUBSIDIARY LEDGER; the chart of accounts shows their aggregate under a CONTROL account:

| Control | Ledger | Sign convention (from the institute's side) | Balance source |
|---|---|---|---|
| 1410 Student receivables | student statements | charge/refund = debit, payment = credit | Register (charges are cash-basis; no journal lines) |
| 1420 Staff advances | staff advance register | advance = debit, repayment/deduction = credit | Journal (advance/repayment/deduction post double-entry) |
| 1430 Other-people balances | other-people in/out register | out = debit, in = credit | Register (posts hit income/expense categories, never 1430) |
| 2110 Supplier payable | stock-in purchases + supplier payments | purchase = credit, payment = debit | Journal (purchases post DR inventory / CR 2110) |

- `ReportService::partyLedger($type, $id, $from, $to)` returns one uniform statement shape
  (opening, rows with running balance, totals, closing) for all four registers; supplier rows
  merge StockMovement purchases with SupplierTransaction payments.
- Staff salary rows are excluded from the register — salaries are compensation expense and
  belong to the salary sheet; they never move the advances balance.
- `ReportService::controlAccountBalance()` supplies the tree balance ONLY for 1410/1430
  (no journal lines exist for them); 1420/2110 already carry their truth in the journal, so
  they are never overridden.
- UI: the Account Statement page gained a "Statement of" dimension (Account/Student/Staff/
  Supplier/Other People) with searchable party select; control accounts in Chart of Accounts
  show their subsidiary balance and a one-click party-statement action; a dedicated
  `prints.party-statement` layout prints the register.
