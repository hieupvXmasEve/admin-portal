---
paths: '**/*.{php,vue,ts}'
---

# Refactor Playbook — Migrating Legacy Code to Modules

Last updated: 2026-04-26
Status: Active — covers backend + frontend end-to-end

This playbook defines the exact steps to migrate a feature from the legacy architecture (`app/Http/Controllers/` + `app/Services/` + legacy Vue pages) to the new architecture (`app/Modules/{Domain}/` + modernized Vue pages).

Follow these steps in order. Do not skip steps.

---

## Pre-conditions

Before starting, confirm:

- [ ] The feature is fully understood (read the old Controller + Service)
- [ ] No other active ticket is modifying the same feature
- [ ] Tests exist for the current behavior (or you will write them first)
- [ ] You know which Module this feature belongs to (check `docs/rules/architecture.md`)

---

## Step 1: Identify

Find all files that belong to this feature:

```bash
# Find the old controller
grep -r "FeatureName" app/Http/Controllers/ --include="*.php" -l

# Find the old service(s)
grep -r "FeatureName" app/Services/ --include="*.php" -l

# Find the routes
grep -r "FeatureName\|featureName\|feature-name" routes/ --include="*.php"

# Find the Vue pages
find resources/js/pages -name "*.vue" | xargs grep -l "featureName" 2>/dev/null
```

**Output:** A list of files you will be touching. Write it down before proceeding.

---

## Step 2: Write a Characterization Test (if no tests exist)

If there are no existing tests, write a minimal Pest test that captures the current behavior before changing anything.

```php
// tests/Feature/FeatureNameTest.php
it('does X when given Y', function () {
    // test current behavior
});
```

Run: `./scripts/dev.sh test --filter FeatureNameTest`

Confirm it passes. This is your safety net.

---

## Step 3: Extract Actions

For each **write operation** (create, update, delete, state-change) in the old Service/Controller:

1. Create `app/Modules/{Domain}/Actions/{VerbEntityAction}.php`
2. Move the business logic into the `run()` method
3. Use constructor injection for dependencies (repositories, other services)
4. Do NOT return HTTP responses — return data or throw domain exceptions

**Action template:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\{Domain}\Actions;

class {Verb}{Entity}Action
{
    public static function run(array $data): mixed
    {
        // Business logic here
        // Throw domain exceptions on failure
        // Return result data (not HTTP response)
    }
}
```

**Reference implementation:** `app/Modules/Identity/Actions/LoginAction.php`

---

## Step 4: Extract Queries

For each **read operation** (list, get, aggregate, report) in the old Service/Controller:

1. Create `app/Modules/{Domain}/Queries/{GetEntityContextQuery}.php`
2. Move read logic into the `handle()` method
3. Use constructor injection for dependencies
4. Return plain data arrays or Eloquent collections — never HTTP responses

**Query template:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\{Domain}\Queries;

class {Get}{Entity}{Context}Query
{
    public function __construct(
        // inject dependencies
    ) {}

    public function handle(mixed ...$args): mixed
    {
        // Read-only logic here
        // May use Eloquent, DB facades, or other queries
        // Return plain data or collections
    }
}
```

**Reference implementation:** `app/Modules/Identity/Queries/GetStudentContextQuery.php`

---

## Step 5: Create the Thin Controller

Create the new controller in the correct Module path:

- Inertia/Web pages → `app/Modules/{Domain}/Http/Web/Admin/` or `Student/`
- JSON API → `app/Modules/{Domain}/Http/Api/Admin/` or `Student/` or `Lecturer/`

**Controller template (Web/Inertia):**

```php
<?php

namespace App\Modules\{Domain}\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\{Domain}\Actions\{Verb}{Entity}Action;
use App\Modules\{Domain}\Http\Requests\{Domain}\{Entity}Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class {Entity}Controller extends Controller
{
    public function index(): Response
    {
        return Inertia::render('{PagePath}/Index', [
            // data from Query
        ]);
    }

    public function store({Entity}Request $request): RedirectResponse
    {
        {Verb}{Entity}Action::run($request->validated());

        return redirect()->route('{route.name}')
            ->with('success', '{Entity} created successfully.');
    }
}
```

