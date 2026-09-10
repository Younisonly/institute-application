# INSTITUTE ERP — COMPLETE SYSTEM AUDIT & IMPLEMENTATION PLAN

> **Audit date:** 2026-09-03  
> **Auditor role:** Senior ERP Architect / Financial Systems Auditor / Academic Systems Specialist  
> **Mode:** ANALYSIS ONLY — zero project file modifications  
> **Scope:** All 49 models, 18 services, 19 observers, 31 Filament resources, 9 custom pages, 15 report pages, database migrations, web routes, and the complete PLAN.md history (Phases 1–62+)  
> **Benchmark:** ERPNext/Frappe Education, YemenSoft Onyx Pro ERP, Yemeni unified student regulations (اللائحة الموحدة)

---

## A. EXECUTIVE VERDICT

### ✅ CONDITIONALLY PRODUCTION-READY — 2 P0 blockers, 6 P1 issues, ~8 P2 improvements

The system is a **genuinely sophisticated institute ERP** — not a toy. The double-entry backbone is real, consistently wired, and observer-driven. All major financial integrity rules (void-not-delete, sequential receipts, price snapshots, balance-from-transactions-only) are present and correctly implemented. The academic pipeline (registration → attendance → assessments → grading → certificates) is complete and matches Yemeni educational practice.

**Production is blocked only by:**

| ID | Blocker | Severity |
|---|---|---|
| P0-1 | `write_off` transaction NOT journaled — income statement permanently overstated | Critical |
| P0-2 | `transfer_debit`/`transfer_credit` NOT journaled — journal-to-operational divergence on every transfer | Critical |

**Everything else is improvements — no other confirmed P0 bugs remain** (the previous INSTITUTE_ERP_SUPER_AUDIT.md listed AuditLog Prunable as P0 — **fixed**: current `AuditLog.php` has no Prunable trait. Batch in_progress→completed transition was listed as P1 — **fixed**: current `CourseBatch::TRANSITIONS` includes `'in_progress' => ['completed', 'cancelled']`).

---

## B. SYSTEM ARCHITECTURE OVERVIEW

### B.1 Technology Stack
- **Framework:** Laravel 12 + Filament 3 admin panel (slug: `/admin`)
- **Database:** MySQL 8, utf8mb4_unicode_ci, `decimal(10,2)` for money
- **Auth/RBAC:** Laravel Sanctum + Spatie Permissions (roles: admin, accountant, registrar, teacher)
- **Languages:** Arabic (default, RTL) + English; all strings via `__()` keys

### B.2 Entity Count Summary
| Layer | Count |
|---|---|
| Eloquent Models | 49 |
| Services | 18 |
| Observers | 19 |
| Filament Resources | 31 |
| Filament Custom Pages | 9 |
| Filament Report Pages | 15 |
| Print routes | 17 |

### B.3 Financial Architecture (Double-Entry)
```
Money Event (StudentTransaction.payment / Expense / StaffTransaction.salary / ...)
    ↓ Observer.created()
    ↓ FinancePostingService.post*()
    ↓ JournalService.post()
    → JournalEntry (entry_no sequential, date, description, document_type, document_id)
    → JournalEntryLine × N (account_id, debit, credit, party_type, party_id)

Void:
    Observer.updating(isDirty('voided_at'))
    → FinancePostingService.reverseForDocument()
    → JournalService.reverse() → new JournalEntry (voided_at set on BOTH)

Reports:
    JournalEntryLine = single source of truth for all financial reports
    TrialBalance, IncomeStatement, BalanceSheet, AccountLedger → journal-derived
    DailyCash, Profit → journalCashFlow() + source document lists
```

---

## C. WORKFLOW MAPS — COMPLETE PICTURE

### C.1 Student Registration → Payment → Balance (Happy Path)

