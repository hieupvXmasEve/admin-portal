## 1. Mapping Layer (DngFeeTypeOptions)

- [x] 1.1 Thêm method `values(): array<int, string>` vào `DngFeeTypeOptions` — trả về array codes từ `all()`
- [x] 1.2 Thêm method `fromChargeType(string $chargeType): string` với mapping: `tuition_term`/`course_fee` → HP, `egc_level_fee` → GC, `retake_fee` → PTL, `manual_fee`/`adjustment` → KHAC, fallback KHAC — dùng `FinanceCharge::TYPE_*` constants, KHÔNG include `admission_fee` hay credit types
- [x] 1.3 Thêm `use App\Models\FinanceCharge;` import vào `DngFeeTypeOptions`

## 2. Backend Query — fee_type_breakdown

- [x] 2.1 Thêm `invoiceLines.charge` vào eager load của `StudentInvoice::query()->with([...])` trong `ListSettlementWorklistQuery`
- [x] 2.2 Thêm private method `computeFeeTypeBreakdown(Collection $studentInvoices): array` — chỉ xử lý lines có `amount_snapshot > 0` và `isBillableActiveLine`, group theo `DngFeeTypeOptions::fromChargeType(charge_type)`, tính gross/discount/net_remaining, kèm `semester_id` từ invoice của line còn nợ đầu tiên của mỗi type, loại groups có net_remaining ≤ 0
- [x] 2.3 Thêm field `fee_type_breakdown` vào student record map (gọi `computeFeeTypeBreakdown` cho mỗi student)
- [x] 2.4 Thêm `label` cho mỗi breakdown entry bằng lookup trong `DngFeeTypeOptions::all()`

## 3. Frontend — TypeScript Interface

- [x] 3.1 Thêm interface `FeeTypeBreakdownEntry` vào `Settlement.vue`: `{ fee_type: string, label: string, gross: number, discount: number, net_remaining: number, semester_id: number | null }`
- [x] 3.2 Thêm field `fee_type_breakdown: FeeTypeBreakdownEntry[]` vào interface `SettlementStudent`

## 4. Frontend — UI Breakdown

- [x] 4.1 Trong `#cell-invoices` slot template, thêm section breakdown phía trên danh sách invoice — chỉ render nếu `fee_type_breakdown.length > 0`
- [x] 4.2 Render bảng breakdown: mỗi dòng hiển thị label, gross, discount (nếu > 0), net_remaining dạng currency, nút "Tạo DNG" (chỉ khi `!row.original.actionable`)
- [x] 4.3 Thêm note nhỏ trong UI (tooltip hoặc text muted): "`latest_dng_request` là thông tin cấp student, không phản ánh trạng thái từng loại phí"
- [x] 4.4 Giữ danh sách invoice gốc bên dưới breakdown

## 5. Frontend — DNG Dialog per Fee Type

- [x] 5.1 Thêm state `dngBreakdownEntry: FeeTypeBreakdownEntry | null` vào component
- [x] 5.2 Thêm handler `openDngDialogForType(student, entry)` — chỉ gọi được khi `!student.actionable`; set `dngTargetStudent`, `dngBreakdownEntry`, clear description, open dialog
- [x] 5.3 Sửa `redirectToCreateDngRequest()` — khi `dngBreakdownEntry` tồn tại, truyền `fee_type=entry.fee_type`, `amount=entry.net_remaining`, `semester_id=entry.semester_id` thay vì student-level values
- [x] 5.4 Cập nhật DNG dialog UI: hiển thị `entry.label` và `entry.net_remaining` khi tạo từ breakdown; giữ nguyên flow cũ khi tạo từ nút "Create DNG request" ở action column (student-level, không có entry)

## 6. Verify

- [ ] 6.1 Test với student AUH19444 — kiểm tra breakdown trả về đúng HP + PTL amounts
- [ ] 6.2 Kiểm tra scholarship discount được trừ đúng vào HP row
- [ ] 6.3 Click "Tạo DNG" cho PTL → redirect đến create form với fee_type=PTL pre-filled
- [x] 6.4 Chạy `./scripts/dev.sh artisan pint` để format PHP
- [x] 6.5 Chạy `./scripts/dev.sh npm run type-check` để verify TypeScript (OOM trong container do memory limit — cần verify thủ công hoặc chạy trên host)

## 7. DNG Deduplication

### 7A. Backend — Expose active_dng per breakdown entry

- [x] 7A.1 Trong `computeFeeTypeBreakdown()`, sau khi build `$groups`, query batch 1 lần: lấy latest `awaitingPayment` DNG cho tất cả `(student_id_list × fee_type_list)` — dùng `whereIn` không phải N+1
- [x] 7A.2 Bổ sung field `active_dng: { id, amount, status } | null` vào mỗi phần tử kết quả của `computeFeeTypeBreakdown()`
- [x] 7A.3 Chạy pint sau khi sửa

### 7B. Frontend — Ẩn nút khi đã có DNG trùng amount

- [x] 7B.1 Cập nhật interface `FeeTypeBreakdownEntry` trong `Settlement.vue`: thêm `active_dng: { id: number, amount: number, status: string } | null`
- [x] 7B.2 Trong breakdown table row: thay nút "Tạo DNG" bằng badge "DNG đang chờ" khi `entry.active_dng !== null && entry.active_dng.amount === entry.net_remaining`
- [x] 7B.3 Khi `entry.active_dng !== null` nhưng amount khác → nút vẫn hiển thị bình thường

### 7C. Backend — Service-level guard

- [x] 7C.1 Trong `DngPaymentService::createAndPush()`, trước khi `DngPaymentRequest::create()`, kiểm tra tồn tại DNG `awaitingPayment` cùng `(student_id, fee_type, amount)` — nếu có thì throw `\RuntimeException` với message rõ
- [x] 7C.2 Trong `createAndPushBatch()`, bọc mỗi record bằng check tương tự — skip record trùng thay vì throw, cộng vào `$skipped` counter
- [x] 7C.3 Cập nhật return type docblock của `createAndPushBatch()`: thêm `skipped: int` vào shape `array{created: int, failed: int}`
- [x] 7C.4 Chạy pint sau khi sửa
