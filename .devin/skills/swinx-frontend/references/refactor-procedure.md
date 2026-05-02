# Refactor Procedure — Legacy to Modular

> Steps 1–11 for migrating a legacy feature from `app/Http/Controllers` + `app/Services`
> to `app/Modules/{Domain}` (Actions/Queries/Controller/routes) with modernized Vue frontend.
>
> **Form patterns, flash, and forbidden patterns** live in `SKILL.md` — not repeated here.

---

## Step 1: Identify the legacy code

Run these searches and report every file involved **before touching anything**:

```bash
grep -r "$ARGUMENTS" app/Http/Controllers/ --include="*.php" -l
grep -r "$ARGUMENTS" app/Services/ --include="*.php" -l
grep -r "$ARGUMENTS" routes/ --include="*.php"
find resources/js/pages -iname "*.vue" | xargs grep -li "$ARGUMENTS" 2>/dev/null
```

Write the full list down. Do not proceed until you have it.

---

## Step 2: Read the playbook

Read these in full. Follow them exactly:

- `docs/rules/refactor-playbook.md`
- `docs/rules/reference-implementations.md` — copy-ready code templates

---

## Step 3: Determine the target Module

| Module | Owns |
|--------|------|
| `app/Modules/Identity/` | auth, users, roles |
| `app/Modules/Academic/` | courses, curriculum, grades, enrollment |
| `app/Modules/Finance/` | invoices, payments, billing |
| `app/Modules/Notification/` | notifications, messaging |

If uncertain, read `docs/rules/architecture.md`.

Check existing structure:

```bash
ls app/Modules/{Domain}/
ls app/Modules/{Domain}/Actions/ 2>/dev/null
ls app/Modules/{Domain}/Queries/ 2>/dev/null
ls app/Modules/{Domain}/Http/    2>/dev/null
ls app/Modules/{Domain}/routes/  2>/dev/null
```

---

## Step 4: Extract Actions (write operations)

For each create / update / delete / state-change in the old code:

- Create `app/Modules/{Domain}/Actions/{Verb}{Entity}Action.php`
- Template: `public static function run(array $data): mixed`
- Return data or throw domain exceptions — **never** `response()->json()`
- Reference: `app/Modules/Identity/Actions/LoginAction.php`

---

## Step 5: Extract Queries (read operations)

For each list / get / aggregate in the old code:

- Create `app/Modules/{Domain}/Queries/{Get}{Entity}{Context}Query.php`
- Template: `public function handle(mixed ...$args): mixed`
- Return plain array or Eloquent collection — **never** HTTP response
- Reference: `app/Modules/Identity/Queries/GetStudentContextQuery.php`

---

## Step 6: Create thin Controller

**Web/Inertia** → `app/Modules/{Domain}/Http/Web/Admin/{Entity}Controller.php`
- Reference: `app/Modules/Identity/Http/Web/Admin/LoginController.php`
- `index()` → call Query → `Inertia::render()`
- `store/update/destroy()` → call Action → redirect

**API/JSON** → `app/Modules/{Domain}/Http/Api/{Actor}/{Entity}Controller.php`
- Reference: `app/Modules/Identity/Http/Api/Student/StudentContextController.php`
- Always `ApiResponse::success(...)` — **never** `response()->json()` directly

Always use FormRequest for validation. Never validate inline in controllers.

---

## Step 7: Register routes in Module

Add to `app/Modules/{Domain}/routes/web.php` or `api.php`.

**Do NOT add to `routes/web/*.php` or `routes/api/*.php`** — those are frozen zones.

If the module has no `routes/` yet:
1. Create `app/Modules/{Domain}/routes/web.php`
2. Register it in `app/Modules/{Domain}/Providers/{Domain}ServiceProvider.php` via `loadRoutesFrom`

---

## Step 8: Refactor Vue Frontend

### 8a — Rename page folder to PascalCase (if needed)

```
resources/js/pages/buildings/     →   resources/js/pages/Buildings/
resources/js/pages/room-bookings/ →   resources/js/pages/RoomBookings/
```

After renaming, update all `Inertia::render()` calls and any TS imports.

### 8b — Migrate list pages

Replace `useInertiaFilters` / `useServerTableQuery` / manual `router.visit` with `useDataTable`.

> Full useDataTable usage, template binding, and pitfalls → `references/swinx-patterns.md`.

### 8c — Fix form and modal patterns

> Form decision table and modal form pattern → `SKILL.md` and `references/swinx-patterns.md`.

### 8d — Fix route references

> Route helpers and systemRoutes → `references/swinx-patterns.md`.

### 8e — Type check

```bash
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

Fix all errors before proceeding.

---

## Step 9: Mark old code deprecated

In old Controller methods:
```php
/** @deprecated Migrated to App\Modules\{Domain}\Http\...\{Entity}Controller. Remove after YYYY-MM-DD. */
```

In old Service methods:
```php
/** @deprecated Logic moved to App\Modules\{Domain}\Actions\{Verb}{Entity}Action. Remove after YYYY-MM-DD. */
```

Do NOT delete yet — wait until new code is tested.

---

## Step 10: Verify

```bash
./scripts/dev.sh test --filter $ARGUMENTSTest
./scripts/dev.sh npm run type-check
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
```

Manual checks:
- [ ] Feature works as before
- [ ] No console errors
- [ ] No PHP errors in logs
- [ ] Filters, sorting, and pagination work on refactored list page

---

## Step 11: Report

Summarize:
- **Backend created:** Actions, Queries, Controller, routes files
- **Backend deprecated:** old Controller/Service methods marked
- **Frontend refactored:** folder renamed, composable migrated, form patterns fixed
- **Remaining manual steps:** delete old files, missing route entries, etc.
- **Violations found** (if any)

---

## Rules reference

| File | What it governs |
|------|----------------|
| `docs/rules/frozen-zones.md` | What you must NOT create/modify |
| `docs/rules/backend.md` | Controller/Action/Query rules |
| `docs/rules/architecture.md` | Module boundary rules |
| `docs/rules/naming.md` | Naming conventions |
| `docs/rules/frontend.md` | Vue component patterns |
| `docs/rules/frontend-gotchas.md` | PHP-to-JS pitfalls (sort, macOS case FS) |
| `docs/rules/filtering.md` | useDataTable + Laravel filter rules |
| `docs/rules/reference-implementations.md` | Copy-ready code for every pattern |
