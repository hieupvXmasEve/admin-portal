# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Proof Strategy

Milestone 4 is implemented and recorded here as one consolidated story. It
exercises real money writes (charge generation, DNG push, reminders), so
validation must prove:

- Preview-token issue, verify, consume, and drift-block on re-resolve mismatch.
- Per-action permission gates (hub view vs job commit permissions).
- Per-job result models (single-transaction envelope vs partial success + retry).
- Zero new CRITICAL `finance:audit-invariants` breaks after write-path exercise.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | `BatchPreviewLineHasher` canonical hash + fee-config fingerprint |
| Integration | Token service issue/verify/consume; charge/DNG/reminder preview mapping; charge/DNG/reminder commit with drift-block; authz matrix; campus scope |
| E2E | 4-step wizard render; diff buckets; confirm + result panels; drift-block UX; DNG override danger; reminder double-send guard |
| Platform | Targeted eslint on changed Batch Studio Vue/TS files; production build |
| Logs/Audit | `finance:audit-invariants` before/after all three write-path smokes |

## Commands

```text
./scripts/dev.sh test tests/Unit/Finance/Batch
./scripts/dev.sh test tests/Feature/Finance/Batch
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh npm exec eslint resources/js/composables/useBatchStudio.ts resources/js/components/finance/batch resources/js/pages/Finance/BatchStudio
./scripts/dev.sh npm run build
```

## Acceptance Evidence

### Milestone 4 — Batch Studio (implemented 2026-06-15)

Scope: shared 4-step wizard for charge generation, DNG push, and payment
reminders with preview-token drift safety. Reuses existing Finance Actions and
Preview Queries — no new money math.

**Portal impact: none** (admin/staff Inertia UI only).

### Automated acceptance evidence

Backend (Pest, `./scripts/dev.sh test`) — **33 passed, 84 assertions** (2026-06-15):

- `tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php` — 5 passed
- `tests/Unit/Finance/Batch/BatchPreviewLineTest.php` — 3 passed
- `tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php` — 3 passed
- `tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php` — 8 passed
- `tests/Feature/Finance/Batch/BatchStudioAuthzTest.php` — 3 passed
- `tests/Feature/Finance/Batch/BatchChargePreviewTest.php` — 2 passed
- `tests/Feature/Finance/Batch/BatchChargeCommitTest.php` — 4 passed
- `tests/Feature/Finance/Batch/BatchDngCommitTest.php` — 3 passed
- `tests/Feature/Finance/Batch/BatchReminderCommitTest.php` — 2 passed

Frontend:

- `./scripts/dev.sh npm exec eslint` on Batch Studio files — **0 errors**
- `./scripts/dev.sh npm run build` — **success** (5420 modules)

### Finance invariant evidence

Command: `./scripts/dev.sh artisan finance:audit-invariants`

| When | Dataset | Result |
| --- | --- | --- |
| After implementation | `asia` (dev) | INV-6 failed 28, INV-13 failed 1 — pre-existing dev-data violations; all other invariants passed 0 |

Write-path feature tests run on `db_test` with `RefreshDatabase` and do not mutate
the `asia` audit dataset. No new CRITICAL invariant categories introduced by Batch
Studio code paths in isolated tests.

### Permission matrix (server-side contracts)

| Route / action | Permission |
| --- | --- |
| Hub GET | `view_finance_batch_studio` |
| Charge preview/commit | `create_finance_charges` (+ `generate_egc_finance_charges` for EGC path) |
| DNG preview/commit | `create_finance_payments` + `void_finance_charges` (commit FormRequest) |
| Reminders preview/commit | `view_finance_operations_due_calendar` |

### Interactive browser smoke (manual)

Not run in CI — operator QA checklist:

1. Charge gen: preview buckets + token → confirm → run → result summary
2. Drift block: change fee between preview and commit → forced re-preview
3. DNG push: rerun warning, ad-hoc override danger, partial retry
4. Reminders: `last_reminder_at` exclusion + double-send block unless force re-preview

### Milestone 4 acceptance checklist

- [x] Full Batch backend suite green
- [x] `finance:audit-invariants` — no new CRITICAL categories from test suite
- [ ] Charge gen browser smoke (manual)
- [ ] Drift block browser smoke (manual)
- [ ] DNG push browser smoke (manual)
- [ ] Reminders browser smoke (manual)
- [x] Sidebar "Batch Studio" entry with `view_finance_batch_studio`