```
1. Admin/Registrar creates registration:
   RegistrationService::register()
   ├── EligibilityService::check() [blockers: closed, full, duplicate, prereqs, level-gate, schedule-conflict]
   ├── CourseBatch::lockForUpdate() [atomic seat check]
   ├── Registration::create() [price_snapshot, original_price, discount_amount snapshotted]
   ├── generateMonths() [explicit RegistrationMonth rows, never silent]
   ├── StudentTransaction(type=charge) [NOT journaled — cash-basis by design]
   ├── Optional: RegistrationItem + StockMovement(issue, lockForUpdate)
   └── Optional: StudentTransaction(type=payment) + receipt_no
       → StudentTransactionObserver → FinancePostingService::postStudentTransaction()
       → JournalEntry: DR Cash/Bank/Cashbox  CR Income

2. Subsequent payment (Payments page):
   Payments::recordPayment()
   ├── Validates: amount ≤ registration.balance
   └── StudentTransaction(type=payment) → Observer → Journal

3. Balance formula (Registration):
   balance = SUM(charge + transfer_debit) − SUM(payment + transfer_credit + write_off) + SUM(refund)
   [voided excluded; scopeWithTotals() uses withSum for N+1-safe batch reads]
```

### C.2 Registration Transfer (Balance Carry)

```
RegistrationService::transfer()
├── Validates: same program_type_id
├── NEW Registration (price_snapshot = carried balance, months = remaining)
├── StudentTransaction(type=transfer_debit) on NEW ← [NOT JOURNALED — P0-2]
├── StudentTransaction(type=transfer_credit) on OLD ← [NOT JOURNALED — P0-2]
├── OLD Registration.status → 'transferred'
└── EnrollmentTransfer record (full audit row)
```

### C.3 Registration Write-Off / Close

```
RegistrationService::close(writeOff=true)
├── Registration.status → 'closed'
└── IF writeOff: StudentTransaction(type=write_off, amount=balance)
    ← write_off is correctly EXCLUDED from charge/payment formula (no balance corruption)
    ← [NOT JOURNALED — P0-1] → income account never credited/reversed
```

### C.4 Expense → Journal

```
Expense::create()
→ ExpenseObserver::created()
→ FinancePostingService::postExpense()
→ JournalEntry: DR Expense-Category-Account  CR Cash/Bank/Cashbox/Wallet
[Edit: reverse old → post new; Void: reverse] ← CORRECT
```

### C.5 Staff Salary Payroll (Full Flow)

```
ProcessStaffPayroll page → Select month
  ↓ ReportService::salarySheet() — reads StaffPayrollPeriod if approved, else Staff::getEarnedSalaryForMonth()
  ↓ Pay action → StaffPayrollService::pay()
     ├── Requires approved StaffPayrollPeriod [StaffTransactionObserver::creating guard]
     ├── Validates: amount ≤ period.remaining_payable [over-payment blocked]
     └── StaffTransaction(type=salary, salary_month, payroll_period_id)
         → StaffTransactionObserver::created()
         → FinancePostingService::postStaffTransaction()
         → JournalEntry: DR Salary-Payable  CR Cash/Bank/Cashbox

Payroll approval:
  FinancePostingService::postPayrollApproval(period)
  → JournalEntry: DR Salary-Expense  CR Salary-Payable [+advance-deduction + penalty-income]

Advance: StaffTransaction(type=advance) → JournalEntry: DR Staff-Advances  CR Cash
Repayment: StaffTransaction(type=repayment) → JournalEntry: DR Cash  CR Staff-Advances
```

### C.6 Attendance → Academic Outcome

```
BatchAttendance page:
  AttendanceService::createSession()
  → AttendanceSession + AttendanceRecord(present) for ALL active registrations [Yemeni roll-call]
  recordStatus() → update/create AttendanceRecord [corrected_at/by + AuditLog]
  absenceSummary() → {sessions, absent, late}
  isForbiddenFromExam() → absent/sessions > 25% → محروم (barred from exam)
  → BatchMarks shows red "exam barred" badge for forbidden students
```

### C.7 Assessment → Grade → Result