**Controller template (API/JSON):**

```php
<?php

declare(strict_types=1);

namespace App\Modules\{Domain}\Http\Api\{Actor};

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\{Domain}\Actions\{Verb}{Entity}Action;
use App\Modules\{Domain}\Http\Requests\{Domain}\{Entity}Request;
use Illuminate\Http\JsonResponse;

class {Entity}Controller extends Controller
{
    public function store({Entity}Request $request): JsonResponse
    {
        $result = {Verb}{Entity}Action::run($request->validated());

        return ApiResponse::success($result, message: '{Entity} created.');
    }
}
```

**Reference implementations:**
- Web/Inertia: `app/Modules/Identity/Http/Web/Admin/LoginController.php`
- API/JSON: `app/Modules/Identity/Http/Api/Student/StudentContextController.php`

---

## Step 6: Register Routes in Module

Add routes to `app/Modules/{Domain}/routes/web.php` or `api.php`:

```php
// web.php
Route::middleware(['auth', 'campus'])->group(function () {
    Route::resource('{entities}', {Entity}Controller::class);
});
```

Do NOT add routes to the legacy `routes/web/*.php` files.

If the module does not have a `routes/` folder yet:
1. Create `app/Modules/{Domain}/routes/web.php`
2. Register it in `app/Modules/{Domain}/Providers/{Domain}ServiceProvider.php`

---

## Step 7: Refactor Vue Frontend (full modernization)

This step handles all frontend changes: naming, composable, form pattern, and routing.

### 7a: Rename page directory (if needed)

If the existing page folder uses `kebab-case` or `lowercase`, rename it to `PascalCase`:

```
# Old (legacy naming)
resources/js/pages/rooms/Index.vue
resources/js/pages/curriculum-versions/Index.vue

# New (correct naming)
resources/js/pages/Rooms/Index.vue
resources/js/pages/CurriculumVersions/Index.vue
```

After renaming, update all imports referencing the old path (check `resources/js/app.ts`, route definitions, and any cross-page links).

**Do not rename** if the rename is out of scope for this refactor — leave a TODO comment instead.

---

### 7b: Migrate list/index pages to useDataTable

If the page is a list/index page with filters and pagination, migrate from `useInertiaFilters` (or `useServerTableQuery`) to `useDataTable`.

**Before (legacy — either composable):**
```typescript
import { useInertiaFilters } from '@/composables/useInertiaFilters';
// OR
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import DataTable from '@/components/DataTable.vue';
import DataPagination from '@/components/DataPagination.vue';
```

**After (current standard):**
```typescript
import { useDataTable } from '@/composables/useDataTable';

const { state, setFilter, apply, setPage, setPerPage, setSort,
        clearAllFilters, hasActiveFilters, isLoading,
        currentPage, totalPages } = useDataTable<Filters>({
    baseUrl: route('admin.entity.index'),
    initialFilters: { search: props.filters?.search ?? '', status: props.filters?.status ?? '' },
    defaultValues: { status: '', per_page: 15 },
    only: ['items', 'filters'],
    debounce: 300,
});
```

Reference: `docs/useDataTable-examples.md` and `docs/rules/filtering.md`

---

### 7c: Fix form patterns

**For Inertia page forms (full-page submission):**

```typescript
// CORRECT — must use Inertia's useForm
import { useForm } from '@inertiajs/vue3';
const form = useForm({ name: '', email: '' });
form.post(route('entity.store'));
```

**For modal/drawer forms (non-navigating JSON API):**

```typescript
// CORRECT — must use vee-validate + zod + useApi
import { useApi } from '@/composables';
import { toTypedSchema } from '@vee-validate/zod';
import { z } from 'zod';
const schema = toTypedSchema(z.object({ name: z.string().min(1) }));
const { post } = useApi();
```

