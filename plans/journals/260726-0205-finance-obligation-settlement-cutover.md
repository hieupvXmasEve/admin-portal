---
title: "Finance Obligation and Settlement Cutover"
date: 2026-07-26
type: journal
---

# Journal: 2026-07-26 — Finance Obligation and Settlement Cutover

## Context

Issue 13 continued the repository migration as a code-only cutover. The goal was
to move Finance consumers to canonical obligation and settlement boundaries
before human review and before reconciling production data differences.

## What Happened

- Finance consumers now use canonical obligations, settlement positions, and
  owner-provided contracts for Academic, Student Registry, Institution, and
  Progression facts.
- Billing exception collection processes bounded chunks and limits retake
  lookups to source references in each chunk.
- Scholarship roster exports stream in batches, while Fee Monitor preserves
  institution-wide behavior when campus context is absent.
- Student Finance presentation includes applied credit in charge progress.
- No schema, data, or backfill changes were made.

## Implementation Decisions

| Decision | Rationale | Impact |
| --- | --- | --- |
| Treat canonical obligations and settlements as the Finance source of truth | Remove runtime dependence on legacy source pointers and cross-context models | Finance decisions now cross module boundaries through contracts |
| Keep Billing and export work bounded | Protect production query and memory behavior | Pagination, retake resolution, and exports remain scalable |
| Preserve null-campus Fee Monitor semantics | Existing users require institution-wide reporting | No silent empty result when campus context is absent |
| Defer reconciliation | Code structure must be reviewed before changing business data | INV-13 `#1067` and INV-18 `#1443` remain separate follow-up work |

## Verification

- Targeted Finance, architecture, reporting, DNG, settlement, and portal checks
  passed.
- Student portal lint, typecheck, and production build passed.
- Independent testing and final code review reported no remaining findings.
- Migration-debt inventory remains above its approved baseline:
  `shared_model_imports 587/568`.

## Reflection and Decisions

The cutover reduced architectural coupling without mixing implementation work
with uncertain data repair. Performance regressions found during review were
resolved before approval. The remaining inventory overage is repository-wide
migration debt, not a reason to weaken the baseline or begin reconciliation
inside this issue.

## Next

- Continue migrating the remaining direct shared-model consumers.
- Revisit INV-13 `#1067` and INV-18 `#1443` only during the dedicated data
  comparison and reconciliation phase.