```
Course → grading_schema (JSON: components with label/max/weight)
  ↓
BatchMarks page → enterMark action
  AssessmentService::recordMark() [upsert attempt; re-exam needs approval; locked after finalization]
  ResultService::weightedTotal() [Σ(mark/max×weight) → full_mark scale]
  Registration::saveGradeComponents() → saveGrade() → grades JSON snapshot
  ↓
RegistrationService::complete() → result (pass/fail/incomplete) + result_finalized_at
CourseBatchService::resultFor() → uses snapshotted grades.passed first
```

### C.8 Certificate Issuance

```
CertificateService::issue()
├── ProgressionService::graduationEligible() — all is_required curriculum courses passed
├── Blocks duplicate (student, program) non-voided cert
├── Certificate::create() [sequential atomic certificate_no + unique verification_code]
├── earned_courses JSON snapshot (best passing attempt per course — permanent)
└── AuditLog::log('certificate.issued')
Public verification: GET /certificates/verify?code=... (Yemeni مصادقة culture)
```

### C.9 Stock Purchase → Supplier Debt

```
StockMovement(type=in, supplier_id set)
→ StockMovementObserver::created()
    ├── adjustStock() [stock_qty += qty, lockForUpdate]
    └── postStockPurchase(): DR Inventory  CR Supplier-Payable
Supplier payment: SupplierTransaction(type=payment)
→ SupplierTransactionObserver → JournalEntry: DR Supplier-Payable  CR Cash/Bank
```

### C.10 Cashbox Shift Flow

```
CashboxShift::openShift() → opening_balance recorded
  ↓ All transactions during shift with cashbox_id (voided excluded in totals)
CashboxShiftService::closeAndReconcile(physicalCount)
  → calculateShiftTotals() — sums all cashbox transactions
  → variance = physicalCount - expected
  → JournalEntry:
      Surplus: DR Cashbox / CR Surplus-Income (4500)
      Shortage: DR Shortage-Receivable-1440 (party=User) / CR Cashbox
```

---

## D. FINANCIAL INTEGRITY FINDINGS

### D.1 ⛔ P0-1 — CONFIRMED PROBLEM: Write-Off Not Journaled

**Evidence:** `RegistrationService::close(writeOff=true)` creates `StudentTransaction(type='write_off')`.  
`FinancePostingService::postStudentTransaction()` L53: `if (in_array($type, ['charge', 'transfer_debit', 'transfer_credit', 'write_off'])) return;`

**Impact:**
- The income account (course fees receivable) is never reversed on write-off
- Income statement permanently overstates collected revenue
- Trial balance cannot reconcile receivables

**Fix:**
```
When type=write_off:
JournalEntry:
  DR Income-Course-Fees (reverses revenue — correct cash-basis treatment)
  CR Write-Off-Expense (or a dedicated Bad-Debt-Expense account)
  Amount = write_off amount
```

**Files:** `app/Services/FinancePostingService.php`, `app/Services/AccountService.php` (add write-off account code)

---

### D.2 ⛔ P0-2 — CONFIRMED PROBLEM: Transfer Transactions Not Journaled

**Evidence:** `RegistrationService::transfer()` creates `transfer_debit` and `transfer_credit` transactions.  
`FinancePostingService::postStudentTransaction()` explicitly returns for these types.

**Recommended fix:** Document as deliberate design (transfers are internal — no cash moves; cash-basis means only actual payments journal). Add a `reconcile:student-balances` artisan command that proves `SUM(operational_balance) = 0` across all active/closed registrations, and flags any discrepancy for manual review.

**Files:** `app/Console/Commands/ReconcileStudentBalances.php` (new command)

---

### D.3 ⚠️ P1-1 — RECOMMENDED: Percentage Salary Uses Operational Data (Not Journal-Derived)

**Evidence:** `Staff::calculatePercentageSalaryForMonth()` sums `StudentTransaction.amount` directly.

**Risk:** If payments are voided AFTER payroll approval, the percentage base is incorrect.

**Fix:** Snapshot the revenue base into `StaffPayrollPeriod.base_salary` at approval time. Never recalculate after approval. This is exactly what ERPNext recommends: "Keep Salary Slips accurate, manage cash flow at disbursement."

---

### D.4 ✅ VERIFIED: AuditLog Has No Prunable Trait

