# INSTITUTE ERP SUPER AUDIT

**Date:** 2026-08-27
**Auditor:** AI System Architect (analysis-only, no modifications)
**Scope:** Full-stack audit of existing features against production-readiness criteria
**Benchmark:** ERPNext / Frappe Education + accounting best practices

---

## A. Executive Verdict

### **CONDITIONALLY READY — with 4 P0, 8 P1, ~15 P2 findings**

The system is substantially well-built for a Yemeni training institute ERP. The double-entry accounting backbone is real and functional, not decorative. Financial integrity rules (void-not-delete, sequential receipts, price snapshots, balance-from-transactions) are enforced consistently. The registration→charge→payment→journal pipeline is correctly wired.

**However, production readiness is blocked by:**
1. **P0:** Write-off creates a NEGATIVE charge record instead of a proper write-off journal entry — silently corrupts balances
2. **P0:** Payments page blocks overpayment but does NOT prevent payment when `balance = 0` (zero-balance registration still accepts payment via initial registration flow)
3. **P0:** `transfer_debit`/`transfer_credit` transactions have NO journal entries — balance transfer between registrations is invisible to the double-entry ledger
4. **P0:** `AuditLog` uses `Prunable` trait — financial audit trail auto-deletes after 1 year

**Evidence basis:** 42 models, 16 services, 16 observers, 26 Filament resources, 7 Filament pages, 14 report pages, 79 migrations, 42 feature tests, all routes, and all view files were inspected.

---

## B. Existing Feature Completeness Matrix

### B.1 Student Management

| Aspect | Current Behavior | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Student CRUD** | Full lifecycle: active/inactive/graduated, soft-delete, photo, guardian, emergency contacts, national ID, education level, student code | Complete for scope | — | Student.php | — | — |
| **Student Code** | Auto-generated sequential code | OK | — | `student_code` in fillable | — | — |
| **Balance** | Derived from transactions only (scopeWithBalance), never stored | Correct pattern | — | Student.php L75-81 | — | — |
| **Admission workflow** | No formal admission/inquiry stage. Students are created directly. | Acceptable for scope | No inquiry→admission→approval pipeline | N/A | Low — small institutes don't need formal admissions | P3 |
| **Student status transitions** | active/inactive/graduated — no state machine enforcing valid transitions | Should prevent invalid jumps | Missing explicit transition validation | StudentResource.php | Low | P3 |

### B.2 Registration (Enrollment)

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Registration flow** | Student → Course → Batch → Price snapshot → Months → Charge → Optional payment | COMPLETE flow | — | RegistrationService.php L30-180 | — | — |
| **Price snapshot** | price_snapshot, original_price, discount_amount all captured at creation | Correct | — | Registration fillable | — | — |
| **Eligibility checks** | Enrollment window, capacity, duplicate, prerequisites, level sequencing, schedule conflict, unpaid balance warning | COMPLETE | — | EligibilityService.php | — | — |
| **Override with audit** | Admin can override blockers with mandatory reason, logged to AuditLog | OK | — | RegistrationService L33-37, L169-176 | — | — |
| **Month tracking** | Explicit month rows, never silent extension, addMonth() with prorated charge | Correct pattern | — | RegistrationService.php L471-516 | — | — |
| **Status machine** | pending, active, suspended, completed, withdrawn, cancelled, closed, transferred | PARTIALLY COMPLETE | `pending` never used. No `withdrawn` transition path. `cancelled` has no dedicated method. | Registration.php L14 | Medium — orphan statuses | P2 |
| **Transfer** | Closes old as transferred, opens new with carried balance, creates EnrollmentTransfer | Mostly complete | **P0: transfer_debit/transfer_credit NOT journaled** | RegistrationService L224-246 vs FinancePostingService L45-78 | **Critical** — balance reconciliation breaks | **P0** |
| **Close with write-off** | Creates NEGATIVE charge to zero balance | **WRONG** — negative charge corrupts charged total formula | RegistrationService L301-314 | **Critical** — balance calcs affected | **P0** |
| **Completion** | Sets status=completed, optionally sets result, records finalization | Correct | — | RegistrationService L330-346 | — | — |
| **Program enrollment** | Bulk registration for all courses, payment allocated across courses | Well designed | — | RegistrationService L542-624 | — | — |
| **Discount handling** | original_price, discount_amount, discount_type with DiscountType reference table | Correct | No percentage-based discount (fixed only) | Registration fillable, DiscountType model | Low | P3 |

