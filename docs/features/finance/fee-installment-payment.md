# Yêu cầu: Thu phí theo đợt (Fee Installment Payment)

- **Ngày tạo**: 2026-05-25
- **Module**: Finance
- **Phạm vi**: Portal (admin + sinh viên) + tích hợp DNG Payment
- **Trạng thái**: Draft — chờ review

---

## 1. Bối cảnh hiện tại

Hệ thống thu phí hiện tại có các ràng buộc:

- Mỗi sinh viên chỉ tồn tại **1 khoản phí ACTIVE cho mỗi `charge_type`** (ví dụ "HP"). Tạo khoản phí mới cùng type → khoản trước bị `void` ngay.
- DNG Payment Request cũng tuân theo invariant: chỉ 1 DNG `awaiting_payment` per `(student_id, fee_type)` tại một thời điểm. Tạo DNG mới → DNG cũ bị `CANCELLED` (xem `DngPaymentService::createAndPush()` `app/Modules/Finance/Dng/Services/DngPaymentService.php:49-106`).
- Một DNG Payment Request = một lần push số tiền **đầy đủ** của charge sang DNG.

## 2. Yêu cầu mới

Cho phép sinh viên đóng học phí (và các loại phí khác trong tương lai) **theo nhiều đợt**:

- **Phase 1**: mặc định tách 1 khoản phí thành **2 đợt × 50%**, mỗi đợt có `due_date` riêng. Admin có thể tinh chỉnh amount/due_date trước khi sinh viên trả đợt nào.
- **Phase 1 lock**: chỉ cho phép tách/sửa đợt **1 lần duy nhất**, và chỉ khi **chưa có đợt nào được thanh toán**. Sau khi đợt đầu paid → kế hoạch đợt readonly.
- **Phase tương lai**: dễ mở rộng thành N đợt (≥1), amount mỗi đợt tuỳ chỉnh, có thể tách/sửa nhiều lần.

### Ràng buộc bất biến (must keep)

- **DNG không biết về đợt**: Mỗi thời điểm DNG chỉ nhận 1 yêu cầu thu của (student, fee_type) với 1 số tiền. DNG không cần thêm field "installment_no", "installment_total".
- **Không phá uniqueness hiện tại**: vẫn 1 `FinanceCharge` ACTIVE per type, 1 DNG `awaiting_payment` per (student, fee_type).
- **Backward-compatible**: charge cũ (không chia đợt) vẫn hoạt động như trước.

## 3. Giải pháp tổng quan

Tách "kế hoạch đợt" (Installment Plan) ra khỏi DNG Payment. Lớp đợt sống hoàn toàn ở Portal.

### 3.1. Mô hình dữ liệu

Thêm bảng mới:

```
finance_charge_installments
- id                          PK
- finance_charge_id           FK -> finance_charges
- installment_no              int          (1, 2, 3, ...)
- amount                      decimal(15,2)
- due_date                    date
- status                      enum(pending, awaiting_payment, paid, cancelled)
- dng_payment_request_id      FK -> dng_payment_requests (nullable)
- paid_at                     timestamp (nullable)
- push_attempt_count          int default 0
- last_push_error             text (nullable)
- last_push_attempted_at      timestamp (nullable)
- created_at / updated_at

UNIQUE (finance_charge_id, installment_no)
INDEX (status, due_date)
```

**Ghi chú**:
- `FinanceCharge.amount` không đổi — vẫn là tổng (ví dụ HP đầy đủ). Đợt chỉ là **slice** của charge.
- `installment_total` **không lưu** — suy ra qua `COUNT(*)`.
- Charge cũ → backfill 1 installment duy nhất (`installment_no=1`, `amount = charge.amount`) để code path đồng nhất.

### 3.2. Luồng tách đợt

**Action mới**: `SplitChargeIntoInstallmentsAction`

```
Input:
  - finance_charge_id
  - installments: [
      { installment_no: 1, amount: decimal, due_date: date },
      { installment_no: 2, amount: decimal, due_date: date },
      ...
    ]

Validate:
  - count >= 1
  - sum(amount) == charge.amount        (chống lệch xu)
  - installment_no liên tục từ 1, không trùng
  - due_date tăng dần theo installment_no
  - charge chưa có installment paid     (guard Phase 1: chỉ sửa khi chưa thu)
  - amount mỗi đợt > 0

Hành vi:
  - Xoá toàn bộ installments cũ (nếu có và đều ở status pending)
  - Insert installments mới
  - Đợt 1 → status = pending (sẽ push DNG sau)
```

