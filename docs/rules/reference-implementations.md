# Reference Implementations

Last updated: 2026-04-26
Status: Active

This file points to **real, working code** in the codebase that correctly implements each pattern.
When in doubt, copy from here rather than guessing.

---

## Backend

### Action (write/state-change business logic)

**Simple static Action — `app/Modules/Identity/Actions/LoginAction.php`**

Pattern:
- `public static function run(...)` as entry point
- Takes a typed request (or array) as parameter
- Throws `ValidationException` / domain exceptions on failure
- Returns `void` or simple data — never an HTTP response

```php
class LoginAction
{
    public static function run(LoginRequest $request): void
    {
        // validate rate limiting
        // attempt auth
        // check business rules (isStaff, isActive)
        // throw ValidationException on failure
        // return void on success
    }
}
```

---

### Query (read-only, complex data retrieval)

**Injectable Query with constructor DI — `app/Modules/Identity/Queries/GetStudentContextQuery.php`**

Pattern:
- Constructor injection for dependencies
- `public function handle(...)` as entry point
- Returns plain `array` or Eloquent collection — never HTTP response
- May call other services, but stays read-only

```php
class GetStudentContextQuery
{
    public function __construct(
        protected SomeDependency $dep
    ) {}

    public function handle(Student $student): array
    {
        // aggregate data from multiple sources
        // return structured array
    }
}
```

---

### Controller — Web/Inertia (thin, delegating)

**`app/Modules/Identity/Http/Web/Admin/LoginController.php`**

Pattern:
- Extends `App\Http\Controllers\Controller`
- No business logic
- `store()` calls `Action::run()`, then redirects
- `index()` calls Query, passes data to `Inertia::render()`
- `destroy()` calls Action, then redirects

```php
class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        LoginAction::run($request);       // delegate
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
}
```

---

### Controller — API/JSON (thin, using ApiResponse)

**`app/Modules/Identity/Http/Api/Student/StudentContextController.php`**

Pattern:
- Extends `App\Http\Controllers\Controller`
- Injects Query via method injection
- Calls `$query->handle(...)`, passes result to `ApiResponse::success()`
- Always returns `JsonResponse`
- Never uses `response()->json()` directly

```php
class StudentContextController extends Controller
{
    public function index(Request $request, GetStudentContextQuery $query): JsonResponse
    {
        $context = $query->handle($request->user());
        return ApiResponse::success($context);
    }
}
```

---

### ApiResponse — available methods

File: `app/Http/Responses/ApiResponse.php`

```php
// Success responses
ApiResponse::success($data)                          // 200 with data
ApiResponse::success($data, meta: $meta)             // 200 with data + pagination meta
ApiResponse::success($data, message: 'Created.')     // 200 with message
ApiResponse::paginated($paginator)                   // 200 with paginated envelope

// Error responses
ApiResponse::error($message, $errors, $status)       // Generic error
ApiResponse::validationError($laravelErrors)         // 422 from validator errors
ApiResponse::authenticationError($message)           // 401
ApiResponse::authorizationError($message)            // 403
ApiResponse::notFound($message)                      // 404
ApiResponse::businessLogicError($message)            // 422 business rule violation
ApiResponse::serverError($message)                   // 500
ApiResponse::rateLimitError($message)                // 429
```

---

### FormRequest

**`app/Modules/Identity/Http/Requests/Identity/LoginRequest.php`**

Pattern:
- Lives in `app/Modules/{Domain}/Http/Requests/{Domain}/`
- Extends `Illuminate\Foundation\Http\FormRequest`
- `rules()` returns validation rules
- `authorize()` returns `true` (or uses Policy for complex auth)

---

### ServiceProvider (for new modules)

**`app/Modules/Identity/Providers/IdentityServiceProvider.php`**

Pattern:
- Registers module routes (`loadRoutesFrom`)
- Binds Contracts to implementations
- Use as template when creating a new Module

---

## Frontend

### Page folder naming

| Convention | Status | When |
|---|---|---|
| `PascalCase/` e.g. `Finance/`, `Academic/` | CORRECT — new code | All new page directories |
| `kebab-case/` e.g. `rooms/`, `curriculum-versions/` | LEGACY — do not add new | Existing only, do not rename unless in refactor scope |
| `lowercase/` e.g. `auth/`, `dashboard/` | LEGACY — do not add new | Existing only |

---

### Index/List page — CURRENT STANDARD (use for all new pages)

Composable: `useDataTable` — built-in validation, dependent filters, debounce, performance metrics.  
Full reference: `docs/useDataTable-examples.md` and `docs/rules/filtering.md`

```typescript
import { useDataTable } from '@/composables/useDataTable';

interface Filters {
    search: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const {
    state, setFilter, apply, setPage, setPerPage, setSort,
    clearAllFilters, hasActiveFilters, isLoading,
    currentSort, currentDirection,
} = useDataTable<Filters>({
    baseUrl: route('admin.entity.index'),
    initialFilters: {
        search:    props.filters?.search    ?? '',
        status:    props.filters?.status    ?? '',
        sort:      typeof props.filters?.sort === 'string' ? props.filters.sort : null,  // typeof guard required — see docs/rules/frontend-gotchas.md
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page:  props.filters?.per_page  ?? 15,
    },
    defaultValues: { status: '', per_page: 15 },
    only: ['items', 'filters'],
    debounce: 300,
});
```

