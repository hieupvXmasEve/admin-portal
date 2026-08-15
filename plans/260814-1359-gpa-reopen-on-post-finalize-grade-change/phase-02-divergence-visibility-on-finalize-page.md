---
phase: 2
title: "Divergence visibility on the GPA finalize page"
status: todo
priority: P1
effort: "4h"
dependencies: [1]
---

# Phase 2: Divergence visibility on the GPA finalize page

## Overview

Staleness is derivable, not state: `PreviewSemesterGpaAction:59` already recomputes live semester and cumulative GPA for every student in the campus+semester. Surface the stored snapshot next to it and flag the difference. Zero new columns, zero hooks, and it covers every change shape uniformly — edited grade, new retake attempt, late-graded offering, removed record — because it compares outcomes rather than watching writes.

## Requirements

- Functional: each preview row carries `stored_semester_gpa`, `stored_cumulative_gpa` (null when never finalized) and `is_divergent`.
- Functional: page shows a count banner for divergent students and a per-row badge distinguishing three states: never finalized, finalized and matching, finalized but diverged.
- Functional: the stale help text at `Index.vue:179` is corrected — it currently tells operators finalized GPA is *"không bị ảnh hưởng khi điểm thay đổi sau khi chốt"*, describing an algorithm that never existed (see plan.md → Red Team Review → Decisions resolved #1).
- Functional: divergence must be computed through `App\Shared\Support\Academic\GpaValueComparator` (extracted in Phase 1), the same class the finalize guard and the reconciliation query use, so the page, the action and the audit cannot disagree. The rule is 3-decimal normalization, not an epsilon tolerance.
- Non-goals: no notifications, no new pages, no dashboards, no new audit command — `academic:audit-progression-reconciliation --student-id= --semester-id= --all --format=json` already reports the same divergence as invariant `GPA-001` and is the proactive/cross-semester surface.

## Architecture

`PreviewSemesterGpaAction` already loops students and computes both GPAs. Add one grouped `GpaCalculation` lookup for the students in view, attach stored values + divergence per row. One extra query total, not per student.

Divergence semantics deliberately equal phase 1's skip guard inverted: a row is divergent exactly when re-running Finalize would change it. That makes the badge a truthful prediction of what the button does.

Note the page needs no `is_finalized` filtering change: `PreviewSemesterGpaAction` never references `is_finalized` and already lists every student with final credit-bearing records. The pre-review draft of this plan wrongly claimed the finalize page filters on it.

## Related Code Files

- Modify: `app/Actions/Academic/PreviewSemesterGpaAction.php` (stored-value lookup + divergence fields, via `GpaValueComparator`)
- Read-only dep: `app/Shared/Support/Academic/GpaValueComparator.php` (created in Phase 1)
- Modify: `resources/js/pages/Admin/Academic/Gpa/Index.vue` (badge, count banner, corrected help text at ~:179)
- Read-only deps: `app/Modules/Academic/Http/Web/GpaManagementController.php` (already passes `previewData` through), `app/Models/GpaCalculation.php`
- Create test: `tests/Feature/Academic/Gpa/PreviewDivergenceVisibilityTest.php`
- Migration/route/FormRequest/command: none.

## Implementation Steps

1. Failing tests: preview payload marks a diverged student `is_divergent=true` with correct stored values; a matching student `false`; a never-finalized student `stored_* = null` and `is_divergent=false`. (Feature tests need `_token` — CSRF is active in this suite.)
2. Implement the grouped lookup + row enrichment in `PreviewSemesterGpaAction`, calling `GpaValueComparator` from Phase 1.
3. Vue: badge + banner. Check what `Index.vue` already imports (`Badge` is already in use at ~:247) — add nothing new if an existing component fits.
4. Rewrite the cumulative-GPA bullet in the help text to describe actual behavior: cumulative is always recomputed from transcript records; the finalized row is a published snapshot that can drift from current grades and is corrected by re-finalizing.
5. Per-file lint on the touched Vue file only — whole-project `type-check` is SIGKILLed in the dev container.
6. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Gpa`

## Todo

- [ ] Tests red then green
- [ ] Badge + banner render; AUS19927 shows as diverged on campus 2 / SPRING2026 before phase-1 re-finalize, clean after
- [ ] Help text corrected
- [ ] Per-file lint clean

## Success Criteria

- [ ] Divergent set shown on the page equals the `GPA-001` exceptions from `academic:audit-progression-reconciliation` for the same scope
- [ ] Badge state flips to matching immediately after a successful re-finalize

## Risk Assessment

- Page/action/audit disagreeing on "divergent" would erode trust in the badge. Mitigation: all three call the same `GpaValueComparator`, plus a test comparing page output against the reconciliation query for the same scope.
- The corrected help text changes what operators have been told for months; word it as current behavior plus the corrective action, not as a bug notice.