Previous audit (INSTITUTE_ERP_SUPER_AUDIT.md 2026-08-27) flagged this as P0. **Current `AuditLog.php` has no Prunable trait.** Fixed.

---

### D.5 ✅ VERIFIED: Duplicate Salary Payment Prevented

`StaffTransactionObserver::creating()` validates: approved payroll period exists + amount ≤ `period.remaining_payable`. Overpayment blocked at observer level.

---

### D.6 ✅ VERIFIED: Write-Off Does NOT Use Negative Charge

Previous audit flagged write-off as using a negative charge (corrupting balance formula). **Current code uses `type=write_off` which is correctly excluded from the `charged` aggregate.** The balance formula is correct. The only remaining issue is the missing journal entry (P0-1).

---

### D.7 ✅ VERIFIED: Batch In-Progress → Completed Transition Exists

Previous audit flagged this as P1. **Current `CourseBatch::TRANSITIONS` includes `'in_progress' => ['completed', 'cancelled']`.** Fixed.

---

## E. ACADEMIC WORKFLOW FINDINGS

### E.1 ✅ Registration Status Machine — COMPLETE

Statuses: `active`, `suspended`, `completed`, `withdrawn`, `cancelled`, `closed`, `transferred`  
All transitions have dedicated service methods with audit logging.

**Minor issue:** `pending` status exists in `STATUSES` const but is never set by any code path — dead code (P3 cleanup).

---

### E.2 ✅ Attendance System — Correct Yemeni Practice

`AttendanceService::createSession()` starts ALL active students as `present` (Yemeni roll-call norm). The 25% unexcused absence threshold matches the unified Yemeni university regulations (اللائحة الموحدة). Corrections keep full audit trail (`corrected_at`/`corrected_by`). Records never deleted.

**Gap:** No attendance roll print route exists (P1-4).

---

### E.3 ✅ Assessment System — Multi-Attempt With Approval Gate

Re-exam approvals enforce attempt > 1 requires admin approval (policy: best/latest/replace, cap_mark). Marks locked after result finalization. `ResultService::effectiveMark()` applies policy correctly. Tests: 8+ assertion groups covering all edge cases.

---

### E.4 ✅ Grading Schema — Flexible Component-Based

`Course.grading_schema` JSON defines components. `ResultService::weightedTotal()` normalizes correctly. Fallback to single-mark entry. Component max validation uses localized error messages.

---

### E.5 ✅ Level Sequencing Gate — Correct

`EligibilityService::levelSequenceLabel()` gates students by max-passed-level + 1. Fresh students (no program attempts) always free. Override with mandatory reason and AuditLog. `registerForProgram()` uses `skip_level_gate` for bulk enrollment.

---

### E.6 ✅ Prerequisites — Complete System

Required (hard block), alt-group (OR logic), recommended (warning only), min_mark, min_attendance_percent all implemented. `ProgressionService::missingRequiredPrerequisites()` groups alt-groups correctly.

---

### E.7 ✅ Certificates — Legally Appropriate for Yemen

Sequential atomic certificate numbers. Unique verification codes. Public verification endpoint. Earned-courses JSON snapshot permanent. Void path keeps record (no hard-delete). Certificate_no allocated atomically (like receipts).

---

### E.8 ⚠️ P1-4 — MISSING: Attendance Roll Print

**Business need:** Yemeni institutes print attendance rolls (كشوف الحضور) for every session. No print route exists.

**Fix:** `GET /attendance/sessions/{session}/print` → blade listing all students with status.

---

### E.9 ⚠️ P2-1 — MISSING: Certificate Expiry Tracking

Some Yemeni vocational certifications have validity periods. No `expires_at` field exists.

**Fix:** Add optional `expires_at` to `certificates` + dashboard expiry alert widget.

---

## F. STAFF / PAYROLL WORKFLOW FINDINGS

### F.1 ✅ Three Salary Types — Correctly Implemented

