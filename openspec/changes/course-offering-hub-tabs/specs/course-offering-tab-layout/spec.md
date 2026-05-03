## ADDED Requirements

### Requirement: Show page uses tab-based layout
The Course Offering Show page SHALL render content in 5 tabs: Overview, Sessions, Students, Scores, Survey. The Show.vue file SHALL be a thin shell (~150 LOC) that renders the lifecycle header and tab container, delegating content to dedicated tab components.

#### Scenario: Default tab on page load
- **WHEN** coordinator navigates to `/course-offerings/{id}` without a `tab` query param
- **THEN** the Overview tab SHALL be active and display course information, enrollment stats, academic details, and important dates

#### Scenario: Direct tab navigation via URL
- **WHEN** coordinator navigates to `/course-offerings/{id}?tab=scores`
- **THEN** the Scores tab SHALL be active and its deferred data SHALL begin loading

#### Scenario: Invalid tab parameter
- **WHEN** coordinator navigates to `/course-offerings/{id}?tab=invalid`
- **THEN** the Overview tab SHALL be active (fallback to default)

### Requirement: Tab switching updates URL (following clubs/Show.vue pattern)
The active tab SHALL be synced to the URL via `?tab=` query parameter. Implementation SHALL follow the `clubs/Show.vue` pattern (`getCurrentTabFromURL()` + `updateTabInURL()` + `handleTabChange()` functions):
- `getCurrentTabFromURL()` — reads `?tab=` from `window.location.search`
- `updateTabInURL()` — uses `router.visit()` with `preserveState: true, preserveScroll: true, replace: true, only: []`
- `popstate` listener — restores tab on browser back/forward via `onMounted`/`onUnmounted`
- Default tab (`overview`) SHALL omit `?tab=` from URL (clean URL)

#### Scenario: User clicks Sessions tab
- **WHEN** coordinator clicks the "Sessions" tab trigger
- **THEN** the URL SHALL update to `?tab=sessions` without full page reload
- **THEN** the Sessions tab content SHALL become visible

#### Scenario: Browser back/forward navigation
- **WHEN** coordinator uses browser back button after switching tabs
- **THEN** the previously active tab SHALL be restored based on URL `?tab=` param
- **THEN** the `popstate` event handler SHALL update `currentTab` ref

#### Scenario: Default tab URL is clean
- **WHEN** coordinator clicks the "Overview" tab (default)
- **THEN** the URL SHALL be `/course-offerings/{id}` (no `?tab=overview`)

### Requirement: Deferred loading for Scores and Survey tabs via Inertia v3
The Scores and Survey tabs SHALL use `Inertia::defer()` on the backend and `<Deferred>` component on the frontend. Overview, Sessions, and Students tabs use data already included in the initial eager-loaded page props.

#### Scenario: Initial page load
- **WHEN** the Show page loads for the first time
- **THEN** `courseOffering` (with `classSessions`, `courseRegistrations`, `academicRecords`) SHALL be included as an eager prop
- **THEN** `availableRooms` and `siblingOfferings` SHALL be wrapped in `Inertia::once()` (evaluated once, skipped on partial reloads)
- **THEN** `scoresData` and `surveyData` SHALL NOT be evaluated on the server (deferred callbacks not invoked)
- **THEN** Inertia SHALL automatically trigger deferred prop loading after the initial render via 2 parallel requests (separate groups `'scores'` and `'survey'`)

#### Scenario: Deferred Scores data loading
- **WHEN** the page renders and Inertia triggers the `scores` deferred group
- **THEN** the `<Deferred data="scoresData">` component SHALL show the `#fallback` slot (loading skeleton)
- **THEN** once data arrives, the `#default` slot SHALL render ScoresTab replacing the fallback

#### Scenario: Deferred Survey data loading
- **WHEN** the page renders and Inertia triggers the `survey` deferred group
- **THEN** the `<Deferred data="surveyData">` component SHALL show the `#fallback` slot (loading skeleton)
- **THEN** once data arrives, the `#default` slot SHALL render SurveyTab replacing the fallback

#### Scenario: Reloading indicator on deferred tabs
- **WHEN** a partial reload is in progress for a deferred group (e.g., manual refresh via `router.reload({ only: ['scoresData'] })`)
- **THEN** the `<Deferred>` `#default` slot SHALL expose `reloading` slot prop as `true`
- **THEN** the tab SHALL show a subtle opacity overlay (`opacity-50`) while retaining previous data

### Requirement: Backend serves deferred props via `Inertia::defer()` and once props via `Inertia::once()`
The `CourseOfferingController@show` method SHALL use `Inertia::defer()` with named groups for `scoresData` and `surveyData`, and `Inertia::once()` for rarely-changing props. Query classes SHALL be placed in `app/Modules/Academic/Queries/` (module path).

#### Scenario: Controller renders with deferred and once props
- **WHEN** the controller handles a Show request
- **THEN** it SHALL return:
  - `courseOffering` — eager (standard prop, with loaded relations)
  - `availableRooms` — `Inertia::once(fn () => ...)` (evaluated once, skipped on partial reloads)
  - `siblingOfferings` — `Inertia::once(fn () => ...)` (evaluated once, skipped on partial reloads)
  - `scoresData` — `Inertia::defer(fn () => GetCourseOfferingScoresQuery::handle($courseOffering), 'scores')`
  - `surveyData` — `Inertia::defer(fn () => GetCourseOfferingSurveyQuery::handle($courseOffering), 'survey')`

