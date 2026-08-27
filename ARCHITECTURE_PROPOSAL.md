# Architecture Audit & Redesign Proposal — Institute Management System

Date: 2026-08-16 · Status: **DRAFT — WAITING FOR APPROVAL**
Scope: academic workflow (program → curriculum → course → batch → enrollment → attendance/assessments/results/payments → academic history → progression → program completion → certificate).

This document audits the current implementation (files cited), proposes the corrected domain model, migration strategy and implementation phases, and lists risks. Nothing here has been implemented yet.

---

## 0. What already exists and is good (REUSE, do not rebuild)

| Capability | Where | Verdict |
|---|---|---|
| Course → Batch separation (remastered) | `course_batches`, `CourseBatchService` | ✅ Already correct per spec (course = template, batch = offering) |
| Batch lifecycle (open/complete) | `CourseBatchService::startNewBatch()/complete()/completeCourse()` | ✅ Reusable core |
| Enrollment with balance-via-transactions | `Registration` balance accessors, `StudentTransaction` (charge/payment/refund, voided) | ✅ Spec-compliant (#22) |
| Payments: sequential receipts, journaling, voids | `ReceiptNumberService`, `FinancePostingService`, `StudentTransaction::void()` | ✅ Reusable |
| Atomic registration + months + items/books + initial payment | `RegistrationService::register()` (+`registerForProgram`) | ✅ Reusable |
| Financial transfer = money movement | `Transfer` model, `TransferResource` | ✅ Keep (it is NOT an enrollment transfer) |
| Enrollment transfer (batch-carrying balance) | `RegistrationService::transfer()` with `newBatchId` | ✅ Reusable as the base for transfer history |
| Grades snapshot on registration (attempt history preserved) | `Registration::saveGrade()`, `grades` JSON | ⚠️ Keep as the *result* snapshot; assessments move to proper tables |
| Audit trail | `AuditLog` (action/entity/details) | ✅ Extend with old/new/required-reason, not rebuild |
| RBAC base | `HasRbac` trait + Spatie roles (admin/accountant/registrar/teacher) | ✅ Keep, add granular permissions on top |
| Months tracking | `RegistrationMonth` | ✅ Keep (billing), do NOT confuse with attendance |
| Periods (study shifts) | `Period` (days + times + labels) | ✅ Keep, becomes the schedule primitive for conflict checks |
| Report engine + prints + Excel | `ReportService`, `PrintController`, print blades | ✅ Reuse; add new report types |
| Bilingual + audit gates | `lang/en|ar`, `strings:audit`, `LocalizationTest` | ✅ Every new string goes through the same gate |
| Marks entry UI pattern | `BatchMarks` page | ⚠️ Refactor to per-assessment entry, same UX pattern |

---

## 1. Current architecture problems (found by inspection)

**P1 — No real Program.** `program_types` = `{name, months_count}` only (`2026_08_09_130000_create_program_types_table.php`). No code, duration/study system, status, curriculum, levels, credits, progression rules, graduation requirements. Courses are flat-scoped via `courses.program_type_id`. "Register into a diploma" = `registerForProgram()` registering the student into **all currently-active courses at once** — there is no sequencing: a student can be enrolled in Level-3 ASP.NET and Level-1 Computer Fundamentals on day one.

**P2 — Course is overloaded.** `courses` carries both *template* data (name, program, months, price) and *offering* data (capacity, teacher_id, enrollment_start/end, course_start/end_month, lifecycle_status, scopeEnrollable, seats_remaining). The batch remaster made `course_batches` the offering, but the course-level copies remain and **still gate registration** (`assertCourseEnrollable`, `Course::scopeEnrollable`, course select options in `RegistrationResource`). Result: capacity is counted TWICE (course-level `capacity > active registrations` across **all** batches + per-batch capacity), letting a course be "full" while the chosen batch has free seats, and vice-versa.

**P3 — Teacher assigned twice.** `courses.teacher_id` (default/fallback) AND `course_batches.teacher_id`. Spec #24 says the batch owns the teacher. The course copy is only a default — acceptable as a template default, but currently still used as a live fallback in `startNewBatch()` → keep only as a default for new batches, never as runtime data.

**P4 — Batch has no real status field.** `course_batches` = `{is_active, finished_at}` only; `lifecycle_status` is derived. There is no *Draft / Open / Full / Scheduled / In Progress / Completed / Cancelled* state machine (#42). Cancelling = soft-deleting (`DeleteAction` on `EditCourseBatch`) with **no handling of enrolled students, refunds or transfers** — exactly what #28 forbids.

**P5 — No attendance at all.** Zero tables/models for attendance sessions or records. `RegistrationMonth` is billing-only (good). Spec #23 requires session-level attendance + computed percentage + audited corrections.

**P6 — No assessments / exams / attempts / re-exams.** All grading is a flat JSON blob `registration.grades` (`total`, `grade`, `passed`, `graded_at`, component labels) via `saveGrade()`. There is no per-assessment entity, no max/weight/date/status, no attempt number, no re-exam approval, no make-up policy (#12, #13, #46). A re-exam today would overwrite the original total — forbidden by the most-important principle.

**P7 — Results: derived, never finalized, and semantically wrong at completion.**
- `GradeResult` = `not_graded|passed|failed` only — no PENDING/INCOMPLETE/ABSENT/WITHDRAWN (#14).
- `CourseBatchService::complete()` marks **every active/suspended registration as `status = 'completed'` regardless of marks** — a student with no marks at all "completes". Spec #14/#39: missing required assessments ⇒ INCOMPLETE/PENDING, never PASS. This is the single most dangerous inconsistency today.
- `Registration::STATUSES = ['active','suspended','closed','transferred']` — but `complete()` writes `'completed'`; the const is stale; `months_remaining` treats `closed|transferred` as ended but not `completed`.

**P8 — No prerequisites / eligibility layer.** Nothing checks "ASP.NET requires C# AND Database". `assertNoDuplicate()` in `RegistrationService` is registration-only. No structured eligibility response (#34), no explain-why UI (#63), no admin-override/approval path, no conditional enrollment.

**P9 — Duplicate protection gaps.** `assertNoDuplicate()` only scans `active|suspended`. (a) A student who **completed** batch X can re-enroll into batch X again; (b) two active registrations in the same course in *different* batches are allowed only via the batchless path check — inconsistent across paths; (c) repeat of a **passed** course is freely allowed (spec #32 wants policy, not silence).

**P10 — No schedule-conflict check (#37).** Periods carry days+times; batch↔periods pivot exists; but enrollment never checks overlap with the student's other active registrations.

**P11 — Capacity race (#52).** Stock/receipt paths use `lockForUpdate`; batch seats do not. Two admins can fill the last seat (`resolveBatch()->hasCapacityLeft()` is read-then-write with no lock).

**P12 — No certificate entity (#41).** "Certificates" = print-only views derived from `grades.passed` (`PrintController::certificate()/certificatesBatch()`). No certificate number, issue/completion date, issuing user, verification, voiding — and nothing ties a certificate to a *program* completion.

**P13 — No program completion / graduation evaluator (#40).** Nothing checks "all required curriculum courses passed ⇒ eligible for graduation ⇒ admin approval ⇒ certificate".

**P14 — No academic-history-first UI (#19/#20).** Student page has a registrations RelationManager and transactions; there is no all-attempts history view (repeat/withdraw/incomplete/transfers), no "current vs failed vs eligible next courses" grouping, no per-attempt result timeline (#49).

**P15 — RBAC is coarse (#25).** `HasRbac` grants access per *resource* (4 roles, static arrays) — e.g. marks entry is `admin|registrar|teacher` in `BatchMarks`, but *anything* a role can enter can be entered with no finalize/lock concept; no `result.finalize`, `result.reopen`, `grade.finalize` permissions; no per-action reason-required correction. Spatie permission tables exist but are unused (`DatabaseSeeder` only creates roles).

**P16 — Audit lacks structure (#26).** `AuditLog.log(action, entity, details)` — no previous/new value pair, no mandatory `reason` on mark/result/attendance correction, freeform action names. Marks today log only the new total (who changed it known; what it *was* is lost unless two entries are manually compared).

**P17 — No fee plan modeling (#22).** One `price_snapshot` per registration; no installments plan, discount or scholarship rows; refund exists (good). Payments infrastructure is solid — only the fee-structure layer is missing.

**P18 — Transfer record gap (#29).** The enrollment transfer closes the old row with `transferred_to_id` + audit entry, but there is no dedicated `enrollment_transfers` record (from/to/dates/reason/approved-by/new-batch). It cannot be reviewed, filtered or reported.

**P19 — No notifications (#53)** — nothing exists; localizable templates need building if in scope.

**P20 — `Transfer` naming collision.** `app/Models/Transfer.php` is a bank/wallet money transfer. Enrollment transfers must NOT reuse it (spec #29) — new entity needed.

**P21 — No admin-dashboard academic widgets (#43)** — dashboard has no "batches ending soon / students needing results / pending approvals / capacity warnings".

---

## 2. Proposed architecture (concept model)

The existing names are kept wherever they already fit:

```
Program (extends program_types)
 ├─ Curriculum: program_course (level_no, semester_no, order, required, hours, min_grade)
 ├─ Courses (existing courses) ── prerequisites ──> course_prerequisites (rule types, OR-groups, min_mark)
 │      └─ Batches (existing course_batches) + status state machine
 │            └─ Enrollments (existing registrations) + enrollment status + result columns
 │                  ├─ Attendance sessions/records (NEW)
 │                  ├─ Assessments (NEW) ── assessment_results (attempts, NEW)
 │                  ├─ Fees/Payments (existing StudentTransaction — unchanged)
 │                  └─ Re-exam approvals (NEW, attached to assessment_results)
 ├─ Student ⟷ Program via enrollments (no direct program on student)
 └─ Program completion evaluator (NEW service, no table until graduation issued)
        └─ Certificates (NEW table, verifiable, issued on program completion)
Transfer records: enrollment_transfers (NEW) — distinct from financial Transfer
```

**One-entity-one-concept rules applied:**
1. Course = reusable unit; Batch = offering of a course (already true).
2. Enrollment (`registrations`) = the ONLY link student↔batch; carries status, payment status (derived), academic result columns.
3. Attendance = session records (never a stored percentage).
4. Assessments = batch assessments; attempts = rows in `assessment_results` (original kept forever).
5. Final Result = computed & finalized state on the enrollment (never "batch ended ⇒ pass").
6. Certificate = an issued document record on the program level, never direct from one batch.
7. Program's student membership: derived from enrollment history (computed `student.current_program_id` read-model if ever needed; never a FK on students).

---

## 3. Entity relationship diagram (target)

```
programs (extend program_types)
  │1─┐
  │  └─< program_course >─┬─ courses (existing; keep program_type_id as legacy default)
  │                        │
  │                        ├─1┐
  │                        │  ├──< course_prerequisites (self-ref-ish: prereq_course_id)
  │                        │  └──1 course_batches (extend: status/code/room)
  │1                       │        │1
  │                        │        └──< course_batch_period (schedule; day+time)
  │                        │
  │                        │        students
  │                        │          │1
  │                        │          └──< registrations (ENROLLMENT)
  │                        │                │1
  │                        │                ├──< registration_months (billing)
  │                        │                ├──< registration_items (stock)
  │                        │                ├──< student_transactions (fees/payments)
  │                        │                ├──< attendance_records
  │                        │                │     └─> attendance_sessions (> batch_id)
  │                        │                ├──< assessment_results (attempt_no, mark, status)
  │                        │                │     └─> assessments (> batch_id)
  │                        │                │     └─ re_exam_approvals (NEW)
  │                        │                ├──< enrollment_transfers (NEW; from/to reg)
  │                        │                └── certificate_id (nullable, on graduation)
  │                        │
  │                        └── result columns on registrations (result, finalized_at, …)
  │
  └──< certificates (program_id, student_id, cert_no, verification_code…) — NEW
```

---

## 4. Database changes (all phases; nothing destructive in Phase A)

### Phase A — additive tables & columns (safe, zero data loss)

```sql
-- Batch status machine
ALTER course_batches
  ADD code VARCHAR(20) NULL,            -- batch code CSH-001
  ADD status VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|open|full|scheduled|in_progress|completed|cancelled
  ADD room VARCHAR(100) NULL,
  ADD fee_schedule JSON NULL;           -- optional batch-level fee plan

-- Enrollment/result separation (registrations)
ALTER registrations
  ADD result VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending|pass|fail|incomplete|absent|withdrawn
  ADD result_finalized_at TIMESTAMP NULL,
  ADD result_finalized_by BIGINT NULL REFERENCES users(id) NULL,
  ADD withdrawn_at TIMESTAMP NULL, ADD withdrawn_reason VARCHAR(255) NULL,
  ADD cancellation_reason VARCHAR(255) NULL,           -- distinct from close_reason (reuse? no — close_reason stays for legacy)
  ADD max_attempts TINYINT NULL, ADD attempts_used TINYINT DEFAULT 1;

-- Attendance
CREATE attendance_sessions (id, course_batch_id FK restrict, date DATE, period_id FK null, notes TEXT, created_by FK null, timestamps)
CREATE attendance_records (id, attendance_session_id FK restrict, registration_id FK restrict, status ENUM('present','absent','late','excused'), note TEXT, corrected_at NULL, corrected_by NULL)
  UNIQUE (attendance_session_id, registration_id)

-- Assessments (batch-scoped)
CREATE assessments (id, course_batch_id FK restrict, name, type VARCHAR(30), -- midterm|final|assignment|project|quiz|practical|makeup
  max_mark DECIMAL(8,2), weight DECIMAL(5,2), date DATE NULL, status VARCHAR(20) DEFAULT 'draft', -- draft|open|closed|finalized
  passing_requirement TINYINT NULL,  -- % of this assessment required to pass (optional)
  sort_order INT DEFAULT 0, notes TEXT, timestamps)

-- Attempts (the re-exam core)
CREATE assessment_results (id, assessment_id FK restrict, registration_id FK restrict,
  attempt_no INT NOT NULL DEFAULT 1, mark DECIMAL(8,2) NULL, status VARCHAR(20) DEFAULT 'not_recorded', -- recorded|absent|excused|invalid
  entered_by FK, grade_label VARCHAR(20) NULL, notes TEXT, created_at, updated_at)
  UNIQUE (assessment_id, registration_id, attempt_no)

-- Re-exam approvals
CREATE re_exam_approvals (id, assessment_id FK, registration_id FK, attempt_no INT, reason TEXT,
  approved_by FK users, decided_at TIMESTAMP, policy VARCHAR(20), -- replace|best|latest|cap
  cap_mark DECIMAL(8,2) NULL, timestamps)

-- Certificate register
CREATE certificates (id, certificate_no VARCHAR(30) UNIQUE, student_id FK restrict, program_id FK restrict,
  title_ar, title_en, issue_date DATE, completion_date DATE, status VARCHAR(20) DEFAULT 'issued', -- issued|voided
  voided_at NULL, void_reason NULL, verification_code VARCHAR(40) UNIQUE, issued_by FK users NULL,
  earned_courses JSON NULL, -- snapshot of (course_id, attempt best, batch, year, mark, result) so the cert stays valid forever
  timestamps)
CREATE INDEX (student_id, status) (program_id)

-- Prerequisites
CREATE course_prerequisites (id, course_id FK restrict, prerequisite_course_id FK restrict,
  rule_type VARCHAR(20) DEFAULT 'required',  -- required | alt_group | recommended
  group_no INT NULL,                          -- OR-group id: A OR B = same group_no
  min_mark DECIMAL(8,2) NULL,                 -- e.g. must pass with ≥ 70
  min_attendance_percent TINYINT NULL, timestamps)
  UNIQUE (course_id, prerequisite_course_id)

-- Curriculum pivot
CREATE program_course (id, program_id FK restrict, course_id FK restrict,
  level_no TINYINT, semester_no TINYINT NULL, sort_order INT, is_required BOOLEAN DEFAULT true,
  credit_hours DECIMAL(5,1) NULL, timestamps)
  UNIQUE (program_id, course_id)

-- Enrollment transfer register
CREATE enrollment_transfers (id, from_registration_id FK restrict, to_registration_id FK restrict,
  from_batch_id FK, to_batch_id FK, reason TEXT, approved_by FK users, transferred_at TIMESTAMP DEFAULT now(),
  balance_carried DECIMAL(16,2) DEFAULT 0, items_carried BOOLEAN DEFAULT false, timestamps)

-- Policy config (single source of truth for rules)
ALTER institute_settings ADD academic_policy JSON NULL;
-- { attempt_policy: 'highest'|'latest'|'replace'|'manual',
--   attendance_pass_min: 75, missing_assessment_result: 'incomplete', reexam_max_cap: null,
--   duplicate_window_months: null, allow_repeat_passed: false }
```

### Phase B — backfill (data-preserving)

1. Every registration with `grades.total != null` ⇒ create ONE assessment "Final Exam" (weight 100, max_mark = grades.full_mark ?? course.full_mark) per batch; one `assessment_results` row attempt 1 with the `grades.total` mark. Grades JSON stays as the *snapshot* of the original rule (never rely on it alone for new flows).
2. `registrations.status='completed'` ⇒ `result` = `pass` when `grades.passed==1`, `fail` when graded and not passed, else `incomplete` (they were completed without marks — honest backfill); set `result_finalized_at` = `closed_at`.
3. Batch `status` backfill: `finished_at != null` ⇒ `completed`; `is_active && enrollable` ⇒ `open`; `is_active && !enrollable` ⇒ `in_progress`; `!is_active && finished_at == null` ⇒ `cancelled` (legacy best-effort; admins can correct).
4. `course_prerequisites`: empty (new feature) — seeds come from admin data entry.

### Phase C — constraints (only after backfills verified)

- `CHECK (result IN (...))`, `CHECK (batch status IN (...))` (Laravel 11/12 check constraints).
- Unique enrollment: `UNIQUE (course_batch_id, student_id, status='active')` — partial index on `(course_batch_id, student_id)` WHERE status IN ('active','suspended','pending') via raw index (MySQL 8 supports functional/partial via generated col → use a `dup_guard_active` generated column or app-level check inside the seat-lock transaction; keep it in the service + unique composite `(course_batch_id, student_id, result IS NULL…)` not portable — decided: app-level inside transaction is the pragmatic choice, documented here).
- FK restrict on all new academic tables (never cascade-delete history).

### Phase D — UI (see workflows below)

---

## 5. State machines (explicit, enforced in services)

### Batch
```
draft ──▶ open(registration window) ──▶ scheduled(before start_month) ──▶ in_progress(start_month reached)
   │            │                          │                                  │
   │            ▼                          ▼                                  ▼
   └─────────▶ cancelled (students handled: transfer/refund/close, records kept)
                                        in_progress ──▶ completed (result processing done, finished_at set)
```
Transitions only via `CourseBatchService` (single entry point). `full` is a derived view of `open`, not a stored state. Cancelling REQUIRES a resolution choice per active enrollment (transfer batch / refund+close / keep-until-end) — never a silent delete.

### Enrollment (`registrations.status`) — corrected set
```
pending ──▶ active ──▶ completed
   │           │  └──▶ withdrawn (academic result = withdrawn; NOT fail)
   │           │  └──▶ cancelled (before start, refund by policy)
   │           └────────▶ transferred (closed + enrollment_transfer row)
```
`STATUSES` const fixed to `['pending','active','suspended','completed','withdrawn','cancelled','transferred']`. `suspended` kept as the existing "pause" (used by institute).

### Result (new `registrations.result`)
```
pending ──▶ pass | fail | incomplete | absent | withdrawn
   ▲               │
   └── reopened (admin only, reason required, audit) ─ recalculated back to pending
```
Finalization: batch reaches completion ⇒ `ResultService` verifies ALL required assessments recorded ⇒ computes weighted total ⇒ sets result + `result_finalized_at/by`. Changing a finalized result requires `result.reopen` permission + reason; the old state is preserved in `AuditLog` (old/new columns).

### Assessment status
```
draft ──▶ open (marks accepted) ──▶ closed (no more entry for normal users) ──▶ finalized (locked; reopen = admin+reason)
```

---

## 6. Permission matrix (Spatie permissions on top of existing roles)

New permissions (names follow spec #25); roles map:

| Permission | admin | academic?¹ | teacher | registrar | accountant |
|---|---|---|---|---|---|
| course.* (view/create/edit/delete) | ✅ | ✅ | view | view | – |
| batch.view / batch.edit / batch.cancel | ✅ | ✅ | view | view | view |
| enrollment.create / enrollment.cancel | ✅ | ✅ | – | ✅ | ✅ |
| enrollment.edit (dates, batch reassign) | ✅ | ✅ | – | – | – |
| attendance.create / attendance.edit / attendance.correct | ✅ | ✅ | ✅/✅/– | ✅/–/– | – |
| assessment.create / assessment.edit | ✅ | ✅ | – | – | – |
| grade.enter | ✅ | ✅ | ✅ (own batch) | ✅ | – |
| grade.finalize | ✅ | ✅ | – | – | – |
| result.finalize / result.reopen | ✅ / ✅ | ✅ / ✅ | – / – | – / – | – |
| payment.create / refund.create | ✅ | – | – | ✅ | ✅ |
| curriculum.manage / prerequisite.manage | ✅ | ✅ | – | – | – |
| certificate.issue / certificate.void | ✅ | ✅ | – | – | – |
| student.view / student.edit | ✅ | ✅ | view | view | view |
| admin.exception (override blockers) | ✅ | ✅ | – | – | – |

¹ `academic_manager` — optional new role; without it, admin covers the column. Teacher marks entry limited to batches where `course_batches.teacher_id = staff.user_id`.

`HasRbac` keeps working (resource access), and gains an optional `->can(static::permissionName($action))` layer on sensitive actions (marks, results, attendance corrections, certificate issue/void, batch cancel).

---

## 7. Complete enrollment workflow (single entry: `EligibilityService.check()` + `EnrollmentService.enroll()`)

```
1. Pick student → pick course → pick batch.
2. EligibilityService::check(student, batch, opts) returns:
   { eligible: bool, blockers: [reason...], warnings: [...], approvals: [...], info: [...] }
   checks (all in ONE place, no UI-side duplication):
     a. batch exists, not cancelled, registration window open        (BLOCKER)
     b. capacity with row-lock (lockForUpdate on batch inside txn)   (BLOCKER)
     c. duplicate: same batch active/pending; same course active in
        another batch within policy window                          (BLOCKER / WARNING)
     d. prerequisites per curriculum + attempt history:
        required ✓/✗ with min_mark + failed attempts list           (BLOCKER)
        recommended missing                                       (WARNING)
     e. schedule conflict vs other active registrations' periods    (BLOCKER, admin override)
     f. unpaid balance on another enrollment                       (WARNING, configurable)
     g. max attempts reached                                        (BLOCKER unless approval)
     h. program rules (holds, registration period)                  (per config: block/warn/approve)
3. Render result: exact missing prerequisites with "current result: FAIL" +
   "available action: Repeat C# / Request approval" (#63).
4. If blocked ⇒ stop (or create approval request if policy allows).
5. If ok ⇒ EnrollmentService::enroll() — ONE transaction:
   registration (status active) + months + fee charge + items/books + optional initial
   payment (receipt). Reuses the existing `RegistrationService::register()` internals.
6. Audit enrollment.created + notification.
```
**Important deviation from current code:** the *course-level* `assertCourseEnrollable`/`scopeEnrollable`/course-capacity gate is removed from the enroll path (batch is the unit); course-level only filters option lists. Course capacity field stays as the *default* for new batches.

## 8. Complete result workflow (`ResultService`)

```
1. Batch end_month reached / admin clicks "Process results".
2. ResultService::process(batch): for each enrollment (active|suspended):
   a. required assessments (status finalized, attempts recorded) missing?
        → result = incomplete (or pending, per policy), flagged "missing: Final Exam"
        → NO PASS.
   b. attendance % < policy min (if configured) → warning / fail per policy.
   c. weighted total = Σ (mark_best × weight) per policy (best/latest/replace).
   d. result = total ≥ pass_mark ? pass : fail; grade label from grading schema.
   e. audit per enrollment; DO NOT touch status yet.
3. Admin review screen (batch page Results tab): table with proposed results,
   per-student missing items; admin confirms/adjusts (adjust = grade.finalize+reason).
4. "Finalize batch results" ⇒ locks results, sets result_finalized_at/by,
   sets enrollments status = completed, sets batch.status = completed (CourseBatchService::complete reworked to call this first).
5. Post-finalize: ProgressionService::recommend(student) computes eligible next courses
   (curriculum order + satisfied prerequisites) — shown as recommendations ONLY (#16).
```

## 9. Failed-course workflow (no auto-next)

```
Result fail → ProgressionService::nextOptions(student):
  - Repeat same course: allowed if attempts_used < max_attempts (or approval).
  - Remedial course exists in curriculum? → offer.
  - Next course despite fail: only with admin.approval (conditional enrollment flag on
    the new enrollment, audited).
blocker message: "Cannot enroll in ASP.NET because C# Programming was not completed
(attempt 1: 45 — FAIL)." + actions.
```

## 10. Re-exam workflow (needs #13)

```
1. Teacher observes missing/absent/failed assessment → re_exam_approvals row
   (reason required, approved_by = admin/academic, policy chosen: replace|best|latest|cap).
2. Approved ⇒ create a NEW assessment of type 'makeup' (or new attempt row
   attempt_no+1 on same assessment — cleaner: new attempt row on the same assessment).
3. Teacher enters mark as attempt 2 → original attempt 1 untouched.
4. ResultService applies the configured policy (default: 'best') when computing totals.
5. Audit: who approved, why, policy, old/new effective mark.
```

## 11. Batch management workflow

```
Open new batch (existing CourseBatchService::startNewBatch — extend to set status='open').
Batch page tabs (new): Overview | Students | Attendance | Assessments | Marks | Results | Payments | History.
Edit dates/teacher/schedule/capacity: batch.edit permission; changes audited (old/new).
Cancel batch: batch.cancel permission; REQUIRED resolution per active enrollment:
  → transfer to another batch (enrollment_transfers row)
  → refund + cancel (payment.void/refund via existing machinery)
  → keep until next batch (status suspended)
  Batch keeps its row + history; students' history untouched.
```

## 12. Student academic history workflow (#19/#20/#49)

```
Student page new "Academic History" tab (read-model built from registrations +
assessment results + certificates — no new storage):
  - Current: active/suspended/pending enrollments
  - Past attempts grouped by course: each attempt = batch, year, attendance %,
    final mark, result, finalized date, transfer-out link
  - Repeated courses: all attempts listed chronologically (never overwritten)
  - Eligible next: ProgressionService recommendations
  - Certificates issued
  - Timeline tab: AuditLog entries for this student's academic entities rendered as a
    readable history (enrolled → midterm recorded → result ||: FAIL → re-exam approved …)
```

## 13. Payment workflow (mostly exists — minimal change)

Keep everything: charges/payments/refunds/voids/receipts/journaling. Add only:
- Batch fee plan (`course_batches.fee_schedule` JSON) snapshotted into `price_snapshot` at enroll (default = course price) — spec rule 1 respected (never read live price later).
- Discount/scholarship → a `charge` with negative? No: add `discount_type/discount_amount` snapshot columns on registrations (Phase A) so the finance flow stays intact and reporting can split discount vs fee.
- Cancelled enrollment ⇒ automatic refund handling via existing `refund` transaction (reason required).

## 14. Program completion workflow (#40, #41)

```
ProgressionService::graduationEligible(student, program):
  - every is_required course in program_course has ≥1 successful attempt (result=pass)
    (best attempt per course; alternatives resolved via OR-groups)
  - optional: credit hours met, attendance met, certificate_fee cleared (config)
  ⇒ status "eligible_for_graduation" (derived) → admin approves (audit, reason)
  ⇒ CertificateService::issue() → certificates row (cert_no + verification_code,
    earned_courses JSON snapshot) → notification → printable (existing print engine).
```

---

## 15. Edge cases (highest-value from the list of 40 — full table skipped for brevity, each one lands in the state machines/services above)

| # | Case | Allowed? | Who | What changes | History preserved | Financial | Academic |
|---|---|---|---|---|---|---|---|
| 1 | Registers, never attends | Yes | — | attendance 0%, result per policy (incomplete/fail) | all sessions | balance stays | result = policy |
| 2/3/4 | Withdraw after pay / before start / batch cancelled | Yes | admin | status withdrawn/cancelled + resolution | enrollment kept | refund via existing machinery | result=withdrawn (≠fail) |
| 5/6 | Teacher or dates change mid-batch | Yes | admin | batch.edit + audit old/new | audit | none | none |
| 7 | Transfer batch | Yes | admin/academic | enrollment_transfers + status transferred | original enrollment | balance carried (existing) | attempts moved with enrollment |
| 8/9 | Repeat failed course / multiple fails | Policy | admin w/ approval past max | new enrollment (new attempt) | all attempts visible | new fee or waiver | attempts_used++ |
| 10/11 | Missed final / approved make-up | Yes | admin approval | attempt 2 row | attempt 1 kept | none | policy applied |
| 13/14 | Correct mark after finalize / reopened result | Admin only | result.reopen + reason | old/new in audit; recalcs | yes | none | result recalculated |
| 15/16 | No prerequisite / admin override | Override = approval | admin.exception | conditional flag on enrollment | flagged in history | none | allowed with record |
| 17 | Unpaid fees | Warning by default | config | blocker if configured | — | — | academic never auto-changes by payment |
| 18 | Schedule conflict | Admin override | admin.exception | override flag | audit | none | allowed once |
| 19/20 | Full batch / concurrent admins | No / serialized | — | lockForUpdate seat reservation | audit on reservation | — | blocked beyond capacity |
| 21/22/39 | Archived course/program, changed prereqs mid-flight | Keep history | admin | future curriculum edits don't touch existing attempts; prereq checks use attempts, not live course rows | attempts immutable | — | grandfathered enrollments kept |
| 23 | Student changes program | Yes | admin | new program enrollments; history per program kept | yes | — | new curriculum applies to new enrollments |
| 28 | Result reopened after finalization | Admin | as #14 | see #14 | yes | none | recalc |
| 30/31 | Program completed but fee unpaid | Eligible but not issued | admin approves only after policy | certificate pending | student stays "eligible" | fee holds issuance if configured |
| 32 | Repeats a PASSED course | Policy (default: blocked with warning) | admin.exception | new enrollment flagged | both attempts | new fee | both attempts recorded |
| 33 | Optional course outside sequence | Yes | registrar/admin | normal enrollment | — | fees normal | fine |
| 35 | Two batches of same course | Blocked (P9 fix) unless transfer | admin | see P9 | — | — | — |
| 36 | Wrong student's marks | Teacher + admin correct | grade.enter/correct + reason | reassign attempt row, audit | original row voided-not-deleted | none | recalc result |
| 37 | Assessment deleted after marks exist | Never hard-delete | — | status='closed' only | rows kept | — | totals still computable |
| 40 | Refunded payment | Yes | accountant | void+refund (existing) | ledger keeps reversal | journal reversal | nothing |

---

## 16. Migration strategy (execution order)

1. **Phase 0 — freeze:** run full suite (140 passing today); note the batch-complete-without-marks behavior as a bug to fix inside the new flow (not by hot-fix now).
2. **Phase A — schema:** the additive migrations from §4-A. No data change, no test breakage expected (new nullable columns/tables only). `Registration::STATUSES` const fixed in the same release.
3. **Phase B — backfill** (§4-B) + a `BackfillAcademicHistoryTest` that asserts: graded→assessment rows; ungraded-completed→incomplete; batch statuses mapped; counts match before/after (no loss).
4. **Phase C — services:** `EligibilityService`, `ResultService`, `AssessmentService`, `AttendanceService`, `CertificateService`, `ProgressionService` + integration into `RegistrationService::register()` (eligibility call replaces `assertCourseEnrollable`+`assertNoDuplicate` internals), `CourseBatchService::complete()` (calls ResultService first).
5. **Phase D — UI:** batch page tabs, course page batch stats, student history tab, Program curriculum manager (on ProgramType resource), certificate register resource, result-processing screen, attendance session entry (batch page), per-assessment marks entry (replaces BatchMarks flat grid).
6. **Phase E — permissions/audit/constraints/polish** (§6, audit old/new columns, Phase-C constraints, dashboard widgets, notifications if approved).
7. Each phase ends with: `php -l`, `strings:audit` clean, full suite green, `LocalizationTest` green, log scan, PLAN.md signed.

---

## 17. Duplicated logic to remove

1. **Capacity/enrollability checks triplicated:** `Course::scopeEnrollable` + `CourseBatch::scopeEnrollable` + `RegistrationService::resolveBatch/assertCourseEnrollable` → single `EligibilityService` (batch-scoped) + seat lock.
2. **Register-for-program vs register:** `registerForProgram()` duplicates register internals → refactor to loop `enroll()`.
3. **Grades verdict logic triplicated:** `getGradeResultAttribute` vs `saveGrade` vs `passMark()` → `ResultService::evaluate(assessmentTotal, course, policy)` single source; JSON snapshot kept for history only.
4. **Teacher resolution duplicated:** `CourseResource` batch form vs `startNewBatch` vs `EditCourseBatch` → `CourseBatchService::defaultTeacher(course)`.
5. **Statuses lists hardcoded in resources/pages** (ViewRegistration visibility arrays, filters) → `Registration::STATUSES` + `Registration::RESULTS` constants everywhere.
6. **Periods label fallback duplicated** (`PeriodsLabelOrCourseAttribute` etc.) — fine, small; consolidate into `CourseBatch::scheduledPeriodsLabel()`.

## 18. New services/classes required

`EligibilityService` · `EnrollmentService` (thin wrapper; or extend `RegistrationService`) · `ResultService` · `AssessmentService` · `AttendanceService` · `CertificateService` · `ProgressionService` (+ optional `NotificationService` templates) · new models: `AttendanceSession`, `AttendanceRecord`, `Assessment`, `AssessmentResult`, `ReExamApproval`, `Certificate`, `CoursePrerequisite`, `ProgramCourse`, `EnrollmentTransfer`.

## 19. Existing code reused (no rebuild)

`RegistrationService` (register/transfer/close/payment machinery), `CourseBatchService` (open/complete — extended), `ReportService` + `PrintController` + print blades, `ReceiptNumberService`, `FinancePostingService`, `AccountService`, `AuditLog`, `HasRbac` + `User` roles, `MonthPicker`, `MoneyInput`, `PaymentDetails` form, `BatchMarks` UI pattern, `RegistrationsRelationManager`/`TransactionsRelationManager` patterns, `InstituteSetting`, localization pipeline + strings:audit, tests scaffolding (`RefreshDatabase`, existing factories? — check: tests build models inline; keep pattern).

## 20. Risks and inconsistencies

1. **Backfill ambiguity:** legacy `grades.total` may represent an aggregate that never matched the course `full_mark` — backfill maps totals as-is into a synthetic "Final Exam" (weight 100); result recomputation must NOT rerun against live course pass_mark for legacy rows (use the snapshot) — otherwise history changes when pass_mark is edited (violates price/rule-1 spirit).
2. **Course-level data becomes template-only:** any flow still reading `course.capacity`/`course.enrollment_*` as live gates after Phase C will double-count — grep-audit step in Phase C (list of call sites to update: RegistrationResource options, CourseResource lifecycle columns, ReportService queries).
3. **`status='completed'` semantics change:** queries using `in('active','suspended')` for "active seats" now exclude completed (they already do) — but including `completed` in STATUSES const may surface in filters; audit all status dropdowns.
4. **Batchless legacy registrations** (course_id without batch): completion logic must keep handling them (existing `completeCourse` covers; eligibility per batch must skip them or use course-level processing).
5. **Localization surface:** every new string (statuses, results, tabs, notifications, reports) must hit `strings:audit` + en/ar parity; RTL-friendly naming (program levels as «المستوى الأول»).
6. **Maintenance burden:** attendance rows grow (1 row per student per session) — batch-paged entry UI + indexes on `(session_id, registration_id)`; periodic pruning NOT allowed (history), rely on indexes.
7. **Two-transfer concept confusion:** keep `Transfer` (money) totally separate from `EnrollmentTransfer` in nav/labels (Transfer resource stays «حوالات»).
8. **Teacher identity:** teacher permission scoping needs `staff.user_id` linkage (Staff model) — check real linkage exists before wiring `grade.enter` scoping. (Currently roles grant access globally; per-batch scoping is a follow-up that must not block Phase A.)
9. **Concurrency:** seat locking adds a `lockForUpdate` on `course_batches` in every enroll — keep inside the same transaction, watch deadlock risk with receipt allocation (both acquire locks in transaction; order locks: batch → setting → student to stay consistent).

---

## Recommended execution order after approval

1. Phase A migration set (one migration) + STATUSES const fix + tests still green.
2. Assessments+attempts+quick ResultService (marks entry per assessment, BatchMarks reworked) — highest academic value.
3. Attendance (sessions + records + percentage + corrections).
4. EligibilityService + enrollment gating + duplicate fix + seat lock + schedule-conflict check.
5. Result finalization flow + fix "complete-batch marks everyone completed".
6. Batch status machine + cancellation handling.
7. Student academic history tab + timeline.
8. Prerequisites + curriculum manager + ProgressionService recommendations.
9. Certificates register + program completion.
10. Permissions/audit-old-new/dashboard widgets/notifications (last).