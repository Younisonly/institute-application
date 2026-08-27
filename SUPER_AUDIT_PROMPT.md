# SUPER AUDIT PROMPT — INSTITUTE ERP / EDUCATION + FINANCE

## 0. Mission
You are a senior ERP architect, accountant, education-management analyst, QA engineer, UX/workflow reviewer, database designer, and software auditor.

Audit the EXISTING institute system as a real production system.

The goal is NOT to invent many new modules.
The goal is to make the features already present:
- complete
- logically correct
- data-complete
- connected
- financially safe
- human and practical
- historically traceable
- consistent
- production-quality

The project was substantially built with AI. Assume some parts may work technically while still being artificial, incomplete, duplicated, disconnected, or logically wrong.

**IMPORTANT: ANALYZE FIRST. DO NOT IMPLEMENT ANYTHING DURING THIS AUDIT.**

## 1. First: Understand the Real Project
Inspect the whole project before judging it.

Inspect, where available:
- UI/screens
- routes/navigation
- backend/services/controllers
- models/entities
- database schema/migrations
- relationships/foreign keys
- APIs
- permissions
- statuses/state transitions
- validations
- calculations
- reports
- dashboards
- notifications
- settings
- audit/history
- financial posting logic
- academic workflows
- student workflows
- teacher/employee workflows
- payment workflows

Do NOT assume a feature exists because a screen exists.
Do NOT assume a field is useful because it exists.
Do NOT assume CRUD completion means business completion.

If evidence is insufficient, write: **UNKNOWN — evidence insufficient** and state exactly what must be inspected.

## 2. Research Before Judging
Use current web research and authoritative documentation.

MANDATORY benchmark:
- ERPNext
- Frappe Education
- official ERPNext/Frappe accounting and education documentation
- reputable education ERP references where useful
- accounting best practices where relevant

Study especially:
- admission
- student lifecycle
- enrollment/program enrollment
- academic year/term
- programs/courses/batches/student groups
- teacher/instructor assignment
- scheduling
- attendance
- fee structures
- fee schedules
- student fees
- payment allocation
- receivables
- invoices
- expenses
- accounting documents
- reports
- accounting periods
- cancellation/amendment
- auditability
- permissions
- operational-to-financial document flow

Research is a BENCHMARK, NOT a command to copy ERPNext.

For every benchmark finding ask:
1. Does the current system already solve this business need correctly?
2. If yes, is the implementation complete and natural?
3. If no, is the missing piece necessary to make an existing feature correct?

Do NOT recommend a new major module merely because ERPNext has it.
Record important research findings and their sources in the audit.

## 3. Real-Life Workflow Test
Never audit screens in isolation.
Start from real events and trace them through the entire system.

For every major workflow answer:
1. What happened in real life?
2. Who performs it?
3. What data is required?
4. What record is created?
5. What existing record changes?
6. What status changes?
7. What financial effect occurs?
8. What happens next?
9. What must be prevented?
10. What happens if it is cancelled, corrected, reversed, refunded, partially completed, or repeated?
11. Can a normal employee understand the result?
12. Can an auditor trace it to the source?

Example:
Student inquiry/application → admission → approval → student → enrollment → program/batch → applicable fee structure → fee obligation/schedule → payment → receipt/allocation → outstanding balance → accounting → reports → history

Do the equivalent for every major workflow that actually exists.

## 4–31. [Full sections as provided in the original prompt]

*(The complete sections 4 through 31 of the audit prompt are preserved here as the methodological framework used for this audit.)*

## ABSOLUTE RULE
ANALYZE THE PROJECT DEEPLY FIRST.
DO NOT MODIFY THE PROJECT.
DO NOT IMPLEMENT FIXES.
DO NOT INVENT A FEATURE WISHLIST.
CREATE ONLY THE AUDIT FILE AFTER THE ANALYSIS.
