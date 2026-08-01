# Phase 3a — Redemption (BACKEND + API contract) — Completion Report

Date: 2026-08-01
Plan: [260801-0203-merchandise-store](../260801-0203-merchandise-store/index.md) · Phase [3](../260801-0203-merchandise-store/phase-3-redemption.md)
Branch: `claude/merchandise-store-review-840d9b`
Status: **3a DONE** (56 pass + 1 pre-existing env fail; money/concurrency core reviewed sound). **3b (Nuxt portal) NOT started — repo `FE/student-nuxt` not present.**
Portal impact: student.

## Delivered

**Migrations** (varchar allow-lists, integer Gold, idempotent):
- `redemption_orders` (student_id, campus_id snapshot [RT-13], code unique, status/previous_status varchar, method, total_gold INT, `idempotency_key` + UNIQUE(student_id, idempotency_key) [RT-8], pickup/shipping/reject/cancel columns).
- `redemption_order_items` (snapshot D7: merchandise_name, variant_label, gold_price_each, line_total, quantity).
- FK `stock_movements.redemption_order_id` → redemption_orders (idempotent add).
- **Double-refund DB guard (RT-7)**: `gold_transactions.redemption_dedup_key` STORED generated column (`CASE WHEN type IN (redemption, redemption_refund) THEN CONCAT(type,source_type,source_id) ELSE NULL`) + UNIQUE index. MariaDB treats NULL as distinct, so it's a partial-unique — a second `redemption_refund` for an order collides at the DB.

**RedemptionService** (`app/Modules/Merchandise/Support/`) — the money/concurrency core:
- `createOrder`: idempotency replay → generate `MRD-YYYYMM-######` code (FIN-13 random+retry, outside the money tx) → one `DB::transaction(…,3)`: lock wallet (global order #1), lock variants ascending by id (#2), validate active/campus/stock/balance, snapshot order+items, deduct Gold (`redemption`), deduct stock (`redemption_out`). Any failure rolls back all three.
- `refund` (symmetric): add Gold (`redemption_refund`) + restore stock, same wallet→variants(asc) lock order, inside the locked transition.
- All 11 transitions via one `transition()` helper: `lockForUpdate()->first()` then status-check **under the lock** (equivalent to conditional `UPDATE … WHERE status=` — a second concurrent transition blocks, then reads the new status and is rejected). State machine matches plan §.
- Global lock order (RT-15) identical in create + refund; multi-item carts lock variant ids ascending (dedup+ksort) → no A[7,3]/B[3,7] deadlock.

**Student API** (`student.api.auth` ONLY — parents 403, D13): store list (D2 availability), product detail, create order (FormRequest: per-line + per-student-per-product qty caps; Idempotency-Key; shipping_address required for shipping), my orders + detail + timeline, request/direct cancel. `throttle:merchandise-checkout` on create + cancel. Routes in a dedicated sub-group OUTSIDE the parent-accessible `either:` group.

**Staff API** (`['web','auth']`): queue + approve/reject/ready/extend/confirm-collected/mark-shipped/mark-overdue/cancel-overdue/handle-cancellation. Coarse `can:<verb>_redemption_order` gate + fine `RedemptionOrderPolicy` (campus from the order's snapshot campus_id — in-flight orders stay in the snapshot-campus queue, RT-13). New `redemption` permission group (6 verb_noun codes, config-driven seeder, super-admin default).

**Notification** (RT-4 seam, no cross-module import): `RedemptionNotificationPublisher` via `App\Shared\Contracts\DomainEvents\DomainEventPublisher` + `NotificationPayloadFactory`. 7 types wired (6 student + 1 staff pending_review) + `merchandise.order` deep-link in the URL registry. Fired afterCommit.

**Bug fix (RT-5, Phase 1 leftover)**: `GoldTransactionController::show` IDOR — `Auth::guard('student')->user()` was null for non-student-guard actors so the ownership check was skipped, returning any transaction by id. Now checks `$request->user() instanceof Student` + ownership (parent-proxy resolves to the target student, so legit access preserved). `GoldTransactionOwnershipTest` 4/4.

**Contract**: `docs/api/student/merchandise.md` (canonical, for 3b).

## Review (self, money/concurrency-focused)
Traced every lock path. **No defects found.** Verified: consistent wallet→variants(asc) ordering create+refund (no deadlock inversion; create never locks an order row); under-lock status recheck is a valid double-refund guard; partial-unique is a real second backstop; idempotency replay returns the existing order (pre-check + catch); FIN-13 code retry sits outside the money tx; snapshots immutable. Subagent's lock+recheck deviation from the mandated conditional-UPDATE is correctly reasoned and equivalent.

## Tests — 56 pass, 1 pre-existing env fail
`tests/Feature/Merchandise/Redemption/` (18) + `GoldTransactionOwnershipTest` (4) + Phase 1/2 regression. 163 assertions. Proven: atomic-create rollback (all 3 revert), symmetric refund, **double-refund blocked at BOTH the state guard AND the DB partial-unique**, idempotency replay (Gold deducted once), legal/illegal transitions, cross-campus staff 403, parent-token 403 on every student endpoint, snapshot immutability after reprice/rename/archive. The 1 failure is the known `MerchandiseAdminPageTest` Inertia-render env issue (Phase 2), unrelated.

## Deviations / accepted
- `markShipped` allowed from `approved` regardless of method (a pickup order could technically be marked shipped) — low risk, staff action, non-money. Not guarded by method.
- Staff "cancellation requested" notification deliberately NOT wired: order detail carries `shipping_address` PII; recipient scoping is a Notification-module/product decision (flagged, not faked).
- Staff transition endpoints use inline `$request->validate()` (small 1–2 field), matching existing controller style; no dedicated FormRequests.

## NOT done
- **Phase 3b — student portal** in `FE/student-nuxt` (separate git-ignored Nuxt repo, not checked out here). Codes against `docs/api/student/merchandise.md`. Cart is client-side only; reconcile stale price/stock at submit (backend re-checks under lock).

## Unresolved questions
1. Staff cancellation-request notification recipient scoping (PII) — Notification owner decision, then wire type #8.
2. Should `markShipped` be restricted to shipping-method orders?
3. `FE/student-nuxt` not present — check it out (per docs/portal-repos.md) before 3b.
4. Same pre-existing branch failures (MigrationDebtInventoryTest baseline, AcademicNotificationBoundaryArchTest, MerchandiseAdminPageTest env-render) still open.
