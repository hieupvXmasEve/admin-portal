# Retire Course Statistics per-offering scores page → redirect to cockpit scores tab, and clean up menus

Status: ready-for-human (implemented 2026-07-03; all acceptance criteria met, see commit 2ad43831 on dev)

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

Retire the Course Statistics per-offering assessment-scores page (`resources/js/pages/CourseStatistics/AssessmentScores.vue`) in favor of the cockpit's scores tab, which already renders the same scores grid data (via `GetCourseOfferingScoresQuery`). Old links/bookmarks to the retired page redirect to the cockpit scores tab for the same offering. Excel exports stay under Course Statistics — this issue does not touch export functionality, only the interactive per-offering scores page.

Course Statistics keeps its aggregate views, drill-downs, and exports; only the single per-offering scores page is retired. Report pages that previously linked to the retired page should instead deep-link to the cockpit.

As part of this same issue, clean up sidebar/navigation menus to match the ownership boundaries established across this whole PRD: remove or relabel menu entries that pointed at now-retired or now-demoted surfaces (the retired Course Statistics scores page from this issue, and the demoted standalone Attendance recording entry points from phase B), so the menu structure matches where operations actually live (the cockpit) versus where reporting lives (Course Statistics, Attendance reporting).

## Acceptance criteria

- [x] The Course Statistics per-offering assessment-scores page route redirects to the corresponding cockpit scores tab; the old Vue page is removed (not left as dead code).
- [x] The cockpit scores tab renders the same scores grid data/component that the retired page used — no behavioral drift between what was there and what's now in the cockpit.
- [x] Course Statistics aggregate views, drill-downs, and Excel exports (attendance grid, combined stats) are unaffected and still work.
- [x] Any Course Statistics report views that linked to the old per-offering scores page now deep-link to the cockpit instead.
- [x] Sidebar/navigation menus no longer expose the retired scores page or the demoted per-offering Attendance recording entry points as separate management flows; entries route staff toward the cockpit for operations and toward Course Statistics/Attendance for reporting.
- [x] Pest HTTP feature test confirms the old scores page route redirects correctly and the cockpit scores tab renders the expected grid for a known offering/scores fixture.

## Blocked by

- Operational state read model + cockpit displays lifecycle, blockers, and gated Finalize button (display only)
- Demote standalone Attendance pages to cross-offering reporting only
