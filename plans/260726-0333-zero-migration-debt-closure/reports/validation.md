---
title: "Zero Migration Debt Plan Validation"
status: complete
last_verified: 2026-07-26
---

# Zero Migration Debt Plan Validation

## Decision log

| Question | Validated answer |
|---|---|
| What does zero mean? | All immutable 14 rule IDs are `exact: 0`, required roots remain scanned, and independent ownership/occurrence audits also pass. |
| Can ownership exclusions manufacture zero? | No. The ownership map is frozen; additions require accepted owner evidence and non-owner runtime tests. |
| Does zero require deleting every old table? | No. Canonical physical tables may remain. Only proven superseded schema is cleanup-eligible. |
| Can a response helper hide HTTP debt? | No internal compatible-helper use remains; provider protocols use closed named response classes. |
| Can command renaming hide migration debt? | No. Explicit lifecycle metadata separates one-time tools from permanent owned operations. |
| What is the unit of implementation? | One owner/workflow work package per bounded PR with an exact file and debt-delta ledger. |
| When are production data writes allowed? | Only after fresh off-workspace backup restore, exact rehearsal, live preflight, and named campus approval. |
| When can schema be dropped? | A later approval after backfill, zero readers/writers/jobs/config/provider/portal consumers, row-level reconciliation, and recovery rehearsal. |
| How are forward-only failures handled? | Stop, keep the new schema, and deploy a prebuilt compatible recovery/forward-fix artifact; restore is last resort with queue/callback reconciliation. |
| Which campus deploys first? | The signed post-rehearsal risk dossier chooses; approvals and soak remain independent. |
| Are portal releases part of one backend SHA? | No. One immutable manifest records Swinx, student portal, and lecturer portal SHAs/artifact hashes. |
| What closes the plan? | Both campuses pass rollout/soak, one-time tools are retired, all 14 rules and independent audits are zero, and current-state docs/issues close. |

## Structural validation

- Twelve phase files exist and every phase contains requirements, file inventory,
  interface checklist, dependency map, implementation steps, test scenario matrix,
  success criteria, and risk/security notes.
- The phase graph is acyclic. Package-level dependencies may be narrower but cannot
  bypass their phase's hard safety gates.
- Every current debt category has a terminal owner; zero-tolerance categories remain zero.
- Red-team P0/P1 corrections are recorded in `reports/red-team.md`.

## Execution validation

Before each phase begins, its broad inventory is hydrated into
`work-package-template.md` rows with exact files, named tests/commands, and numeric
before/delta/after counts. A phase is not executable until those rows are frozen.
This preserves strategic readability while preventing mega-PR execution.

No unresolved planning decision authorizes production mutation. Campus order,
thresholds, operator identity, maintenance windows, and approval names are
intentionally release-dossier fields because they depend on fresh production evidence.
