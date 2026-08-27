# MASTER_RECOVERY_PLAN.md — Audit Findings & Remediation

Verified 2026-08-15 (accounting transformation audit). Register of every finding from the
2026-08-15 full accounting audit, organized by severity P0 → P3. Items carry: finding, root cause,
affected screens/entities, solution, migration, tests, status. Each fix is `[x]` + date when done.
Legacy `notes_to_fix.md` remains the UX/feature backlog.

## P0 — Accounting correctness

| ID | Finding | Root cause | Affected screens / entities | Fix | Migration | Tests | Status |
|---|---|---|---|---|---|---|---|
| ACC-001 | Student/registration receivable formula disagreed across surfaces. Model accessors + ArrearsReport used `charges − payments + refunds` (accounting-correct: a refund returns money to the student, increasing the receivable). Dashboard widgets and the printed statement used `− refunds`, and the printed statement running balance also subtracted refunds. Students with refunds showed different balances on different pages | The formula was copy-pasted per page instead of centralized; no regression test locked the refund sign | StatsOverview, ArrearsAlertWidget, PartyBalancesWidget, PrintController::statement, prints.statement | Single formula `charges − payments + refunds` (voided excluded) used everywhere; widgets + print fixed; shared helper added | — | StudentsStaffTest refund case + new AccountingCoreTest (statement print + widget path equality) | [x] 2026-08-15 |
| ACC-002 | `JournalService::post`/`reverse` balance & line checks live only in application code; a direct DB write could insert an unbalanced line, a negative amount, a both-sides line, a zero-amount transfer or a self-transfer | No DB-level invariants on the two core money tables | `journal_entry_lines`, `transfers`, `accounts` | Migration `2026_08_15_add_accounting_check_constraints`: CHECK `debit>=0`, `credit>=0`, exactly one side > 0, `transfers.amount>0`, `from!=to`, `accounts.type IN (...)`; `accounts.description` column added | `2026_08_15_add_accounting_check_constraints` | AccountingCoreTest: bare-insert violations rejected at DB level | [x] 2026-08-15 |
| ACC-003 | No UI could answer "why is this account balance X?" — the statement chain Account → Statement → Entry → Lines → Document did not exist. The ledger report showed lines without entry links, and journal entries did not link to their source document | Feature-by-feature growth: ledger/profit/daily-cash pages were built before account navigation existed | whole reporting area, `journal_entry_lines` read paths | New **AccountStatement** page (`ReportService::accountStatement()`): per-line debits/credits, counterparty (other side of the entry), party, running balance, entry_no; every row links to the journal view page; journal view links to the source document page and to per-account statements | — | AccountingCoreTest: statement traceability + consistency vs ledger/trial balance/income statement | [x] 2026-08-15 |
| ACC-004 | Chart of Accounts was invisible: no AccountResource; accounts only auto-created; no way to add accounts, read per-account balances in one place, or fix a mis-named generated account | Accounts were treated as infrastructure, not as a chart to be managed | all report pages, widgets | New **AccountResource** (nav "Ledger"): list (code/name/type/parent/place/balance/active), create custom accounts, edit names/parent/description/is_active, view with statement link; type locked when lines exist; system accounts protected | — | AccountingCoreTest: resource render + balance column | [x] 2026-08-15 |

## P1 — Report integrity

| ID | Finding | Root cause | Affected | Fix | Migration | Tests | Status |
|---|---|---|---|---|---|---|---|
| ACC-005 | `dailyCash()`/`profit()` summaries were transaction-derived (cash-basis tables) while every other finance report reads the journal — two sources of truth; stock sales were missing from daily-cash "collected", and supplier payments were counted as "spent" in profit (they're a liability reduction) | P3-2 legacy decision from 2026-08-14 audit | DailyCashReport, ProfitReport, prints.daily-cash, prints.profit | Both rewritten **journal-derived** (`ReportService::cashStatement`/`profit`): daily cash = place-account lines (in=debits, out=credits, counterpart per entry); profit = income/expense accounts for the month (closing excluded) — identical to income statement | — | AccountingCoreTest: daily-cash in/out equal place debits/credits; profit == income statement for the month | [x] 2026-08-15 |

## P2 — UX / hardening

| ID | Finding | Root cause / fix | Status |
|---|---|---|---|
| ACC-006 | `MovementsRelationManager` (Item & Book): insufficient-stock branch returns inside `DB::transaction` (commits nothing, no exception) and the trailing success toast still fires → user sees danger + success together | Fix: throw `ValidationException` in the branch; success toast only fires on actually-saved movements (both RMs) | [x] 2026-08-15 |
| ACC-007 | Dead English fallbacks `__('...') ?? 'English'` in TodayCollectionsWidget + ClosedYearsWidget (`__()` never returns null) | Removed the null-coalescing fallbacks | [x] 2026-08-15 |
| ACC-008 | "Today collections" disagreed between dashboard widget (student payments only) and Payments page (student payments + other-people `in`) | Widget now matches the payments-page definition (both include other-people `in`, voided excluded), shared query code | [x] 2026-08-15 |
| ACC-009 | `SalarySheetReport::hoursForm()` helperText dereferences possibly-null `$arguments` (`null['staff_id']`) | Null-safe access | [x] 2026-08-15 |
| ACC-010 | `MoneyPlacesWidget`, TrialBalance/BalanceSheet pages, ArrearsReport, JournalResource flow: formatting closures typed `Model $record` (may receive `null` on empty tables → TypeError) | Typed `?Model` + null-safe bodies (agent-invented B9 — fixed where confirmed) | [x] 2026-08-15 |
| ACC-011 | Journal void from the Journal page wrote no `audit_logs` entry (financial mutation with no audit trail beyond the void fields) | `AuditLog::log()` on journal void in `JournalResource` + regression assertion | [x] 2026-08-15 |

## P3 — Accepted limitations (documented, not scheduled — see ACCOUNTING_ARCHITECTURE §15)

1. Stock `in` without supplier → no inventory journal; no COGS credit on issues/sales.
2. Charges/billing rows not journaled (cash basis by design).
3. A few `JournalService` `RuntimeException` messages surface untranslated at the UI boundary.
4. Per-hour double-pay (duplicate StaffTransaction for same staff+month) not guarded.
5. Audit logs pruned after 1 year (deliberate).
6. No bank reconciliation against external statements (no external-statement concept).
7. No period (month) posting lock below the fiscal year (back-dated same-year entries allowed).

## Regression coverage (tests/Feature)

- `AccountingCoreTest` (new, 2026-08-15): refund-sign equality across all surfaces; statement
  traceability + running balance; statement⇔ledger⇔trial-balance⇔income-statement consistency;
  journal-derived daily cash & profit; DB-level constraint violations rejected; journal void writes
  audit log; AccountStatement + AccountResource pages render; refund-only student NOT in arrears.
- Existing: FinanceTest, AuditRecoveryTest, RegistrationFlowTest, StudentsStaffTest, LocalizationTest,
  AuditCrawlTest, ReportsPhase17Test (all kept green — full suite count at session end: **see
  AI_CONTINUATION.md**).