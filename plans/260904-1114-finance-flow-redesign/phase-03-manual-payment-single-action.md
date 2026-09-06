---
phase: 3
title: "Thu tay một thao tác + phiếu thu nội bộ"
status: completed
priority: P1
effort: "2-2.5d"
dependencies: [1]
---

# Phase 3: Thu tay một thao tác + phiếu thu nội bộ

## Overview

Owner chốt: sinh viên chỉ quét QR, nhưng **kế toán vẫn ghi tiền mặt/chuyển
khoản**, và chứng từ cần có là **phiếu thu nội bộ**. Hôm nay việc thu tay bị
chẻ 3 khúc: ghi nhận Payment ở Student 360 → phân bổ ở trang khác → **không có
chứng từ**. Hệ quả nghiệp vụ: tiền đã vào nhưng nằm ở "Còn dư", và vì đồng bộ
trạng thái Academic chỉ xảy ra **khi phân bổ**, sinh viên đã trả tiền vẫn bị
chặn xếp lịch thi lại. Đây là mắt hở đồng bộ **duy nhất** còn lại.

## Key Insights

- Ghi nhận: `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php:44-64`
  → `PaymentService::recordPayment()` (`app/Modules/Finance/Services/PaymentService.php:49`).
  Chỉ tạo Payment, **không** phân bổ, **không** gọi syncer.
- Phân bổ (đường khác): `app/Modules/Finance/Actions/AllocatePaymentAction.php:79-80`
  và `app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php:190-191` —
  đây là nơi gọi `RetakeRegistrationPaymentSyncer` / `ExamResitAttemptPaymentSyncer`.
- Mọi đường xác nhận tiền khác **đã** đồng bộ Academic qua
  `app/Modules/Finance/Support/AcademicDngPaymentProjectionSync.php`
  (`DngReconciliationService.php:309`, `BridgePaidDngRequestsForChargeAction.php:71-73`,
  `CaptureDngProviderReceiptAction.php:77-79`, `ResolveDngReceiptExceptionAction.php:98-100`,
  `DngWebhookService.php:436,455`).
- Preview phân bổ đã có: `PreviewManualAllocationQuery`
  (`FinanceStudentPaymentController::allocatePreview:26-31`).
- Phân bổ phải tôn trọng slot DNG đang giữ: `PaymentService::allocatePayment()`
  kiểm `DngPaymentRequestReservationTarget::holdingCollection()`.
- **Không có thư viện PDF** trong dự án: `composer.json:14` chỉ có
  `maatwebsite/excel ^3.1`. Không có route print/pdf nào cho invoice
  (`app/Modules/Finance/routes/web.php:165-175`).

## Requirements

- Functional: một thao tác của kế toán = ghi nhận Payment + phân bổ (có preview
  sửa được) + sinh phiếu thu trong **một** transaction có guard; đồng bộ Academic
  chạy **sau khi guard trả về** (xem Architecture 1).
- Functional: thao tác **idempotent** — double-click hoặc retry không tạo 2
  Payment / 2 bộ allocation / 2 số phiếu thu.
- Functional: nếu không thể phân bổ hết (dòng bị DNG giữ / hết dòng), phần dư
  **phải** được ghi nhận là Còn dư **kèm lý do + người chịu trách nhiệm**, không
  được để trôi im lặng.
- Functional: phiếu thu in được, có số phiếu, ghi rõ "phiếu thu nội bộ — không
  phải hoá đơn GTGT", liệt kê khoản được cấn trừ.
- Non-functional: không thêm dependency PDF ⇒ dùng trang in HTML (print
  stylesheet), tận dụng convention Inertia/Blade hiện có.
- Non-functional: đồng bộ Academic không được làm rollback toàn bộ giao dịch khi
  một syncer lỗi (theo tiền lệ Phase 2 của plan `260901-0155`: try/catch,
  không throw ra ngoài).