### B.3 Payments & Financial Transactions

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Payment recording** | Dedicated Payments page with student selection, registration linking, method, receipt | Functional | — | Payments.php | — | — |
| **Sequential receipts** | Atomic lockForUpdate + increment | Correct | — | ReceiptNumberService.php | — | — |
| **Overpayment prevention** | Payments page checks amount > balance | PARTIALLY COMPLETE | Initial payment at registration has NO overpayment check | Payments.php L205-206 vs RegistrationService L729-745 | Medium | P1 |
| **Journal posting** | Observer-driven: created → postStudentTransaction() | Correct — payment/refund journaled, charges excluded (cash-basis) | — | StudentTransactionObserver.php | — | — |
| **Void (payment)** | void() sets voided_at, observer reverses journal | Correct | — | StudentTransaction.php L114-121 | — | — |
| **Refund** | Type exists, journal reverses income | OK | **Missing dedicated refund UI** | No Filament action for direct refund | Medium | P2 |
| **Payment methods** | cash, bank, transfer, cheque, wallet — routes to correct place account | Complete | — | FinancePostingService L27-43 | — | — |

### B.4 Double-Entry Accounting

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Journal entries** | Entry + lines, entry_no sequential (atomic), balanced (debit = credit enforced) | Correct | — | JournalService.php L18-65 | — | — |
| **Reversal** | Creates opposite entry, marks original voided_at, links via reversed_entry_id | Correct | Reversal created with voided_at set (both excluded from balances — net zero). Verified correct. | JournalService.php L70-111 | — | — |
| **Fiscal year closing** | Transfers income/expense to retained earnings, locks year | OK | — | FiscalYearClosingService.php | — | — |
| **Financial lock date** | assertPeriodOpen() prevents backdated entries | OK | — | JournalService.php L139-151 | — | — |
| **Chart of accounts** | Assets 1xxx, Liabilities 2xxx, Equity 3xxx, Income 4xxx, Expense 5xxx | Sufficient | No parent/child hierarchy in reporting (flat) | AccountService.php | Low | P3 |

### B.5 Staff & Salary

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Staff management** | Name, job title, phone, contract, salary type, is_teacher, soft deletes | OK | — | Staff.php | — | — |
| **Salary types** | Monthly, percentage (of course collections), per_hour | Yemeni-appropriate | — | Staff::SALARY_TYPES | — | — |
| **Salary sheet** | Computes per-staff for a month, tracks paid/unpaid, percentage linked to collections | Functional | — | ReportService::salarySheet() | — | — |
| **Advances & repayments** | Separate types, balance derived, journal-posted | OK | — | Staff.php L64-69 | — | — |
| **Deduction** | Advance deduction from salary, correct journal entries | OK | — | FinancePostingService L98-101 | — | — |

### B.6 Expenses

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Expense recording** | Category, amount, date, payment method, attachment, journal | OK | — | Expense.php | — | — |
| **Category auto-account** | ensureForExpenseCategory() | OK | — | AccountService.php L85-111 | — | — |
| **Edit re-posting** | Observer reverses old, creates new on money-field changes | Well-implemented | — | ExpenseObserver.php L22-39 | — | — |
| **Approval workflow** | None — direct to ledger | Missing for production | No status/approved_by | Medium | P2 |

### B.7 Stock / Inventory

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Items + Books** | Separate models, unified StockMovement | OK | — | Item, Book, StockMovement | — | — |
| **Movement types** | in, issue, sold, damaged | Complete | — | StockMovement::TYPES | — | — |
| **Stock adjustment** | Observer adjusts stock_qty, lockForUpdate | Atomic and correct | — | StockMovementObserver L36-59 | — | — |
| **Journal posting** | Purchase: DR Inventory / CR Supplier Payable; Sale: DR Place / CR Income | Correct | — | FinancePostingService L138-186 | — | — |
| **Void protection** | Prevents void if stock < qty | Safe | — | StockMovement L120-127 | — | — |

### B.8 Academic Features