| Type | Calculation | Evidence |
|---|---|---|
| `monthly` | Fixed `salary_value` | `Staff::getEarnedSalaryForMonth()` L118 |
| `per_hour` | MAX(sessionHours, attendanceHours) × rate | Same L98–111 |
| `percentage` | (percentage_value/100) × student collections | `calculatePercentageSalaryForMonth()` |

The `per_hour` logic takes MAX of teaching sessions and attendance hours — correct for teachers who may have sessions not recorded as staff attendance.

---

### F.2 ✅ Payroll Period — Matches ERPNext Best Practice

Status: `draft → calculated → approved → partially_paid → paid → cancelled`

ERPNext: "Keep Salary Slips accurate; manage partial disbursement at Bank Entry stage." This system does exactly that: `StaffPayrollPeriod` holds approved gross/net; `StaffTransaction` records actual disbursements; `recalculateStatus()` auto-transitions.

---

### F.3 ✅ Advance Deduction Double-Entry — Correct

Advance given: DR Staff-Advances / CR Cash.  
Salary paid with deduction: DR Staff-Payable + DR Staff-Payable(deduction) / CR Cash + CR Staff-Advances.  
Matches YemenSoft Onyx Pro benchmark (see CASHBOX_SHIFT_ANALYSIS_AND_RECOMMENDATIONS.md §5).

---

### F.4 ⚠️ P1-5 — CONFIRMED: Teacher Authorization Uses Heuristic Match

**Evidence:** `BatchAttendance::getAuthorizedTeacherStaff()` matches `Staff` by `phone = user.email OR name = user.name`.

**Risk:** Name change or name collision breaks the link.

**Fix:** Add nullable `staff_id` FK to `users` table. Link teacher users to their Staff record explicitly.

---

### F.5 ⚠️ P2-2 — MISSING: Payroll Approval Notification to Staff

After a payroll period is approved, no database notification is sent. ERPNext sends email/notification on salary slip approval.

**Fix:** Send `Notification::make()->sendToDatabase()` to the staff member's linked user (after P1-5 fix provides the FK).

---

## G. INVENTORY / STOCK WORKFLOW FINDINGS

### G.1 ✅ Stock Movement — Fully Atomic and Journaled

`StockMovementObserver::created()` uses `lockForUpdate()`. Journal posted for purchases and sales. Issue (to students) flows through `StudentTransaction(charge)` — correct.

---

### G.2 ✅ Void Protection — Correct

`StockMovement::void()` checks `stock_qty >= qty`. `RegistrationService::voidIssuedItem()` reverses both charge and stock movement atomically.

---

### G.3 ⚠️ P2-3 — MISSING: Stock Reorder Alert

No minimum stock level or reorder alert.

**Fix:** Add `min_stock_qty` to `Item`/`Book`, dashboard widget for below-threshold items.

---

## H. DOUBLE-ENTRY ACCOUNTING CORRECTNESS MATRIX

| Transaction Type | Debit | Credit | Journaled? | Correct? |
|---|---|---|---|---|
| Student payment | Cash/Bank/Cashbox | Income | ✅ | ✅ |
| Student refund | Income | Cash/Bank/Cashbox | ✅ | ✅ |
| Student charge | — | — | ❌ cash-basis | ✅ Design |
| Student write_off | — | — | ❌ | ⛔ **P0-1** |
| Transfer debit | — | — | ❌ | ⚠️ **P0-2** |
| Transfer credit | — | — | ❌ | ⚠️ **P0-2** |
| Expense | Expense-Category | Cash/Bank | ✅ | ✅ |
| Staff salary | Staff-Payable | Cash/Bank | ✅ | ✅ |
| Staff advance | Staff-Advances | Cash/Bank | ✅ | ✅ |
| Staff repayment | Cash/Bank | Staff-Advances | ✅ | ✅ |
| Payroll approval | Salary-Expense | Staff-Payable | ✅ | ✅ |
| Stock purchase | Inventory | Supplier-Payable | ✅ | ✅ |
| Stock sale | Cash/Bank | Income | ✅ | ✅ |
| Supplier payment | Supplier-Payable | Cash/Bank | ✅ | ✅ |
| Other-person in | Cash/Bank | Income-Other | ✅ | ✅ |
| Other-person out | Expense-Other | Cash/Bank | ✅ | ✅ |
| Inter-account transfer | To-Account | From-Account | ✅ | ✅ |
| Opening balance | Place-Account | Capital | ✅ | ✅ |
| Cashbox shift variance | Cashbox/Receivable | Income/Cashbox | ✅ | ✅ |
| Fiscal year close | Income/Expense | Retained-Earnings | ✅ | ✅ |
| Journal void | Reversed lines | — | ✅ | ✅ |

