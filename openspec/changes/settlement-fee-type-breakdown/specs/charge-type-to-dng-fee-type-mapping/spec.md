## ADDED Requirements

### Requirement: DngFeeTypeOptions cung cấp mapping từ charge_type sang fee_type
`DngFeeTypeOptions` SHALL cung cấp method static `fromChargeType(string $chargeType): string` trả về DNG fee_type tương ứng.

Chỉ các **positive billable charge types** mới có mapping xác định. Mapping chuẩn:
- `tuition_term`, `course_fee` → `HP`
- `egc_level_fee` → `GC`
- `retake_fee` → `PTL`
- `manual_fee`, `adjustment` → `KHAC`
- Bất kỳ giá trị không nằm trong danh sách trên → `KHAC` (fallback)

**Các charge_type bị loại khỏi mapping có chủ đích:**
- `admission_fee` — không thuộc scope settlement này
- `defer_credit`, `scholarship_credit`, `voucher_credit`, `egc_exempt_credit` — là credit lines (amount_snapshot < 0), không tạo fee row trong breakdown; chúng chỉ ảnh hưởng qua `discountAllocations` trên positive charge lines liên quan

#### Scenario: charge_type đã biết trả về đúng fee_type
- **WHEN** `fromChargeType('retake_fee')` được gọi
- **THEN** trả về `'PTL'`

#### Scenario: tuition_term map về HP
- **WHEN** `fromChargeType('tuition_term')` được gọi
- **THEN** trả về `'HP'`

#### Scenario: egc_level_fee map về GC
- **WHEN** `fromChargeType('egc_level_fee')` được gọi
- **THEN** trả về `'GC'`

#### Scenario: charge_type không xác định fallback về KHAC
- **WHEN** `fromChargeType('unknown_type')` được gọi
- **THEN** trả về `'KHAC'`

#### Scenario: credit charge_type fallback về KHAC (không tạo row riêng)
- **WHEN** `fromChargeType('scholarship_credit')` được gọi
- **THEN** trả về `'KHAC'` — caller phải lọc trước bằng `amount_snapshot > 0` để credit lines không bao giờ vào breakdown

### Requirement: DngFeeTypeOptions cung cấp danh sách valid values
`DngFeeTypeOptions` SHALL cung cấp method static `values(): array<int, string>` trả về mảng tất cả fee_type codes hợp lệ (e.g., `['HP', 'BHYT', 'HL', ...]`).

#### Scenario: values() trả về danh sách codes
- **WHEN** `DngFeeTypeOptions::values()` được gọi
- **THEN** trả về array string chứa tất cả fee_type codes từ `all()`
