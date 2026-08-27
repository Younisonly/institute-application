# AGENTS.md — Institute Management System (Yemen / YER)

The ONLY always-loaded file. Work happens in `institut/` — run all artisan/composer commands there.

## 1. Project

Laravel 12 + Filament 3 panel (`app`, `/admin`), bilingual AR/EN (default `ar`, RTL), YER only.
Features: students, dynamic courses (short course/diploma), registrations (price snapshot + month tracking), payments + sequential receipts, staff (salary types + advances), books/supplies stock, expenses + profit reports, student ID cards, double-entry finance (journal, banks/wallets, books catalog, supplier debts, other-people ledgers, transfers, opening balances, account-ledger/trial-balance/income-statement/balance-sheet reports).
NOT in scope: librarian, transport, multi-currency, timetables.

## 2. Environment

- Use `/usr/bin/php` for ALL artisan/composer commands (LAMPP `php` 8.2 fails the platform check).
- Dev server: `/usr/bin/php artisan serve --port=8001`. `APP_URL` = `http://127.0.0.1:8001` (must match browser host or FileUpload previews break).
- Tests use MySQL `institute_test` (wiped by RefreshDatabase — never point tests at `institute`).
- Login: `admin@institute.local` / `admin123`. Roles: admin, accountant, registrar, teacher.
- Don't upgrade laravel/framework past `^12.0`.

## 3. Financial rules (non-negotiable)

1. **Price snapshot** — copy price into the child record at creation; never read the live price later.
2. **Balance via transactions only** — charges − payments (voided excluded); never mutate a balance column.
3. **No hard delete of financial data** — void + reason + audit log instead.
4. **Sequential receipts** — allocate atomically (`lockForUpdate` inside a transaction), never reused.
5. **Money = `decimal(10,2)` + `cast: 'decimal:2'`**, integer math, no floats. YER only.
6. **Suspend/close/transfer keeps history**; transfer carries balance to a same-type class.
7. **Month tracking** — registrations track start → expected end; months open/close individually; never silently extend.
8. **Every money event journals itself** — payments, expenses, supplier purchases, other-people in/out, transfers, book issues post balanced double-entry entries via `FinancePostingService`; charges/billing rows are cash-basis (NOT journaled).
9. **Void = reversing entry** — the reversal journal entry is created with `voided_at` set (audit trail kept, excluded from balances); never delete an entry or line.
10. **`JournalEntryLine::entry()`** uses explicit FK `journal_entry_id` (Laravel would infer `entry_id`); reversal/void observers must use `isDirty('voided_at')` — Laravel 12 fires `updating` before `syncChanges()`, so `wasChanged()` is always false.
11. **Account `name` is an accessor** (`name_ar`/`name_en`) — never `pluck('name')` on `accounts`; use `mapWithKeys` + `->name`.
12. **Native Filament tables everywhere** — custom pages/reports use `InteractsWithTable` + `{{ $this->table }}` (report pages) or `TableWidget`s via `getFooterWidgets()` (Finances page). Never plain HTML `<table>` in page views. Table actions: per-record closures use `$record`, NOT `->arguments(closure)` (this version rejects closures there). Summarizers live in `Filament\Tables\Columns\Summarizers`.

## 4. Engineering rules

