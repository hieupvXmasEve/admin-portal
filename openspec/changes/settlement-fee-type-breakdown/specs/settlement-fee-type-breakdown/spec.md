## ADDED Requirements

### Requirement: Breakdown chỉ group positive active billable lines
`ListSettlementWorklistQuery` SHALL tính `fee_type_breakdown` chỉ từ các `InvoiceLine` thỏa mãn đồng thời:
1. `amount_snapshot > 0` — là charge dương, không phải credit
2. Line đang active (`isBillableActiveLine`)
3. `charge` tồn tại (có `charge_id`)

Credit lines (`amount_snapshot < 0`, ví dụ từ `scholarship_credit`, `defer_credit`) **không tạo fee_type row riêng**. Chúng chỉ ảnh hưởng gián tiếp qua `discountAllocations` trên charge line liên quan.

#### Scenario: Credit line không tạo row breakdown
- **WHEN** student có `tuition_term` line 10M và `scholarship_credit` line -2M trong cùng invoice
- **THEN** breakdown chỉ có 1 row HP; credit line không tạo row KHAC hay row nào khác

#### Scenario: Discount từ discountAllocations được tính đúng
- **WHEN** `tuition_term` line có `discountAllocations` tổng 2M
- **THEN** HP row có `discount=2,000,000`, `net_remaining = gross - 2M - paid`

### Requirement: Settlement worklist trả về fee_type_breakdown per student
`ListSettlementWorklistQuery` SHALL trả về field `fee_type_breakdown` trong mỗi student record. Đây là mảng các nhóm phí, mỗi nhóm ứng với một DNG `fee_type`.

Mỗi phần tử SHALL có cấu trúc:
```
{
  fee_type: string,       // e.g. 'HP', 'PTL', 'GC', 'KHAC'
  label: string,          // e.g. 'HP: Học phí'
  gross: float,           // tổng amount_snapshot của positive active lines của type này
  discount: float,        // tổng discountAllocations của các lines đó
  net_remaining: float,   // gross - discount - paid (≥ 0)
  semester_id: int|null   // semester của invoice chứa line còn nợ đầu tiên của type này
}
```

Chỉ bao gồm các nhóm có `net_remaining > 0`.

Các charges cùng `fee_type` SHALL được cộng dồn vào cùng một nhóm.

**Business invariant (không phải assumption của UI):** Mỗi `fee_type` của một student chỉ thuộc về một `semester_id` duy nhất trong một chu kỳ billing. `semester_id` trong breakdown entry lấy từ invoice của line còn nợ đầu tiên của fee_type đó.

**Lưu ý về `latest_dng_request`:** Field này trong student record là thông tin tham khảo cấp student, không đại diện trạng thái DNG theo từng fee_type. UI SHALL NOT dùng `latest_dng_request` để suy ra "HP đã có DNG" hay "PTL đã có DNG". Đây là limitation được chấp nhận ở scope hiện tại.

#### Scenario: Student có 2 loại phí khác nhau
- **WHEN** student có `tuition_term` lines remaining 5,000,000 và `retake_fee` lines remaining 800,000
- **THEN** `fee_type_breakdown` chứa 2 phần tử: `{fee_type: 'HP', net_remaining: 5000000}` và `{fee_type: 'PTL', net_remaining: 800000}`

#### Scenario: Nhiều charges cùng type được cộng dồn
- **WHEN** student có 3 `tuition_term` lines với remaining lần lượt là 1M, 2M, 3M
- **THEN** breakdown chứa 1 phần tử HP với `net_remaining = 6,000,000`

#### Scenario: Scholarship được trừ đúng type
- **WHEN** student có `tuition_term` line gross=10M và discountAllocation=2M
- **THEN** HP entry có `gross=10,000,000`, `discount=2,000,000`, `net_remaining=8,000,000`

