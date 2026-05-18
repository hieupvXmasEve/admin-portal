# Non-Academic Charge Generation (Operations Page Refactor) - Context

**Feature slug:** non-academic-charge-generation
**Date:** 2026-05-18
**Exploring session:** complete
**Scope:** Standard
**Domain types:** SEE, RUN, ORGANIZE

## Feature Boundary

Refactor `finance/operations/generate-charges` từ trang bulk multi-EGC + options thành trang chuyên tạo **non-academic charges** (BHYT và một số fee cố định khác). Bỏ hoàn toàn logic bulk EGC + options voucher/scholarship trên trang này; EGC academic flow đã có trang riêng (`finance/egc/charges`) và đã tự auto-apply discount. Feature dừng ở: form chọn fee_type + nhập thông số + upload CSV mssv → generate charges + summary inline. Không bao gồm sửa trang EGC, không bao gồm batch history, không bao gồm trang config fee_type.

## Locked Decisions

These are fixed. Planning must implement them exactly.

- **D1:** Trang `finance/operations/generate-charges` chỉ phục vụ non-academic charges. Bulk multi-EGC bị bỏ hoàn toàn. Trang EGC (`finance/egc/charges`) giữ độc quyền charge học phí academic.
- **D2:** Danh sách fee_type non-academic được khai báo bằng enum cố định (BHYT là loại đầu tiên, kèm các loại như bảo hiểm thân thể, đồng phục, KTX…). Admin chọn từ dropdown. Không có UI cấu hình loại mới trong feature này.
- **D3:** Non-academic charges KHÔNG áp voucher/scholarship. Auto-discount chỉ thuộc về EGC academic flow (đã tồn tại).
- **D4:** Feature này CHỈ refactor `Operations/GenerateCharges`. Không động vào trang EGC. Giả định EGC đã auto-apply voucher/scholarship.
- **D5:** Cách chọn target sinh viên là **upload CSV** chứa danh sách mssv. Không dùng filter cohort/program. Không có chế độ chọn từng SV trên UI.
- **D6:** Thông số charge nhập trên **form** trước khi upload: fee_type, amount, due_date, period/note. Tất cả SV trong CSV nhận cùng bộ thông số. CSV chỉ chứa mssv (không có cột override amount/due_date).
- **D7:** Duplicate handling = **skip + report**. SV đã có charge cùng fee_type + cùng kỳ → bỏ qua. Summary inline liệt kê: tạo mới N, bỏ qua M (kèm lý do mỗi dòng).
- **D8:** Campus scope = `selected_campus` từ session admin (không có dropdown campus trên form). SV trong CSV không thuộc campus đang chọn → skip + report. Nhất quán với pattern campus-scoped hiện có.
- **D9:** Không có batch history. Sau khi generate, summary (created/skipped + reasons) hiển thị inline cuối trang; refresh là mất. Muốn xem charge phải sang trang charges/transactions thông thường.

### Agent's Discretion

- Tên fee_type cụ thể trong enum ngoài BHYT (đồng phục, KTX, bảo hiểm thân thể…) — planning đề xuất shortlist, user xác nhận trong planning gate.
- Tên route/page slug sau refactor (giữ `finance/operations/generate-charges` hay đổi để phản ánh "non-academic") — planning đề xuất.
- Cấu trúc CSV (header bắt buộc, encoding, max rows) — planning chọn theo convention hiện có.

## Specific Ideas And References

- BHYT đầu năm là use-case driver: upload danh sách mssv sinh viên cần đóng BHYT của một kỳ, mọi SV cùng amount + due_date.
- Nghiệp vụ EGC đã thay đổi (auto-apply voucher/scholarship) → trang Operations cũ với options là dư thừa. Đây là lý do refactor.

## Existing Code Context

### Reusable Assets

- `resources/js/pages/Finance/Operations/GenerateCharges.vue` (43.7K) — trang hiện tại, sẽ được rewrite/cắt gọt. Logic bulk EGC + options voucher/scholarship cần xoá.
- `resources/js/pages/Finance/EgcOperations/GenerateCharges.vue` (19.8K) — trang EGC academic, KHÔNG động vào.
- `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` — controller render trang Operations (method `showGenerateCharges`).
- `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php` — API hiện có liên quan generate, sẽ cần API mới cho non-academic submit.
- `app/Modules/Finance/Exports/GenerateChargesPreviewExport.php` — export preview hiện tại; xem có còn dùng được không sau refactor.
- `app/Modules/Finance/Http/Web/Admin/EgcChargeGenerationController.php` — controller EGC, tham chiếu pattern.

### Established Patterns

- Module structure: `app/Modules/Finance/Actions/` cho business logic, `Http/Web/Admin` cho Inertia, `Http/Requests` cho FormRequest, `Policies/` cho auth.
- Permission gate: `can:view_finance_operations_generate_charges` đang gate route hiện tại.
- Campus-scoped invariant: Swinx enforce campus_id rõ ràng; pattern tham chiếu DbEmailContentProvider/notification.
- API response: `ApiResponse::success/error`.

### Integration Points

- `app/Modules/Finance/routes/web.php` — định nghĩa route `/generate-charges`; cần quyết định giữ slug hay đổi.
- Bảng charges (Finance) — Action mới insert vào đây. Schema/constraints phải verify trong planning.
- Permission gate hiện tại — có thể đổi tên cho phản ánh non-academic.

## Canonical References

- `docs/rules/architecture.md`, `docs/rules/backend.md`, `docs/rules/frontend.md` — implementation rules.
- `docs/inertiajs-vue-info.md` — Inertia v3 API.
- `.devin/skills/swinx-frontend/` — frontend patterns (useForm, useApi, useDataTable).
- `history/learnings/critical-patterns.md` — promoted patterns.

## Outstanding Questions

### Resolve Before Planning

(none — all user-facing decisions locked)

### Deferred To Planning

- [ ] Enum non-academic fee_type cụ thể: shortlist các loại ngoài BHYT (đồng phục, KTX, bảo hiểm thân thể, ...) — planning đề xuất, user xác nhận.
- [ ] Route/page slug: giữ `finance/operations/generate-charges` hay đổi thành tên phản ánh non-academic — planning đề xuất.
- [ ] Sync vs async (queue) khi CSV lớn — quyết định kỹ thuật trong planning; ảnh hưởng UX summary.
- [ ] CSV schema chi tiết (encoding, header bắt buộc, max rows, lỗi format) — planning chọn theo convention.
- [ ] Validation error handling (mssv không tồn tại, status không phù hợp) — pattern "skip + report" như D7 hay strict reject — planning xác nhận.
- [ ] Cơ chế lưu form khi reload (state retention) — planning quyết định theo UX hiện có.
- [ ] Permission name sau refactor (giữ `view_finance_operations_generate_charges` hay đổi) — planning đề xuất.

## Deferred Ideas

- Trang config quản lý enum fee_type — out of scope; enum hardcode.
- Batch history page — out of scope (D9).
- CSV per-row override amount/due_date — out of scope (D6).
- Filter-based target selection (cohort/program) — out of scope (D5).
- Multi-campus generation trong 1 batch — out of scope (D8).
- Voucher/scholarship cho non-academic — out of scope (D3).
- Sửa trang EGC academic — out of scope (D4); ghi nhận giả định EGC đã auto-apply discount.

## Handoff Note

CONTEXT.md is the source of truth. Decision IDs (D1–D9) are stable. Planning reads locked decisions, code context, canonical references, and deferred-to-planning questions. Validating and reviewing use locked decisions for coverage and UAT.
