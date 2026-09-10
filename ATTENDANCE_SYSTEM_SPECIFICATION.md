# ATTENDANCE_SYSTEM_SPECIFICATION.md — Comprehensive Employee & Teacher Attendance Architecture Plan

> **Note:** This specification defines the unified architecture, business logic, RBAC security, past-date locking rules, and phased implementation workflow for replacing the redundant attendance resources with a best-practice Attendance Management System in the Institute ERP.
> **Constraint Enforced:** No source code modifications are made during this planning phase. This document serves as the authoritative blueprint.

---

## 1. System Audit & Root Problem Analysis

### 1.1 Current Architecture Issues
In the current codebase (`institut/`), staff and teacher attendance is fragmented across two disconnected resources:
1. **`StaffAttendanceResource`** (`app/Filament/Resources/StaffAttendanceResource.php`):
   - Manages `StaffAttendance` model (`staff_id`, `course_batch_id`, `date`, `status`, `hours_worked`, `notes`, `created_by`).
   - Generic table listing individual attendance rows without employee-level aggregation or history drill-down.
2. **`TeachingSessionResource`** (`app/Filament/Resources/TeachingSessionResource.php`):
   - Manages `TeachingSession` model (`course_batch_id`, `period_id`, `date`, `primary_teacher_id`, `actual_teacher_id`, `status`, `planned_hours`, `actual_hours`, `cancellation_reason`, `notes`, `created_by`).
   - Tracks teaching sessions per batch and syncs with `AttendanceSession` via `TeachingSessionObserver`.

### 1.2 Core Deficiencies Identified
- **Fragmented User Experience:** Users must navigate two separate pages to track staff presence versus teacher sessions, leading to double-entry confusion.
- **Unrestricted Past-Date Editing:** Non-admin roles (e.g. `accountant`, `teacher`) can edit past attendance records at any time, compromising historical data integrity and payroll audit trails.
- **Unfiltered Batch Selection:** In existing forms, batch dropdowns list all batches in the database, rather than dynamically restricting selection to active studying batches (`in_progress`) assigned to the specific teacher.
- **Missing Employee Drill-Down History:** No dedicated per-employee attendance ledger exist where administrators can inspect an employee's full attendance history across a customized date range (`from_date` to `to_date`).
- **Missing Audit & Reversal Guards:** Lack of explicit RBAC gates enforcing that past date records can **only** be modified by the `admin` role.

---

## 2. Target Architecture & Design Principles

### 2.1 Unified Navigation & Model Integration
- **Remove Redundant Navigation Items:** Remove `TeachingSessionResource` from sidebar navigation and consolidate all staff/teacher attendance under a single unified navigation entry: **`general.staff_attendances`** (حضور وتبصيم الموظفين والمعلمين).
- **Dual-Model Synchronization Engine:** 
  - Attendance records for general staff create a `StaffAttendance` record.
  - Attendance records for teachers with batch selection atomically create/update **both** `StaffAttendance` and `TeachingSession` (which in turn triggers `TeachingSessionObserver` to sync `AttendanceSession` for student attendance).
  - Payroll calculation (`Staff::getEarnedSalaryForMonth`) consumes consolidated actual hours seamlessly.

---

## 3. UI/UX Specifications & Workflows

### View 1: Master All Staff Table & Quick Attendance Hub (`/admin/staff-attendances`)
- **Master Table Features:**
  - Displays all staff members (`Staff` model) regardless of job title or teacher status.
  - Columns:
    - **Photo & Code:** Avatar + Staff ID.
    - **Staff Name & Job Title:** Name in bold + job title badge.
    - **Staff Type:** Badge indicating `Teacher` (معلم) or `Employee` (إداري/موظف).
    - **Today's Status:** Dynamic status badge for today's date (`Present`, `Absent`, `Late`, `Excused`, `Cancelled Session`, or `Not Recorded Yet`).
    - **Today's Hours:** Numeric display of recorded working/teaching hours today.
    - **Actions:** "View Attendance History" button + "Register Attendance" button.
  - **Search & Filters:**
    - Live text search by staff name, phone, contract number.
    - Select filter by job title / department.
    - Select filter by Staff Type (`Teacher` vs `Administrative`).
    - Select filter by Today's Status.

- **Header Action: "Register Attendance" (تسجيل الحضور):**
  - Opens a dynamic modal / slide-over form:
    1. **`staff_id` (Select):** Pick staff member (live reactivity).
    2. **`date` (DatePicker):** Default today (`now()`). 
       - *Past-Date Lock Enforcement:* If `date < today`, the date input and form submit are strictly restricted to `admin` role users. Non-admin users see a disabled/warning state.
    3. **`status` (Select):** Options: Present (`present`), Absent (`absent`), Late (`late`), Excused (`excused`), Cancelled Session (`cancelled_session`).
    4. **`course_batch_id` (Select - Conditional):**
       - **Visible ONLY when selected staff is a teacher (`is_teacher == true`).**
       - **Strict Options Filter:** Queries `CourseBatch` where:
         - `status = 'in_progress'` (الدفعة قيد الدراسة حالياً).
         - AND (`teacher_id = staff_id` OR assigned in active `TeacherAssignment`).
       - *Auto-fill Logic:* Selecting a batch pre-fills `planned_hours` and `hours_worked` with `course_batch.daily_hours`.
    5. **Teaching Session & Substitution Details (if Teacher):**
       - `period_id` (Select from `Period` options).
       - `primary_teacher_id` vs `actual_teacher_id` (supports substitute teacher assignments).
       - `planned_hours` & `actual_hours`.
       - `cancellation_reason` (visible if status == `cancelled_session`).
    6. **`hours_worked` (TextInput):** Numeric hours (defaults to batch daily hours for teachers or default shift hours for staff; set to `0` if status is `absent` or `cancelled_session`).
    7. **`notes` (Textarea):** Optional notes/justification.

