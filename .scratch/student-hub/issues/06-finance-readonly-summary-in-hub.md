# Hub Finance tab: read-only summary + deep link to Finance Office

Status: ready-for-human

## Parent

`.scratch/student-hub/PRD.md`

## What to build

A read-only finance summary inside the Hub's Finance tab, finance-aware but not finance-owning. Respect ADR-0007 and the "no Eloquent joins across module boundaries" convention.

End-to-end behavior:

- The **Finance tab** shows fees, gold wallet, and scholarships as a **read-only summary**.
- The data is sourced from the **Finance module through a cross-module read contract** — the Academic Hub does not join into Finance tables directly.
- A **deep link** opens the corresponding Finance Office surface for any money operation.
- **No mutation** path exists in the Hub Finance tab.

## Acceptance criteria

- [x] The Finance tab shows a read-only fees + gold + scholarships summary.
- [x] The summary comes from a Finance read contract (no cross-module Eloquent joins).
- [x] A deep link opens the corresponding Finance Office surface.
- [x] No mutation is possible from the Hub Finance tab.
- [x] Feature test asserts the read summary returns data and exposes no mutation, following prior art `GetStudentFeeSummaryQueryTest`.

## Implementation notes

- Read contract: `App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader` (interface) implemented by `App\Modules\Finance\Support\HubStudentFinanceSummaryReader`, bound in `FinanceServiceProvider`. Mirrors the `AiFinanceStudentProfileReader` prior art — the cross-module join executes Finance-side; the Academic Hub depends only on the interface.
- Hub: `StudentAcademicSummaryController::finance()` + `students.academic-summary.finance` (GET, `can:view_student_summary`); `Finance.vue` renders the read-only summary and deep-links to `finance.students.overview`. The single read-only **Finance** tab replaces the former finance-owning **Fees** and **Gold** tabs in `hub-tabs.ts`.
- Tests: `HubStudentFinanceSummaryReaderTest` (contract seam) and `StudentHubFinanceTabTest` (renders summary + deep link, forbids without permission, POST→405 proves no mutation).
- Follow-up (issue (g), nav consolidation): the old `fees`/`gold` controller methods, routes, and Vue (`Fee.vue`/`Gold.vue`) are now unlinked from the tab bar but remain reachable by direct URL; remove/redirect them there.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