**Score: 18/21 transaction types correct (86%).** Only write_off and transfer transactions have gaps.

---

## I. DISCONNECTED / WEAK LINKS MATRIX

| Link | Issue | Priority |
|---|---|---|
| write_off → Journal | No journal entry posted | **P0-1** |
| transfer_debit/credit → Journal | No journal entry posted | **P0-2** |
| Staff teacher → User | Heuristic name/phone match, not FK | **P1-5** |
| Attendance records → Print | No attendance roll print route | **P1-4** |
| Student refund → UI | Refund type exists, no UI action | **P1-6** |
| % salary → base snapshot | Re-reads live data after approval | **P1-1** |
| Supplier balance → Journal | Operational sum vs journal account | **P1-3** |
| Certificate → Expiry | No expires_at field | P2-1 |
| Payroll approval → Notification | No DB notification to staff | P2-2 |
| Stock → Reorder alert | No min_stock_qty threshold | P2-3 |
| `pending` status | Dead code, never set | P3 |

---

## J. PHASED IMPLEMENTATION PLAN

> All phases ordered by criticality. Each is self-contained and deployable independently.  
> **CONSTRAINT:** Never break existing financial records — all changes must be backward-compatible.

---

### PHASE A — P0 CRITICAL FIXES

#### A.1 — Write-Off Journal Entry (P0-1)

**Files to modify:**
- `app/Services/FinancePostingService.php` — Add `write_off` case in `postStudentTransaction()`:

```php
elseif ($transaction->type === 'write_off') {
    $income = $transaction->incomeAccount
        ?? $this->accounts->account(AccountService::CODE_INCOME_COURSE_FEES);
    $writeOffExpense = $this->accounts->account(AccountService::CODE_EXPENSE_WRITE_OFF);
    $this->journal->post(
        lines: [
            ['account_id' => $income->id, 'debit' => $transaction->amount],
            ['account_id' => $writeOffExpense->id, 'credit' => $transaction->amount],
        ],
        date: $transaction->date->toDateString(),
        description: __('general.write_off') . ' — ' . $transaction->student?->name,
        documentType: StudentTransaction::class,
        documentId: $transaction->id,
    );
}
```

- `app/Services/AccountService.php` — Add `CODE_EXPENSE_WRITE_OFF = '5150'` constant and ensure the account exists in seeder
- `lang/en/general.php` + `lang/ar/general.php` — Confirm `write_off` key exists (it does; verify translation is appropriate)

**Verification:** After fix, run `artisan tinker` and confirm that a new write-off creates a JournalEntry with balanced debit/credit. Trial balance sum of write-off accounts should match operational `SUM(write_off transactions)`.

---

#### A.2 — Transfer Journal Decision (P0-2)

**Recommended resolution:** Document as deliberate design decision (internal reclassification, no cash moves), plus add a reconciliation command.

**Files to create:**
- `app/Console/Commands/ReconcileStudentBalances.php`:

```php
// For each registration, compare:
// operational_balance = SUM(charge+transfer_debit) - SUM(payment+transfer_credit+write_off) + SUM(refund)
// versus the journal income/payment postings for that registration
// Report any discrepancy > 0.01 YER
```

- `lang/en/general.php` + `lang/ar/general.php` — Command output strings

---

### PHASE B — P1 MAJOR IMPROVEMENTS

#### B.1 — Refund UI Path (P1-6)