## Architecture

1. **Ranh giới transaction — pin chính xác (red team 2026-09-06, High).**
   `SettlementMutationGuard::withinGuard()` **chính là** transaction
   (`app/Modules/Finance/Support/SettlementMutationGuard.php:63-64`, kèm
   `lockForUpdate()` trên `BillingAccount`); lời gọi lồng nhau nhập vào
   transaction ngoài cùng. Nên "gọi syncer sau khi commit" **không thể** xảy ra
   bên trong guard.
   - **Trong guard**: `recordPayment()` → phân bổ theo mảng kế toán xác nhận
     (mặc định từ `PreviewManualAllocationQuery` + thứ tự chính sách Phase 1) →
     ghi bản ghi phiếu thu.
   - **Sau khi guard trả về**: 2 syncer Academic, bọc try/catch + `Log`.
   Đây đúng tiền lệ code hiện có: `AllocatePaymentAction.php:55-77` rồi `:79-80`;
   `AutoAllocatePaymentsAction.php:190-191`. Giữ syncer trong guard còn kéo dài
   thời gian giữ lock `BillingAccount` qua 2 lệnh ghi cross-module.
1b. **Idempotency (red team, High).** `store()` hiện **không có** khoá idempotent
   (`FinanceStudentPaymentController.php:44-64`), trong khi endpoint anh em bắt
   buộc: `DisposePaymentSurplusRequest.php:27` `'idempotency_key' => ['required','uuid']`,
   và `DisposePaymentSurplusAction.php:22-29` phát lại bản ghi cũ thay vì tạo mới.
   Phase này biến thao tác thành modal 3 bước ⇒ rủi ro double-submit tăng.
   Thêm `idempotency_key` vào `RecordManualPaymentRequest` + unique index, theo
   đúng mẫu surplus disposition.
1c. **Phần dư phải có lý do — nhưng API tái dùng không trả lý do (red team, High).**
   `PaymentService::allocatePayment()` xử lý mọi trường hợp không phân bổ được
   bằng `continue` trần và chỉ trả về `$createdAllocations` (`:208`): dòng không
   tồn tại (`:164-169`), dòng bị DNG giữ (`:167-173`), sai student (`:176-179`),
   bị chặn bởi outstanding (`:183-191`) — caller **không phân biệt được**.
   Yêu cầu "phần dư kèm lý do" không cài được nếu không mở rộng. Thêm bước: cho
   `allocatePayment()` trả thêm kênh `skipped: [{charge_id, reason}]` với reason
   thuộc tập đóng (`dng_held` | `no_active_line` | `capped` | `student_mismatch`),
   tương thích ngược. Phiếu thu snapshot phải ghi cả dòng bị bỏ qua + lý do —
   nếu không, kế toán chọn 3 dòng mà phiếu chỉ in 2, không giải thích.
2. **Phiếu thu**: bảng mới `finance_payment_receipts` (số phiếu, payment_id,
   campus_id, issued_by_user_id, issued_at, snapshot dòng cấn trừ + dòng bị bỏ
   qua dạng JSON) — snapshot để phiếu đã in không đổi khi ledger đổi.
   **Số phiếu — pin generator (red team, High):** repo có **3** generator
   `invoice_number` với 2 định dạng và chỉ 1 cái có retry. Dùng đúng
   `CreateFinanceChargeAction::createInvoiceForSemester` + `isInvoiceNumberCollision`
   (`app/Modules/Finance/Actions/CreateFinanceChargeAction.php:327-353`) làm mẫu.
   **Không** copy `GenerateBatchChargesAction.php:475` (`'INV-'.time().'-'...`,
   **không có** retry) — 2 thu ngân trong cùng một giây sẽ 500 giữa transaction
   trên đường tiền thật.
   **Đặt tên:** `Receipt` trong module này đã có nghĩa "biên lai từ provider DNG"
   (`CaptureDngProviderReceiptAction`, `DngReceiptException`, và worklist
   receipt-exception mà Phase 8 gom). Chọn tên tránh đụng, ví dụ
   `finance_payment_vouchers` / `PaymentVoucher` — chốt khi cook.
   **Số tuần tự — ĐÃ CHỐT (owner, validation 2026-09-06): KHÔNG cần tuần tự.**
   Dùng generator ngẫu nhiên + retry như `invoice_number`. Không bảng sequence,
   không khoá ghi. Phiếu thu là chứng từ nội bộ tra cứu được, không phải sổ
   chứng từ liên tục.
   <!-- Updated: Validation Session 1 - số phiếu ngẫu nhiên + retry -->
