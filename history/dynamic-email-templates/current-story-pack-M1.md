# Current Story Pack: M1 — ListDeliveriesQuery — RETIRED 2026-05-19

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 2
**Status:** ⛔ **RETIRED — already exists from prior P3-paused session.**

## Retirement memo

Validating pass (2026-05-19) discovered `app/Modules/Notification/Queries/ListDeliveriesQuery.php` already exists. Full implementation:
- `handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator`
- Filters: `search` (event_id, title), `status`, `channel`, `date_from`, `date_to`, `has_error`, `sort`, `direction`, `per_page`, `page`
- Eager-loads `message:id,event_id,type_key,title,recipient_user_id,campus_id` + `message.recipient:id,name,email`
- Campus-scoped via `whereHas('message', fn $q => $q->where('campus_id', $id))`
- Sortable columns: `created_at`, `channel`, `status`, `attempts`, `queued_at`, `sent_at`

This satisfies M1's stated outcome. No new work needed.

## Downstream impact

- **M2** (controller re-point) now reads directly: re-point `EmailLogController::index` → `ListDeliveriesQuery::handle()`. Open question for M2 planning: does the existing `EmailHistoryModal.vue` prop shape match `NotificationDelivery` + eager-loaded relations, or does M2 need a small DTO adapter? Decide in M2 pack.
- **Critical-path edge** `S2 → M1 → M2` collapses to `S2 → M2` (M2 now has no predecessor inside the M-chain).

## Action items

- [ ] Update `epic-map-p3.md` Story Queue: remove M1 row, update M2's `Depends On` from `M1, R3` to just `R3` (and note "uses existing ListDeliveriesQuery from prior session").
- [ ] Original pack body below is preserved for audit but is no longer the work spec.

---

# Original pack body (audit trail only)

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 2
**Mode:** high-risk (parent) · **Story risk:** LOW (read-only query construction)
**Depends on:** S2 ✅ — caller graph confirms no surprises on Notification module reads.

## Entry State

- `/systems/email-history` UI page exists, served by `app/Http/Controllers/EmailLogController.php` reading `email_logs`.
- `resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue` consumes the existing prop shape.
- `notification_deliveries`, `notification_messages`, `notification_email_templates` tables live; outbox flow populates them per P2.
- Existing Query convention: `app/Modules/Notification/Queries/*Query.php` with `handle(...)` method (verify pattern).

## Exit State

- New `app/Modules/Notification/Queries/ListDeliveriesQuery.php` accepting filters (date range, status, recipient, campus_id) and returning an Inertia-prop-equivalent DTO/collection matching the existing `EmailHistoryModal.vue` prop shape.
- Unit test covering: empty result, single result, paginated result, all four filters applied, status-filter values cover all enum states.
- `./scripts/dev.sh test tests/Unit/Notification/Queries/ListDeliveriesQueryTest.php` green.
- No controller wired yet (M2 does that).

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Notification/Queries/ListDeliveriesQuery.php` | create |
| `tests/Unit/Notification/Queries/ListDeliveriesQueryTest.php` | create |

Total: 2 new files. Well under 10-file pack limit.

## Verification Commands

```bash
# Confirm Query convention precedent
ls app/Modules/Notification/Queries/

# Confirm prop shape consumed by Vue
grep -n "props\|defineProps" resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue

# Run new test
./scripts/dev.sh test tests/Unit/Notification/Queries/ListDeliveriesQueryTest.php
./scripts/dev.sh artisan pint --test app/Modules/Notification/Queries/
```

## DAG Row

`[S2] → [M1] → [M2 (controller re-point)]` — M1 produces the Query; M2 wires it.

## Critical Patterns Applied

- **Pattern #1 (unsafe-char fixtures):** test must use fixtures containing `<`, `>`, `"`, `&`, `'` in recipient/subject fields (rendered output not yet rendered in M1, but search/filter on recipient must not break).
- **Pattern #7 (≥2-row fixtures):** pagination + multi-recipient delivery test uses ≥2 rows.
- **Pattern #8:** DAG row above.
- **Pattern #11 (schema-precedent grep):** validating must cite Query convention with file:line.

## Feasibility Notes

- No schema change. No write path.
- Risk: column-name mismatch between `notification_deliveries`/`notification_messages` and the legacy `email_logs` prop shape. Validating must compare schemas before M2 commits.

## Handoff to Validating

Validating gates:
1. Cite the existing Query convention with file:line (Critical Pattern #11 — schema-precedent grep).
2. Confirm the prop shape map: each legacy field in `EmailHistoryModal.vue` → matching `notification_deliveries`/`notification_messages` column. If any field has no equivalent, surface BEFORE M1 ships.
3. Confirm pagination convention (Inertia `WhenVisible` vs paginator vs cursor) used in sibling Queries.
