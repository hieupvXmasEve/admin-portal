# Current Story Pack: M2 — EmailLogController re-point to ListDeliveriesQuery

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 3
**Mode:** high-risk (parent) · **Story risk:** MEDIUM (user-visible page)
**Depends on:** M1-retired (existing `ListDeliveriesQuery`). R3 dependency REMOVED — R-epic parked on operator S1; M2 ships without it via feature-flag rollback.

## Entry State

- `app/Http/Controllers/EmailLogController.php` serves `/systems/email-history` via Inertia — currently reads from `email_logs`.
- `resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue` consumes existing prop shape.
- `app/Modules/Notification/Queries/ListDeliveriesQuery.php` exists, returns `LengthAwarePaginator` of `NotificationDelivery` with `message:id,event_id,type_key,title,recipient_user_id,campus_id` + `message.recipient:id,name,email` eager-loaded.

## Exit State

- `EmailLogController::index` reads via `ListDeliveriesQuery::handle($filters, $campusId)`.
- Optional small DTO adapter if Vue prop shape diverges from raw `NotificationDelivery` JSON.
- Feature flag `email_history.use_outbox` (config or env) gates the switch — old code path stays as fallback for 1-deploy rollback window.
- Vue prop shape unchanged from user perspective.
- Pre-cutoff rows visible but Retry button hidden (D4) — Retry button itself ships in R-epic; for M2 the Retry button stays disabled overall until R2 lands.
- `./scripts/dev.sh test tests/Feature/SystemsEmailHistoryTest.php` (or equivalent) green; Playwright smoke green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Http/Controllers/EmailLogController.php` | edit (re-point to `ListDeliveriesQuery`, gate behind flag) |
| `config/email_history.php` or `config/notifications.php` | edit (add `use_outbox` flag default false) |
| `resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue` | edit (prop-shape adapter if needed; Retry button stays disabled per R-epic park) |
| `tests/Feature/Admin/EmailHistoryControllerTest.php` (or current location) | edit/create |

Total: 4 files. Well under limit.

## DAG Row

`[ListDeliveriesQuery (M1-retired exists)] → [M2]`; M2 ships independently of R-epic.

## Critical Patterns Applied

- **#5 useApi ↔ useForm** — Vue page uses `useDataTable` per project convention (Inertia filters); confirm before swapping.
- **#7 ≥2-row fixtures** — test uses multiple deliveries to confirm pagination.
- **#8 pack-as-bead** — DAG row above.

## Feasibility Notes

- User-visible page → Playwright smoke required.
- Risk: prop-shape mismatch between `NotificationDelivery` eager-load and `EmailHistoryModal.vue` expectations. Validating must diff the shapes before workers commit.
- Risk: existing `EmailLogController` may have features (CSV export, filter UI) that need parity in the new path.

## Handoff to Validating

Validating gates:
1. Diff: `email_logs` columns consumed by Vue vs `NotificationDelivery` + `message` + `recipient` eager-load output.
2. Confirm campus-scoping precedent — `app('campus')->id` resolution (per Critical Pattern #13 — campus scope NEVER nulls).
3. Confirm feature-flag convention used elsewhere in the project (config-based or env-based).
4. Identify Playwright test file location; reuse existing fixture pattern.