- `$fillable` on all models (never `$guarded = []`); schema changes via migrations only; `foreignId` FKs; `utf8mb4_unicode_ci`.
- Soft deletes where history matters (students, staff, items) — NEVER on financial records (rule 3).
- Business logic in models/services/observers — never in Blade views or table columns.
- Every user-facing string via `__()` with keys in BOTH `lang/en` and `lang/ar` in the same change. Dates `d/m/Y`. RTL + Arabic labels for anything user-facing.
- **Localization is mandatory for EVERY string a user can see** — labels, placeholders, hints, help text, notifications, snackbars/toasts, modals, titles, empty states, error messages, exception messages that surface via `$e->getMessage()`, even `throw new \RuntimeException(...)` inside commands/services. Audit gate (non-negotiable): `/usr/bin/php artisan strings:audit` MUST return 0 findings after any change that touches user-facing text, and new lang keys must be added to BOTH `lang/en` and `lang/ar` (a `LocalizationTest` enforces: audit exits 0, en/ar key parity for `general`/`money_words`/`validation`/`auth`/`passwords`/`pagination`, every Filament `::make('field')`/`->name('field')` name exists in `validation.php` `attributes`, published Filament locale files non-empty).
- Multi-step writes in `DB::transaction()`. Eager-load (no N+1), paginate, index every filtered/ordered column.
- Security: `->authorize()` on sensitive resources (Spatie roles); never log/expose secrets; validate in form schema/Form Request, never inline.
- No comments unless asked; no dead code/TODOs; copy patterns from neighboring files; reuse before building new.
- **Filament gotchas:** custom page forms use `getFormActions()` (no `Form::actions()`); closures typed `Model $record` CAN receive `null` — type `?Model` + null-safe body.
- **User-reported errors/requests are fixed EVERYWHERE the same pattern can occur** — grep the whole app for the same construct (e.g., `$set('../...')` inside a repeater row) and fix every occurrence, then add a regression test that reproduces the exact interaction.

## 5. Workflow

- **RULE-001 — Every new feature is full-stack, researched, and polished.** When asked for a feature/improvement:
  1. Web-search the Yemeni context first (how Yemeni institutes handle it) — apply what fits.
  2. Design the best flow it can be: full happy path + edge cases (partial payments, voids, transfers, empty states), checked against section 3.
  3. Build backend AND frontend together: migration + model + relations + service + Filament resource/page/widget + bilingual strings + print view if needed. Never ship DB-only or backend-only unless explicitly told.
  4. Apply every rule in section 4 + the matching skill(s) from section 6.
  5. Polish: confirmations on destructive/money actions, empty states, defaults, clear errors. Then verify per task size below.
- **Tiny edit** (1 file): `php -l`, done.
- **Small feature** (no money): edit + `php -l` changed files + scan `storage/logs/laravel.log` for NEW errors.
- **Large feature or ANY money flow**: load the skill(s) in section 6, inspect 1–2 existing files doing the same thing and copy the pattern, write code, add a regression test ONLY if it adds real value, verify: `php -l` + log scan + `artisan route:list` if routes changed. Functional testing is done by the owner in the browser — never block on it.
- **Sign the plan after EVERY progress**: as soon as an item is finished, mark it `[x]` in `PLAN.md` and append the date to the box (e.g. `- [x] ... ✅ done 2026-08-12`) — never batch it at session end. If the plan's expected approach changed, note the deviation in the box itself.
- **PLAN.md status markers** (set them the moment the state changes, same rule as signing):
  - `- [ ] item` — queued, not started yet.
  - `- [ ] item — 🔍 research needed <date>` — needs web-research before design; set BEFORE researching, flip when research done.
  - `- [ ] item — 🔄 in progress <date>` — set when you start building; append date of every substantial progress note inside the box.
  - `- [x] item ... ✅ done <date>` — completed. Never claim done without the verification numbers (test/suite counts, audits) inside the box.
  - `- [ ] item — ⛔ blocked <date>: <reason>` — stuck on an external dependency or unanswerable question; keep it out of "in progress".
  - Every status flip is a one-line edit with today's date — same session, not batched.

## 6. Skills (load on demand — only 2)

| Skill | Load when working on |
|---|---|
| `financial_integrity` | ANY money flow — registrations, payments, receipts, voids, transfers, stock, salaries, expenses, reports |
| `laravel_filament` | Models, migrations, Filament resources/pages/widgets, validation, queries, performance |

## 7. Commands

```bash
/usr/bin/php artisan serve --port=8001   # dev server
/usr/bin/php artisan test                # full suite (institute_test)
/usr/bin/php artisan test --filter=<name>
/usr/bin/php artisan migrate
/usr/bin/php artisan db:seed --force
/usr/bin/php -l <file>                   # syntax check
```

## 8. Key files

- `institut/app/Providers/Filament/AdminPanelProvider.php` — panel config
- `institut/app/Filament/Pages/InstituteSettings.php` — settings (receipt counter, currency label)
- `institut/app/Services/ReceiptNumberService.php` — atomic sequential receipts
- `PLAN.md` — living feature plan (update at session end)
