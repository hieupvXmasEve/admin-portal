# CLAUDE.md

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
**UI**: reka-ui (shadcn-vue), @tanstack/vue-table
**Validation**: vee-validate + Zod
**State**: Pinia
**Auth**: Sanctum + Socialite, multiple guards (web, student, api)
**Permissions**: Spatie Laravel Permission (campus-scoped)

## 2. Commands You May Need

```bash
# Dev everything
composer dev

# PHP tests
composer test
php artisan test
./vendor/bin/pest

# TS type check
pnpm run type-check
pnpm run vue-tsc --noEmit

# Frontend build/lint/format
pnpm run dev
pnpm run build
pnpm run build:ssr
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
5. **Next Step Suggestion (Optional)**

   * One line if obvious.

## 4. Architectural Rules (Service–Request–Resource Pattern)

* **Service** (`app/Services/`): Business logic + transactions.
* **FormRequest** (`app/Http/Requests/`): Validation only.
* **API Resource** (`app/Http/Resources/`): JSON shaping.
* **Web Controller** (`app/Http/Controllers/Web/`): Inertia views.
* **API Controller** (`app/Http/Controllers/Api/`): JSON endpoints.

Controllers stay thin: call Service → return Resource/Redirect.

## 5. Multi-Campus Rules
* Campus context must exist (middleware `CheckCampusSelected`).
* All queries filter by current campus.
* Permissions/roles are campus-specific.

## 6. Frontend Standards (Vue 3 + TS)

* `<script setup lang="ts">` for all new components.
* Use `reka-ui` components, `DataTable.vue`, `DataPagination.vue`, `DebouncedInput.vue`.
* Filters are server-driven; use `applyFilters()` to push params via Inertia.
* **Never** use empty string in `<SelectItem value="">`; use meaningful placeholders (e.g. "none").
* Zod schema ≙ Laravel validation. Keep them in sync.
* Convert form data types before submit (string→number/null, etc.).
* Use `preserveState` & `preserveScroll` in Inertia visits.

**DataTable pattern (short):**

```ts
const filters = ref({ search: '', sort: '', direction: 'asc', per_page: 15 })
const applyFilters = (f: typeof filters.value) => {
  const p = new URLSearchParams()
  // set params if present ...
  router.visit(`/resource-path${p.toString() ? '?' + p : ''}`, {
    preserveState: true,
    preserveScroll: true,
    only: ['items', 'filters'],
  })
}
```

## 7. Testing & Quality
* Use Pest for PHP tests, factories for data.
* Unit tests for services, feature/integration for APIs.
* Transactional tests for DB isolation.
* All code must pass ESLint, Prettier, Pint, TS strict.

## 8. Common Mistakes To Avoid
* Using fields not in DB schema.
* Mixing controller concerns (validation/business logic).
* Returning partial diffs or unnamed files.
* Ignoring campus context or permissions.
* TS any/implicit types.

## 9. Output Format Rules For Claude
* **Edits**: Prefer diff blocks:
```dif a/app/Services/ExampleService.php
+++ b/app/Services/ExampleService.php
@@
- old line
+ new line
```

* **New files**: Show full path then code block.
* **Multiple files**: Separate clearly with headings.
* **No prose walls**: Keep explanations short.

## 10. When Unsure
* Ask a **single, precise** clarification question.
* Offer safest assumption and proceed if trivial.

## 11. Import/Export & Misc
* Uses `maatwebsite/excel` for bulk ops → validate client & server side.
* Soft deletes everywhere unless stated.
* Transactions for multi-step data ops.
* 
## 12. Model & Schema Compliance (MANDATORY)
You MUST validate that all:

- Field names exist in the actual database schema
- Eloquent relationships are correctly defined and used
- Foreign key references match real model connections
- Pivot tables (many-to-many) are handled with correct intermediate model or `belongsToMany()`

Before using any model field or relationship:

- Look at the actual Model class if present
- OR ask for schema/table definition if unsure

❌ DO NOT:
- Guess field names
- Invent relationships
- Assume `$model->relatedModel` exists without confirmation

✅ INSTEAD:
- Use `$model->relation_name` only if it's defined in the Model class
- Use `::with('relation')` only for valid eager-loadable relations

Always assume the schema is real, fixed, and authoritative.

## 13. Test-Driven Workflow (REQUIRED)

All new features MUST follow Test-Driven Development (TDD):

1. **Write a test first** that defines the expected behavior.
2. **Run test and confirm it fails** (red).
3. **Write only enough code** to make the test pass (green).
4. **Refactor if needed**, and ensure all tests still pass (green).
5. **Update tests** if existing code is changed.

Required test coverage includes:
- ✅ Unit tests for Service methods
- ✅ Feature tests for API endpoints
- ✅ Permission tests for protected routes
- ✅ Integration tests for data flow and DB integrity
- ✅ Factory usage for test data

Test Frameworks:
- Use [Pest PHP](https://pestphp.com) for all PHP tests
- Use model factories + `RefreshDatabase` trait
- Use `@test` methods or `it()` syntax
- Prefer `expect()` and `assertDatabaseHas()` for clarity

DO NOT:
- Skip test writing
- Write code without a failing test
- Leave broken or outdated tests behind

ALL PRs must include passing tests for new features and bugfixes.
