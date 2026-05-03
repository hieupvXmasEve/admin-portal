## Why

Academic Coordinators managing a single course offering must navigate 4+ sidebar sections and 5-6 page transitions to access related data (class sessions, attendance, assessment scores, survey results). The Course Offering Show page (994 lines) is a monolithic scroll with no lifecycle context, while the Index page crams 8 actions into a single dropdown mixing navigation with mutations. This fragmentation increases cognitive load and slows daily operations.

## What Changes

- **Refactor Course Offering Show page** into a tab-based layout with 5 tabs: Overview, Sessions, Students, Scores, Survey
- **Add Lifecycle Header** component with progress indicator (Setup → Registration → Teaching → Grading → Completed) and warnings bar showing pending action items
- **Pull assessment scores data** (currently at `/course-statistics/{id}/assessment-scores`) into the Show page's Scores tab via Inertia partial loading
- **Pull survey data** (currently at `/surveys/{id}`) into the Show page's Survey tab
- **Simplify Index page actions**: extract navigation actions (View, Statistics) out of dropdown; keep only mutation actions in dropdown; add lifecycle badge column replacing separate status columns
- **Restructure sidebar**: remove standalone "Class Sessions" menu item under Attendance Management; merge "Attendance Management" group into "Reports & Analytics" for cross-class views only
- **Remove Survey column** from Index table (moved into Show page)

## Capabilities

### New Capabilities
- `course-offering-tab-layout`: Tab-based Show page with lazy-loaded tabs (Overview, Sessions, Students, Scores, Survey) and Inertia partial reload support
- `course-lifecycle-header`: Computed lifecycle progress bar and warnings aggregation displayed above tabs
- `index-action-cleanup`: Simplified Index page with reduced columns, reorganized actions, and lifecycle badge

### Modified Capabilities
<!-- No existing spec-level behavior changes. All changes are UI/UX restructuring of existing data. -->

## Impact

- **Frontend**: `pages/course-offerings/Show.vue` (994 LOC) → split into ~6 components. `pages/course-offerings/Index.vue` (880 LOC) → column/action reduction. `constants/menu-sidebar.ts` → sidebar restructure.
- **Backend**: `CourseOfferingController@show` needs optional `?tab=scores` and `?tab=survey` params to serve partial data. Reuse existing `CourseStatisticsController` and `SurveyManagementController` query logic.
- **Routes**: No breaking changes. Existing URLs (`/course-statistics/{id}/assessment-scores`, `/class-sessions/{id}`) continue working. Show page adds tab query param.
- **Dependencies**: No new packages. Uses existing shadcn-vue Tabs component.