If you find `useForm` from `vee-validate` on an Inertia page, or `useForm` from `@inertiajs/vue3` on a modal/drawer — fix it.

---

### 7d: Fix route references

Replace all literal URL strings with `route()` or `systemRoutes`:

```typescript
// WRONG
router.visit('/rooms');
axios.get('/api/rooms');

// CORRECT
router.visit(systemRoutes.rooms.index());
api.get(route('api.rooms.index'));
```

If `systemRoutes` doesn't have an entry for the new Module route yet, add it to `resources/js/utils/routes.ts`.

---

### 7e: Verify frontend types

```bash
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

Fix all TypeScript errors before proceeding.

---

## Step 8: Mark Old Code for Removal

Once the new Module code is working and tested:

**In the old Controller method**, add a deprecation comment:

```php
/** @deprecated Migrated to App\Modules\{Domain}\Http\...\{Entity}Controller. Remove after {DATE}. */
public function store(Request $request)
{
    // ...existing code stays until route is fully removed...
}
```

**In the old Service method**, add a deprecation comment:

```php
/** @deprecated Logic moved to App\Modules\{Domain}\Actions\{Verb}{Entity}Action. Remove after {DATE}. */
public function createEntity(array $data): mixed
{
```

Do NOT delete yet — wait for Step 9.

---

## Step 9: Verify

Run all checks before considering the migration done:

```bash
# Run relevant tests
./scripts/dev.sh test --filter {FeatureNameTest}

# Type check frontend
./scripts/dev.sh npm run type-check

# Lint PHP
./scripts/dev.sh artisan pint

# Lint frontend
./scripts/dev.sh npm run lint
```

Manually test the feature in browser:
- [ ] Feature works as before
- [ ] No console errors
- [ ] No PHP errors in logs

---

## Step 10: Delete Legacy Code

Only after Step 9 is fully clean:

1. Delete old methods from `app/Http/Controllers/{OldController}.php`
2. Remove old methods from `app/Services/{OldService}.php`
3. Remove old route entry from `routes/web/*.php`
4. If the old Controller/Service file is now empty, delete the entire file

---

## Common Mistakes to Avoid

**Backend:**

| Mistake | Why wrong | Correct |
|---|---|---|
| Business logic in the new Controller | Controllers are adapters only | Move to Action |
| Action returns `response()->json(...)` | Actions are not HTTP-aware | Return data, Controller calls `ApiResponse::success()` |
| New Service class in `app/Services/` | Frozen zone | Use Action in Module |
| Skipping FormRequest | Validation must be explicit | Always create a FormRequest |
| Deleting old code before new code is tested | Risk of breakage | Test first, delete last |
| Cross-module Eloquent join in new Action | Module isolation rule | Use a Contract |

**Frontend:**

| Mistake | Why wrong | Correct |
|---|---|---|
| `useInertiaFilters` on new list page | Legacy — auto-sync behavior | Use `useDataTable` |
| `useServerTableQuery` on new list page | Legacy — superseded by `useDataTable` | Use `useDataTable` |
| `DataTable` + `DataPagination` + inline inputs on new page | Legacy component set | Use `useDataTable` (see `docs/useDataTable-examples.md`) |
| `useForm` from `vee-validate` on Inertia page | Wrong form pattern | Use `useForm` from `@inertiajs/vue3` |
| `useForm` from `@inertiajs/vue3` in modal/drawer | Wrong form pattern | Use `vee-validate` + `zod` + `useApi` |
| Literal URL `/path` in `router.visit()` or `axios.get()` | Not type-safe, breaks on route changes | Use `route()` or `systemRoutes.*` |
| New page folder in `kebab-case` | Legacy naming | Use `PascalCase` for new folders |

---

## After Completion

Update the session record:
```bash
python3 ./.trellis/scripts/add_session.py --title "Migrated {Feature} to Module" --commit "$(git rev-parse --short HEAD)"
```
