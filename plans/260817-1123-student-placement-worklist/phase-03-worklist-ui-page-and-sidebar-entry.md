---
phase: 3
title: "Worklist UI page and sidebar entry"
status: completed
priority: P2
effort: "5h"
dependencies: [2]
---

# Phase 3: Worklist UI page and sidebar entry

## Overview

Vue page for the worklist plus a per-row "Classify" dialog posting to the
existing `students.placement.initialize` route, and a sidebar menu entry with
its downstream consumers kept green.

## Requirements

- Functional: table (student code, name, program, intake, admission date),
  search box, program filter dropdown, pagination; row action opens classify dialog with the same
  fields as the lifecycle-tab initialize form: `has_ielts` switch, IELTS score
  + optional issue date/notes/upload + optional level, or placement-test
  `english_level` (0-5), `semester_id` select, `notes`; success toast +
  row removed (Inertia reload).
- Dialog posts `student_id`, `semester_id`, `has_ielts`, and conditional
  fields to route `students.placement.initialize` (validated by existing
  `InitializePlacementRequest`); display validation errors inline.
- Sidebar: entry "Placement Worklist" in the Academic group of
  `resources/js/constants/menu-sidebar.ts`, permission-gated the same way
  neighboring student entries are.
- Non-functional: small self-contained dialog component — do NOT refactor
  `EgcControls.vue` (26.5K) in this plan.

## Architecture

Page `resources/js/pages/Academic/PlacementWorklist/Index.vue` +
`ClassifyStudentDialog.vue` beside it. Form via Inertia `useForm`, mirroring
`EgcControls.vue` lines ~82-140 (fields, `usesPlacementTest` computed using
`ieltsScoreThreshold` prop). Table follows the repo's existing DataTable/
pagination idioms from `Students/Index.vue`.

## Related Code Files

- Create: `resources/js/pages/Academic/PlacementWorklist/Index.vue`
- Create: `resources/js/pages/Academic/PlacementWorklist/ClassifyStudentDialog.vue`
- Modify: `resources/js/constants/menu-sidebar.ts`
- Modify (if label assertions break): `tests/Feature/Navigation/SidebarMenuStructureTest.php`
  and other menu-sidebar consumer tests (3 PHP tests total per repo memory)
- Modify (if group labels shown in user guide): `docs-site` user-guide pages

## Implementation Steps

1. Build Index.vue against controller props from phase 2.
2. Build ClassifyStudentDialog.vue; port field logic from `EgcControls.vue`
   initialize section; post to `route('students.placement.initialize', id)`.
3. Add sidebar entry; run the menu-sidebar consumer tests
   (`./scripts/dev.sh artisan test tests/Feature/Navigation/ --compact`)
   and fix assertions; check docs-site CI gate expectations.
4. Per-file eslint on new/changed files (whole-project type-check OOMs in dev
   container — skip it).
5. Manual smoke: classify one EGC + one IELTS student on dev DB; confirm rows
   in `student_action_logs`, `academic_progression_events`, enrollment stage,
   and disappearance from list.

## Success Criteria

- [x] Page renders list, search, pagination (Inertia render + props asserted
      server-side in PlacementWorklistTest)
- [x] EGC classify and IELTS classify both succeed with correct DB side
      effects (phase 1 log included) — EGC path proven end-to-end via HTTP
      POST in PlacementWorklistTest; IELTS path via PlacementOwnershipTest
- [x] Validation errors surface inline (same error-binding markup as the
      proven EgcControls placement dialog)
- [x] Sidebar entry visible only with permission; all sidebar consumer tests
      green (Navigation 5 + Finance consumers 20); docs-site gate green
      (check-docs-freshness all current, pnpm build success)
- [x] eslint clean on touched files

**Post-completion UX iteration (2026-08-17, user request):** dialog reworked —
semester defaults to the student's intake semester; `has_ielts` switch replaced
by an EGC/Major radio (default EGC): Major shows IELTS fields (missing-scan
flag allowed, no EGC level), EGC shows only English Level; below-threshold
Major score shows an inline warning that the server will still place into EGC.
Backend contract unchanged (radio maps to `has_ielts`). Docs step 4 updated in
all four locales.

**Verification note:** step 5's manual browser smoke on the dev DB was not
run this session; DB side effects are covered by the HTTP feature tests
above. Recommend one click-through (EGC + IELTS classify) on dev before
shipping to prod.

## Risk Assessment

- Sidebar collision with pending plan `260816-2125-sidebar-menu-ia-restructure`
  — whichever lands second rebases the entry (coordination note in plan.md).
- Form drift vs `EgcControls.vue`: accepted duplication for v1; extraction into
  a shared component is a follow-up only if the two forms diverge painfully.
