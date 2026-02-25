# API Route Security Scout (2026-02-25)

## Scope
- `routes/api.php`
- `routes/api/v1/student.php`
- `routes/api/v1/lecturer.php`
- `app/Modules/*/routes/api.php` (`Identity`, `Finance` found)

## Protected vs Unprotected

### 1) `routes/api.php`
- Protected:
  - `Broadcast::routes(['middleware' => ['auth:sanctum', 'student.api.auth']])`
- Unprotected:
  - `GET /api/system-config`
  - `PUT /api/system-config`
  - `POST /api/system-config/upload`
  - `GET /api/system-config/{key}`
  - `GET /api/health`
- Note: `uploads`, `admin`, `modules` are required from other files; this scout did not re-audit those subfiles.

### 2) `routes/api/v1/student.php`
- Protected only.
- Group middleware is consistent at top-level:
  - `auth:sanctum`
  - `api.logging`
  - `api.actor:student_or_parent`
  - `either:parent.student.access,student.api.auth`

### 3) `routes/api/v1/lecturer.php`
- Protected only.
- Group middleware is consistent at top-level:
  - `auth:sanctum`
  - `api.actor:lecturer`
  - `lecturer.api.auth`
  - `api.logging`

### 4) `app/Modules/Identity/routes/api.php`
- Unprotected guest auth endpoints:
  - `POST /api/v1/student/auth/login`
  - `POST /api/v1/student/auth/login/google`
  - `POST /api/v1/student/parent/auth/login`
  - `POST /api/v1/student/parent/auth/login/google`
  - `POST /api/v1/lecturer/auth/login`
  - `POST /api/v1/lecturer/auth/login/google`
  - `GET /api/v1/lecturer/auth/check-science`
- Protected endpoints:
  - Student: `/auth/logout`, `/auth/refresh`, `/context`
  - Parent: `/parent/auth/logout`, `/parent/auth/refresh`, `/parent/context`
  - Lecturer: `/auth/refresh`, `/auth/logout`, `/auth/me`
- Mounting is coherent:
  - `IdentityServiceProvider` wraps with `Route::prefix('api')->middleware('api')`.

### 5) `app/Modules/Finance/routes/api.php`
- Declared as protected by `['web', 'auth']` (session guard), not API token middleware.
- Endpoints:
  - Student finance: 4 GET endpoints
  - Finance operations: 4 POST endpoints
- Mounting behavior:
  - `FinanceServiceProvider` uses `loadRoutesFrom()` directly (no API prefix wrapper).
  - File hardcodes `Route::prefix('api/v1/...')`, so paths resolve, but middleware stack is web-session-centric, not API-centric.

## Middleware Consistency Check
- Strong consistency in student/lecturer v1 route files: actor + sanctum + logging pattern.
- Inconsistency at module level:
  - Identity API uses `api` middleware stack and sanctum/actor checks.
  - Finance API uses `web` + `auth` only.
- Inconsistency at top-level API:
  - `system-config*` endpoints are public while they appear administrative/sensitive.

## Key Auth Gaps
1. `system-config` mutation endpoints are unauthenticated (`PUT /api/system-config`, `POST /api/system-config/upload`). High risk config tampering.
2. `GET /api/system-config` and `GET /api/system-config/{key}` are unauthenticated. Potential sensitive config exposure.
3. Finance operations (`/api/v1/finance/operations/*`) rely on generic `auth` session middleware with no explicit role/permission middleware visible in route layer.
4. Finance API uses `web` middleware on API paths; this diverges from sanctum/API actor pattern and may create inconsistent auth behavior for SPA/mobile/API clients.
5. `GET /api/v1/lecturer/auth/check-science` is public; verify it does not expose internal validation/enumeration signal.

## Docs To Update
1. `docs/project-overview-pdr.md`
- Add explicit auth model per API surface (sanctum+actor vs web+auth), and note current exceptions.
2. `docs/system-architecture.md`
- Add module-route mounting differences (`IdentityServiceProvider` prefix+api middleware vs `FinanceServiceProvider` raw load).
3. `docs/api/lecturer.md`
- Document public status and intent/security constraints for `/api/v1/lecturer/auth/check-science`.
4. `docs/api/*` (new file suggested: `docs/api/finance.md`)
- Document Finance endpoints, required auth guard, and consumer type (web-session vs API token).
5. Add/refresh central API auth matrix doc (suggested: `docs/api/auth-middleware-matrix.md`)
- One table: route group -> middleware -> guard -> actor constraint -> intended client.

## Unresolved Questions
- Should `system-config*` be admin-only and moved under authenticated admin API middleware?
- Are Finance API consumers browser-session only by design, or should they align with sanctum token APIs?
- Is `/api/v1/lecturer/auth/check-science` intended for public preflight, and what data does it reveal on failure/success?
- Should broadcast auth support lecturer channels too, or is student-only intentional?