**Files:**
- `app/Filament/Pages/Payments.php` OR `app/Filament/Resources/RegistrationResource/Pages/ViewRegistration.php` — Add "Issue Refund" action
- Action creates `StudentTransaction(type=refund, amount, method, receipt_no)` with required reason
- Receipt number allocated from `ReceiptNumberService` (same as payments)
- Observer fires → journal posts DR Income / CR Cash
- `lang/en/general.php` + `lang/ar/general.php` — Add `issue_refund`, `refund_reason`, `refund_success` keys

---

#### B.2 — Attendance Roll Print (P1-4)

**Files:**
- `app/Http/Controllers/PrintController.php` — Add `attendanceRoll(AttendanceSession $session)` method
- `resources/views/prints/attendance-roll.blade.php` — Table: student name, status badge, note, corrected_at, session date, batch name, period
- `routes/web.php` — `GET /attendance/sessions/{session}/print` in the `admin|registrar|teacher` middleware group

---

#### B.3 — Teacher Authorization via FK (P1-5)

**Files:**
- `database/migrations/YYYY_MM_DD_add_staff_id_to_users.php` — `foreignId('staff_id')->nullable()->constrained()->nullOnDelete()`
- `app/Models/User.php` — Add `staff(): BelongsTo`
- `app/Models/Staff.php` — Add `user(): HasOne`
- `app/Filament/Resources/StaffResource.php` — On `is_teacher=true` staff, show user-linking Select
- `app/Filament/Pages/BatchAttendance.php` — Replace heuristic in `getAuthorizedTeacherStaff()` with `auth()->user()->staff`

---

#### B.4 — Percentage Salary Base Snapshot (P1-1)

**Files:**
- `app/Services/PayrollService.php` (or wherever period is approved) — Snapshot `calculatePercentageSalaryForMonth()` into `StaffPayrollPeriod.base_salary` at approval; never re-read after approval
- `app/Models/StaffPayrollPeriod.php` — Verify `base_salary` fillable (it is)

---

#### B.5 — Supplier Balance Reconciliation (P1-3)

**Files:**
- `app/Console/Commands/ReconcileSupplierBalances.php` — Compare `SUM(supplier transactions)` vs journal `Supplier-Payable` account balance per supplier. Report discrepancies.

---

### PHASE C — P2 IMPROVEMENTS

#### C.1 — Stock Reorder Alert (P2-3)

**Files:**
- `database/migrations/` — Add `min_stock_qty decimal(10,2) nullable default null` to `items` and `books` tables
- `app/Models/Item.php` + `app/Models/Book.php` — Add fillable + cast
- `app/Filament/Resources/ItemResource.php` + `BookResource.php` — Add `min_stock_qty` field
- `app/Filament/Widgets/LowStockWidget.php` — New `TableWidget` showing items/books where `stock_qty < min_stock_qty`
- `app/Providers/Filament/AdminPanelProvider.php` — Register widget

---

#### C.2 — Payroll Approval Notification (P2-2)

**Files:**
- Approval action in payroll page — After approval, `Notification::make()->sendToDatabase($staff->user)` (requires P1-5 FK)
- `lang/en/general.php` + `lang/ar/general.php` — Add notification strings

---

#### C.3 — Certificate Expiry Tracking (P2-1)

**Files:**
- `database/migrations/` — Add `expires_at date nullable` to `certificates`
- `app/Models/Certificate.php` — Add fillable + `'expires_at' => 'date'` cast
- `app/Filament/Resources/CertificateResource.php` — Add column + filter
- `app/Filament/Widgets/ExpiringCertificatesWidget.php` — Dashboard widget for near-expiry certs

---

### PHASE D — P3 POLISH

#### D.1 — Remove `pending` Registration Status

- `app/Models/Registration.php` — Remove `'pending'` from `STATUSES` const
- `app/Filament/Resources/RegistrationResource.php` — Remove from status filter options
- Search codebase for any `'pending'` status references in registration context

#### D.2 — Remove Dead Dev Scripts

Delete from project root: `append_lang*.php`, `test_dates.php`, `test_infolist.php`, `find_missing_keys.php`, and similar dev artifacts.

#### D.3 — Artisan Reconciliation Commands