#### Scenario: Deferred callback structure for scores
- **WHEN** Inertia requests the `scores` deferred group
- **THEN** `App\Modules\Academic\Queries\GetCourseOfferingScoresQuery::handle()` SHALL return the same data structure as `CourseStatisticsController@assessmentScores`: assessment components, assessment details, student scores grid, statistics

#### Scenario: Deferred callback structure for survey
- **WHEN** Inertia requests the `survey` deferred group
- **THEN** `App\Modules\Academic\Queries\GetCourseOfferingSurveyQuery::handle()` SHALL return: survey target info, completion stats, response summary (or `null` if no survey exists)

### Requirement: Component extraction from Show.vue with explicit prop interfaces
Show.vue (994 LOC) SHALL be split into dedicated components with explicit TypeScript prop interfaces.

#### Shared type definitions
```typescript
// Matches GetCourseOfferingScoresQuery::handle() return shape
interface ScoresData {
    assessmentComponents: Array<{ id: number; name: string; weight: number }>
    assessmentDetails: Array<{ id: number; component_id: number; name: string; max_score: number }>
    scoresGrid: Array<{
        student_id: number
        student_name: string
        scores: Record<number, number | null>  // detail_id → score
        component_totals: Record<number, number>
        final_percentage: number | null
        final_letter_grade: string | null
    }>
    statistics: { average: number; median: number; highest: number; lowest: number; passed: number; failed: number }
}

// Matches GetCourseOfferingSurveyQuery::handle() return shape
interface SurveyData {
    surveyTarget: { id: number; form_id: number; status: string; start_at: string; end_at: string | null } | null
    completionStats: { total: number; completed: number; pending: number } | null
    formVersion: { id: number; title: string; code: string } | null
}
```

#### Component: Show.vue (thin shell, ~150 LOC)
```typescript
// Receives all Inertia page props, distributes to tab components
interface Props {
    courseOffering: CourseOffering  // with classSessions, courseRegistrations, academicRecords
    availableRooms: Room[]
    siblingOfferings: CourseOffering[]
    scoresData?: ScoresData        // deferred — may be undefined initially
    surveyData?: SurveyData        // deferred — may be undefined initially
}
```

#### Component: OverviewTab.vue
```typescript
interface Props {
    courseOffering: CourseOffering
    siblingOfferings: CourseOffering[]
}
```

#### Component: SessionsTab.vue
```typescript
interface Props {
    courseOffering: CourseOffering  // needs classSessions relation
    availableRooms: Room[]
}
```

#### Component: StudentsTab.vue
```typescript
interface Props {
    courseOffering: CourseOffering  // needs courseRegistrations, academicRecords
    siblingOfferings: CourseOffering[]
}
```

#### Component: ScoresTab.vue
```typescript
interface Props {
    courseOffering: CourseOffering  // needs id, syllabusTemplate
    scoresData?: ScoresData        // from deferred prop
}
```

#### Component: SurveyTab.vue
```typescript
interface Props {
    courseOffering: CourseOffering  // needs id, form_targets
    surveyData?: SurveyData        // from deferred prop
}
```

#### Component: CourseLifecycleHeader.vue
```typescript
interface Props {
    courseOffering: CourseOffering  // needs classSessions statuses, course_status, current_enrollment, form_targets
}
```

#### Scenario: Show.vue renders all tabs
- **WHEN** Show.vue is loaded
- **THEN** it SHALL import and render each tab component within shadcn-vue `TabsContent` wrappers
- **THEN** ScoresTab and SurveyTab SHALL be wrapped in `<Deferred>` components
- **THEN** Show.vue SHALL contain no more than ~200 LOC (shell only)

### Requirement: Tab empty states
Each tab SHALL handle the "no data" case with a meaningful empty state.

#### Scenario: Sessions tab with no sessions
- **WHEN** `courseOffering.classSessions` is empty
- **THEN** SessionsTab SHALL display "No sessions scheduled yet" with a Generate Sessions button

#### Scenario: Students tab with no enrollments
- **WHEN** `courseOffering.courseRegistrations` is empty
- **THEN** StudentsTab SHALL display "No students enrolled"

#### Scenario: Scores tab with no syllabus template
- **WHEN** `courseOffering.syllabusTemplate` is null
- **THEN** ScoresTab SHALL display "No syllabus template assigned — assessment scores require a syllabus template"

#### Scenario: Survey tab with no survey
- **WHEN** `surveyData` is null or has no survey target
- **THEN** SurveyTab SHALL display "No survey created yet" with a Create Survey button

### Requirement: All navigation uses route helpers
All router navigation in extracted components SHALL use `route(...)` helpers instead of literal URL strings.

#### Scenario: Navigate to class session detail
- **WHEN** coordinator clicks a class session row
- **THEN** navigation SHALL use `route('class-sessions.show', sessionId)` (not `router.visit('/class-sessions/' + id)`)

#### Scenario: Navigate to student detail
- **WHEN** coordinator clicks a student name link
- **THEN** navigation SHALL use the appropriate route helper

### Requirement: Existing standalone routes continue working
The existing routes `/course-statistics/{id}/assessment-scores`, `/course-statistics/{id}/students`, `/class-sessions/{id}`, and `/surveys/{id}` SHALL continue to work as before.

#### Scenario: Direct access to assessment scores page
- **WHEN** user navigates to `/course-statistics/{id}/assessment-scores`
- **THEN** the standalone assessment scores page SHALL render normally
- **THEN** a breadcrumb link "View in Course Offering" SHALL be present linking to `/course-offerings/{id}?tab=scores`

#### Scenario: Direct access to class session detail
- **WHEN** user navigates to `/class-sessions/{id}`
- **THEN** the standalone class session page SHALL render normally
- **THEN** the "Back to" button SHALL clearly show the course offering name/code