#### Scenario: Phần tử có net_remaining = 0 bị loại
- **WHEN** tất cả lines của một fee_type đã được thanh toán đầy đủ
- **THEN** fee_type đó không xuất hiện trong `fee_type_breakdown`

#### Scenario: Student chỉ có 1 loại phí
- **WHEN** student chỉ có `tuition_term` charges
- **THEN** `fee_type_breakdown` có đúng 1 phần tử với `fee_type = 'HP'`

### Requirement: Settlement UI hiển thị fee-type breakdown trong student row
Settlement worklist UI SHALL hiển thị bảng fee-type breakdown trong student row khi `fee_type_breakdown` có dữ liệu.

Mỗi dòng trong breakdown SHALL hiển thị: fee_type label, gross amount, discount amount (nếu > 0), net_remaining.

UI SHALL hiển thị note rõ ràng: `latest_dng_request` là thông tin cấp student, không phản ánh trạng thái DNG của từng loại phí.

#### Scenario: Breakdown hiển thị đầy đủ thông tin
- **WHEN** student row được render và `fee_type_breakdown` có 2 phần tử
- **THEN** UI hiển thị 2 dòng, mỗi dòng có label, số tiền, và nút "Tạo DNG"

#### Scenario: Discount hiển thị khi có giá trị
- **WHEN** fee_type HP có `discount > 0`
- **THEN** UI hiển thị dòng discount (e.g., "Giảm: -2,000,000")

### Requirement: Nút "Tạo DNG" từ breakdown chỉ hiển thị khi student là no_cash
Nút "Tạo DNG" trong fee-type breakdown SHALL chỉ xuất hiện khi `student.actionable === false` (tức là student không có `unapplied_balance` — trạng thái `no_cash`).

Đây là gate bắt buộc: `net_remaining` theo fee_type chỉ là con số hợp lý để prefill DNG khi không có tiền pending trong tài khoản. Nếu student có unapplied cash, settlement nên apply trước.

**Gate này phải được áp dụng ở cả UI lẫn flow redirect.** Backend route `finance.payments.create` không có gate riêng, nhưng spec này yêu cầu UI không expose nút khi `actionable === true`.

#### Scenario: Student no_cash thấy nút Tạo DNG
- **WHEN** `student.actionable === false` và `fee_type_breakdown` có phần tử HP
- **THEN** nút "Tạo DNG" hiển thị tại dòng HP

#### Scenario: Student actionable không thấy nút Tạo DNG từ breakdown
- **WHEN** `student.actionable === true` (có unapplied cash)
- **THEN** nút "Tạo DNG" không hiển thị trong breakdown; thay vào đó chỉ hiển thị nút "Apply"

### Requirement: Nút "Tạo DNG" từ breakdown pre-fill đúng fee_type và semester
Khi nhấn "Tạo DNG" từ một dòng trong fee-type breakdown, hệ thống SHALL redirect đến `finance.payments.create` với params:
- `student_id`
- `fee_type` = fee_type của dòng đó
- `amount` = `net_remaining` của dòng đó
- `semester_id` = `semester_id` từ breakdown entry (invoice của line còn nợ đầu tiên của fee_type này)
- `source_context` = `'settlement_no_cash'`

`semester_id` được xác định dựa trên business invariant: mỗi fee_type của student chỉ thuộc 1 semester trong chu kỳ billing. Nếu invariant bị vi phạm (edge case), lấy semester của line còn nợ đầu tiên theo thứ tự invoice.

#### Scenario: Click "Tạo DNG" cho PTL
- **WHEN** nhân viên click "Tạo DNG" tại dòng PTL của student AUH19444 (`no_cash`)
- **THEN** hệ thống redirect đến trang tạo DNG với `fee_type=PTL`, `amount=net_remaining_PTL`, `semester_id` đúng của PTL pre-filled