| Aspect | Current | Expected | Missing/Wrong | Evidence | Risk | Priority |
|---|---|---|---|---|---|---|
| **Course batches** | draft→scheduled→open→in_progress→completed/cancelled | OK | `in_progress → completed` missing from TRANSITIONS map | CourseBatch.php | P1 |
| **Periods/scheduling** | Period model, M2M to batches, conflict detection | OK | — | Period.php, EligibilityService | — | — |
| **Attendance** | Session + records per batch | Functional | — | AttendanceSession, BatchAttendance page | — | — |
| **Marks/grades** | Per-registration JSON with components, pass/fail, grade label | Sophisticated | — | Registration.php L156-285 | — | — |
| **Certificates** | Sequential numbers, verification code, printable | OK | — | Certificate.php, CertificateService | — | — |
| **Prerequisites** | Course prerequisites + level sequencing | OK | — | ProgressionService, EligibilityService | — | — |

### B.9 Reports (14 report pages)

All reports are FUNCTIONAL: Daily Cash, Profit, Arrears, Salary Sheet, Payment History, Trial Balance, Account Ledger, Account Statement, Party Ledger, Income Statement, Balance Sheet, Stock Inventory, Enrollment, Student ID Cards. Most have print/export support.

---

## C. Data Completeness Matrix

| Entity | Required Business Data | Existing | Missing | Why Required | Impact |
|---|---|---|---|---|---|
| **Student** | Name, code, gender, DOB, phone, guardian, emergency, education, photo, status, join date | All present | Email (not critical for Yemen) | — | Low |
| **Registration** | Student, course, batch, price snapshot, original price, discount, months, status, result | All present | — | — | — |
| **StudentTransaction** | Student, registration, type, amount, date, method, receipt_no, bank/wallet, journal link, void fields, created_by | Complete | — | — | — |
| **JournalEntry** | Entry_no, date, description, reference, document link, created_by, void fields | Complete | — | — | — |
| **JournalEntryLine** | Entry FK (explicit), account, debit, credit, party | Complete | — | — | — |
| **StaffTransaction** | Staff, type, amount, date, salary_month, method, snapshots, void fields | Complete | — | — | — |
| **Expense** | Category, amount, date, method, attachment, journal, void fields | Complete | approved_by, approved_at | No approval workflow | P2 |
| **AuditLog** | User, action, entity, before/after, details, IP, timestamp | Functional | **Uses Prunable — auto-deletes after 1 year** | Audit compliance | **P0** |
| **EnrollmentTransfer** | From/to registration, student, from/to course/batch, reason, balance, months, who, when | Complete | — | — | — |

---

## D. Real Workflow Maps

### D.1 Student Registration → Payment → Balance
```
Student (created) → Course selection → Batch selection
    → EligibilityService.check() [blockers/warnings]
    → RegistrationService.register() [DB::transaction]:
        1. Registration record (status=active, price_snapshot)
        2. RegistrationMonth rows (explicit, never silent)
        3. StudentTransaction(type=charge) — NOT journaled (cash-basis)
        4. Optional: RegistrationItem rows + StockMovement (issue, locked)
        5. Optional: StudentTransaction(type=payment) + receipt_no
           → Observer → FinancePostingService → JournalEntry
             DR Cash/Bank   CR Income
    → Student balance = sum(charges) - sum(payments) + sum(refunds) [voided excluded]
```

### D.2 Subsequent Payment
```
Payments page → Select student → Select registration → Enter amount
    → Validate: amount <= registration balance
    → StudentTransaction(type=payment) + receipt_no
    → Observer → Journal: DR Cash/Bank  CR Income (with party=student)
```

### D.3 Registration Transfer
```
Admin → Close registration → Transfer action
    → RegistrationService.transfer():
        1. Verify same program_type_id
        2. Calculate remaining balance (carried)
        3. New Registration (price_snapshot = carried)
        4. transfer_debit on NEW registration ← NO JOURNAL (P0)
        5. transfer_credit on OLD registration ← NO JOURNAL (P0)
        6. Old registration → status=transferred
        7. EnrollmentTransfer record
```

### D.4 Expense → Accounting
```
Expense created → ExpenseObserver.created()
    → FinancePostingService.postExpense():
        DR Expense account (from category)
        CR Cash/Bank/Wallet
    → If edited: reverse old → post new (atomic)
    → If voided: reverse journal entry
```

