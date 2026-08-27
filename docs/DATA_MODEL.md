# DATA_MODEL.md — Institute Management System (Yemen / YER)

Last verified: 2026-08-15. All schema changes via migrations; `utf8mb4_unicode_ci`; FKs via `foreignId`.
Accounting concepts (why each column exists) are documented in `ACCOUNTING_ARCHITECTURE.md` —
this file is the schema reference.
Financial tables NEVER delete: they carry `voided_at` + `void_reason`.
Soft deletes (history matters): students, staff, job titles, staff documents, items, item
categories, suppliers, banks, wallets, party types, income/expense categories, other people,
courses, program types, periods, books, staff_course_specialties.

## Financial tables

### accounts
`id · code (string, unique) · name_ar · name_en (accessor name) · type (asset|liability|equity|income|expense)`
`· parent_id (self FK, nullOnDelete) · place_type/place_id (morph; indexes added 2026-08-14) · is_system · is_active · description (added 2026-08-15)`
CHECK `type IN (...5 values)` (2026-08-15). Managed via `AccountResource` (created 2026-08-15):
type is locked for accounts with lines; system accounts cannot be deactivated.
Place accounts: banks 1200+id, wallets 1300+id (via `AccountService::ensureForPlace`, collision-safe
auto-step since 2026-08-14); income categories 4400+id, expense categories 5900+id.
System codes: 1100 cash · 1420 staff advances · 1510 books inventory · 1520 items inventory ·
2110 supplier payable · 3100 capital · 3200 retained earnings · 4100 course fees · 4200 book sales ·
4300 item sales · 4400 other income · 5100 salaries · 5900 other expenses.

### journal_entries
`id · entry_no (unique; atomic via JournalService::nextEntryNo() on institute_settings.journal_next_no)`
`· date (index) · description · reference · document_type/document_id (morph, index)`
`· created_by (users, nullOnDelete) · voided_at + void_reason · reversed_entry_id (self FK, nullOnDelete)`
Reversal entries have `voided_at` set (audit trail kept) and mark the original voided.

### journal_entry_lines
`journal_entry_id (explicit FK, cascadeOnDelete) · account_id (restrictOnDelete)`
`· debit/credit decimal(10,2) · party_type/party_id (indexed) · notes`
CHECKs (2026-08-15): `debit >= 0`, `credit >= 0`, exactly one side > 0 (`chk_jel_*`).
`JournalEntryLine::entry()` uses explicit FK `journal_entry_id` — Laravel would infer `entry_id`.

### banks / wallets
`name, account_no|provider, branch, phone, notes, is_active` + softDeletes.
Payment mutual exclusion: cash XOR bank XOR wallet given by `method` (cash|bank|cheque|transfer|wallet).

### transfers
`from_account_id/to_account_id (accounts, restrictOnDelete) · amount · date (index) · reference ·
description · voided_at + void_reason · created_by · journal_entry_id (journal_entries, nullOnDelete)`
CHECKs (2026-08-15): `amount > 0`, `from_account_id != to_account_id` (`chk_transfers_*`).

### supplier_transactions / other_people_transactions / staff_transactions / student_transactions
Common shape: party FK (`supplier_id`|`other_person_id`|`staff_id`|`student_id`) · `type`
(supplier: payment|adjustment; other: in|out; staff: salary|advance|repayment|deduction; student:
charge|payment|refund) · `amount decimal(10,2)` · `date (index)` · `method` + `bank_id`/`wallet_id`
(nullOnDelete) + `transaction_ref` · `receipt_no (nullable, unique — sequential via ReceiptNumberService)`
· `voided_at + void_reason` · `created_by` · `journal_entry_id (nullOnDelete) · timestamps`.
`student_transactions` also: `registration_id` (FIXED 2026-08-13 → restrictOnDelete) and
`registration_item_id` (FK to registration_items, restrictOnDelete, index — added 2026-08-14, links
items charges to the issued item so voids survive renames).
`staff_transactions` also: `salary_month` (Y-m, index), `hours` for per-hour pay.
Financial FKs on all 4 transaction tables are RESTRICT (migration 2026_08_13_000001) — the full
cascade path was a P1 data-loss bug (registration deletion → months/items cascade → transactions).

### expense_categories / income_categories
`name (unique) · is_active · account_id (accounts, nullOnDelete)` + softDeletes.

### stock_movements
`book_id XOR item_id (nullable) · type (in|issue|sold|...) · qty (unsigned) · unit_price ·
method + bank_id/wallet_id + transaction_ref (added 2026-08-14 for paid sales) · supplier_id
(only when type=in) · registration_item_id (nullable FK) · date (index) · reference · description ·
created_by · voided_at + void_reason · journal_entry_id (journal_entries, nullOnDelete; index, backfilled 2026-08-14)`
CHECK `qty > 0` (2026_08_14_000002). Stock qty is adjusted ONLY by `StockMovementObserver::created`
(+qty for `in`, −qty otherwise, lockForUpdate, floored at 0).

