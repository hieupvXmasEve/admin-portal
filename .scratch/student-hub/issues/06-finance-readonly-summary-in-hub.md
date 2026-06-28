# Hub Finance tab: read-only summary + deep link to Finance Office

Status: ready-for-agent

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

- [ ] The Finance tab shows a read-only fees + gold + scholarships summary.
- [ ] The summary comes from a Finance read contract (no cross-module Eloquent joins).
- [ ] A deep link opens the corresponding Finance Office surface.
- [ ] No mutation is possible from the Hub Finance tab.
- [ ] Feature test asserts the read summary returns data and exposes no mutation, following prior art `GetStudentFeeSummaryQueryTest`.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
