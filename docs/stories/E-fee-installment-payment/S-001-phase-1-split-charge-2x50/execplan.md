# Exec Plan — S-001

## Goal

Cho phép admin tách 1 `FinanceCharge` HP thành 2 đợt × 50% (action support N), tự động push đợt kế tiếp sang DNG sau khi đợt trước paid, với retry policy + UI admin + UI student. Backward-compatible cho mọi charge cũ qua backfill 1 installment.

## Scope

In scope (Phase 1):
- Migration `finance_charge_installments` + backfill.
- Model `FinanceChargeInstallment` + relation trên `FinanceCharge`.
- `SplitChargeIntoInstallmentsAction` + FormRequest + Policy method + permission key.
- Sửa `DngPaymentService::createAndPush()` nhận `installment_id` (optional).
- `PushNextInstallmentAction` + `PushNextInstallmentJob` (retry 3, exp backoff).
- Hook post-commit vào `ReconcileDngPaymentsJob`.
- 3 events: `InstallmentPushed`, `InstallmentPushFailed`, `ChargeFullySettled`.
- 4 email templates mới × 2 lang qua `EmailContentRegistry` (skeleton + placeholder copy; team Noti viết content thật trước prod).
- UI admin: tab "Đợt thu" trong `FinanceCharges/Show.vue` + modal split + retry button.
- UI student: bảng đợt trong portal.
- Unit + integration test bắt buộc; E2E nếu hạ tầng sẵn.

Out of scope:
- Sửa kế hoạch đợt nhiều lần (chỉ 1 lần, trước paid).
- Void charge sau khi có đợt paid (Phase 2).
- Apply cho fee_type ≠ HP (action ready, chỉ không enable UI).
- Auto remind trước due_date.
- Gộp nhiều charge khác type vào 1 DNG.
- Migration rollback path tự động (manual recovery, có guard).

## Risk Classification

Risk flags (từ intake #1):
- data-model (table mới + backfill)
- external-systems (DNG)
- existing-behavior (sửa `DngPaymentService` + `ReconcileDngPaymentsJob`)
- multi-domain (Finance ↔ Notification)
- audit-security (financial data, audit log requirement)
- authz (permission key mới + policy)

Hard gates:
- Data migration → cần dry-run + snapshot DB staging trước khi prod migrate.
- External provider behavior → cần fake HTTP client coverage cho cả 3 outcome modes.

## Work Phases

1. **Discovery (DONE)** — spec đọc, codebase scout, 4 open questions resolved.
2. **Design (DONE)** — `design.md` viết, alternatives reject reason ghi rõ.
3. **Validation planning (DONE)** — `validation.md` với 20 test cases + fixtures.
4. **Implementation** — chia 5 batch theo dependency order:
   - **Batch A (data layer)**: migration + model + factory. Smoke: chạy migrate + factory tạo row.
   - **Batch B (action layer)**: `SplitChargeIntoInstallmentsAction` + FormRequest + Policy + permission key. Smoke: unit tests 1-7.
   - **Batch C (DNG integration)**: sửa `DngPaymentService` + `PushNextInstallmentAction` + `PushNextInstallmentJob` + hook `ReconcileDngPaymentsJob`. Smoke: integration tests 8-12.
   - **Batch D (events + noti)**: 3 events + 4 template skeletons + outbox wiring. Smoke: assert event fired + outbox row inserted.
   - **Batch E (UI)**: Admin tab + modal + retry button + student portal table. Smoke: manual smoke trong dev.
5. **Verification** — chạy full `./scripts/dev.sh test --filter=...` cho 20 cases. Backfill migration dry-run trên snapshot DB. Manual smoke admin + student.
6. **Harness update** — `harness story update --status accepted`, `harness trace` cho mỗi batch, `harness decision add` cho 4 ADRs nếu phát sinh (vd. "auto-balance đợt cuối thay vì throw"), `harness backlog add` cho Phase 2 items (void handling, multi-edit, fee_type khác).

## Stop Conditions

Pause for human confirmation if:
- **Product behavior ambiguous**: gặp tình huống spec không cover (vd. SV trả thiếu xu, charge bị edit amount giữa chừng).
- **Data migration risk**: backfill phát hiện charge có data shape khác giả định (vd. `amount = 0`, `due_date` null mà charge đã paid).
- **Validation weakening**: cần giảm test coverage vì hạ tầng test thiếu (vd. không có DNG fake client) → propose new fixture trước khi skip.
- **Architecture direction change**: phát hiện cần thay đổi invariant DNG (vd. cần multi-installment cùng `awaiting_payment`) → quay lại design phase.
- **Coordination required**: cần team Notification confirm template wiring point trước khi mở UI student → pause + escalate.

## Estimated Touch Surface

- New files: ~12 (1 migration, 1 backfill migration, 1 model, 1 action, 1 request, 1 job, 1 action `PushNext`, 3 event classes, 1 policy method patch, 4 email type classes, 1 modal Vue, 1 admin tab Vue, 1 student portal patch).
- Modified files: ~6 (`DngPaymentService.php`, `ReconcileDngPaymentsJob.php`, `FinanceChargePolicy.php`, `config/permission.php`, `EmailContentRegistry.php` or its consumer, `app/Models/FinanceCharge.php`).
- Test files: ~5 (1 unit per action, 1 integration job, 1 backfill migration test, 1 manual smoke checklist).

Total estimated time: 2-3 working days nếu không gặp surprise; +1 day nếu E2E test hạ tầng cần setup.
