# Master Specification & Phased Instructions: Staff Payroll, Partial Salary Cuts, Backdated Months, Discounts & Batch-Hours Attendance
## Benchmark Standards: YemenSoft Onyx Pro ERP, ERPNext & Odoo Accounting

> **Target System:** Institute Management System (Yemen / YER)  
> **Purpose:** Authoritative Specification & Phased Execution Blueprint for Staff Payroll, Installment/Cut Payouts, Late Salary Months, Disciplinary Fines/Discounts, Course Batch Schedule Configuration, and Teacher Attendance Integration.

---

## 1. Executive Summary & Problem Analysis

### 1.1 Real-World Yemeni Institute Context
In Yemeni training institutes and regional ERP benchmarks (**YemenSoft Onyx Pro ERP**, **ERPNext**, **Odoo**), payroll and teacher management operate under specific business rules:

1. **Backdated & Late Salary Disbursements (صرف الرواتب المتأخرة):**
   Institutes may pay salaries late due to cash flow cycles (e.g., paying salary for Month `03/2026` in Month `06/2026`). The system must decouple the **Salary Month (`salary_month` / YYYY-MM)** from the **Payment Date (`date`)**.
2. **Partial Salary Payout Cuts / Installments (صرف الرواتب على دفعات وأقساط):**
   Salaries are rarely paid in one single lump sum. An institute may disburse a salary in 2, 3, or 4 partial cuts (e.g. paying 20,000 YER mid-month, 15,000 YER late-month, and 10,000 YER at final settlement).
3. **Discounts, Penalties & Absences (الخصومات والجزاءات والغياب):**
   Employees may incur disciplinary fines or absence discounts (`penalty_amount`) that directly reduce net payable salary.
4. **Loan Advance Deductions (خصم السلف المستحقة):**
   Outstanding employee advances (`outstanding_advance`) must be optionally deducted during any salary payout cut.
5. **Teacher Batch Hours & Attendance Integration (ربط ساعات وحضور المدرس بالمجموعات):**
   Teachers teach specific Course Batches (`course_batches`). Each batch defines:
   - **Daily Hours (ساعات الجلسة اليومية):** e.g., 2.00 hours/day.
   - **Total Course Hours (إجمالي ساعات الدورة):** e.g., 30 hours total.
   - **Working Days (أيام الدراسة):** e.g., Sunday, Tuesday, Thursday.
   Attendance records (`staff_attendances`) track actual teacher presence and auto-calculate working hours to drive per-hour salary calculations (`salary_type = 'per_hour'`).

---

## 2. Comprehensive Mathematical & Accounting Principles

### 2.1 The Monthly Salary Settlement Equation

For any staff member and salary month ($M = \text{YYYY-MM}$):

$$\text{Gross Earned Salary (استحقاق الراتب)} = \begin{cases}
\text{salary\_value} & \text{if } \text{salary\_type} = \text{'monthly'} \\
\left( \sum \text{Approved Attendance Hours in } M \right) \times \text{salary\_value} & \text{if } \text{salary\_type} = \text{'per\_hour'} \\
\text{Fee Collection Percentage Calculation} & \text{if } \text{salary\_type} = \text{'percentage'}
\end{cases}$$

$$\text{Net Payable Salary} = \text{Gross Earned Salary} - \text{Penalties/Discounts} - \text{Advance Deductions}$$

$$\text{Total Previously Paid Cuts} = \sum_{t \in T_M} t.\text{amount} \quad \text{where } T_M = \{ t \in \text{StaffTransaction} \mid t.\text{type} = \text{'salary'}, t.\text{salary\_month} = M, t.\text{voided\_at} = \text{null} \}$$

$$\text{Remaining Payable Balance} = \text{Net Payable Salary} - \text{Total Previously Paid Cuts}$$

### 2.2 Payment Cut Validation Rule
For any partial salary payout cut ($C$):
$$1 \le C \le \text{Remaining Payable Balance}$$

### 2.3 Double-Entry General Ledger Rules (`FinancePostingService`)

Every salary transaction cut posts a balanced double-entry journal entry:

| Account Code | Account Name | Debit (مدين) | Credit (دائن) | Description |
| :--- | :--- | :--- | :--- | :--- |
| **`5100`** | **Salary Expense (مصروف المرتبات والأجور)** | $\text{Gross Amount}$ | 0 | Debits total gross salary impact |
| **`1420`** | **Staff Advances (سلف وعُهد الموظفين)** | 0 | $\text{Advance Deducted}$ | Credits & settles staff loan advance |
| **`5100` / `4510`** | **Penalty / Fine Offset (خصومات وجزاءات)** | 0 | $\text{Penalty Amount}$ | Offsets salary expense for fines |
| **`1110` / `1120`** | **Cash / Bank / Cashbox (الصندوق / البنك)** | 0 | $\text{Net Paid Cut}$ | Credits treasury for cash disbursement |

