# Phase 2 — Catalog + Inventory

> **Status: BACKEND DONE (2026-08-01).** Module + 4 migrations + StockService + CRUD API + permissions + `MerchandiseVariantPolicy` (campus-from-record) + Upload via Shared Contract `FileUploadGateway`. 33/33 test (incl. DomainBoundary + Gold regression). Report: [cook-260801-1433-merchandise-store-phase-2-backend](../reports/cook-260801-1433-merchandise-store-phase-2-backend.md). Deltas from draft: image upload goes through the **Shared Contract** (not Upload module concretes — DomainBoundaryArchitectureTest forbids cross-module concrete imports); variant CREATE campus-scoped to target campus_id; campus_id immutable on update. **Admin Vue/Inertia UI still pending (separate slice).**

Module mới `app/Modules/Merchandise/` theo skeleton module hiện hành (Actions, Models, Http/Api, Queries, routes).

**RT-4: đăng ký `MerchandiseServiceProvider` ở `bootstrap/providers.php`** — không thì routes/bindings của module không load. Nêu rõ như một bước.

## DB (migrations mới, varchar + allow-list, không DB enum)

`merchandise`:
- `name`, `description` nullable, `gold_price` `integer` signed (D8; giá hiện hành — order line snapshot riêng), `status` varchar(32) allow-list `active/coming_soon/hidden/archived`, timestamps. Archive thay xóa (giữ lịch sử §5), không route delete.

`merchandise_images`:
- `merchandise_id` FK, `path`, `sort_order`, `is_primary`.

`merchandise_variants`:
- `merchandise_id` FK, `campus_id` FK, `color` nullable, `size` nullable, `sku` nullable unique, `stock_quantity` unsignedInteger, `is_active` bool.
- Unique (`merchandise_id`,`campus_id`,`color`,`size`).

`stock_movements`:
- `merchandise_variant_id` FK, `change` (signed int), `quantity_before`, `quantity_after`, `type` varchar(32) allow-list: `stock_in`, `redemption_out` (D7), `redemption_refund`, `manual_increase`, `manual_decrease`, `damaged`, `lost`; `redemption_order_id` FK nullable, `performed_by` FK users, `note`, `created_at`.

## Business rules

- D2: availability derived — SV thấy `available` khi status=`active` và tổng tồn variant active tại campus mình > 0; `out_of_stock` khi = 0. `coming_soon` hiển thị nhưng khóa Redeem. `hidden/archived` không hiển thị.
- Trừ/cộng tồn: lock variant row (`lockForUpdate`) + conditional update `WHERE stock_quantity >= ?`; `stock_quantity` unsigned = lưới chống âm ở DB. Mọi thay đổi ghi `stock_movements` với before/after. **Lock order thống nhất toàn hệ thống** (RT-15): ví trước, rồi variant sort theo `merchandise_variant_id ASC` — áp dụng y hệt ở mọi path (checkout Phase 3, refund, stock adjust) để tránh deadlock.
- Archive chỉ ẩn khỏi store; đơn cũ dùng snapshot.

## Permissions (RT-1/RT-10 — viết lại theo convention THỰC TẾ)

Claim cũ ("`gold.adjust`, `gold.view_transactions` ĐÃ tồn tại", dot-namespace) **SAI** — verified grep 0 hit. Sự thật:
- `config/permission.php:352-355`: `view_gold_transaction`, `view_any_gold_transaction` (snake_case verb_noun). Không có gold-adjust permission nào (đã thêm `adjust_gold_wallet` ở Phase 1, RT-1).
- Toàn repo dùng flat `verb_noun`, KHÔNG dot (`view_report`, `export_report`, `view_finance_reporting`...). Dot-slug là pattern đã biết lỗi — commit `3edac2e5` (HEAD~1) fix đúng lỗi này; không có `Gate::before` → slug sai = 403 mọi người kể cả super_admin.

Việc phải làm:
- Thêm group `merchandise` vào `config/permission.php` (`access` key) dạng verb_noun: `view_merchandise`, `create_merchandise`, `edit_merchandise`, `archive_merchandise`, `manage_merchandise_image`, `manage_merchandise_variant`, `adjust_merchandise_stock`, `view_merchandise_report`, `export_merchandise_report`, `view_merchandise_audit`. Redemption (Phase 3): `view_redemption_order`, `approve_redemption_order`, `reject_redemption_order`, `cancel_redemption_order`, `confirm_redemption_collection`, `mark_redemption_shipped`.
- Đăng ký ở seeder `RoleAndPermissionSeeder` (xác nhận path lúc làm) + `SyncPermissions`.
- Deploy step: seed + `cache:clear` (như `3edac2e5` document).
- Test route-authorization mỗi phase theo mẫu ScholarshipRouteAuthorizationTest.

