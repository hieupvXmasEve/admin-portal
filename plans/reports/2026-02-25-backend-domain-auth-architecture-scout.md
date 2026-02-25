# Backend Domain/Auth Architecture Scout

Date: 2026-02-25  
Scope: `app/Modules`, `app/Http/Middleware`, `app/Services`, `config`

## Major Boundaries

- Runtime is hybrid modular monolith: module providers + shared layers.
  - Module provider registration: `bootstrap/providers.php:4-10`
  - Middleware aliases and global web chain: `bootstrap/app.php:23-47`
- Domain modules:
  - Identity owns login/context endpoints + identity context singleton (`app/Modules/Identity/*`).
  - Academic currently web-first module routes (`app/Modules/Academic/Providers/AcademicServiceProvider.php:15-19`).
  - Finance loads both web+api module routes and module services (`app/Modules/Finance/Providers/FinanceServiceProvider.php:18-50`).
- Shared layer still heavy and cross-cutting:
  - `app/Services/*` is large and used from modules and legacy controllers.
  - Permission gates live in shared provider and read campus from session (`app/Providers/PermissionServiceProvider.php:34-48`).

## AuthN/AuthZ Flow

### Web admin/staff

- Login is session auth (`web` guard), restricted to staff + active/verified:
  - `app/Modules/Identity/Actions/LoginAction.php`
- Campus selection mandatory through middleware/session:
  - `app/Http/Middleware/CheckCampusSelected.php`
  - `app/Http/Middleware/SetCampus.php`
- Authorization mostly route `can:*` gates resolved by DB permissions + `current_campus_id` session:
  - `app/Providers/PermissionServiceProvider.php:34-48`
  - `config/permission.php`

### Student API (+ parent proxy)

- Protected path uses `auth:sanctum` + actor gate + either middleware:
  - `routes/api/v1/student.php:27-37`
  - `app/Modules/Identity/routes/api.php:23-31`
- Login/refresh/logout token lifecycle:
  - Student login issues Student-token (`abilities=['student']`): `app/Modules/Identity/Actions/StudentLoginAction.php:67-71`
  - Refresh replaces current token: `app/Modules/Identity/Http/Api/Student/StudentAuthController.php:45-52`
- Parent access model:
  - Parent token issued on `User` model (`abilities=['parent']`): `app/Modules/Identity/Actions/ParentLoginAction.php:67-69`
  - Parent can proxy into student APIs by supplying `student_id`; middleware swaps request user resolver to Student:
    `app/Http/Middleware/ParentStudentAccess.php:49-52,79-82`

### Lecturer API

- Protected path uses `auth:sanctum` + actor gate + lecturer auth middleware:
  - `routes/api/v1/lecturer.php:25-31`
  - `app/Modules/Identity/routes/api.php:68-73`
- Login issues token on `Lecture` model (`abilities=['lecturer:access']`):
  - `app/Modules/Identity/Actions/LecturerLoginAction.php:67-76`
- Authorization checks include status/employment restrictions and optional permission map in middleware:
  - `app/Http/Middleware/LecturerApiAuthorization.php:32-44,76-111`

### Actor gate layer

- Actor gate middleware maps route actor -> gate ability:
  - `app/Http/Middleware/ApiActorAuthorize.php:15-31`
- Gate logic in policy enforces model/type and account status:
  - `app/Policies/ApiActorPolicy.php:14-53`

## High-Risk Coupling / Drift

1. Finance API auth mode drift (high)
- Finance module API routes use `['web','auth']` not sanctum actor chain:
  - `app/Modules/Finance/routes/api.php:17-19,36-38`
- StudentFinanceController expects `user('student')`, can mismatch with `web/auth` session identity:
  - `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php:26-30,50-54,83-87`

2. Admin/internal API surface partly session-coupled + mixed controllers (high)
- Large `/api` groups protected only by `web` middleware (internal APIs), not explicit API actor/permission middleware:
  - `routes/api/admin.php:26-156,169-188`
- Same file mixes `auth:sanctum+admin` group for other endpoints:
  - `routes/api/admin.php:158-166`

3. Cross-module/service boundary erosion (medium-high)
- Academic module uses shared services directly:
  - `app/Modules/Academic/Http/Web/Admin/StudentController.php:19`
  - `app/Modules/Academic/Http/Web/Admin/StudentAcademicSummaryController.php:11-12`
- Legacy web controller imports module actions/services from multiple modules:
  - `app/Http/Controllers/Web/CourseOfferingController.php:21-24`
- Academic action directly invokes Finance module services:
  - `app/Modules/Academic/Actions/RecordStudentActionAction.php:12,341-367`

4. Middleware orchestration risk in `EitherMiddleware` (medium)
- Middleware executes other middleware with synthetic `$next` + direct `handle()` call; non-standard, brittle for middleware requiring parameters/side effects:
  - `app/Http/Middleware/EitherMiddleware.php:40-46,72-86`

5. Security logging risk (medium)
- Bearer token logged in admin middleware:
  - `app/Http/Middleware/AdminMiddleware.php:19-23`

6. Permission model tied to session campus (medium)
- Gate decision depends on `session('current_campus_id')`; weak fit for pure token APIs unless session context guaranteed:
  - `app/Providers/PermissionServiceProvider.php:40-46`

## Docs Needing Updates

1. `docs/api/student/PARENT_API_USAGE.md` (stale/incorrect)
- Mentions endpoints not present now (register, `/api/v1/parent/*`, children endpoint, parent-specific rate limiter):
  - `docs/api/student/PARENT_API_USAGE.md:11,52,65,144,187`
- Current code path nests parent auth under `/api/v1/student/parent/*`:
  - `app/Modules/Identity/routes/api.php:37-52`

2. `docs/modules/Identity/README.md` (stale implementation details)
- Claims provider initializes IdentityContext eagerly and shows nonexistent `isTopManager()` usage:
  - `docs/modules/Identity/README.md:25,37`
- Current provider states lazy init, no `isTopManager` in context:
  - `app/Modules/Identity/Providers/IdentityServiceProvider.php:19-24`
  - `app/Modules/Identity/IdentityContext.php`

3. `docs/system-architecture.md` (needs explicit mixed-auth API map)
- Security section is accurate but too generic for real mixed `web` vs `sanctum` API splits and Finance API exceptions:
  - `docs/system-architecture.md:97-101`
- Should add explicit table per API surface with middleware chain and actor model.

4. `docs/project-overview-pdr.md` (needs auth-surface caveats)
- States student/lecturer APIs use Sanctum patterns, but does not call out Finance module API routes and internal admin API `web` coupling:
  - `docs/project-overview-pdr.md:31-33,41-43,73-80`

## Unresolved Questions

- Should Finance module API routes migrate to same actor-based Sanctum chain used by v1 student/lecturer APIs?
- Which `/api` surfaces are intended public-client APIs vs internal web AJAX APIs, and should they be split by prefix/middleware?
- Should `EitherMiddleware` be replaced by explicit composed middleware groups to reduce hidden behavior risk?
- Should permission gates for APIs stop depending on session campus and use explicit token/request campus context?
