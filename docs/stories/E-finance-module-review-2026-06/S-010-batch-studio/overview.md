# Overview

## Current Behavior

Finance bulk operations are spread across separate technical pages:

- Non-academic charge generation: `/finance/operations/generate-charges`
- Major tuition generation: `/finance/major/charges`
- EGC charge generation: `/finance/egc/charges`
- DNG batch push: `/finance/operations/dng-worklist`
- Payment reminders: existing API routes on `BillingOperationsController`

Each page has its own form flow. Staff must know which surface to open for each
bulk job. There is no shared preview-to-commit safety contract across jobs, and
no unified wizard that makes preview numbers provably equal to committed numbers.

## Target Behavior

Build **Batch Studio** — one shared 4-step wizard
(`① Thiết lập → ② Xem trước → ③ Xác nhận → ④ Kết quả`) for every bulk finance
job:

- Charge generation (sinh phí)
- DNG push (đẩy DNG)
- Payment reminders (nhắc nợ)

The wizard reuses 100% of existing money logic (generation Actions, DNG batch
Action, reminder Actions, Preview Queries, and shared resolvers). It adds **no
new money math**.

Safety contract:

- Step ② calls a JSON preview endpoint that issues a one-time preview token
  (cache, 30-min TTL) holding per-line SHA-256 hashes of resolved payloads.
- Step ④ is an Inertia write that sends the token plus the confirmed line keys;
  the server re-resolves that subset and recompute-compares per line. Any drift
  blocks the commit.

This packet is the canonical Milestone 4 story for
`docs/superpowers/plans/2026-06-15-finance-office-batch-studio.md`.

## Plan Task Mapping

| Plan task | Included in this story |
| --- | --- |
| Task 0: `grantFinance()` test helper | Shared Batch suite auth bootstrap |
| Task 1: `view_finance_batch_studio` permission | Thin hub view gate + seeder |
| Task 2: `BatchPreviewLineHasher` | Canonical per-line SHA-256 + fee-config fingerprint |
| Task 3: `BatchPreviewLine` + `BatchJobType` | Value object + job enum |
| Task 4: `BatchPreviewTokenService` | Cache-backed issue / verify / consume |
| Task 5: Routes + hub + wizard shells | Route constants, `BatchStudioController` hub, authz tests |
| Task 6: Charge preview | Assembler + JSON preview endpoint (issues token) |
| Task 7: Charge commit | Token verify + existing generation Actions |
| Task 8: DNG preview + commit | Chunk ≤100, override→ad-hoc, rerun-cancels-old, void gate |
| Task 9: Reminders preview + commit | `last_reminder_at` double-send guard |
| Task 10: `useBatchStudio` composable | Wizard state machine + token round-trip |
| Task 11: `BatchWizard` + `Hub` | Shared stepper shell + landing tiles |
| Task 12: `PreviewDiffTable` + `BatchResultPanel` | Diff buckets + result summary + retry subset |
| Task 13: Charge-generation wizard | Reference job implementation |
| Task 14: DNG + reminders wizards | Job-specific wizard hosts |
| Task 15: Nav + milestone evidence | Sidebar entry + invariant acceptance gate |

## Affected Users

- Finance staff who run bulk charge generation, DNG pushes, or payment reminders.
- Finance leads/admins who need drift-safe bulk operations with audit-friendly
  result summaries.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md` (§6 Batch Studio,
  §7.1 pattern 3, §8 contracts, §10 milestone 4)
- `docs/superpowers/plans/2026-06-15-finance-office-batch-studio.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`
- `docs/stories/E-finance-module-review-2026-06/S-010-student-360-full/`

## Portal Impact

Portal impact: none.

This story targets admin/staff Inertia UI and web/API routes under the Finance
module. It does not change `/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- Do not add new money math, resolvers, or ledger tables.
- Do not replace existing source pages (CSV upload page, legacy generation
  pages remain for deep-dive and repair).
- Do not introduce an umbrella "batch" permission — each job stays gated by its
  existing action permission.
- Do not implement async/queued bulk execution in v1 (synchronous commit only).
- Do not build the Cockpit (Milestone 3); Batch Studio may receive hand-offs
  from Cockpit later but does not depend on Cockpit being complete to ship its
  own hub and wizards.

## Unresolved Questions (confirm at implementation)

- **EGC-only operators:** confirm whether a role exists with only
  `generate_egc_finance_charges` (not `create_finance_charges`); split routes if
  needed.
- **Non-academic CSV scope:** v1 keeps CSV on the legacy page; confirm whether
  CSV should move into wizard step ①.
- **`useApi`/`useApiRequest` exact signature:** confirm against
  `docs/rules/api-interaction.md` before wiring the composable.
- **Preview-query row keys:** confirm which keys already exist on `Preview*Query`
  rows before extending assemblers.
- **Large-batch async:** confirm volume thresholds for a follow-up queued story.