## Campus scoping (RT-13 — cơ chế thực tế, không phải "access grants")

Claim cũ "theo CampusUserRole/access grants" lẫn 2 cơ chế. Sự thật:
- Staff campus scoping = `CampusPermissionReader::permissionCodesForUserId($userId, $campusId)` (`EloquentCampusPermissionReader:18-33`, join `campus_user_roles.campus_id`). Cache 1 ngày (`:16`), invalidate chỉ qua `ClearUserPermissionCacheAction`.
- `LazyPermissions::hasPermission` (`app/Traits/LazyPermissions.php:16-18`) resolve campus từ **session `current_campus_id`** (campus đang chọn), KHÔNG phải campus của record → record-blind. Record-level chỉ có khi viết Policy resolve campus từ record (mẫu `StudentApplicationPolicy:42-58`).
- Access-grant models (`LecturerAccessGrant`/`GuardianAccessGrant`) **không có cột campus_id** — không phải cơ chế campus scoping.

Việc phải làm:
- Viết `MerchandiseVariantPolicy` (Phase 2) resolve `campus_id` **từ record** → check `permissionCodesForUserId($user->id, $record->campus_id)`. Không dựa session.
- Mọi list/report: `whereIn('campus_id', $grantedCampusIds)` tầng query.
- Nêu yêu cầu invalidate permission cache khi cấp campus mới (`ClearUserPermissionCacheAction`).
- Test cross-campus 403 (staff campus HN sửa tồn variant campus HCM → 403).

## Backend

- Admin CRUD merchandise + images + variants + điều chỉnh tồn (Action classes, FormRequest, ApiResponse envelope).
- Models extend `AuditableModel` (spatie activitylog) → audit §18.

## Upload ảnh (RT-12/RT-5 — dùng module Upload có sẵn, không tự viết)

D9 ("folder riêng, không media library") đúng ý bỏ media library, nhưng **KHÔNG tự hand-roll** — repo có sẵn module Upload đầy đủ, mạnh hơn:
- `app/Modules/Upload/*` (UploadManager, FileValidator, StoreUploadAction, UploadCleanupManager, CleanupOrphanedFilesJob), đăng ký sẵn `bootstrap/providers.php`, config-driven `config/uploads.php` (contexts với size/type limit, `validate_file_signature`, `detect_polyglot_files`, `check_file_entropy`, `sanitize_filename`, `canonical_extension`). "Validate mimetype + size + random name" của D9 chỉ check MIME client-supplied → polyglot/SVG-script lọt vào store SV thấy.
- **Disk env-switchable, KHÔNG hardcode `Storage::disk('public')`** — repo dùng named disk `images` (`config/filesystems.php:74-90`, driver `env('IMAGE_STORAGE_DRIVER','local')`, S3-ready). Hardcode local disk → multi-container/S3 thì ảnh 404 sau load balancer, xóa no-op node khác.
- Việc phải làm: thêm context `merchandise_image` vào `config/uploads.php` (disk `images`, `directory: merchandise`, public, `max_pixels`, `canonical_extension: true`) + route qua `StoreUploadAction`. Ít code hơn D9 cũ, không nhiều hơn.

## Frontend (admin) (RT-4 — path đúng)

Claim cũ "`resources/js/pages/Admin/Merchandise/` theo pattern Finance/Events" **SAI**: `Admin/Finance` và `Admin/Events` không tồn tại. Finance/Events ở top-level `resources/js/pages/`. Sự thật:
- Đặt ở `resources/js/pages/Merchandise/` (top-level, khớp `Finance/`, `Events/`).
- Reference screen cụ thể: `resources/js/pages/Finance/Operations/` (list+filter+form). Không copy từ `Admin/*` (nhóm khác, và có class bug DataPagination prop-mismatch đã biết).
- Nội dung: list + form (images, variants matrix màu×size×campus, tồn) + trang điều chỉnh tồn với lịch sử movement.

Lưu ý: admin UI này ở TRONG repo Swinx (Inertia/Vue) — khác student portal (repo Nuxt riêng, xem Phase 3).

## Validation

- Feature tests: CRUD + permission gate (route-auth test) + campus scope (cross-campus 403); điều chỉnh tồn ghi movement đúng before/after; conditional update `WHERE stock_quantity >= ?` không cho âm tồn (lưu ý giới hạn RefreshDatabase với race — xem Phase 1 RT-10; ở đây conditional update + unsigned column là lưới chính, test unit logic).
- Smoke admin UI bằng tay.
