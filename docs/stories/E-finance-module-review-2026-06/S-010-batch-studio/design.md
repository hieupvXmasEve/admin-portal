# Design

## Domain Model

Batch Studio orchestrates existing Finance bulk write Actions behind a shared
preview-token safety contract. New domain objects:

- **`BatchJobType`** — enum: `charge_generation`, `dng_push`, `reminder`.
- **`BatchPreviewLine`** — canonical preview row: `key`, `hashPayload`, `display`.
- **`BatchPreviewLineHasher`** — pure per-line SHA-256 over resolved payload +
  fee-config fingerprint (`config_id` + `updated_at`; no `version` column on
  tuition plans).
- **`BatchPreviewTokenService`** — cache-backed `issue` / `verify` / `consume`
  (one-time, 30-min TTL, per-line hashes scoped to user + job type).

Business rules (non-negotiable):

- Preview numbers must equal committed numbers — server re-resolves and
  recompute-compares; never commit from a stale client snapshot.
- **Per-action permissions, no umbrella batch permission.** Hub uses thin
  `view_finance_batch_studio`; each commit route gates its action permission.
- **DNG rerun cancels old DNG** and may void linked charges → commit route also
  requires `void_finance_charges` when applicable.
- **Override ≥1 VND from installment-aware total** drops installment linkage
  (ad-hoc); UI surfaces as danger.
- **Reminders:** `last_reminder_at` guards double-send; caller/UI must exclude
  or force re-preview.
- **Atomicity is per-job:** charge gen = single-transaction envelope; DNG and
  reminders = per-recipient partial success with retry-failed-subset.

## Application Flow

Shared 4-step wizard per job:

1. **Thiết lập** — semester (default from `page.props.semester.selected_id`),
   fee type / scope / filters / overrides / recipient mode.
2. **Xem trước** — `useApi` POST to JSON preview endpoint → canonical lines +
   diff buckets (🟢/🔵/⚪/🟠) + preview token issued.
3. **Xác nhận** — operator confirms subset; big-batch acknowledgement; per-job
   rerun reassurance banners.
4. **Kết quả** — `useForm` Inertia commit → existing `Action::run()` →
   `Inertia::flash` + `BatchResultPanel` (summary, report, Student 360 links,
   retry failed subset for DNG/reminders).

Read-then-write handshake:

```
Preview:  Preview*Query → Assembler → canonical lines → TokenService.issue()
Commit:   TokenService.verify+consume → re-resolve subset → recomputeOrFail()
          → existing Write Action → result summary
```

Reused write Actions (no changes to money math):

- `GenerateBatchChargesAction`, `GenerateMajorChargesAction`,
  `GenerateEgcChargesAction`
- `CreateBatchDngFromChargesAction`
- `SendPaymentRemindersAction`, `SendDueItemRemindersAction` (+ parent variants)

## Interface Contract

### Web (Inertia)

| Route name | Method | Permission | Handler |
| --- | --- | --- | --- |
| `finance.batch-studio.hub` | GET | `view_finance_batch_studio` | Hub landing + job tile flags |
| `finance.batch-studio.charges` | GET | `view_finance_batch_studio` | Charge wizard shell |
| `finance.batch-studio.charges.commit` | POST | `create_finance_charges` (+ EGC check) | `commitCharges` |
| `finance.batch-studio.dng` | GET | `view_finance_batch_studio` | DNG wizard shell |
| `finance.batch-studio.dng.commit` | POST | `create_finance_payments` + `void_finance_charges` | `commitDng` |
| `finance.batch-studio.reminders` | GET | `view_finance_batch_studio` | Reminders wizard shell |
| `finance.batch-studio.reminders.commit` | POST | due-calendar ops permission | `commitReminders` |

### API (JSON preview reads)

| Route name | Method | Permission | Handler |
| --- | --- | --- | --- |
| `finance.batch-studio.charges.preview` | POST | `create_finance_charges` | `previewCharges` |
| `finance.batch-studio.dng.preview` | POST | `create_finance_payments` | `previewDng` |
| `finance.batch-studio.reminders.preview` | POST | due-calendar ops permission | `previewReminders` |

Preview responses: `ApiResponse::success()` with lines, buckets, token, summary.
Commit requests: `preview_token`, `selected_keys[]`, job-specific params.
Drift on commit: 422 validation error forcing re-preview.

Frontend contracts (Inertia v3):

- Preview: `useApi` / `useApiRequest` (never `axios`).
- Commit: `useForm` from `@inertiajs/vue3`.
- Flash: `Inertia::flash()` read via `usePage().props.flash`.
- Routes: `financeRoutes.batchStudio.*` (never literal paths).
- Props: snake_case in TS interfaces.

## Data Model

No new database tables. Preview tokens live in cache only (30-min TTL, one-time
consume). Permission addition: `view_finance_batch_studio` in
`config/permission.php` + seeder.

## UI / Platform Impact

New pages under `resources/js/pages/Finance/BatchStudio/`:

- `Hub.vue` — job tiles gated by per-action permissions.
- `ChargeGeneration.vue`, `DngPush.vue`, `Reminders.vue` — wizard hosts.

Shared components:

- `BatchWizard.vue` — stepper + sticky summary bar.
- `PreviewDiffTable.vue` — diff buckets, filters, search, Excel export.
- `BatchResultPanel.vue` — per-job result models + retry subset.

Sidebar: "Batch Studio" under Finance Office → "Sinh phí" group.

## Observability

- Pest feature tests for preview, commit, drift-block, authz, and per-job result
  shapes.
- `finance:audit-invariants` before/after exercising real write paths — **zero
  new CRITICAL** breaks is the milestone acceptance gate.
- Student 360 deep links from result errors via `GetFinanceAuditGraphQuery`.

## Alternatives Considered

1. **Client-echo commit (rejected):** trusting client-submitted amounts would
   allow stale preview commits; server re-resolve + per-line hash compare is
   required.
2. **Umbrella batch permission (rejected):** design §8 requires per-action gates
   so operators cannot run jobs they are not authorized for.
3. **New money math in assemblers (rejected):** all amounts come from existing
   `Preview*Query` classes and write Actions.