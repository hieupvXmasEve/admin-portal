## Phase 1: Frontend Split + Deferred Props

### 1. Component Extraction — Show.vue Split

- [x] 1.1 Create `resources/js/pages/course-offerings/components/tabs/OverviewTab.vue` — extract course info cards, enrollment stats, academic details, important dates. Props: `{ courseOffering, siblingOfferings }`. Include empty state handling.
- [x] 1.2 Create `resources/js/pages/course-offerings/components/tabs/SessionsTab.vue` — extract class sessions table, generate/add/bulk-edit/delete actions, and all session-related modals (QuickEditClassSessionModal, AddClassSessionModal, BulkEditClassSessionModal, RoomSelectionModal). Props: `{ courseOffering, availableRooms }`. Empty state: "No sessions scheduled yet" + Generate button.
- [x] 1.3 Create `resources/js/pages/course-offerings/components/tabs/StudentsTab.vue` — extract student registration table, StudentSearchModal, MoveStudentModal, delete registration logic, retake info display. Props: `{ courseOffering, siblingOfferings }`. Empty state: "No students enrolled".
- [x] 1.4 Create `resources/js/pages/course-offerings/components/tabs/ScoresTab.vue` — stub component with `<Deferred>` fallback skeleton. Props: `{ courseOffering, scoresData? }`. Empty state: "No syllabus template assigned" when `syllabusTemplate` is null.
- [x] 1.5 Create `resources/js/pages/course-offerings/components/tabs/SurveyTab.vue` — stub component with `<Deferred>` fallback skeleton. Props: `{ courseOffering, surveyData? }`. Empty state: "No survey created yet" + Create button.
- [x] 1.6 Refactor `Show.vue` into thin shell (~150-200 LOC):
  - Import all tab components + `CourseLifecycleHeader`
  - Render shadcn-vue `Tabs` with URL-synced `?tab=` param (follow `clubs/Show.vue` `getCurrentTabFromURL()` + `updateTabInURL()` + `handleTabChange()` pattern)
  - `getCurrentTabFromURL()` + `updateTabInURL()` + `popstate` listener
  - Default tab `overview` omits `?tab=` from URL
  - Wrap ScoresTab in `<Deferred data="scoresData">` with `#fallback` and `#default` slots
  - Wrap SurveyTab in `<Deferred data="surveyData">` with `#fallback` and `#default` slots
  - Import `Deferred` from `@inertiajs/vue3`
  - Example wiring pattern:
    ```vue
    <Deferred data="scoresData">
        <template #fallback>
            <Skeleton class="h-64" />
        </template>
        <template #default="{ reloading }">
            <ScoresTab
                :course-offering="courseOffering"
                :scores-data="scoresData"
                :class="{ 'opacity-50': reloading }"
            />
        </template>
    </Deferred>
    ```
- [x] 1.7 Replace all literal URL paths in extracted components with `route(...)` helpers

### 2. Lifecycle Header

- [x] 2.1 Create `resources/js/pages/course-offerings/components/CourseLifecycleHeader.vue` — progress bar with 5 stages (Setup, Registration, Teaching, Grading, Completed). Computed lifecycle stage from courseOffering props. Cancelled state shows destructive badge + greyed stages. TypeScript prop interface per spec.
- [x] 2.2 Add warnings bar to CourseLifecycleHeader — display contextual alerts: sessions without attendance, survey not created, all sessions completed but course not finalized. Hide bar entirely when no warnings.
- [x] 2.3 Integrate CourseLifecycleHeader into Show.vue shell — render above tabs, pass courseOffering prop

### 3. Backend — Deferred Props with `Inertia::defer()` + `Inertia::once()`

- [x] 3.1 Create `app/Modules/Academic/Queries/GetCourseOfferingScoresQuery.php` — extract assessment scores logic from `CourseStatisticsController@assessmentScores` into reusable query class with `static handle(CourseOffering $courseOffering): array` returning assessment_components, assessment_details, scores_grid, statistics. Place in module path `app/Modules/Academic/Queries/` (new file follows module architecture even though controller is in legacy zone — see D7).
- [x] 3.2 Create `app/Modules/Academic/Queries/GetCourseOfferingSurveyQuery.php` — extract survey data logic with `static handle(CourseOffering $courseOffering): ?array` returning survey target, completion stats, form version data. Returns null if no survey exists. Place in `app/Modules/Academic/Queries/`.
- [x] 3.3 Update `CourseOfferingController@show` — add `Inertia::defer()` and `Inertia::once()` props:
  ```php
  return Inertia::render('course-offerings/Show', [
      // Eager — needed by Overview, Sessions, Students tabs
      'courseOffering' => $courseOffering,

      // Once — rarely change during page session, skip on partial reloads
      'availableRooms' => Inertia::once(fn () => $availableRooms),
      'siblingOfferings' => Inertia::once(fn () => $siblings),

      // Deferred — loaded after initial render, in separate named groups (parallel)
      'scoresData' => Inertia::defer(fn () => GetCourseOfferingScoresQuery::handle($courseOffering), 'scores'),
      'surveyData' => Inertia::defer(fn () => GetCourseOfferingSurveyQuery::handle($courseOffering), 'survey'),
  ]);
  ```