---

### View 2: Employee Attendance History & Drill-Down (`/admin/staff-attendances/employee/{staff}`)
- **Header Info & Summary KPI Cards:**
  - Employee identity card (photo, name, job title, contract type: per hour / monthly / percentage).
  - KPI Stat 1: **Total Days Present** (عدد أيام الحضور).
  - KPI Stat 2: **Total Absent Days** (عدد أيام الغياب).
  - KPI Stat 3: **Total Hours Worked / Taught** (إجمالي الساعات) within selected date range.
  - KPI Stat 4: **Attendance Rate %** (نسبة الحضور).

- **Detailed Attendance Table:**
  - Lists all historical attendance entries for the selected employee.
  - **Date Range Search (من تاريخ - إلى تاريخ):**
    - `from_date` and `to_date` date pickers in table header. Filters records precisely.
  - Columns:
    - **Date:** formatted `d/m/Y` + day of week name (e.g., الاثنين 2026-08-31).
    - **Type/Batch:** Badge indicating general staff attendance or Course Batch name (if teacher session).
    - **Status:** Badge (`present` = green, `late` = warning, `excused` = info, `absent` = danger, `cancelled_session` = gray).
    - **Hours:** Recorded hours.
    - **Notes:** Truncated preview.
    - **Actions:**
      - **"View Details" Action:** Modal opening complete day breakdown (Created by user, creation timestamp, period, substitution teacher, cancellation reason).
      - **"Edit Action":** Visible **ONLY to `admin` role** for past dates. Non-admins cannot see or invoke edit/delete on past date records.

---

## 4. RBAC Security & Business Rules (Non-Negotiable)

### Rule 1: Past-Date Edit Lock (`admin` Only)
- Any attendance record with a date prior to today (`date < now()->toDateString()`) can **ONLY** be created, edited, or deleted by a user holding the `admin` role.
- For non-admin roles (`accountant`, `teacher`):
  - Can only register/edit attendance for **today's date** (`date == now()->toDateString()`).
  - Attempting to backdate or edit past dates throws a `ValidationException` with translated error message: `__('general.past_date_edit_restricted_to_admin')`.

### Rule 2: Closed Payroll Period Protection
- If a monthly payroll period (`StaffPayrollPeriod`) for the staff member and month (YYYY-MM) is in `approved`, `partially_paid`, or `paid` status, attendance records for that month are **locked**.
- Neither admins nor accountants can modify attendance rows in closed payroll months without formally reopening the payroll period.

### Rule 3: Dynamic Active Batch Restriction
- When selecting a course batch for teacher attendance, the query **MUST** enforce:
  1. `CourseBatch::where('status', 'in_progress')`
  2. `where(fn($q) => $q->where('teacher_id', $staffId)->orWhereHas('teacherAssignments', fn($q2) => $q2->where('staff_id', $staffId)->where('is_active', true)))`
- Inactive, completed, or unassigned batches are excluded from the dropdown.

### Rule 4: Atomic Financial & Audit Trail Sync
- Multi-table writes (`StaffAttendance`, `TeachingSession`, `AttendanceSession`) execute inside `DB::transaction()`.
- Every modification creates an `AuditLog` entry detailing `staff_id`, `date`, `previous_status`, `new_status`, and `modified_by`.

---

## 5. Phased Implementation Roadmap

### Phase 1 — Data Layer & Lock Engine Setup
- **Task 1.1:** Create `StaffAttendancePolicy` and `StaffAttendanceObserver`.
- **Task 1.2:** Implement past-date lock validation guard in service layer (`AttendanceManagementService`).
- **Task 1.3:** Create DB transaction sync logic linking `StaffAttendance` and `TeachingSession`.

### Phase 2 — Master All Staff Attendance Resource Rebuild
- **Task 2.1:** Update `StaffAttendanceResource` to list all `Staff` members in master overview table.
- **Task 2.2:** Build dynamic "Register Attendance" action with teacher-dependent batch filtering (`status = in_progress` & assigned teacher).
- **Task 2.3:** Integrate substitution teacher, period, and hours logic.

### Phase 3 — Employee Attendance History Page & Date-Range Filter
- **Task 3.1:** Create `EmployeeAttendanceHistory` custom Filament page (`/admin/staff-attendances/employee/{staff}`).
- **Task 3.2:** Implement `from_date` and `to_date` date range filters and KPI summary stats.
- **Task 3.3:** Build detail slide-over modal for single-day attendance inspection.

### Phase 4 — Navigation Cleanup & RBAC Hardening
- **Task 4.1:** Remove `TeachingSessionResource` from navigation sidebar (`shouldRegisterNavigation() => false`).
- **Task 4.2:** Enforce `admin`-only edit gates on past dates across all tables and forms.
- **Task 4.3:** Add bilingual AR/EN translation strings to `lang/ar/general.php` and `lang/en/general.php`.

### Phase 5 — Verification & Automated Testing
- **Task 5.1:** Write regression tests covering:
  - Non-admin blocked from editing past attendance dates.
  - Admin permitted to edit past attendance dates.
  - Teacher batch dropdown strictly limited to active studying batches assigned to teacher.
  - Date range filtering (`from_date` to `to_date`) accurately calculating total hours and days.
  - Payroll closed period lock.
- **Task 5.2:** Run `/usr/bin/php artisan strings:audit` to verify 0 findings.
- **Task 5.3:** Run `/usr/bin/php artisan test` to ensure 100% test suite pass rate.

---