3. **Trang in — greenfield, không có convention (red team, High).**
   `grep -rln "@media print\|window.print" resources/js` trả **0**;
   `resources/views` chỉ có 4 blade (`app`, 2 email, mcp/authorize). Không có gì
   để "tận dụng". Xây **một** layout in dùng chung (Blade — Inertia không thêm
   giá trị cho trang in), đặt ở Phase này, và Phase 6 tái dùng chính nó.
   Route `GET /finance/payments/{payment}/receipt`. Không thêm PDF lib.
   Layout phải giải quyết: khổ A4, letterhead campus, định dạng VND, ngắt trang,
   dòng miễn trừ pháp lý.
4. **UI**: Student 360 → form "Ghi nhận thanh toán" trở thành 3 bước trong một
   modal: nhập tiền → xem/sửa phân bổ → xác nhận & in.
5. **Phần Còn dư — hàng đợi phải tự chứa, không trỏ sang Phase 7 (red team, Medium).**
   Hàng đợi của Phase 7 chỉ nhận SV **đã rời trường** (`unapplied_cash > 0` ∩
   trạng thái rời trường), nên dư của SV **đang học** — đúng ca của Phase 3 —
   không bao giờ vào đó. Nếu ép kế toán phải chọn disposition để đóng form, họ bị
   đẩy tới `retain_forfeit`/`reallocate`, tức là tịch thu tiền SV để thoả mãn một
   form — đúng thứ Phase 7 muốn bắt phải phê duyệt.
   ⇒ Phase này định nghĩa loại việc riêng: **"tiền dư chưa quyết (SV đang học)"**,
   không phụ thuộc trạng thái học vụ, không yêu cầu disposition ngay. Kế toán
   được phép để dư mà **không** chọn gì, miễn là sinh việc. Phase 8 hiển thị nó
   cạnh loại việc của Phase 7.

## Related Code Files