- [x] 3.4 Refactor `CourseStatisticsController@assessmentScores` to use shared `GetCourseOfferingScoresQuery` — maintain existing route behavior, DRY the logic
- [x] 3.5 Update `createSurvey` method flash to use `Inertia::flash('message', '...')` pattern instead of legacy `back()->with('success', ...)` — per D10

### 4. Scores Tab — Full Integration

- [x] 4.1 Implement ScoresTab.vue — render assessment scores grid (port logic from `CourseStatistics/AssessmentScores.vue`): assessment components table headers, student scores rows, component totals, color-coded badges, legend. Use horizontal scroll for wide grids.
- [x] 4.2 Wire `<Deferred data="scoresData">` in Show.vue — `#fallback` slot shows loading skeleton, `#default` slot receives `{ reloading }` prop. Content renders ScoresTab with `scoresData` prop. Apply `opacity-50` class when `reloading` is true.
- [x] 4.3 Move "Recalculate Course Result" action into ScoresTab — add button (visible only when course_status is completed) that triggers the recalculate API call using route helper. Use `Inertia::flash()` for success/error feedback (per D10).

### 5. Survey Tab — Full Integration

- [x] 5.1 Implement SurveyTab.vue — show survey status (created/not created), creation form (select survey form + create button), completion stats (X/Y students completed), link to full survey results page
- [x] 5.2 Wire `<Deferred data="surveyData">` in Show.vue — `#fallback` slot shows loading skeleton, `#default` slot receives `{ reloading }` prop. Content renders SurveyTab with `surveyData` prop.
- [x] 5.3 Move survey creation logic from Index.vue into SurveyTab — survey form select dialog, create survey action using `useForm`, success/error handling using route helpers + `Inertia::flash()` pattern (per D10)

## Phase 2: Index Page Cleanup

### 6. Index Page — Action Cleanup

- [x] 6.1 Add row click navigation — configure DataTable to navigate using `route('course-offerings.show', id)` on row click (exclude clicks on action buttons)
- [x] 6.2 Add visible Eye icon button — render always-visible Eye icon as primary action outside the dropdown, navigate using route helper
- [x] 6.3 Simplify dropdown actions — remove "View Details" and "View Statistics" from dropdown. Remove "Recalculate Course Result" (now in Scores tab). Keep: Edit, Duplicate, Toggle Registration, Mark Completed, Delete (conditional on status). All actions use route helpers.
- [x] 6.4 Replace status columns with Lifecycle badge — merge `enrollment_status` + `course_status` columns into single "Lifecycle" column with computed badge per acceptance criteria:
  - not_started + open → "Enrolling" (default)
  - not_started + closed → "Scheduled" (secondary)
  - in_progress → "Teaching" (secondary)
  - all sessions completed but course not completed → "Grading" (warning)
  - completed → "Completed" (outline)
  - cancelled → "Cancelled" (destructive)
- [x] 6.5 Remove Survey column from Index table — delete survey column definition and related dialog (Create Survey Dialog) from Index.vue
- [x] 6.6 Replace all literal URL paths in Index.vue with `route(...)` helpers
- [x] 6.7 Clean up unused imports and functions in Index.vue after removals

## Phase 3: Sidebar Restructure

### 7. Sidebar Restructure

- [x] 7.1 Update `constants/menu-sidebar.ts` — remove "Attendance Management" group. Add "Attendance Summary" item under "Reports & Analytics" linking to existing attendance route.
- [x] 7.2 Verify all sidebar links still work — ensure attendance report route, course statistics route, and other moved items resolve correctly

### 8. Cross-Links and Breadcrumbs

- [x] 8.1 Update `class-sessions/Show.vue` breadcrumb — change "Back to" button to show course code and link text: "Back to [COURSE_CODE]" using route helper
- [x] 8.2 Add "View in Course Offering" link to `CourseStatistics/AssessmentScores.vue` — breadcrumb link using route helper to course offering Show with `?tab=scores`
- [x] 8.3 Add "View in Course Offering" link to `CourseStatistics/Detail.vue` — breadcrumb link using route helper to course offering Show with `?tab=sessions`

## Verification

### 9. Quality Gates

- [x] 9.1 Run type-check (`./scripts/dev.sh npm run type-check`) — ensure all new components have correct TypeScript interfaces
- [x] 9.2 Run lint (`./scripts/dev.sh npm run lint`) — no new lint errors
- [x] 9.3 Run format check (`./scripts/dev.sh npm run format:check`) — no formatting issues
- [x] 9.4 Manual smoke test — navigate through all 5 tabs, verify `<Deferred>` loading skeletons appear and data populates, check lifecycle header stages, test Index row click and dropdown actions
- [x] 9.5 Verify URL-backed tab state — test `?tab=scores` direct URL, browser back/forward, clean URL for default tab
- [x] 9.6 Verify backward compatibility — confirm `/course-statistics/{id}/assessment-scores`, `/class-sessions/{id}`, and `/surveys/{id}` routes still work independently
- [x] 9.7 Verify no literal URL paths remain — grep for `router.visit('/'` and `router.post('/'` patterns in changed files
