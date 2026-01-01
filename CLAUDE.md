# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Goal**: Tell Claude Code exactly how to work in this repo—fast, accurate, minimal fluff.

## 0. TL;DR For Claude

* **Always**: 1) Confirm context → 2) List a short plan → 3) Output final code/patch.
* **Never invent** DB fields, routes, or props. Verify against schema & codebase.
* **Touch one concern per turn** (small, composable tasks).
* **Return diffs or full files with correct paths.**
* **Follow our architectures & patterns below without re-explaining them.**

## 1. Repo Facts (Do Not Re-Explain)

**Backend**: Laravel 12, PHP 8.4, FrankenPHP, MySQL 8, Redis
**Frontend**: Vue 3, TS, Inertia.js, TailwindCSS 4
**UI**: reka-ui (shadcn-vue), @tanstack/vue-table, lucide-vue-next
**Validation**: vee-validate + Zod (Frontend), FormRequest (Backend)
**State**: Pinia
**Auth**: Sanctum + Socialite, multiple guards (web, student, api)
**Permissions**: Spatie Laravel Permission (campus-scoped)
**Architecture**: Transitioning to **Modular Monolith** (`app/Modules`) from Service-layer (`app/Services`).

## 2. Commands You May Need

```bash
# Dev everything (starts server, queue, logs, vite)
composer dev

# PHP tests
composer test          # Run all
php artisan test       # Run pest
./vendor/bin/pest      # Run pest directly

# TS type check
pnpm run type-check
pnpm run vue-tsc --noEmit

# Frontend build/lint/format
pnpm run dev
pnpm run build
pnpm run lint
pnpm run format
pnpm run format:check

# PHP format
./vendor/bin/pint
```

## 3. Workflow You Must Follow

1. **Check Context**
    * Ask for missing files only if truly needed.
    * Reuse given paths/types instead of guessing.
2. **Plan First**
    * Bullet the steps you’ll take (max \~5 bullets).
3. **Implement**
    * Provide code with correct file paths.
    * For edits, output a **unified diff** or a clearly marked replacement block.
4. **Self-Review**
    * Validate field names, route names, relationships.
    * Ensure TS/PHPCS/ESLint rules will pass.

## 4. Architectural Rules

### New Code (Modular Monolith) - Preferred
* **Modules** (`app/Modules/{Domain}/`): Independent domains (e.g., Identity, Academic).
* **Actions** (`Actions/`): Business logic, single use-case (e.g., `CreateStudentAction`). Entry point: `run()`.
* **Queries** (`Queries/`): Complex reads/reporting. Entry point: `handle()`.
* **Controllers**: Adapters only. Validate request → Call Action/Query → Return Response.

### Legacy Code (Service Pattern)
* **Service** (`app/Services/`): Business logic + transactions.
* **FormRequest** (`app/Http/Requests/`): Validation.
* **Controllers**: Call Service → Return Resource/Redirect.

### General Backend
* **Inertia Pages**: Do not call APIs directly. Pass data via props.
* **Multi-Campus**: Queries must filter by current campus (via middleware/scope).
* **Validation**: Always use FormRequests.

## 5. Frontend Standards (Vue 3 + TS)

* `<script setup lang="ts">` for all new components.
* **UI Components**: Use `reka-ui` (shadcn) in `@/components/ui`.
* **Icons**: Use `lucide-vue-next`.
* **Forms**:
    * Use `vee-validate` + `zod` schema (sync with Backend validation).
    * Use `useForm` from `@inertiajs/vue3` for submission.
* **Tables**: Use `DataTable.vue` + `DataPagination.vue`.
* **Filters**: Server-driven. Update URL params via `router.visit()` with `preserveState`.
* **API**: If needed (rarely), use `useApi()` composable.

## 6. Naming Conventions

* **Modules**: PascalCase, Singular (e.g., `Identity`, `Finance`).
* **Actions**: Verb + Entity + Action (e.g., `UpdateStudentProfileAction`).
* **Controllers**: `Web/Admin/StudentController.php`, `Api/Student/AuthController.php`.
* **Vue Components**: PascalCase (e.g., `StudentList.vue`).
* **Routes**: `{module}.{resource}.{action}` (e.g., `identity.login.show`).

## 7. Model & Schema Compliance (MANDATORY)

You MUST validate that all:
- Field names exist in the actual database schema.
- Eloquent relationships are correctly defined and used.
- Foreign key references match real model connections.

❌ DO NOT:
- Guess field names.
- Invent relationships.
- Assume `$model->relatedModel` exists without confirmation.

✅ INSTEAD:
- Look at the actual Model class if present.
- OR ask for schema/table definition if unsure.
