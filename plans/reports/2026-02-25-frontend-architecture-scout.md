# Scout Report — 2026-02-25 — Frontend Architecture + Integration Hotspots

## Scope
- `resources/js/pages`
- `resources/js/components`
- `resources/js/composables`
- `resources/js/types`
- `resources/js/app.ts`
- `resources/js/ssr.ts`
- `vite.config.ts`
- Backend contract touchpoints (`routes/*`, `app/Http/Middleware/HandleInertiaRequests.php`)

## Largest Complexity Areas
1. Page-domain hotspots by LOC:
- `Admin`: ~13,540 LOC / 41 files
- `students`: ~7,383 LOC / 26 files
- `Forms`: ~6,173 LOC / 18 files
- `Finance`: ~4,328 LOC / 13 files
- `syllabus`: ~3,707 LOC / 8 files
- `curriculum-versions`: ~3,697 LOC / 8 files

2. Largest individual files:
- `resources/js/pages/student-applications/index.vue` (~1,817 LOC)
- `resources/js/types/models.ts` (~1,599 LOC)
- `resources/js/pages/course-offerings/Show.vue` (~994 LOC)
- `resources/js/pages/Events/ManualParticipants.vue` (~972 LOC)
- `resources/js/pages/Admin/Canvas/Courses/Index.vue` (~936 LOC)

3. Composable hotspots:
- `useImageUpload.ts` (509 LOC)
- `useEmailMonitoring.ts` (471 LOC)
- `useAdminSchedule.ts` (448 LOC)
- `useStudentApplicationImport.ts` (441 LOC)
- `useScheduleManagement.ts` and `useEventReports.ts` (386 LOC each)

4. Entry-point architecture pressure:
- `app.ts` resolves pages via eager `import.meta.glob('./pages/**/*.vue', { eager: true })`; this loads all pages into the client bundle path (larger startup surface).
- `ssr.ts` resolves via `resolvePageComponent(...)` and depends on `page.props.ziggy` shape.

## Duplication Risks
1. Filter orchestration duplication:
- `useFilters.ts` and `useInertiaFilters.ts` implement overlapping URL sync/pagination/filter responsibilities.

2. Permission abstraction duplication:
- `usePermission.ts` vs `usePermissions.ts` with overlapping `can(...)` capability and slightly different contracts.

3. API client pattern fragmentation:
- Mixed `useApi()` wrapper + raw `fetch()` + raw `XMLHttpRequest` in upload flows.
- Multiple response-shape assumptions coexist (`ApiResponse<T>` envelope vs direct JSON body parsing).

4. Route composition fragmentation:
- Mixed `route('...')` helper and hardcoded paths.
- Current counts: `route('...')` refs ~372, `router.visit('/...')` literal refs ~51, hardcoded `'/api/...'` refs ~53.

5. Type contract duplication:
- Very large shared model file (`types/models.ts`) coexists with page-local interfaces in large pages, increasing drift risk.

## Contract Boundaries With Backend
1. Inertia shared props boundary:
- `HandleInertiaRequests::share()` defines app-wide contract:
  - `auth.user`, `auth.permissions`, `auth.current_campus_id`, `auth.current_campus`
  - `ziggy` object (`location`, `route` included)
  - `flash.*`, `sidebarOpen`, `name`, `quote`

2. Inertia page-name boundary:
- Backend `Inertia::render('X/Y')` must match `resources/js/pages/X/Y.vue` exactly.
- High concentration in domains: `Admin` (29 renders), `students` (15), `Forms` (13), `Finance` (12).

3. Named-route boundary (Ziggy):
- Frontend relies heavily on route-name contracts (`route('...')` and centralized `utils/routes.ts`).
- Route-name changes in PHP can silently break TS call sites without unified compile-time coupling.

4. API envelope/auth boundary:
- `useApiRequest` enforces CSRF + `credentials: include` and expects Laravel-style SPA/session behavior.
- Several composables bypass that wrapper and parse raw fetch responses manually.

5. Realtime boundary:
- `setupEcho()` env-driven (`VITE_BROADCASTER`, key/host vars).
- Notifications channel contract: `notifications.{id}` (see `routes/channels.php`).

## Integration Hotspots
1. Student applications flows:
- Large page logic + bulk actions + import process with custom fetch/error parsing.

2. Course offerings show page:
- Heavy orchestration of sessions, registrations, modals, and direct API calls in one view model.

3. Event manual participants:
- Mixes table state, filtering, batch operations, and both `useApi` + raw `fetch` paths.

4. Canvas admin integration:
- Multiple endpoints, mapping dialogs, sync workflows, and mixed route styles (`/admin/...` literals + named routes elsewhere).

## Unresolved Questions
1. Should `useFilters` be deprecated in favor of `useInertiaFilters` (or vice versa) to enforce one filtering contract?
2. Which permission composable is canonical (`usePermission` or `usePermissions`)?
3. Should all network IO be normalized behind one client (`useApi`) and one response envelope?
4. Is eager page loading in `app.ts` intentional for this app scale, or should page resolution become lazy/code-split?
5. Is `resources/js/types/models.ts` manually maintained or generated from backend resources/contracts?
6. Should hardcoded path usage (`/api/...`, `/admin/...`) be phased into named routes to reduce coupling drift?