$$\sum \text{Debits} = \sum \text{Credits} \implies \text{100\% Balanced Reconciled Ledger}$$

---

## 3. Database Schema Specifications

### 3.1 `course_batches` Table Schema Additions
```sql
ALTER TABLE `course_batches` 
ADD COLUMN `daily_hours` DECIMAL(5, 2) NOT NULL DEFAULT 2.00 AFTER `capacity`,
ADD COLUMN `total_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER `daily_hours`,
ADD COLUMN `working_days` JSON NULL AFTER `total_hours`;
```

### 3.2 `staff_transactions` Table Schema Additions
```sql
ALTER TABLE `staff_transactions` 
ADD COLUMN `penalty_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER `amount`;
```

### 3.3 `staff_attendances` Table Creation
```sql
CREATE TABLE `staff_attendances` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `course_batch_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'present', -- present, absent, late, excused
  `hours_worked` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_batch_id`) REFERENCES `course_batches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_staff_attendance_staff_date` (`staff_id`, `date`),
  INDEX `idx_staff_attendance_batch_date` (`course_batch_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Phase-by-Phase Instructions for AI Execution

### PHASE 1: Database Migrations & Eloquent Model Enhancements
- **Task 1.1:** Migration `2026_08_28_200000_add_schedule_columns_to_course_batches_table.php` adding `daily_hours`, `total_hours`, `working_days`.
- **Task 1.2:** Migration `2026_08_28_200100_add_penalty_amount_to_staff_transactions_table.php` adding `penalty_amount`.
- **Task 1.3:** Migration `2026_08_28_200200_create_staff_attendances_table.php` creating `staff_attendances`.
- **Task 1.4:** Update `CourseBatch.php`:
  - Add `$fillable`: `daily_hours`, `total_hours`, `working_days`.
  - Add `$casts`: `'daily_hours' => 'decimal:2'`, `'total_hours' => 'integer'`, `'working_days' => 'array'`.
- **Task 1.5:** Update `StaffTransaction.php`:
  - Add `'penalty_amount'` to `$fillable` and `$casts` (`'penalty_amount' => 'decimal:2'`).
- **Task 1.6:** Create `StaffAttendance.php`:
  - Fillable: `staff_id`, `course_batch_id`, `date`, `status`, `hours_worked`, `notes`, `created_by`.
  - Casts: `'hours_worked' => 'decimal:2'`, `'date' => 'date'`.
  - Relations: `staff()`, `courseBatch()`, `createdBy()`.
- **Task 1.7:** Update `Staff.php`:
  - Relations: `attendances(): HasMany`.
  - Method `getEarnedSalaryForMonth(string $month): float`:
    - Monthly: returns `(float) $this->salary_value`.
    - Per hour: queries `attendances()` where `WHERE DATE_FORMAT(date, '%Y-%m') = $month`, sums `hours_worked` for `present`/`late`, multiplies by `(float) $this->salary_value`.
    - Percentage: returns calculated percentage.

### PHASE 2: Course Batch Schedule & Hours UI (`CourseBatchResource`)
- **Task 2.1:** Edit `CourseBatchResource.php` form schema:
  - Add TextInput `daily_hours` (`numeric`, `default(2.00)`, `required`).
  - Add TextInput `total_hours` (`numeric`, `default(30)`, `required`).
  - Add CheckboxList `working_days` (`options`: `['sun' => __('general.day_sun'), 'mon' => __('general.day_mon'), 'tue' => __('general.day_tue'), 'wed' => __('general.day_wed'), 'thu' => __('general.day_thu'), 'fri' => __('general.day_fri'), 'sat' => __('general.day_sat')]`).
- **Task 2.2:** Edit `CourseBatchResource.php` table columns:
  - Add TextColumn `daily_hours`.
  - Add TextColumn `total_hours`.
  - Add TextColumn `working_days` (`badge`, `separator(',')`).

### PHASE 3: Teacher & Staff Attendance Management (`StaffAttendanceResource`)
- **Task 3.1:** Create `StaffAttendanceResource.php` with List, Create, Edit pages.
- **Task 3.2:** Form Schema:
  - Select `staff_id` (searchable, required, live).
  - Select `course_batch_id` (searchable, nullable, live, options filtered to batches assigned to selected staff).
  - DatePicker `date` (`default(now())`, required, live).
  - Select `status` (`options`: `present`, `absent`, `late`, `excused`, default `present`, live).
  - TextInput `hours_worked` (`numeric`, default auto-filled from `course_batch.daily_hours` when `status === 'present'`).
  - Textarea `notes`.
