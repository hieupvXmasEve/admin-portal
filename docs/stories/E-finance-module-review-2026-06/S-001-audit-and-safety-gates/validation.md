# Validation

## Proof Strategy

Prove this story by showing the audit baseline exists and that immediate safety
changes do not change money math or provider protocol.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Formatting/helpers touched by console-log removal, if any |
| Integration | Existing finance operation actions still authorize and validate |
| E2E | Browser smoke for touched Finance operation pages |
| Platform | Docker wrapper commands only |
| Performance | Not required beyond proving audit command completes |
| Logs/Audit | Dangerous actions still write existing audit records |

## Fixtures

- A snapshot with representative charges, invoices, payments, DNG requests, and
  known INV-6/INV-11 failures if available.

## Commands

```text
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh test --filter=Finance
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
./scripts/dev.sh artisan pint
git diff --check
```

## Acceptance Evidence

Run date: 2026-06-14. Implementation slice scope: invariant baseline + independent UI
safety gates (no schema, no ledger math, no DNG protocol changes).

### Invariant baseline

`./scripts/dev.sh artisan finance:audit-invariants --sample` → 31 offending
(INV-6 = 28 duplicate invoice groups, INV-11 = 3 duplicate webhook payload hashes;
all other invariants 0). Full output + failure-sample ids archived in
`evidence/audit-invariants-2026-06-14.md`. Matches review §D1 (2026-06-13).

### Safety changes shipped

| Finding | Change | File |
| --- | --- | --- |
| UI-SAFE-1 | Void-charge dialog now shows financial impact (amount, paid, allocations to reverse, paid installments) before confirm | `Charges/Show.vue` |
| UI-SAFE-2 | Temporary raw-`charge_id` manual allocation form removed; replaced with safe blocked state + guidance (full selector deferred to S-007) | `Payments/Show.vue` |
| UI-SAFE-4 | Confirm guards added to settlement apply, fix-exception, due-reminder sends, auto-allocate dialog | `Settlement.vue`, `ExceptionsQueue.vue`, `DueCalendar.vue`, `AutoAllocateDialog.vue` |
| UI-STD-3 / UI-SAFE-4 | DNG webhook retry switched from `window.confirm` to Pinia confirm store | `DngWebhookEvents/Show.vue` |
| UI-STD-6 | `Settlement.vue` runtime fix: imported missing `toast` (ReferenceError on 0-student selection); added explicit `AppLayout` mount | `Settlement.vue` |
| UI-STD-5 | Removed Finance `console.log`/`console.error` | `utils/format.ts`, `ExceptionsQueue.vue`, `DueCalendar.vue`, `AutoAllocate.vue`, `AutoAllocateDialog.vue` |

Note: AppLayout is auto-applied to all pages by `app.ts` resolve(); the `Settlement.vue`
runtime crash was the missing `toast` import — the layout add is belt-and-suspenders
consistency with sibling Operations pages.

### Command results

| Command | Result |
| --- | --- |
| `finance:audit-invariants --sample` | ✅ ran read-only; baseline captured |
| `vendor/bin/pint --test` (audit command) | ✅ PASS (1 file) |
| `eslint` (9 touched files) | ✅ clean |
| `prettier --check` (9 touched files) | ✅ conformant |
| `test --filter=Finance` | 272 passed / 26 failed — failures are pre-existing `StudentFinanceDngAccessTest` 401-auth cases on `/api/v1/student/*` (student-portal API); **out of this story's scope** (zero PHP/route/API changes here) |
| `git diff --check` | ✅ no whitespace errors |
| `vue-tsc --noEmit` (full project) | ⚠️ could not complete — container OOM-kills full-project type-check (pre-existing env limit, not a type error). Touched files type-safe by inspection + clean under typescript-eslint |

### Scope notes / not done (intentional)

- No DB constraints (INV-6/INV-11 dirty data must be reconciled first — S-002/S-003).
- No `SettlementService` / ledger math changes.
- No DNG checksum/dedup/protocol changes.
- EGC hardcode/scope bug (UI-SAFE-3, FIN-06) NOT touched — gated on the open EGC
  fee-source business decision (review §6 Q1) and outside this execplan's work phases.

### Unresolved questions

- Snapshot provenance: local snapshot used (owner: local mirrors prod). Source/timestamp/
  restore command still unrecorded — must be captured before this is promoted to formal
  prod evidence.
- The 26 failing `StudentFinanceDngAccessTest` cases predate this story; confirm they are
  tracked elsewhere (student-portal auth), not regressions.
