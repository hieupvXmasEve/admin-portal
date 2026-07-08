# Complete `Sổ cái` With Voided Lines

Status: ready-for-agent

## Parent

.scratch/student-finance-overview/PRD.md

## What to build

Make **Sổ cái** complete enough to explain one student's tuition history without hiding important context. It should remain grouped by semester, but include every semester with finance history, even if the semester has no current **Còn phải thu** or only has voided lines.

Voided invoice lines should appear in their original semester, muted and marked **Đã hủy**. They must not count toward **Còn phải thu**, but they should remain visible so staff can understand cases where fees existed, payment was applied, and then the fee was removed.

## Acceptance criteria

- [ ] **Sổ cái** remains grouped by semester.
- [ ] Every semester with finance history appears, including semesters with zero current collectible amount.
- [ ] A semester with no current collectible amount is marked **Không còn phải thu**.
- [ ] Active lines and voided lines can both appear in the same semester.
- [ ] Voided lines are visually muted and marked **Đã hủy**.
- [ ] Voided lines do not contribute to **Còn phải thu** or current collectible totals.
- [ ] Paid/reversed payment context on voided lines remains understandable from the page.
- [ ] A regression scenario shaped like `AUS121787` shows the SUMMER2026 voided EGC lines instead of making the semester appear empty or missing.
- [ ] Feature tests assert active and voided line presence, semester state, and that voided lines are excluded from collectible totals.
- [ ] Frontend type/lint checks pass for changed Vue/TypeScript files.

## Blocked by

- .scratch/student-finance-overview/issues/01-first-viewport-student-finance-kpis.md
