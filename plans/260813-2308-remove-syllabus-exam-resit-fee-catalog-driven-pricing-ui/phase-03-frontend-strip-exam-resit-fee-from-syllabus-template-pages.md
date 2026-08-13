---
phase: 2
title: "Frontend: strip exam_resit_fee from Syllabus Template pages"
status: completed
priority: P1
effort: "2h"
dependencies: [1]
---

# Phase 2: Frontend — strip exam_resit_fee from Syllabus Template pages

## Overview

Remove the now-nonexistent `exam_resit_fee` field from every Syllabus
Template Vue page (Create, Edit, Show, Index) so staff no longer see a form
field that does nothing.

## Requirements

- Functional: Create/Edit forms no longer have an `exam_resit_fee` input or
  its Zod validation.
- Functional: Show/Index pages no longer display an `exam_resit_fee`
  column/row.
- Non-functional: no dead TS types referencing `exam_resit_fee` on
  `SyllabusTemplate`.

## Architecture

Pure removal — no replacement UI needed here (the replacement UX is Phase 3,
on the Pricing Operations page, not the Syllabus Template page). Do not
conflate: `resources/js/types/finance.ts`'s `ActiveChargeType` union entry for
`'exam_resit_fee'` is the **charge_type constant**, unrelated to
`SyllabusTemplate` — leave it untouched (see plan.md Non-Goals).

## Related Code Files

- Modify: `resources/js/pages/Syllabus/TemplatesCreate.vue`
  — remove Zod schema field (line 50), default value `750000` (line 104),
  form input `name`/label (lines 501, 511).
- Modify: `resources/js/pages/Syllabus/TemplatesEdit.vue`
  — remove type def (line 59), Zod validation `min:1` (line 81), form value
  init (line 97), submit payload key (line 228), form input `name`/label
  (lines 479, 489).
- Modify: `resources/js/pages/Syllabus/TemplatesShow.vue`
  — remove type def (line 65), `formatExamResitFee(template.exam_resit_fee)`
  display block (line 298) and the now-unused `formatExamResitFee` helper if
  it has no other caller.
- Modify: `resources/js/pages/Syllabus/TemplatesIndex.vue`
  — remove type def (line 33), table `accessorKey` column (line 138), row
  cell formatter (line 141).
- Do NOT modify: `resources/js/types/finance.ts` — `exam_resit_fee` there is
  the Finance charge_type constant, still live and used.

## Implementation Steps

1. Grep `exam_resit_fee` under `resources/js/pages/Syllabus/` to confirm the
   exact current line numbers (file line numbers likely shifted since the
   original scout) before editing each file.
2. Remove field from Create/Edit forms (schema + input + label + submit key).
3. Remove display from Show/Index pages.
4. Check `formatExamResitFee` (or similarly named formatter) for other call
   sites before deleting it — if unused after this phase, delete; if shared,
   leave and just remove the one call site.
5. Run `pnpm eslint --fix` on touched files, then per-file `vue-tsc`/`tsc`
   type-check (NOT whole-project — known OOM in dev container, see memory
   `vue-tsc-typecheck-ooms-in-dev-container`).

## Success Criteria

- [ ] `grep -ri exam_resit_fee resources/js/pages/Syllabus/` returns zero hits
- [ ] Create/Edit forms submit successfully without the removed field
- [ ] Show/Index pages render without console errors for missing property

## Risk Assessment

- **Risk:** `formatExamResitFee` might be a generic currency formatter reused
  elsewhere under a fee-specific name — verify before deleting, rename/keep if
  actually generic.
- **Risk:** stale cached Inertia page props on staging/prod after deploy if
  backend and frontend deploy is not atomic — same-PR deploy avoids this,
  flag if this repo's deploy pipeline splits FE/BE releases.
