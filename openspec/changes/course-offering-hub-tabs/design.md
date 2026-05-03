## Context

Course Offering Show page (`Show.vue`, 994 LOC) is a monolithic scroll rendering course info, class sessions, and student registrations in one long page. Related data (assessment scores, attendance grids, survey results) lives on entirely separate pages under different sidebar sections. The Index page (`Index.vue`, 880 LOC) mixes navigation and mutation actions in a single 8-item dropdown per row.

**Target user**: Academic Coordinator managing full course lifecycle.

**Existing patterns**: The codebase already uses shadcn-vue `Tabs` with URL `?tab=` parameter syncing (see `clubs/Show.vue` `getCurrentTabFromURL()` + `updateTabInURL()` + `handleTabChange()` pattern). Inertia partial reloads via `router.reload({ only: [...] })` are used in 146+ places.

**Current data flow for Show page**:
- `CourseOfferingController@show` returns: `courseOffering` (with `class_sessions`, `course_registrations`, `academic_records`), `availableRooms`, `siblingOfferings`
- Assessment scores: `CourseStatisticsController@assessmentScores` — separate route/controller
- Survey results: `SurveyManagementController` — separate route/controller

## Goals / Non-Goals

**Goals:**
- Coordinator can access all course data (sessions, students, scores, survey) from a single Show page via tabs
- Lifecycle stage is immediately visible (Setup → Registration → Teaching → Grading → Completed)
- Pending action items (missing attendance, ungraded sessions, uncreated survey) surface as warnings
- Index page actions are simpler: navigation via row click/icon, mutations in dropdown
- Tab data loads lazily — only active tab fetches its data
- Existing URLs (`/course-statistics/{id}/assessment-scores`, `/class-sessions/{id}`) continue working

**Non-Goals:**
- Replacing cross-class analytics pages (Failed Students, GPA, Academic Report) — those remain separate
- Building a new API layer — reuse existing controller/query logic
- Mobile-first redesign — desktop coordinator workflow is the priority
- Changing backend business logic (grading, registration, completion flows)

## Decisions

### D1: Tab-based layout over stepper/workflow

**Chosen**: Tabs with 5 panels (Overview, Sessions, Students, Scores, Survey)

**Alternative**: Workflow stepper (linear stage-based UI)

**Rationale**: Coordinators need random access — they check attendance, jump to scores, then review survey. Stepper forces linear navigation which conflicts with their non-linear workflows. A lifecycle progress bar at the top provides stage awareness without restricting access.

### D2: URL-synced tabs with Inertia v3 deferred props

**Chosen**: `?tab=overview|sessions|students|scores|survey` query param, matching `clubs/Show.vue` pattern (`getCurrentTabFromURL()` + `updateTabInURL()` + `popstate` listener).

Data loading strategy per tab:

| Tab | Loading | Mechanism |
|-----|---------|-----------|
| Overview | Eager (initial page load) | Standard Inertia prop |
| Sessions | Eager (already nested in `courseOffering.classSessions`) | Standard Inertia prop |
| Students | Eager (already nested in `courseOffering.courseRegistrations`) | Standard Inertia prop |
| Scores | **Deferred** — loaded after initial render | `Inertia::defer(fn () => ..., 'scores')` + `<Deferred data="scoresData">` |
| Survey | **Deferred** — loaded after initial render | `Inertia::defer(fn () => ..., 'survey')` + `<Deferred data="surveyData">` |

Backend uses `Inertia::defer()` with **separate named groups** (`'scores'`, `'survey'`), resulting in 2 parallel requests after initial render. This is preferred over default grouping (1 sequential request) because scores and survey are independent heavy data sets — parallel loading gives faster perceived load for whichever tab the user clicks first. Frontend uses `<Deferred>` component with `#fallback` and `#default` slots for loading/reloading states. This replaces the manual `router.reload({ only: [...] })` approach.

**Alternative 1**: Manual `router.reload({ only: [...] })` on tab click.
**Alternative 2**: Client-side fetch via `useApi` composable.
**Alternative 3**: `Inertia::optional()` + manual `router.reload({ only: ['scoresData'] })` on first tab click — loads data only when user actually clicks the tab, saving bandwidth when user never visits Scores/Survey tabs. Requires manual trigger logic and `ref` tracking per tab.

**Rationale**: `Inertia::defer()` is the idiomatic Inertia v3 pattern for lazy-loaded props — it automatically triggers a partial reload after the initial render, handles caching, and provides the `<Deferred>` component for declarative loading states. The codebase has **zero** usages of `Inertia::defer()` today, making this change a pioneering adoption of the v3 feature. Manual `router.reload` requires imperative trigger logic and manual loading state management; `Inertia::defer()` is declarative and built-in. `Inertia::optional()` (Alternative 3) was considered but rejected: it adds imperative trigger logic per tab, and the common coordinator workflow visits 2-3 tabs per session — the bandwidth savings are marginal vs the added complexity.

### D3: Backend — `Inertia::defer()` in existing controller

