## Why

Settlement worklist hiện gộp toàn bộ khoản nợ của một sinh viên thành một dòng, không phân biệt loại phí. DNG yêu cầu mỗi `fee_type` phải là một request riêng biệt, dẫn đến việc nhân viên không thể tạo DNG đúng loại khi một sinh viên có nhiều khoản phí khác nhau (ví dụ: HP và PTL cùng lúc).

## What Changes

- **Backend**: Bổ sung mapping `FinanceCharge.charge_type → DNG fee_type` vào `DngFeeTypeOptions` (method `fromChargeType()` + `values()`).
- **Backend**: `ListSettlementWorklistQuery` tải thêm `invoiceLines.charge` và trả về field `fee_type_breakdown` per student — mảng các nhóm `{ fee_type, label, gross, discount, net_remaining }`, cộng dồn các charges cùng type.
- **Frontend**: Settlement row mở rộng phần "Open invoices" hiển thị bảng fee-type breakdown thay vì chỉ danh sách invoice.
- **Frontend**: Mỗi dòng trong breakdown có nút **"Tạo DNG"** riêng — pre-fill type, amount, description.
- **Frontend**: Dialog tạo DNG từ settlement truyền đúng `fee_type` và `amount` từ dòng được chọn.
- Không thay đổi luồng Apply settlement (auto-allocate vẫn per-student).
- Không tách row theo fee_type (giữ nguyên 1 row = 1 student).

## Capabilities

### New Capabilities

- `settlement-fee-type-breakdown`: Phân tách số tiền còn nợ của mỗi sinh viên theo `fee_type` (HP, PTL, GC, KHAC…) ngay trong settlement worklist row, với tổng gộp cho các charges cùng type.
- `charge-type-to-dng-fee-type-mapping`: Mapping chuẩn từ `FinanceCharge.charge_type` sang DNG `fee_type`, là nguồn sự thật duy nhất dùng chung cho cả settlement và batch-dng.

### Modified Capabilities

_(không có — batch-dng sẽ được implement riêng sau)_

## Impact

- `app/Modules/Finance/Dng/Support/DngFeeTypeOptions.php` — thêm `fromChargeType()`, `values()`
- `app/Modules/Finance/Queries/Operations/ListSettlementWorklistQuery.php` — thêm eager load charge, tính breakdown
- `resources/js/pages/Finance/Operations/Settlement.vue` — thêm breakdown UI + DNG dialog per type
- Không ảnh hưởng API, không migration, không breaking change
