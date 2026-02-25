# Frontend + Integration Scout Report (2026-02-25)

## Scope scanned
- `resources/js/app.ts`, `resources/js/ssr.ts`
- `resources/js/pages/**`
- `resources/js/composables/**`
- `resources/js/types/**`
- Route helper layers in `resources/js/utils/routes.ts` + `resources/js/constants/*-routes.ts`

## Hotspots
- Route helper center: `resources/js/utils/routes.ts` (194 `route(...)` calls). Main abstraction seam for web+API route names.
- Largest frontend pages (change-risk/high churn):
  - `resources/js/pages/student-applications/index.vue` (1817 LOC)
  - `resources/js/pages/course-offerings/Show.vue` (994 LOC)
  - `resources/js/pages/Events/ManualParticipants.vue` (972 LOC)
  - `resources/js/pages/curriculum-versions/Index.vue` (927 LOC)
- Largest shared contract/type file: `resources/js/types/models.ts` (1599 LOC). Wide blast radius when model shapes change.
- Composable hotspots for integration:
  - `useAdminSchedule.ts` (API-heavy)
  - `useStudentApplicationImport.ts` (multi-step import contract)
  - `useInertiaFilters.ts` (list/filter navigation contract reused across pages)
- Route/API usage balance in scanned FE code:
  - `route(...)` calls: 501
  - hardcoded router URL calls: 64
  - hardcoded `fetch('/...')` calls: 18
  - Insight: route helpers are dominant, but literal-path pockets remain and are drift-prone.

## Coupling with backend contracts
- Entry bootstrapping contract:
  - `app.ts` wires `ZiggyVue`; `ssr.ts` reconstructs route fn from `page.props.ziggy`.
  - Coupled to backend shared Inertia props carrying `ziggy` object.
- Route-name coupling:
  - TS constants declare parity with PHP constants (`app/Constants/*Routes.php`), then composed by `utils/routes.ts`.
  - Any rename in Laravel route names/constants can break FE compile/runtime navigation unless mirrored in constants/helpers.
- Finance operations coupling (tight, typed-ish in FE pages):
  - FE expects `api.finance.operations.*` endpoints: preview, run, fix-exception, send-reminders.
  - Confirmed route group exists at `app/Modules/Finance/routes/api.php` with `api/v1/finance/operations` prefix and names `api.finance.operations.*`.
- Event manual participants coupling:
  - `Events/ManualParticipants.vue` depends on `api.admin.events.manual-participants.*` contracts and returns with specific participant/status payload shapes.
  - Also coupled to web route `events.manage-participants` for tab/filter pagination reload semantics.
- Student action import coupling:
  - FE import page enforces `preview_token` and optional `shared_upload_record_id` in execute flow.
  - Backend validates anti-tamper pairing and excludes `ADMISSION_DEFERRAL` for import path.
  - FE types still include `ADMISSION_DEFERRAL` globally (`types/student-action.ts`) for non-import flows; import page documents exclusion explicitly.
- Room bookings coupling:
  - Mixed pattern: some pages use helper (`systemRoutes.roomBookings.index()`), many actions still use literal paths (`/room-bookings-*`, `/room-bookings/{id}/approve`).
  - Backend route names and paths currently align (`routes/web/room-bookings.php`), but literal path usage increases refactor risk.
- API response envelope coupling:
  - `useApiRequest.ts` assumes `{ success, message, data }` envelope for `useApi()` methods.
  - Pages bypassing `useApi` via raw `fetch` often manually parse/branch; behavior divergence risk on error payload changes.

## Docs update implications
- If route names/paths/middleware change:
  - Update `docs/system-architecture.md` (route organization, web/api boundaries)
  - Update `docs/project-overview-pdr.md` (exposed surfaces and auth assumptions)
  - Update `docs/codebase-summary.md` (entry points and route map notes)
- If FE integration standard changes (e.g., move raw `fetch` to `useApi` or remove literal paths):
  - Update `docs/RULES_vue-form-useApi.md` with enforced conventions and exceptions.
  - Update `docs/design-guidelines.md` for route helper usage norms.
- If student action import contract changes:
  - Update domain docs + contract references to keep `preview_token`, optional `shared_upload_record_id`, and unsupported action-type behavior explicit.
- If finance operations endpoints change:
  - Update docs for `/api/v1/finance/operations/*` endpoint contract and expected response envelope.

## High-priority drift risks
- Literal URLs in FE pages bypassing helpers (`room-bookings`, `events reports`, `users import`, some admin pages).
- Large page files + local inline interfaces (contract duplication) vs centralized `types/*`.
- Mixed transport style (`useApi` + raw `fetch`) can desync error handling and auth header conventions.

## Unresolved questions
- Which API surfaces are intended as stable external contracts vs internal admin-only UI contracts?
- Should room-booking and events pages be normalized to helper-based routes only (no literal path strings)?
- Is there a target policy to migrate all authenticated JSON calls to `useApi()` for consistent envelope/error behavior?