UI Admin (Phase 1):
- Form prefill **2 dòng × 50%** + due_date mặc định.
- Cho phép `+ Thêm đợt` / `- Xoá đợt` (vì action đã support N đợt).
- Cho phép sửa amount: gõ đợt N → đợt cuối auto-balance phần còn lại.
- Lock form khi đã có đợt paid → readonly hoàn toàn.

### 3.3. Push DNG theo đợt

Sửa `DngPaymentService::createAndPush()` để nhận thêm tham số `installment_id` (optional):

- Nếu có `installment_id` → push amount = `installment.amount`, gắn `dng_payment_request_id` lên installment, set `installment.status = awaiting_payment`.
- Nếu không có (charge legacy không chia đợt sau backfill thì luôn có) → push full amount như cũ.

**Khuyến nghị**: backfill tất cả charge cũ → 1 installment, để code path luôn dùng `installment.amount`, không phải maintain 2 nhánh logic.

### 3.4. Auto-push đợt kế tiếp khi đợt trước paid

**Action mới**: `PushNextInstallmentAction`

```
Input: finance_charge_id

Logic:
  1. Lock charge (DB lock SELECT ... FOR UPDATE) chống race với webhook khác.
  2. Tìm installment kế tiếp: status=pending, installment_no nhỏ nhất.
  3. Nếu không còn → fire event ChargeFullySettled, return.
  4. Nếu còn → gọi DngPaymentService::createAndPush() với installment đó.
  5. Set installment.status = awaiting_payment, gắn dng_payment_request_id.
  6. Fire event InstallmentPushed (cho noti SV "Đợt N đã sẵn sàng thanh toán").
```

**Hook vào webhook reconcile** (`ReconcileDngPaymentsJob`):

Thứ tự **bắt buộc** trong transaction:
1. Update `DngPaymentRequest.status = PAID`.
2. Create `Payment` + `PaymentApplication` (allocate vào invoice_line tương ứng).
3. Update `installment.status = paid`, `paid_at = now()`.
4. Commit transaction.
5. **Sau commit** → dispatch `PushNextInstallmentJob(finance_charge_id)`.

Lý do dispatch sau commit, không inline trong transaction:
- Việc đợt 1 đã paid là sự thật, không được rollback nếu push đợt 2 fail.
- Push DNG là HTTP call → tách job riêng có retry, không khoá DB lâu.

### 3.5. Retry push fail

**Job**: `PushNextInstallmentJob`

Retry policy:
- 3 attempts, exponential backoff: 1 phút → 5 phút → 15 phút.
- Mỗi lần fail: `installment.push_attempt_count += 1`, `last_push_error = exception message`, `last_push_attempted_at = now()`.
- Sau 3 lần fail → installment vẫn ở `status = pending` (không lên `awaiting_payment`), fire event `InstallmentPushFailed` → noti admin.

**Manual retry button**:

Trong UI admin, đợt có `last_push_error != null && status = pending` hiển thị:
- Badge đỏ "Push DNG lỗi" + tooltip `last_push_error`.
- Button **"Thử push lại"** → call cùng `PushNextInstallmentAction` (có guard `status = pending`).
- Thành công → reset `push_attempt_count = 0`, `last_push_error = null`, status → `awaiting_payment`.

### 3.6. Race condition đã xử lý

- Webhook đợt 1 đến **trước khi** DNG đợt 1 status update → DB lock charge ngăn 2 webhook concurrent.
- Push đợt 2 chạy **sau khi** DNG đợt 1 đã chuyển từ `awaiting_payment` → `paid` → không vi phạm uniqueness DNG (`DngPaymentService::createAndPush()` line 51-60).
- 2 admin cùng bấm "Tách đợt" → unique constraint `(finance_charge_id, installment_no)` chặn double insert.

## 4. Edge case

