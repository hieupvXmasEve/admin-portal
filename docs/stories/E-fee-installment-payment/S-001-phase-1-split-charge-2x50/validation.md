# Validation — S-001

## Proof Strategy

3 lớp bằng chứng độc lập, không layer nào được skip:

1. **Unit** — Action invariants (sum amount, sequential `installment_no`, paid-guard) chạy isolated bằng `RefreshDatabase`.
2. **Integration** — Flow end-to-end qua DB: split → push DNG đợt 1 → webhook PAID → auto push đợt 2. Dùng real DB transaction, mock chỉ HTTP DNG.
3. **Migration backward-compat** — chạy backfill trên fixture charges hiện trạng (3 kịch bản: chưa push, đang awaiting, đã paid) → assert 1 installment per charge với đúng status.

Trace của mỗi attempt phải lưu vào harness DB (`harness trace`) để audit retrospective.

## Test Plan

| Layer | Cases |
|---|---|
| Unit | (1) `SplitChargeIntoInstallmentsAction` validate sum amount mismatch → throw. (2) installment_no không liên tục → throw. (3) due_date không tăng dần → throw. (4) charge đã có đợt paid → throw `ChargeHasPaidInstallmentException`. (5) split thành công khi clean: assert N rows insert đúng amount, status `pending`. (6) split lần 2 khi chưa paid → xoá row cũ, insert mới. (7) `PushNextInstallmentAction` không tìm thấy pending → fire `ChargeFullySettled` event. |
| Integration | (8) Split 2×50% → push đợt 1 sang DNG (mock HTTP) → assert installment.status = `awaiting_payment` + `dng_payment_request_id` filled. (9) Webhook reconcile PAID đợt 1 → assert thứ tự transaction: DNG.paid → Payment + PaymentApplication created → installment.paid → commit → `PushNextInstallmentJob` dispatched. (10) Job push đợt 2 thành công → installment 2 = `awaiting_payment`. (11) Job fail 3 lần → installment vẫn `pending`, `push_attempt_count = 3`, `last_push_error` filled, `InstallmentPushFailed` fired. (12) Manual retry button gọi cùng action → reset error fields, status → `awaiting_payment`. (13) Phase 1 lock: split khi đã có đợt paid → 409 `charge_has_paid_installment`. |
| E2E | (14) Pest 4 browser test (nếu hạ tầng E2E sẵn): Admin login → mở charge HP của 1 SV → click "Tách đợt" → form 2×50% prefill → submit → assert UI hiển thị 2 đợt với badge đúng. (15) SV login portal → thấy charge có 2 đợt, đợt 1 button "Thanh toán" enabled, đợt 2 disabled. |
| Platform | (16) Migration backfill chạy không lỗi trên fixture DB chứa: 3 charge (chưa push / awaiting / paid). Assert mỗi charge → 1 installment với status map đúng. (17) Rollback migration khi đã có data installment → fail với guard message. |
| Performance | (18) Backfill 10k charges ≤ 30s trên local MySQL (sanity, không phải SLO). Dùng chunk insert. |
| Logs/Audit | (19) Mỗi `SplitChargeIntoInstallmentsAction` invocation → 1 audit log entry với `old_plan` + `new_plan` JSON. (20) Mỗi push attempt → 1 JSON log line với keys quy định trong design §Observability. |

## Fixtures

- `StudentFactory::createWithCampus()` — SV thuộc 1 campus cụ thể (avoid cross-tenant bug từ critical pattern "Campus scope fallback to null").
- `FinanceChargeFactory::activeHP(amount: 5_000_000)` — charge HP 5tr đang ACTIVE, chưa có installment.
- `DngFakeHttpClient` — mock `DngClient::push()` với 3 modes: `success`, `transient_fail`, `permanent_fail`.
- `DngWebhookPayloadFixture::paid(amount, request_code)` — payload PAID cho `DngWebhookService::handle()`.

Fixture quan trọng: ít nhất 1 test case có amount lẻ cần auto-balance (vd. 1,000,001 chia 2 → 500,000 + 500,001).

## Commands

Sẽ điền sau khi script tồn tại. Pattern dự kiến:

```text
./scripts/dev.sh test --filter=FinanceChargeInstallment
./scripts/dev.sh test --filter=PushNextInstallment
./scripts/dev.sh test --filter=ReconcileDngPaymentsJobInstallment
./scripts/dev.sh artisan migrate --pretend  # dry-run backfill review
```

## Acceptance Evidence

(Sẽ điền sau verification phase.)

- [ ] All unit tests green
- [ ] All integration tests green
- [ ] Backfill migration run trên staging DB (snapshot trước, đối chiếu sau)
- [ ] Admin manual smoke: split 1 charge → push đợt 1 → webhook test → push đợt 2
- [ ] Student portal manual smoke: thấy 2 đợt, trả được đợt 1
- [ ] Audit log có entries cho split action
- [ ] `./scripts/harness query matrix` → S-001 row có cột unit/integ/e2e đều `yes`