**Chosen**: Modify `CourseOfferingController@show` to use `Inertia::defer()` for scores and survey data. The controller currently eager-loads everything (line 362-383: `$courseOffering->load([...])` with `classSessions`, `courseRegistrations`, `academicRecords`, plus room conflict queries and sibling offerings). The refactored controller:

```php
return Inertia::render('course-offerings/Show', [
    // Eager — needed by Overview, Sessions, Students tabs
    'courseOffering' => $courseOffering,  // with classSessions, courseRegistrations, academicRecords

    // Once — rarely change during page session, skip on partial reloads
    'availableRooms' => Inertia::once(fn () => $availableRooms),
    'siblingOfferings' => Inertia::once(fn () => $siblings),

    // Deferred — loaded after initial render, in separate named groups (parallel)
    'scoresData' => Inertia::defer(
        fn () => GetCourseOfferingScoresQuery::handle($courseOffering),
        'scores'
    ),
    'surveyData' => Inertia::defer(
        fn () => GetCourseOfferingSurveyQuery::handle($courseOffering),
        'survey'
    ),
]);
```

Query logic extracted into shared Query classes under `app/Modules/Academic/Queries/`. Even though the controller lives in the legacy zone, Query classes are **new files** and follow the module architecture per `CLAUDE.md`. The controller already imports from `App\Modules\Academic\Actions\` (e.g., `MoveStudentToSectionAction`) — same cross-boundary pattern.

**Alternative**: Create new dedicated API endpoints.

**Rationale**: `Inertia::defer()` is the v3 replacement for manual partial reload orchestration. The callback only executes when Inertia requests the deferred group, so there's zero overhead on initial page load. `Inertia::once()` wraps `availableRooms` and `siblingOfferings` because they rarely change during a session — this avoids re-evaluation on partial reloads (deferred group fetches). Shared Query classes follow the `Queries/` pattern from `CLAUDE.md` and keep `CourseStatisticsController` working via the same query.

### D4: Component extraction — one file per tab

**Chosen**: Split Show.vue into:
```
pages/course-offerings/
├── Show.vue                         (~150 LOC shell: header + lifecycle + tabs)
├── components/
│   ├── CourseLifecycleHeader.vue     (progress bar + warnings)
│   ├── tabs/
│   │   ├── OverviewTab.vue          (info cards, enrollment, dates)
│   │   ├── SessionsTab.vue          (class sessions table + all modals)
│   │   ├── StudentsTab.vue          (registrations + search/add/move)
│   │   ├── ScoresTab.vue            (assessment grid — reuse AssessmentScores logic)
│   │   └── SurveyTab.vue           (survey status + results)
```

**Alternative**: Keep single file with `<component :is>` dynamic switching.

**Rationale**: 994 LOC in one file is unmaintainable. Each tab is self-contained with its own props, computed, and event handlers. Follows existing patterns where complex pages have `components/` subdirectories (see `Finance/`, `Forms/Admin/`).

### D5: Index page — row click navigation + simplified dropdown

**Chosen**:
- Row click or Eye icon → navigate to Show page
- Remove from dropdown: "View Details" (now row click), "View Statistics" (now in Show Scores tab)
- Move "Recalculate Course Result" into Scores tab actions inside Show page
- Merge `enrollment_status` + `course_status` columns into single `Lifecycle` badge
- Remove Survey column from Index (now in Show)

**Alternative**: Keep all actions in dropdown but add visual grouping.

**Rationale**: Reducing from 8 to 5 dropdown items, all mutations only. Navigation actions should not be buried in menus. Survey creation and recalculation are contextual actions that belong in the detail page where the user has full context.

### D6: Sidebar restructure

**Chosen**:
- Remove "Attendance Management → Class Sessions" from sidebar
- Rename "Attendance Management" to merge into "Reports & Analytics" (add "Attendance Summary" item)
- Keep `/class-sessions` route working for direct URL access and bookmarks
- Keep `/course-statistics` route for cross-unit aggregate views

**Alternative**: Keep sidebar unchanged, only add tabs.

**Rationale**: Removing the standalone Class Sessions menu eliminates the false impression that sessions exist independently from course offerings. Cross-class attendance summary stays in Reports where it belongs.

### D7: Architecture boundary — minimal controller touch, new files in module

**Chosen**: The `CourseOfferingController` stays in legacy `app/Http/Controllers/Web/` — NOT migrated. Only 2 lines added (`Inertia::defer()` + `Inertia::once()`). New Query classes are placed in `app/Modules/Academic/Queries/` (module path) because they are **new files** and must follow module architecture per `CLAUDE.md`. The controller already imports from `App\Modules\Academic\Actions\MoveStudentToSectionAction` — same cross-boundary import pattern.

**Alternative**: Migrate controller to modular path as part of this change.

**Rationale**: The course offering controller is 2000+ LOC with deep coupling to legacy routes, middleware, and other controllers. Migrating it is a separate, high-risk change that should not be bundled with a UI/UX improvement. New Query classes go in the module path (not `app/Queries/`) because the project convention mandates new code in `app/Modules/{Domain}/` — even when consumed by legacy controllers.

### D8: Tab permissions — role-based visibility

**Chosen**: All 5 tabs are visible to all authenticated users who can access the Show page (existing policy check). Tab content follows existing data-level authorization:

| Tab | Visibility | Empty state |
|-----|-----------|-------------|
| Overview | Always visible | N/A (always has data) |
| Sessions | Always visible | "No sessions scheduled yet" + Generate button |
| Students | Always visible | "No students enrolled" |
| Scores | Visible when `syllabusTemplate` exists | "No syllabus template assigned" if missing |
| Survey | Always visible | "No survey created yet" + Create button |

No per-tab permission gates in Phase 1. If role-based tab hiding is needed later (e.g., hide Scores from non-academic staff), it can be added via a `tabPermissions` prop from the controller without changing the component structure.

**Rationale**: Adding per-tab RBAC adds complexity without clear user demand. The existing page-level policy already restricts access to authorized users. Empty states handle the "no data" case gracefully.

### D9: Route helpers over literal paths

**Chosen**: All navigation in the refactored components SHALL use `route(...)` helpers (Ziggy/Wayfinder) instead of literal URL strings. The existing Show.vue has ~15 instances of literal paths like `router.visit('/course-offerings/...')` that will be replaced.

**Rationale**: Follows `CLAUDE.md` forbidden pattern rule — literal URLs in frontend are explicitly forbidden. Route helpers provide type safety and survive route renaming.

### D10: Flash pattern — `Inertia::flash()` for new/moved actions

**Chosen**: Actions moved into tab components (survey creation in SurveyTab, recalculate in ScoresTab) SHALL use `Inertia::flash('message', '...')` for success/error feedback. This follows the Inertia v3 flash pattern. Access via `page.flash` in frontend, NOT `page.props.flash`.

**Current state**: The existing `createSurvey` method uses legacy flash (`back()->with('success', ...)`). When moved to SurveyTab, it will be upgraded.

**Rationale**: Per swinx-frontend Hard Gate #3 — new flash uses `Inertia::flash()`. Legacy controllers using `back()->with()` continue working (239 controllers still use this pattern) but new/moved code follows v3 conventions.

## Risks / Trade-offs

| Risk | Impact | Mitigation |
|------|--------|------------|
| Scores tab data is heavy (large assessment grid) | Slow tab switch | `Inertia::defer()` loads after initial render. Backend pagination if student count > 100. `<Deferred>` shows loading skeleton automatically. |
| First codebase usage of `Inertia::defer()` | Team unfamiliarity | Document pattern in PR description. `<Deferred>` is a drop-in component — simpler than manual `router.reload`. |
| Bookmarked URLs to `/course-statistics/{id}/assessment-scores` | User confusion if removed | Keep route working. Add "View in Course Offering" breadcrumb link on the standalone page. |
| Show page becomes a "god page" with too many responsibilities | Maintenance burden | Each tab is a self-contained component with explicit TypeScript prop interface. Show.vue is just a thin shell (~150 LOC). |
| Shared Query classes between controllers | Coupling risk | Query classes are read-only, stateless, and testable in isolation. This is the documented `Queries/` pattern. |
| Sidebar removal may confuse staff who bookmarked "Class Sessions" menu | Workflow disruption | Phase the change: first add tabs (additive), then remove sidebar items in a later deployment after staff notification. |
| Architecture boundary — changes in frozen legacy zone | Risk of touching frozen code | Minimal controller change (add 2 `Inertia::defer()` lines). Query classes are new files, not edits to existing legacy code. |

## Migration Plan

1. **Phase 1 (Frontend split + deferred props)**: Extract Show.vue into tab components. Add Lifecycle Header. Add `Inertia::defer()` for scores/survey in controller. All existing routes still work.
2. **Phase 2 (Index cleanup)**: Simplify columns and actions. Add lifecycle badge. Replace literal paths with route helpers.
3. **Phase 3 (Sidebar)**: Remove "Class Sessions" menu item. Merge "Attendance Management".
4. **Rollback**: Each phase is independently revertable via git. No database migrations involved.

## Resolved Questions

- **Scores tab: full grid or summary?** → Full grid embedded in tab with horizontal scroll. The standalone page at `/course-statistics/{id}/assessment-scores` continues working and links back to the tab. No summary intermediary needed — coordinators want the grid directly.
- **Tab state persistence across navigation?** → Yes, via URL. When coordinator navigates to `?tab=scores`, clicks a student link, and uses browser back — the `?tab=scores` query param is preserved in browser history. The `popstate` listener (following `clubs/Show.vue` pattern) restores the correct tab.
- **Default tab?** → `overview`. When no `?tab=` param is present, the Overview tab is active.
- **Mobile behavior?** → Tabs use shadcn-vue `TabsList` which already handles horizontal scroll on small screens. No mobile-first redesign in this change (D1 non-goal). Tab labels may be abbreviated on `< sm` breakpoints: "Overview", "Sessions", "Students", "Scores", "Survey" (already short).