| Tình huống | Xử lý |
|---|---|
| SV trả thừa đợt 1 qua DNG | DNG fix amount = đợt 1 → không thể trả thừa. OK. |
| Đợt cuối paid | `PushNextInstallmentAction` không tìm thấy đợt kế → fire `ChargeFullySettled` → invoice gen receipt cuối. |
| Admin huỷ kế hoạch đợt sau khi đợt 1 đã paid | **Phase 1: cấm**. Trả 400 với message rõ. |
| Charge bị void giữa chừng (sau đợt 1 paid) | Cancel toàn bộ installment chưa paid, cancel DNG đang `awaiting_payment` nếu có. |
| SV muốn trả sớm đợt 2 trước due_date | Mặc định đợt 2 đã được auto-push ngay sau đợt 1 paid → SV vào portal thấy DNG đợt 2 sẵn sàng → trả bất cứ lúc nào, không cần button riêng. |
| Sum installment lệch 1 xu so với charge.amount | Validate `sum(amount) == charge.amount` exact (decimal so sánh sau khi cast). UI auto-balance đợt cuối để không bao giờ lệch. |

## 5. Backward compatibility & Migration data cũ

- Chạy migration script: với mỗi `FinanceCharge` đang ACTIVE chưa có installment → tạo 1 row `finance_charge_installments` với `installment_no=1`, `amount = charge.amount`, `due_date = charge.due_date` (hoặc null nếu charge không có), `status` map từ trạng thái thu hiện tại của charge:
  - Charge chưa push DNG / DNG `awaiting_payment` → installment `awaiting_payment`, gắn `dng_payment_request_id` hiện tại.
  - Charge đã thu xong → installment `paid` + `paid_at`.
- Sau migration: code mới luôn đọc qua installment, không cần if/else legacy.

## 6. Deliverable (task list)

1. Migration `finance_charge_installments` + backfill script.
2. Model `FinanceChargeInstallment` + relation `FinanceCharge::installments()`.
3. `SplitChargeIntoInstallmentsAction` + FormRequest validate.
4. Sửa `DngPaymentService::createAndPush()` nhận `installment_id`, push theo `installment.amount`.
5. `PushNextInstallmentAction` + `PushNextInstallmentJob` (retry 3 lần, exponential backoff).
6. Hook vào `ReconcileDngPaymentsJob`: sau allocate (post-commit) → dispatch job.
7. UI Admin: form chia đợt (dynamic N rows, default 2×50%) + bảng đợt với status badge + button Retry.
8. UI Sinh viên: hiển thị các đợt + đợt nào đang sẵn sàng thanh toán (`awaiting_payment`).
9. Event + Notification:
   - `InstallmentPushed` → email SV "Đợt N đã sẵn sàng thanh toán".
   - `InstallmentPushFailed` → noti admin.
   - `ChargeFullySettled` → invoice gen receipt cuối.
10. Test:
    - Unit: validate sum amount, validate installment_no liên tục, guard sửa khi đã paid.
    - Integration: paid đợt 1 → DNG đợt 2 lên đúng amount, đúng (student, fee_type).
    - Edge: push fail 3 lần → noti + button retry hoạt động.
    - Backward-compat: charge cũ (đã backfill 1 installment) vẫn thu được như trước.

## 7. Mở rộng tương lai (out of scope Phase 1)

- Cho phép admin sửa kế hoạch đợt **nhiều lần**, kể cả sau khi đã có đợt paid (chỉ sửa các đợt chưa paid, validate `sum(remaining) == charge.amount - sum(paid)`).
- Cho phép > 2 đợt với amount tuỳ ý (action + DB đã hỗ trợ, chỉ cần mở UI).
- Áp dụng cho fee_type khác ngoài HP (ví dụ phí lại môn, phí ký túc xá) — không cần đổi code, chỉ enable UI tại các trang fee tương ứng.
- Tự động remind SV trước `due_date` X ngày (mở rộng `SendPaymentRemindersAction`).
- Gộp nhiều charge khác type vào cùng 1 đợt thanh toán DNG (hiện DNG đã hỗ trợ qua `DngPaymentRequestCharge` pivot, nhưng chưa wire vào installment).

## 8. Câu hỏi mở (cần xác nhận trước khi code)

1. **Default due_date của 2 đợt mặc định**: tính từ đâu? (Học kỳ start date? Charge created_at + 30/60 ngày? Cấu hình ở config?)
2. **Khi charge bị void**: có gửi noti SV không, và DNG `awaiting_payment` của đợt hiện tại có auto-cancel ngay không?
3. **Notification SV khi đợt N được push thành công**: template email/SMS có cần duyệt với team Notification trước?
4. **Quyền tách đợt**: role nào được phép (Admin Finance? Department head?) — cần định nghĩa policy.
