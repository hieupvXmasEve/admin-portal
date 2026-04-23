## Context

Settlement worklist (`ListSettlementWorklistQuery`) hiện nhóm toàn bộ `StudentInvoice` theo `student_id`, tính tổng `active_due` và `net_amount_to_collect` mà không phân biệt loại phí. `InvoiceLine` có `charge_id → FinanceCharge.charge_type`, nhưng thông tin này không được expose ra UI.

`DngFeeTypeOptions` hiện là danh sách options cho dropdown, chưa có mapping từ `charge_type`. `FinanceCharge` đã có đầy đủ constants (`TYPE_TUITION_TERM`, `TYPE_RETAKE_FEE`, …).

Settlement page không tách row theo fee_type vì `unapplied_balance` là tiền của student, không phải của từng loại phí — phân bổ nó theo type đòi hỏi chạy lại priority logic, phức tạp không cần thiết. Thay vào đó, bổ sung breakdown data trong response và hiển thị trong UI.

## Goals / Non-Goals

**Goals:**
- Nhân viên thấy được số tiền còn nợ theo từng `fee_type` (HP, PTL, GC, KHAC…) ngay trong row của settlement
- Nhân viên có thể tạo DNG riêng cho từng loại phí chỉ với 1 click từ settlement
- Charges cùng type được cộng dồn (gross, discount, net_remaining)
- `DngFeeTypeOptions` trở thành nguồn sự thật duy nhất cho mapping `charge_type → fee_type`

**Non-Goals:**
- Tách row settlement thành nhiều rows theo fee_type
- Thay đổi logic auto-allocate (Apply settlement)
- Phân bổ `unapplied_balance` theo fee_type
- Thay đổi batch-dng page (scope riêng)

## Decisions

### D1: Mapping `charge_type → fee_type` nằm trong `DngFeeTypeOptions`

**Quyết định**: Thêm `fromChargeType(string $chargeType): string` vào `DngFeeTypeOptions`, dùng `FinanceCharge::TYPE_*` constants.

**Mapping** (chỉ positive billable charge types):
```
tuition_term, course_fee  → HP
egc_level_fee             → GC
retake_fee                → PTL
manual_fee, adjustment    → KHAC
```

Các type bị loại có chủ đích:
- `admission_fee` — không thuộc scope settlement này
- Credit types (`defer_credit`, `scholarship_credit`, `voucher_credit`, `egc_exempt_credit`) — không tạo fee row; ảnh hưởng qua `discountAllocations` trên charge line liên quan

`fromChargeType` fallback về `KHAC` cho giá trị không xác định. Caller phải lọc `amount_snapshot > 0` trước khi gọi mapper.

**Lý do**: Tránh tạo thêm class mới, `DngFeeTypeOptions` đã là nơi quản lý fee type. Dùng constants của `FinanceCharge` để tránh magic strings.

**Alternative bị loại**: Class `ChargeTypeToDngFeeTypeMapper` riêng — thêm indirection không cần thiết.

---

### D2: Tính breakdown ở PHP, không ở frontend

**Quyết định**: `ListSettlementWorklistQuery` trả về field `fee_type_breakdown: array<{fee_type, label, gross, discount, net_remaining}>` trong mỗi student record.

**Lý do**: Frontend không có đủ dữ liệu line-level để tính. PHP query đã load invoiceLines — chỉ cần thêm `invoiceLines.charge` vào eager load và group kết quả.

---

### D3: Chỉ hiển thị breakdown khi có > 1 fee_type khác nhau, hoặc luôn hiển thị

**Quyết định**: Luôn trả về breakdown (dù chỉ có 1 type). UI chọn cách render.

**Lý do**: Đơn giản hóa backend. Frontend có thể ẩn breakdown nếu chỉ có 1 type để tránh clutter.

---

### D4: Eager load `invoiceLines.charge` — tác động N+1

**Hiện tại**: Query đã load `invoiceLines.paymentApplications` và `invoiceLines.discountAllocations`. Thêm `invoiceLines.charge` là 1 eager load nữa.

**Đánh giá**: `FinanceCharge` records ít (1 per line), Eloquent xử lý bằng `whereIn` — không có N+1. Tác động performance không đáng kể.

---

### D5: DNG dialog từ settlement — pre-fill fee_type và amount

**Quyết định**: Mỗi breakdown row có nút "Tạo DNG" redirect đến `finance.payments.create` với query params `fee_type`, `amount`, `description`, `student_id`, `semester_id`. Nút **chỉ hiển thị khi `student.actionable === false`** (no_cash).

**Lý do gate no_cash**: Khi student có `unapplied_balance > 0`, `net_remaining` per type chưa phản ánh phần tiền sẽ được apply. Tạo DNG trước khi apply là sai về nghiệp vụ. No_cash loại bỏ rủi ro này.

**Lý do**: Reuse existing create payment form, không cần tạo endpoint mới. `semester_id` lấy từ breakdown entry của fee_type đó — dựa trên business invariant "mỗi fee_type của student chỉ thuộc 1 semester trong chu kỳ billing". Nếu vi phạm invariant, lấy invoice của line còn nợ đầu tiên.

### D6: latest_dng_request là student-level, không phải fee_type-level

**Quyết định**: Không thay đổi cách query `latest_dng_request`. Nó vẫn là DNG request mới nhất của student bất kể fee_type.

**Lý do**: Query per (student, fee_type) đòi hỏi thêm GROUP BY phức tạp. Scope hiện tại không cần thiết.

**Implication cho UI**: UI SHALL NOT dùng `latest_dng_request` để suy ra trạng thái DNG của từng breakdown row. Nếu student đã có DNG cho HP nhưng chưa có cho PTL, UI không phân biệt được — đây là limitation được chấp nhận. Cần note rõ trong UI để tránh hiểu nhầm.

## Risks / Trade-offs

**[Risk] Một invoice có lines của nhiều fee_type** → Breakdown tính đúng vì split ở line level, không invoice level. Không có issue.

**[Risk] Discount (scholarship) bị gán sai type** → `discountAllocations` nằm trên `InvoiceLine`, gắn với charge cụ thể. Khi group theo fee_type, discount được tính đúng cho type của line đó.

**[Risk] `fee_type_breakdown` có thể rỗng** nếu tất cả lines là credits hoặc đã paid → Frontend cần handle empty array, fallback về hiển thị invoice list như cũ.

**[Trade-off] Luôn load `invoiceLines.charge`** dù settlement page không cần khi không có action DNG → Chi phí nhỏ, không đáng tách thành lazy load.