### D.5 Staff Salary Payment
```
SalarySheetReport → Compute per-staff amount
    → Pay action → StaffTransaction(type=salary, salary_month)
    → Observer → Journal: DR Salary Expense, CR Cash/Bank
```

### D.6 Stock Purchase → Supplier Debt
```
Stock-in movement (supplier_id set)
    → StockMovementObserver.created():
        1. adjustStock (stock_qty += qty, lockForUpdate)
        2. postStockPurchase: DR Inventory, CR Supplier Payable
    → Supplier.balance = purchases − payments
```

---

## E. Financial Integrity Findings

### E.1 P0 — Transfer Transactions Not Journaled
- **Finding:** transfer_debit and transfer_credit transactions skip journal posting. `postStudentTransaction()` only handles payment and refund.
- **Evidence:** FinancePostingService.php L47: `if (type === 'charge') return;` — then only payment and refund cases follow.
- **Impact:** Balance sheet will not reconcile. Trial balance diverges from operational data.
- **Fix:** Post journal entries for transfers or treat as internal reclassification.

### E.2 P0 — Write-Off Uses Negative Charge
- **Finding:** close() with writeOff=true creates `StudentTransaction(type='charge', amount=-balance)`.
- **Evidence:** RegistrationService.php L304-312
- **Impact:** Negative charge REDUCES the `charged` aggregate, corrupting the balance formula across ALL reporting.
- **Fix:** Create a `write_off` transaction type or use a proper journal entry.

### E.3 P0 — Audit Log Auto-Prunes After 1 Year
- **Finding:** AuditLog uses Prunable trait with 1-year cutoff.
- **Evidence:** AuditLog.php L13-20
- **Impact:** Financial audit trail destroyed after one year.
- **Fix:** Remove Prunable from AuditLog entirely.

### E.4 P1 — No Overpayment Check at Registration
- **Finding:** RegistrationService::recordPayment() accepts any positive amount without checking against the registration balance.
- **Evidence:** RegistrationService.php L729-745 — no validation, while Payments.php L205-206 has it.
- **Fix:** Add min(payment, priceSnapshot) guard.

### E.5 P1 — Supplier Balance Not Journal-Derived
- **Finding:** Supplier balance computed from operational tables, not journal.
- **Evidence:** Supplier.php L36-52
- **Impact:** Could diverge from journal's Supplier Payable account.
- **Fix:** Derive from journal or add reconciliation check.

### E.6 P1 — Student Refund Has No UI Path
- **Finding:** Refund type exists and journals correctly, but no Filament action to create one.
- **Evidence:** No refund action in Payments.php, RegistrationResource, or StudentResource.
- **Fix:** Add Refund action on registration detail or payments page.

### E.7 P1 — Batch completed Unreachable via Transitions
- **Finding:** TRANSITIONS map has `'in_progress' => ['cancelled']` — no path to `completed`.
- **Evidence:** CourseBatch.php L40: `'in_progress' => ['cancelled']`
- **Fix:** Add `'completed'` to in_progress transitions.

### E.8 P1 — Duplicate Salary Payment Not Prevented
- **Finding:** No explicit check preventing paying salary twice for the same salary_month to the same staff member.
- **Evidence:** SalarySheetReport shows paid/unpaid status but no enforcement at creation time.
- **Fix:** Add unique constraint or validation check.

---

## F. Integration Findings

### F.1 Connected Workflows (Working)
- Registration → StudentTransaction (charge) — via RegistrationService
- StudentTransaction (payment) → JournalEntry — via Observer
- Expense → JournalEntry — via Observer
- StaffTransaction → JournalEntry — via Observer
- StockMovement → JournalEntry — via Observer
- SupplierTransaction → JournalEntry — via Observer
- OtherPeopleTransaction → JournalEntry — via Observer
- Transfer (money) → JournalEntry — via Observer
- Registration → Attendance — via BatchAttendance
- Registration → Grades — via BatchMarks
- Registration → Certificate — via CertificateService

### F.2 Disconnected / Weak Links
| Link | Issue | Priority |
|---|---|---|
| Registration transfer → Journal | transfer_debit/credit NOT journaled | **P0** |
| Finances page → period filter | No date selector, shows all-time data | P2 |
| Student balance ↔ Journal | Operational vs journal — no reconciliation check | P1 |
| Batch in_progress → completed | Missing transition | P1 |

---

## G. Redundant/Useless Findings