- **Task 3.3:** Table Columns:
  - TextColumn `date`, TextColumn `staff.name`, TextColumn `courseBatch.name`, TextColumn `status` (`badge`), TextColumn `hours_worked`.
- **Task 3.4:** Add "Record Batch Attendance" Header Action on `CourseBatchResource` to quickly record teacher attendance for a specific day.

### PHASE 4: Action Placement & Salary Payout Engine (`صرف راتب موظف`)
- **Task 4.1:** Form Logic for Salary Disbursement (`salaryAndDeductionFields`):
  - Select `staff_id` (searchable, required, hidden when called from single staff view).
  - Select `salary_month` (options: past 12 months + active month, default `now()->format('Y-m')`, live).
  - Placeholders (Live computed):
    - `earned_salary_placeholder`: shows `Staff::getEarnedSalaryForMonth($month)`.
    - `previously_paid_placeholder`: sums non-voided `salary` transactions for staff and `$month`.
    - `outstanding_advances`: shows `$staff->outstanding_advance`.
    - `remaining_payable_placeholder`: `Earned - Deductions - Penalties - Previously Paid`.
  - MoneyInput `deduct_advance_amount` (optional).
  - MoneyInput `penalty_amount` (optional discount/fine, live).
  - MoneyInput `amount` (partial cut payout amount, required, validated $\le \text{Remaining Payable}$).
  - DatePicker `date` (default today, required).
  - `...PaymentDetails::fields()` (method, cashbox_id, bank_id, wallet_id, reference, description).
- **Task 4.2:** Action Placement:
  - Place `salaryPayment` Action on:
    1. `StaffResource` Table Header Actions & Table Row Actions (`->recordTitleAttribute('name')`).
    2. `ViewStaff` Header Actions.
    3. `TransactionsRelationManager` Header Actions.
    4. `SalarySheetReport` Row Actions.

### PHASE 5: Double-Entry Journal Posting (`FinancePostingService`)
- **Task 5.1:** Update `postStaffTransaction(StaffTransaction $transaction)`:
  - Compute gross salary amount: `$transaction->amount + $transaction->penalty_amount`.
  - Post journal entry:
    - Line 1: Debit Salary Expense (`5100`) for `$transaction->amount + $transaction->penalty_amount`.
    - Line 2: Credit Cash/Bank/Cashbox place account for `$transaction->amount`.
    - Line 3 (if penalty > 0): Credit Penalty Offset (`5100` / `4510`) for `$transaction->penalty_amount`.

### PHASE 6: Localization & Verification Audit
- **Task 6.1:** Add bilingual keys to `lang/ar/general.php` and `lang/en/general.php`:
  - `daily_hours`, `total_hours`, `working_days`, `staff_attendances`, `hours_worked`, `penalty_amount`, `partially_paid`, `remaining_payable`, `previously_paid`, `earned_salary`, `salary_payment_cut`.
- **Task 6.2:** Add attributes to `lang/ar/validation.php` & `lang/en/validation.php`.
- **Task 6.3:** Run `/usr/bin/php artisan strings:audit` to verify **0 unlocalized findings**.
- **Task 6.4:** Create `StaffPayrollPartialCutsAndAttendanceTest.php`:
  - Test batch hours & working days schema.
  - Test teacher attendance recording & per-hour calculation.
  - Test backdated month payouts (Month 03 paid in Month 06).
  - Test multi-cut partial payouts (Cut 1: 20,000, Cut 2: 25,000).
  - Test penalty discount & double-entry journal balance.
- **Task 6.5:** Run full test suite: `/usr/bin/php vendor/bin/phpunit`.

---

## 5. Verification & Audit Matrix

| Test Objective | Expected Verification Outcome |
| :--- | :--- |
| **Backdated Month Payout** | Selecting `salary_month = '2026-03'` on `2026-06-15` accurately queries Month 03 earned salary and past cuts. |
| **Partial Cut 1** | Paying 20,000 YER out of 45,000 YER payable leaves 25,000 YER remaining balance for that month. Status becomes `partially_paid`. |
| **Partial Cut 2** | Paying remaining 25,000 YER updates month status to `paid`. |
| **Journal Balancing** | Every cut generates balanced journal lines ($\sum \text{Debits} = \sum \text{Credits}$). |
| **Teacher Attendance** | Per-hour staff salary automatically aggregates `hours_worked` from approved attendance records. |
| **Zero String Audit Findings** | `/usr/bin/php artisan strings:audit` exits 0. |