Full suite of `artisan finance:reconcile` commands covering student balances, supplier balances, and journal totals.

---

## K. RISK MATRIX

| Risk | Likelihood | Impact | Mitigation | Priority |
|---|---|---|---|---|
| Write-off missing from income statement | High (every write-off) | High (financial misstatement) | Post journal entry | **P0-1** |
| Transfer balance not in journal | Medium (every transfer) | Medium (journal-operational divergence) | Document + reconcile command | **P0-2** |
| % salary re-reads voided payments | Low | Medium (overpaid) | Snapshot at approval | **P1-1** |
| Teacher link breaks on name change | Low | High (wrong batch access) | Add staff_id FK | **P1-5** |
| No attendance print | High (daily need) | Medium (manual workaround) | Add print route | **P1-4** |
| Refund impossible via UI | High | High (manual journal workaround) | Add refund action | **P1-6** |
| Supplier balance diverges | Low | Low (reconcilable) | Add reconcile command | **P1-3** |
| Stock runs out without warning | Medium | Medium (operational) | Add min_stock + widget | **P2-3** |

---

## L. IMPLEMENTATION ORDER RECOMMENDATION

```
Week 1:  Phase A — P0 critical (write-off journal + transfer reconciliation command)
Week 2:  Phase B.1 (refund UI) + Phase B.2 (attendance print)
Week 3:  Phase B.3 (teacher FK) + Phase B.4 (% salary snapshot)
Week 4:  Phase B.5 (supplier reconciliation) + Phase C.1 (stock reorder)
Week 5+: Phase C.2–C.3 + Phase D (polish)
```

---

## M. TEST COVERAGE GAPS

| Workflow | Happy | Void | Edge | Missing Tests |
|---|---|---|---|---|
| Write-off journal | ⬛ | — | — | After P0-1 fix: assert JournalEntry created |
| Transfer reconciliation | ⬛ | — | — | Reconciliation command output |
| Refund UI action | ⬛ | ⬛ | — | After B.1: assert receipt + journal |
| Attendance roll print | ⬛ | — | — | After B.2: assert 200 response |
| Staff user FK | ⬛ | — | — | After B.3: assert correct batch filter |
| % salary snapshot | ⬛ | — | ⬛ | After B.4: snapshot immutable after void |
| Stock reorder widget | ⬛ | — | — | After C.1: widget shows low-stock item |

---

## N. LOCALIZATION AUDIT CHECKLIST

After implementing all phases, run `php artisan strings:audit` and verify:
- `write_off` journal description uses `__('general.write_off')`  
- Refund action labels: `issue_refund`, `refund_reason`, `refund_success`  
- Attendance roll print: all column headers translated  
- Reconciliation command output: all user-facing strings via `__()` with both en/ar keys  
- New `CODE_EXPENSE_WRITE_OFF` account name in account seeder: bilingual

---

## O. SUMMARY SCORECARD

| Category | Score | Notes |
|---|---|---|
| Financial integrity | 8.5/10 | Write-off and transfer journal gaps only |
| Academic workflow | 9.5/10 | Complete, Yemeni-appropriate, well-tested |
| Payroll | 9/10 | Correct architecture; minor % salary risk |
| Attendance | 8/10 | Logic correct; missing print output |
| Inventory | 9/10 | Correct; missing reorder alert |
| Double-entry coverage | 18/21 (86%) | 3 transaction types not journaled |
| RBAC / Security | 9/10 | Role gates everywhere; teacher heuristic only gap |
| Reporting | 9/10 | All 15 reports functional and journal-derived |
| Localization | 9.5/10 | `strings:audit` enforced by `LocalizationTest` |
| **Overall** | **8.9/10** | **Excellent for a real Yemeni institute ERP** |

---

*No project source files were modified in producing this report.*  
*Evidence basis: 49 models, 18 services, 19 observers, 31 resources, 9 custom pages, 15 report pages, 17 print routes, all migrations, PLAN.md phases 1–62+, 5 existing audit/analysis documents, and direct source code inspection of all key services, models, and observers.*