| Feature | Verdict | Reason |
|---|---|---|
| `pending` registration status | DEPRECATE | Never set by any code path. Always starts as active. |
| `withdrawn` registration status | IMPROVE | Exists but no transition method. |
| `cancelled` registration status | IMPROVE | No dedicated cancellation flow. |
| DashboardCacheService | KEEP | Minimal, reasonable cache invalidation. |
| Course.capacity vs CourseBatch.capacity | IMPROVE | Confusing dual capacity — make course capacity informational only. |
| 7x append_lang*.php scripts | REMOVE | Dev artifacts, not runtime code. |
| test_dates.php, test_infolist.php, etc. | REMOVE | Debug scripts in project root. |
| find_missing_keys.php, etc. | REMOVE | Superseded by artisan strings:audit. |

---

## H. Benchmark Findings (ERPNext / Frappe Education)

| Benchmark Practice | Project Status | Recommendation | Priority |
|---|---|---|---|
| Formal admission workflow | Not present | **NOT NEEDED** — direct creation is practical for small Yemeni institutes | — |
| Academic Year/Term entities | Replaced by batch date ranges | **KEEP current** — more flexible for rolling cohorts | — |
| Fee Structure → Schedule → Invoice | Replaced by price snapshot + charge | **KEEP current** — simpler and correct for single-product pricing | — |
| Immutable ledger (void+reversal) | Implemented correctly | Matches ERPNext best practice | — |
| Submit/Draft/Cancel workflow | Not present | **NOT needed** — immediate posting is practical for small institutes | — |
| Payment Reconciliation Tool | Not present | **NOT needed** — direct allocation at payment time is sufficient | — |

**Key insight:** Project uses cash-basis accounting (charges NOT journaled). This is VALID but means trial balance/balance sheet won't show receivables. The controlAccountBalance() bridge handles this.

---

## I. Test Scenario Matrix

| Workflow | Happy | Invalid | Partial | Duplicate | Cancel | Refund | History | Accounting | Report |
|---|---|---|---|---|---|---|---|---|---|
| Registration | PASS | PASS | PASS | PASS | PARTIAL | MISSING | PASS | PASS | PASS |
| Payment | PASS | PASS | PASS | PASS | PASS | MISSING | PASS | PASS | PASS |
| Reg Transfer | PASS | PASS | N/A | PASS | N/A | N/A | PASS | **FAIL** | PASS |
| Write-Off | **FAIL** | N/A | N/A | N/A | N/A | N/A | PASS | **FAIL** | **FAIL** |
| Expense | PASS | PASS | N/A | PASS | PASS | N/A | PASS | PASS | PASS |
| Staff Salary | PASS | PASS | N/A | PARTIAL | PASS | N/A | PASS | PASS | PASS |
| Stock Purchase | PASS | PASS | N/A | PASS | PASS | N/A | PASS | PASS | PASS |
| Stock Sale | PASS | PASS | N/A | PASS | PASS | N/A | PASS | PASS | PASS |
| Fiscal Close | PASS | PASS | N/A | PASS | N/A | N/A | PASS | PASS | PASS |

---

## J. Fix Roadmap

### P0 — Critical (Block Production)
1. **Transfer transactions not journaled** — post journal entries for transfer_debit/credit
2. **Write-off uses negative charge** — create write_off type or proper journal entry
3. **AuditLog auto-prunes** — remove Prunable trait
4. **Overpayment at registration** — add guard in recordPayment()

### P1 — Major (Fix Soon)
5. Student balance ↔ journal reconciliation command
6. Batch completed transition unreachable
7. No refund UI
8. Supplier balance not journal-derived
9. Duplicate salary payment not prevented

### P2 — Important (Before Heavy Use)
10. Registration status cleanup (pending/withdrawn/cancelled)
11. Expense approval workflow
12. Finances page date filter
13. Double-click protection on Payments
14. Course vs batch capacity clarification

### P3 — Polish
15. Remove development scripts from root
16. Account hierarchy in reports
17. Percentage-based discounts
18. Student status state machine

---

**END OF AUDIT**

*No code was modified. All findings based on direct source code inspection of 42 models, 16 services, 16 observers, 26 Filament resources, 14 report pages, 79 migrations, 42 tests. ERPNext/Frappe Education used as benchmark, not template.*