#### Scenario: Click "Tạo DNG" cho HP sau khi nhập description
- **WHEN** nhân viên nhập description và confirm
- **THEN** redirect với `fee_type=HP`, `amount=net_remaining_HP`, `semester_id` đúng của HP, `description=input`

### Requirement: Breakdown entry expose trạng thái DNG hiện tại theo fee_type
`ListSettlementWorklistQuery` SHALL bổ sung field `active_dng` vào mỗi phần tử của `fee_type_breakdown`.

```
active_dng: {
  id: number,
  amount: number,
  status: string   // 'pending' | 'pushed_to_dng'
} | null
```

`active_dng` là `DngPaymentRequest` gần nhất có trạng thái `awaitingPayment` (pending hoặc pushed_to_dng) cho cùng `(student_id, fee_type)`. `null` nếu không có.

Query này được thực hiện một lần cho tất cả `(student_id, fee_type)` của trang hiện tại (batch), không phải N+1.

#### Scenario: Student không có DNG nào đang chờ cho HP
- **WHEN** không có `awaitingPayment` DNG nào có `fee_type=HP` cho student
- **THEN** `fee_type_breakdown[HP].active_dng = null`

#### Scenario: Student có DNG đang chờ cho PTL
- **WHEN** có 1 DNG `pushed_to_dng` với `fee_type=PTL`, `amount=800000` cho student
- **THEN** `fee_type_breakdown[PTL].active_dng = { id: X, amount: 800000, status: 'pushed_to_dng' }`

### Requirement: UI ẩn nút "Tạo DNG" khi đã có active DNG trùng amount
Khi `entry.active_dng !== null` và `entry.active_dng.amount === entry.net_remaining`, nút "Tạo DNG" SHALL được thay bằng badge trạng thái, không được phép tạo thêm.

Badge hiển thị: "DNG đang chờ" với status label tương ứng. Không cần link chi tiết ở scope này.

Khi `entry.active_dng !== null` nhưng `amount` khác `net_remaining` (DNG cũ với số tiền đã lỗi thời), nút "Tạo DNG" SHALL vẫn hiển thị bình thường — người dùng cần tạo DNG mới với đúng số tiền hiện tại.

#### Scenario: Nút bị ẩn khi có DNG trùng amount
- **WHEN** `entry.active_dng.amount === entry.net_remaining` cho fee_type PTL
- **THEN** không hiển thị nút "Tạo DNG" cho dòng PTL; hiển thị badge "DNG đang chờ"

#### Scenario: Nút vẫn hiển thị khi amount khác
- **WHEN** `entry.active_dng.amount !== entry.net_remaining` (e.g., số tiền thay đổi sau khi có payment)
- **THEN** nút "Tạo DNG" vẫn hiển thị để cho phép tạo DNG mới với đúng số tiền

### Requirement: Service-level guard chống tạo DNG trùng
`DngPaymentService::createAndPush()` SHALL kiểm tra trước khi tạo: nếu đã tồn tại DNG `awaitingPayment` với cùng `(student_id, fee_type, amount)`, throw exception với message rõ ràng.

Đây là safety net bắt buộc, bảo vệ mọi entry point (single và batch), kể cả khi UI đã ẩn nút.

`createAndPushBatch()` SHALL bỏ qua (skip) record trùng thay vì throw, để không block toàn batch. Record bị skip SHALL được report trong kết quả trả về dưới dạng `skipped` count.

#### Scenario: Tạo đơn trùng bị block
- **WHEN** `createAndPush()` được gọi với `fee_type=PTL`, `amount=800000` nhưng đã có DNG `awaitingPayment` cùng `(student_id, fee_type=PTL, amount=800000)`
- **THEN** throw exception, không tạo record mới, không hủy record cũ

#### Scenario: Batch skip record trùng
- **WHEN** `createAndPushBatch()` nhận 3 records, 1 trong đó trùng
- **THEN** tạo 2 records thành công, skip 1, trả về `{ created: 2, skipped: 1, failed: 0 }`
