# Institute Management ERP — Codebase for ChatGPT Analysis

Welcome! This zip archive contains the full source code and technical specifications for the **Institute Management System** (Laravel 12 + Filament 3, double-entry accounting engine, YER currency, bilingual Arabic/English).

---

## 📁 Repository Structure & Key Entry Points

```
/
├── README_FOR_CHATGPT.md                         <-- This guide
├── AGENTS.md                                     <-- Architecture guidelines & non-negotiable rules
├── PLAN.md                                       <-- Active project feature roadmap & history
├── ARCHITECTURE_PROPOSAL.md                      <-- System architecture & DB design breakdown
├── INSTITUTE_ERP_SUPER_AUDIT.md                  <-- System audit checklist & findings
├── CASHBOX_SHIFT_ANALYSIS_AND_RECOMMENDATIONS.md <-- Multi-cashbox & shift engine specs
├── STAFF_PAYROLL_PARTIAL_CUTS_AND_ATTENDANCE.md  <-- Payroll & attendance deduction specs
├── SUPER_AUDIT_PROMPT.md                         <-- Prompt templates for deep code audits
└── institut/                                     <-- Main Laravel 12 Application
    ├── app/
    │   ├── Filament/                             <-- Admin Panel (Resources, Pages, Widgets)
    │   │   ├── Resources/                        <-- Student, Registration, Payment, Staff, Account...
    │   │   ├── Pages/                            <-- Finances, Reports, Trial Balance, Income Statement...
    │   │   └── Widgets/                          <-- Financial stats & table widgets
    │   ├── Models/                               <-- Eloquent Models (Student, Registration, JournalEntry...)
    │   ├── Services/                             <-- Core Domain Logic
    │   │   ├── FinancePostingService.php         <-- Double-entry journal posting engine
    │   │   └── ReceiptNumberService.php          <-- Atomic sequential receipt generator
    │   ├── Observers/                            <-- Financial reversal & audit observers
    │   └── Policies/                             <-- Spatie Role/Permission policies
    ├── config/                                   <-- Application configuration
    ├── database/
    │   ├── migrations/                           <-- Complete Database Schema
    │   └── seeders/                              <-- Chart of Accounts & Seed Data
    ├── lang/                                     <-- Arabic (ar) & English (en) translations
    ├── resources/                                <-- Blade Views, Custom CSS, JS
    ├── routes/                                   <-- Web & Console routes
    └── tests/                                    <-- PHPUnit / Pest Test Suite
```

---

## ⚖️ Key Financial Rules & Architectural Invariants

1. **Price Snapshot**: Course/diploma fee is copied to registration on creation. Never read live course price later.
2. **Double-Entry Ledger (`FinancePostingService.php`)**:
   - Every money event (payment, expense, purchase, salary payout, transfer, book issue, opening balance) posts balanced journal entries (`JournalEntry` + `JournalEntryLine`s).
   - Equal total Debits and Credits.
3. **No Hard Deletes**: Financial transactions and journal entries are NEVER hard deleted. Voiding generates a reversing entry with `voided_at` set.
4. **Atomic Sequential Receipts (`ReceiptNumberService.php`)**: Uses row-locking (`lockForUpdate`) inside DB transactions to guarantee sequential receipt numbers without gaps or collisions.
5. **Money Precision**: `decimal(10,2)` everywhere, YER currency only.
6. **Bilingual Requirement**: Every user-facing string must support both Arabic (`ar`, default RTL) and English (`en`).

---

## 🎯 Recommended ChatGPT Analysis Prompts

You can use the following prompts when conversing with ChatGPT after uploading this zip file:

### 1. General Best Practices & Code Quality Audit
> *"Please review this Laravel 12 + Filament 3 codebase against modern PHP/Laravel best practices. Analyze structure, clean code principles, DRY compliance, performance (N+1 queries), and Filament 3 usage pattern correctness."*

### 2. Double-Entry Accounting & Financial Integrity Audit
> *"Examine `FinancePostingService.php`, `ReceiptNumberService.php`, and the financial models/observers. Check if double-entry balancing, void reversal logic, atomic receipt numbering, and balance calculations follow accounting standards and prevent edge cases."*

### 3. Security & Authorization Audit
> *"Review the authentication, authorization policies, form validation, and Filament resource permissions across the application. Identify any potential security vulnerabilities, missing policy checks, or unvalidated inputs."*

### 4. Localization & Multi-Language Audit
> *"Verify localization implementation across Filament resources, pages, notifications, and Blade views. Check if all strings strictly use `__()` and have matching keys in `lang/ar` and `lang/en`."*

---

*Generated specifically for ChatGPT analysis.*
