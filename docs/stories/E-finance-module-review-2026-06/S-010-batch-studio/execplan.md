# Exec Plan

## Goal

Implement and accept Milestone 4 from
`docs/superpowers/plans/2026-06-15-finance-office-batch-studio.md` as one
consolidated Harness story.

Deliver Batch Studio: a shared 4-step wizard for charge generation, DNG push, and
payment reminders, with a preview-token drift-safety contract that reuses all
existing Finance write Actions and Preview Queries without new money math.

## Scope

In scope:

- Shared preview-token safety core (`BatchPreviewLineHasher`,
  `BatchPreviewLine`, `BatchJobType`, `BatchPreviewTokenService`).
- `view_finance_batch_studio` thin view permission + seeder sync.
- Three JSON preview endpoints + three Inertia commit endpoints with per-action
  `can:` gates.
- Charge, DNG, and reminder assemblers delegating to existing `Preview*Query`
  classes.
- Frontend wizard shell, composable, diff table, result panel, and three job
  pages.
- Sidebar nav entry under "Sinh phí".
- Full Batch backend test suite + `finance:audit-invariants` acceptance gate.
- Browser smoke for all three jobs including drift-block scenario.

Out of scope:

- Milestones 1–3 shell/Student 360/Cockpit work (already merged or separate).
- New money calculations, ledger tables, or campaign persistence.
- Async/queued bulk execution (deferred follow-up).
- Moving non-academic CSV upload into the wizard (v1 keeps legacy page).
- Student/lecturer portal API changes.

## Risk Classification

Risk flags:

- Authorization and per-action permission matrix.
- Finance bulk write orchestration (charges, DNG, reminders).
- Drift-safety correctness (preview-token recompute-compare).
- External email delivery for reminders.
- Existing behavior around DNG rerun cancel + linked charge void.
- Multi-domain touch (preview queries, write actions, cache, Inertia + JSON API).
- Weak browser automation coverage for wizard UI.

Hard gates:

- Preview-token recompute-compare blocks every commit; no stale-snapshot writes.
- Per-action permissions only; no umbrella batch permission.
- DNG commit requires `void_finance_charges` when rerun can cancel linked
  charges.
- Write paths must call existing tested Actions — no new money math.
- `finance:audit-invariants` must show zero new CRITICAL breaks after write-path
  exercise.

## Work Phases

1. **Test foundation** — `grantFinance()` helper (Task 0).
2. **Permission + safety core** — view permission, hasher, value objects, token
   service (Tasks 1–4).
3. **Routes + hub shells** — route constants, controller hub, authz tests
   (Task 5).
4. **Backend jobs** — charge preview/commit, DNG preview/commit, reminders
   preview/commit (Tasks 6–9).
5. **Frontend core** — types, `useBatchStudio`, `BatchWizard`, shared panels
   (Tasks 10–12).
6. **Frontend jobs** — charge reference wizard, DNG + reminders wizards
   (Tasks 13–14).
7. **Acceptance** — sidebar mount, full test suite, invariant before/after,
   browser smoke, evidence recorded (Task 15).

## Stop Conditions

Pause for human confirmation if:

- A step requires new money math not present in existing Actions/Queries.
- Preview-query row keys are missing and cannot be extended read-only.
- EGC-only permission split is needed and role mapping is unclear.
- Drift-block or token consume semantics cannot be made one-time + per-line.
- `finance:audit-invariants` shows new CRITICAL breaks after write exercise.
- A portal API contract would be affected.

## Implementation Source

Execute task-by-task from
`docs/superpowers/plans/2026-06-15-finance-office-batch-studio.md` (Tasks
0–15). Use `./scripts/dev.sh` wrappers for all commands.

Recommended execution mode: subagent-driven development (one subagent per task
with review between tasks).