See `docs/useDataTable-examples.md` for full template patterns (shadcn-vue table primitives).

---

### Index/List page — LEGACY PATTERN (existing pages only, do not use for new pages)

**`resources/js/pages/rooms/Index.vue`**

Composable: `useInertiaFilters` (auto-sync — navigates on every filter change with debounce)  
Components: `DataTable` + `DataPagination` + inline `Select`/`Input`

```typescript
// LEGACY — do not use for new pages
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter,
        handleSortChange, handlePaginationNavigate, handlePageSizeChange,
        currentSort, currentDirection } = useInertiaFilters<RoomFilters>({
    baseUrl: systemRoutes.rooms.index(),
    initialFilters: { search: '', status: 'all', ... }
});
```

**Why legacy:** Both `useInertiaFilters` and `useServerTableQuery` are frozen. New pages must use `useDataTable` (see `docs/useDataTable-examples.md`).

---

### Inertia page form (full-page navigation, no modal)

**`resources/js/pages/auth/Login.vue`** or any page with `useForm` from Inertia

Pattern:
- Use `import { useForm } from '@inertiajs/vue3'`
- Call `form.post(route(...))` or `form.put(...)` to submit
- NO vee-validate, NO zod on this type of form

```typescript
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'));
}
```

---

### Modal/Drawer form (non-navigating, JSON API)

**`resources/js/pages/curriculum-versions/summary/Units.vue`**

Pattern:
- Use `useApi` or `useApiRequest` composable (never `axios` directly)
- Use `vee-validate` + `zod` for validation
- `toTypedSchema(z.object({...}))` for form schema
- `<Form>` from vee-validate + `<FormField>`, `<FormItem>`, `<FormLabel>`, `<FormMessage>`
- Use `route()` helper for API URL

```typescript
import { useApi } from '@/composables';
import { toTypedSchema } from '@vee-validate/zod';
import { Form } from 'vee-validate';
import { z } from 'zod';

const formSchema = toTypedSchema(z.object({
    name: z.string().min(1, 'Required'),
    code: z.string().min(1, 'Required'),
}));

const { post, loading } = useApi();

async function handleSubmit(values: { name: string; code: string }) {
    await post(route('api.entity.store'), values);
}
```

---

### Route helpers

**`resources/js/utils/routes.ts`** and Ziggy's `route()` function

Decision tree:
- Admin web pages with typed routes → `systemRoutes.{domain}.{action}()`
- Any route by name (including API) → `route('route.name', { param: value })`
- Never hardcode `/path/strings` anywhere

```typescript
import { systemRoutes } from '@/utils/routes';
import { route } from 'ziggy-js';

// Inertia web navigation
router.visit(systemRoutes.rooms.index());

// API calls (in composables)
await api.get(route('api.v1.student.context'));

// In useDataTable
useDataTable({ baseUrl: route('admin.entity.index'), ... });
```

---

## Cross-Module Communication

### Contract (interface)

Currently sparse — only 2 files in `app/Shared/Contracts/`.
When creating a new Contract:

1. Define interface in `app/Shared/Contracts/{Domain}/{NameReader}.php`
2. Implement in `app/Modules/{Domain}/Services/{NameReader}Service.php`
3. Bind in `app/Modules/{Domain}/Providers/{Domain}ServiceProvider.php`
4. Inject via constructor in the consuming module

**Rule:** Never pass raw Eloquent models across module boundaries via Contracts.
Return plain arrays or typed DTOs.

---

## Anti-patterns (what NOT to copy)

### Do not copy these patterns, even though they exist in the codebase:

**Backend:**

| File | Bad pattern | Correct alternative |
|---|---|---|
| `app/Modules/Finance/Dng/Http/Controllers/DngPaymentController.php` | `response()->json([...])` directly | `ApiResponse::success(...)` |
| `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` | `response()->json([...])` directly | `ApiResponse::success(...)` |
| `app/Modules/Academic/Http/Web/Admin/StudentController.php` | `response()->json([...])` directly | `ApiResponse::success(...)` |
| Any `app/Services/*.php` | New business logic added here | Create Action in Module |
| Any `app/Http/Controllers/*.php` (non-base) | New controller methods added | Create controller in Module |

**Frontend:**

| Pattern | Bad (Legacy) | Correct (New) |
|---|---|---|
| List page composable | `useInertiaFilters` or `useServerTableQuery` | `useDataTable` (see `docs/useDataTable-examples.md`) |
| List page table component | `DataTable` + `DataPagination` + inline inputs | shadcn-vue table primitives via `useDataTable` |
| Inertia page form | `useForm` from `vee-validate` | `useForm` from `@inertiajs/vue3` |
| Modal/drawer form | `useForm` from `@inertiajs/vue3` | `vee-validate` + `zod` + `useApi` |
| HTTP calls | `axios.get('/literal/path')` | `useApi` + `route('route.name')` |
| Page folder naming | `kebab-case/` or `lowercase/` for new dirs | `PascalCase/` for all new page directories |
