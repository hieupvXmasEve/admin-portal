# Phase 4 — Reports (Student Services)

> **DONE (2026-08-02).** Read-only report queries (Merchandise/Queries/Reports: orders-by-status+queues, gold used/refunded reconciled to ledger, most-redeemed from order_items, stock-by-campus+movements) + MerchandiseReportController (Inertia + JSON data endpoint) + admin UI resources/js/pages/Merchandise/Reports + menu, campus-scoped via granted campuses. 66 test. **Excel export DEFERRED** (stakeholder-gated per §20 default; export_merchandise_report perm + maatwebsite/excel ready for later). Also fixed a Phase-2 latent bug: abort_unless(403)→500 on JSON, now throws AccessDeniedHttpException. Report: [cook-260802-0057-merchandise-store-phase-4-reports](../reports/cook-260802-0057-merchandise-store-phase-4-reports.md).

Phụ thuộc Phase 2–3. Gated: chờ stakeholder chốt scope export (§20 Q10) trước khi làm.

## Nội dung (§19)

Tất cả đều là truy vấn đọc trên dữ liệu các phase trước — không schema mới:

- Đơn theo trạng thái; danh sách chờ duyệt / sẵn sàng nhận / quá hạn / đã ship.
- Tổng Gold đã dùng, tổng Gold đã hoàn (từ ledger: `redemption`, `redemption_refund`).
- Merchandise đổi nhiều nhất (từ order_items).
- Tồn kho theo campus/variant + lịch sử movement.

(Báo cáo transfer trong spec §19: bỏ — transfer ngoài scope, D10.)

## Backend

- Query classes trong module Merchandise, permission `view_merchandise_report` (+ `export_merchandise_report`) — verb_noun, không dot (RT-10). Campus scope qua `CampusPermissionReader::permissionCodesForUserId($userId, $campusId)` + `whereIn('campus_id', $grantedCampusIds)` tầng query, KHÔNG dựa session (RT-13).
- Export Excel: `maatwebsite/excel: ^3.1` ĐÃ cài (`composer.json:25`) — bỏ hedge "nếu đã cài" (RT unresolved). Vẫn theo index default (chưa làm export phase đầu trừ khi stakeholder yêu cầu); khi làm dùng package này.

## Frontend

- `resources/js/pages/Merchandise/Reports/` (top-level, khớp `Finance/`, KHÔNG `Admin/Merchandise` — RT-4): bảng + filter (campus, khoảng thời gian, trạng thái). Reference: `resources/js/pages/Finance/Reporting/`. Đây là admin UI trong repo Swinx (Inertia/Vue), không phải portal Nuxt.

## Validation

- Feature tests số liệu: seed dữ liệu → đối chiếu tổng (đơn, Gold used/refund khớp ledger), campus scope.