## Core business tables

### students
`name · gender · birth_date · phone · address · education_level · guardian_name/relation/phone ·
photo · join_date · status (active|suspended|closed|transferred|new) · student_code (unique) ·
emergency_contact_name/phone + medical_notes (2026-08-14)` + softDeletes. No father-name / national-ID
(Yemen context research).

### staff
`name · phone · email · address · photo (nullable) · job_title_id (job_titles, nullOnDelete) ·
contract_type (monthly|percentage|per_hour) · monthly_salary · percentage_rate · hourly_rate ·
hire_date · status · salary_month (last paid) · notes · emergency_contact* (2026-08-14)` + softDeletes.
`staff_course_specialties` pivot: staff_id + course_id, `updated_at` added 2026-08-14 (relation uses
`withTimestamps()`); `Staff::courses()` sync.

### program_types / courses / periods
- program_types: `name · months_count · color`, softDeletes.
- courses: `name · program_type_id (nullOnDelete) · months · price (snapshot target, never read live
  for balance) · color · description · image`, softDeletes.
- periods: `name_ar/name_en · start_time · end_time · days (JSON sat..fri)` (2026-08-13).
- `course_period` pivot — many-to-many.

### registrations
`student_id (restrictOnDelete) · course_id (restrictOnDelete) · status (active|completed|suspended|
closed|transferred) · start_month (Y-m) · months_count · expected_end_month (computed) ·
price_snapshot · discount_amount · notes · created_by · journal-less (cash basis)`.
DELETE blocked: `RegistrationObserver::deleting()` throws
`general.cannot_delete_registration_with_history` when months/items/transactions exist.

### registration_months
`registration_id (restrictOnDelete — fixed 2026-08-14) · month (Y-m) · status (open|closed|exempt) ·
charge_amount · charge_date · closed_at`. Month tracking: open months accrue charges
(`ProcessMonthlyCharges`); closing an open month forwards its balance one month; regs never silently extend.

### registration_items
`registration_id (restrictOnDelete — fixed 2026-08-14) · book_id XOR item_id · qty · unit_price
(snapshot) · voided_at + void_reason (added 2026-08-14 — void marks pivot, never deletes)`
`scopeActive()` = `whereNull('voided_at')`.

## Support tables

### institute_settings
Single row: `name_ar/name_en · logo · address · phone · currency_label · current_month ·
receipt_counter · journal_next_no · ...` — `InstituteSetting::current()`.

### audit_logs
`user_id · action · model_type/model_id · changes (JSON) · ip · created_at` — `Prunable` > 1 year.

### users / roles / permissions
Spatie standard; `model_has_roles` etc. Roles: admin | accountant | registrar | teacher.

### misc
`staff_documents (FK staff, softDeletes) · item_categories · items (stock_qty, low_stock_threshold,
purchase_price, sale_price, supplier_id, softDeletes) · books (course_id, buy_price, sale_price,
stock_qty, low_stock_threshold, is_active, softDeletes) · suppliers (softDeletes) · other_people
(party_type_id, softDeletes) · party_types (softDeletes) · expenses (expense_category_id, amount,
payment_method, bank_id, wallet_id, transaction_ref, date, notes, voided_at, created_by) ·
fiscal_year_closings (year unique, closed_at, closing_journal_entry_id) · jobs tables`.

## Cross-cutting rules

1. Registration deletion cascade chain is FULLY RESTRICT now (registrations → student/course/
   transaction FKs, registration_months, registration_items). Never loosen back to cascade.
2. `books.stock_qty` / `items.stock_qty` are derived through stock_movements — only the observer adjusts.
3. Every voidable financial row keeps `journal_entry_id`; void = `reverseForDocument` +
   entity-level `void()` (StockMovement::void also restores stock).
4. All filtered/ordered columns have indexes (performance migration 2026_08_12_100500
   + places/voided_at indexes in 2026_08_14_000004).
5. Journal lines and transfers carry DB CHECK constraints (2026-08-15, migration
   `2026_08_15_add_accounting_check_constraints`) — a bare `DB::table` write cannot create an
   unbalanced line, a negative amount, a zero-amount transfer, or a self-transfer.
6. Statements are derived views only — `journal_entries` + `journal_entry_lines` are the single
   source of truth; nothing stores "balance" or "statement" rows.
7. Refund semantics: refunds increase a student's receivable (money returned to the student).
   Every consumer (model accessors, widgets, report formulas, printed statement) must use
   `balance = charges − payments + refunds` (voided excluded) — see ACCOUNTING_ARCHITECTURE §7.