- Create: `app/Modules/Finance/Actions/RecordAndAllocateManualPaymentAction.php`
- Create: migration + model `FinancePaymentReceipt`
- Create: layout in dùng chung (Blade, ví dụ `resources/views/print/layout.blade.php`) + view phiếu thu — **greenfield**, Phase 6 tái dùng
- Modify: `app/Modules/Finance/Services/PaymentService.php:160-208` (trả kênh `skipped` + lý do)
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php:44-64`
- Modify: `app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php` (thêm allocation + lý do dư + `idempotency_key`)
- Create: migration unique index cho `idempotency_key` trên payment (theo mẫu `payment_surplus_dispositions`)
- Modify: `app/Modules/Finance/routes/web.php:180-183` (route store) + route receipt mới
- Reuse: `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php`
- Reuse: `app/Modules/Finance/Support/AcademicDngPaymentProjectionSync.php` (hoặc gọi 2 syncer trực tiếp như `AllocatePaymentAction:79-80`)
- Modify: `resources/js/pages/Finance/...` trang Student 360 (xác định file chính xác khi cook)

## Implementation Steps

1. Test đỏ: ghi nhận thu tay 1.500.000 cho SV có `retake_fee` unpaid → hôm nay
   Payment tồn tại, charge vẫn unpaid, `hq_fee_status` vẫn unpaid.
2. Test đỏ: không tồn tại route/bản ghi phiếu thu.
2b. Test đỏ: gửi 2 request thu tay giống hệt → hôm nay tạo 2 Payment.
3. Mở rộng `PaymentService::allocatePayment()` trả kênh `skipped` + lý do.
4. Tạo action gộp (guard bọc record+allocate+receipt; syncer **ngoài** guard) +
   `idempotency_key` + migration/model phiếu thu (generator có retry).
5. Nối UI 3 bước; preview dùng query có sẵn (**đã sửa ở Phase 1** để dùng chung
   sort — nếu không, preview và thực thi lệch thứ tự).
6. Gọi 2 syncer Academic sau khi guard trả về (try/catch, log khi lỗi).
7. Layout in dùng chung + view phiếu thu + dòng "không phải hoá đơn GTGT".
8. Test xanh: charge chuyển settled, `hq_fee_status` = paid, phiếu thu tồn tại
   và bất biến khi ledger đổi sau đó.

## Todo

- [x] Test đỏ: thu tay không phân bổ, không đồng bộ Academic
- [x] Action gộp record + allocate + sync + receipt (trong guard)
- [x] Bảng/model phiếu thu + số phiếu + snapshot
- [x] Trang in phiếu thu (không thêm PDF lib)
- [x] UI 3 bước ở Student 360
- [x] `idempotency_key` + unique index + test double-submit
- [x] `allocatePayment()` trả lý do bỏ qua (tập đóng)
- [x] Loại việc "tiền dư chưa quyết (SV đang học)" — không phụ thuộc Phase 7
- [x] Layout in dùng chung (Phase 6 tái dùng)
- [x] Test xanh + `pint --dirty`

## Success Criteria

- [x] Sau một thao tác thu tay: `remaining` của khoản giảm đúng, `hq_fee_status`
      của retake/resit chuyển `paid`, phiếu thu in được.
- [x] Phần không phân bổ được luôn có lý do; không có đường tạo Còn dư im lặng.
- [x] Phiếu thu đã in không đổi nội dung khi charge/discount thay đổi sau đó.
- [x] Syncer Academic lỗi không rollback tiền đã ghi nhận (test riêng) — chứng minh được vì syncer chạy **ngoài** guard.
- [x] Gửi 2 lần cùng `idempotency_key` → 1 Payment, 1 bộ allocation, 1 số phiếu.
- [x] Dòng bị DNG giữ hiện trên phiếu thu kèm lý do, không biến mất im lặng.

## Risk Assessment

- **Cao:** đây là đường tiền thật. Toàn bộ mutation phải trong
  `SettlementMutationGuard`; không được tính lại số tiền ngoài Settlement Position.
- **Trung bình:** phân bổ vào dòng đang bị DNG giữ phải bị chặn (đã có cơ chế)
  — test riêng cho trường hợp này.
- **Trung bình:** số phiếu thu trùng khi chạy song song. Repo có 3 generator
  `invoice_number`, chỉ `CreateFinanceChargeAction:347-351` có retry. Copy nhầm
  generator = 500 giữa transaction tiền thật.
- **Trung bình:** trang in là greenfield (0 `@media print` trong repo). Ước lượng
  2-2.5d của phase này **chưa** tính layout in; xem lại effort khi cook.

## Security Considerations

Route ghi nhận thu tay đang dùng `can:create_finance_payments` và giới hạn
campus (`assertCampusVisible`, `FinanceStudentPaymentController:66-78`) — giữ
nguyên. Phiếu thu chứa dữ liệu tài chính cá nhân: route in phải kiểm cùng
campus + permission xem payment.

## Next Steps

Phase 4 hiển thị kết quả thu tay bằng trạng thái hợp nhất; Phase 7 nhận phần
Còn dư của SV đã rời trường (dư của SV đang học do Phase này sinh việc).
