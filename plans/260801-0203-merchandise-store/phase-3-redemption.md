# Phase 3 — Redemption

> **3a DONE (2026-08-01).** Backend + contract shipped, 56 test. RedemptionService (createOrder atomic, symmetric refund, 11 transitions), migrations (redemption_orders/items, stock_movements FK, gold_transactions partial-unique dedup key), student API (student.api.auth only — parent 403), staff API + RedemptionOrderPolicy, notification via Shared Contract, docs/api/student/merchandise.md, GoldTransactionController IDOR fix. Report: [cook-260801-1602-merchandise-store-phase-3a-redemption](../reports/cook-260801-1602-merchandise-store-phase-3a-redemption.md). **3b portal pending — FE/student-nuxt not checked out.** Deferred: staff cancellation-request notification (PII scoping = Notification-owner call).

Phụ thuộc Phase 1 (ledger) + Phase 2 (catalog/inventory).

**Tách 3a (backend) và 3b (portal Nuxt) — RT-6.** Student portal KHÔNG phải Vue page trong repo này. Là repo Nuxt riêng `FE/student-nuxt` (git-ignored, history riêng), theo `docs/portal-repos.md`: contract ở `docs/api/student/`, chạy `./scripts/portal-status.sh`, lint/typecheck/build riêng. `Portal impact: student`.

## Phase 3a — Backend + API contract

### DB

`redemption_orders`:
- `student_id`, `campus_id` (snapshot campus SV lúc tạo đơn — RT-13), `code` unique, `status` varchar(32), `previous_status` varchar(32) nullable, `method` varchar(16) `pickup/shipping`, `total_gold` `integer` (D8), `idempotency_key` varchar nullable + unique scoped `student_id` (RT-8).
- Pickup: `collection_location`, `ready_at`, `collection_deadline` (mặc định ready_at + 14 ngày, staff sửa được), `collected_at`, `collected_confirmed_by`, `collection_note`.
- Shipping (D5): `shipping_address` text (SV nhập lúc checkout), `shipped_at`, `shipped_by`, `shipping_note`.
- Reject/cancel: `reject_reason`, `cancellation_requested_by/at`, `cancellation_reason`, `cancellation_handled_by/at`, `cancellation_result`, `cancellation_note`, `cancelled_at`.
- Timestamps.

`redemption_order_items` (snapshot D7):
- `redemption_order_id`, `merchandise_variant_id` FK, snapshot: `merchandise_name`, `variant_label`, `gold_price_each` + `line_total` `integer` (D8), `quantity`.

Status allow-list (varchar): `pending_review, approved, ready_for_collection, pickup_overdue, cancellation_requested, collected, shipped, rejected, cancelled`.

**Ledger idempotency (RT-7)**: partial unique index trên `gold_transactions (source_type, source_id, type)` cho `redemption`/`redemption_refund` → duplicate refund fail ở DB.

### Mã đơn (RT-9 — dùng pattern FIN-13, không sequential)

`MRD-YYYYMM-####` kiểu sequential = read-then-insert race (2 SV cùng tính `0001`, insert thứ hai đụng unique → rollback cả money transaction → 500). Repo đã học bài này: dùng random suffix + retry (`CreateFinanceChargeAction:279` `str_pad(random_int(0,999999),6,'0')`, hằng số max-attempts FIN-13). Sinh mã **ngoài/đầu money transaction** để retry đụng độ không rollback công việc Gold/tồn.

### State machine (chốt các gap review)

- Tạo đơn → `pending_review`. Approve → `approved`; Reject (bắt buộc lý do) → `rejected` + hoàn Gold + hoàn tồn.
- Pickup: `approved` → staff nhập location/deadline → `ready_for_collection` → `collected` (staff confirm).
- `pickup_overdue`: staff đánh tay (D7, không cron); thoát: → `ready_for_collection` (gia hạn), → `collected` (đến trễ vẫn nhận), → `cancelled` (hoàn Gold + tồn).
- Shipping: `approved` → staff ship ngoài hệ thống → `shipped` (terminal).
- Hủy: `pending_review` SV hủy thẳng → `cancelled` + hoàn. `approved/ready_for_collection/pickup_overdue` → `cancellation_requested`; staff chấp nhận → `cancelled` + hoàn, từ chối → về `previous_status`. Không hủy khi `collected/shipped`.
- Mọi transition ghi audit (actor, before/after, lý do) + timeline cho SV.

### Concurrency — TẤT CẢ transition, không chỉ tạo đơn (RT-7/RT-15)

**Mọi transition** (không riêng tạo đơn) phải: mở đầu bằng `RedemptionOrder::whereKey($id)->lockForUpdate()->first()` + conditional `UPDATE ... WHERE status = :expected`, abort nếu 0 row affected. Chống 2 staff cùng bấm "Cancel & refund" → hoàn Gold 2 lần.

**Lock order thống nhất (RT-15)**: ví trước → variant sort `merchandise_variant_id ASC`. Áp dụng y hệt ở checkout VÀ refund/cancel. Multi-item cart lock theo thứ tự id, không theo thứ tự SV thêm vào giỏ → tránh deadlock A[7,3] vs B[3,7]. Bọc `DB::transaction($cb, 3)` (retry deadlock built-in Laravel); thân transaction side-effect-free (notification/file bắn `afterCommit`, mẫu `NotificationDomainEventPublisher:44`).

Tạo đơn trong MỘT transaction: (0) check idempotency_key, replay trả đơn cũ; (1) lock ví, check đủ Gold; (2) lock variant (thứ tự id), check available+campus+active+tồn; (3) tạo order+items snapshot; (4) trừ Gold (`redemption`, ref order, before/after); (5) trừ tồn (`redemption_out`, before/after, ref order). Lỗi → rollback. Refund đối xứng: `redemption_refund` Gold+tồn, cùng transaction, cùng lock order, có conditional status guard.

### Actor: CHỈ SV, phụ huynh KHÔNG truy cập gì (D13 — validate)

`routes/api/v1/student.php` group cho phép **parent** hành động thay SV (`either:parent.student.access`, inject qua `student_id`/`X-Student-ID`). User chốt: phụ huynh KHÔNG truy cập Store/Gold của con.
- **Mọi endpoint merchandise (read + write)**: chỉ `student.api.auth`, KHÔNG dùng group `either:parent`. Menu Store không hiện cho parent (portal 3b).
- Test: parent token → 403 trên tất cả endpoint merchandise (list, detail, create, cancel, my orders).
- "Gold History" tab: nếu tái dùng endpoint gold-wallet hiện có mà endpoint đó đang ở group parent-accessible → tab này trong Store chỉ gọi khi actor là SV. Vẫn fix null-guard `GoldTransactionController::show` (`:82-92`) ở Phase 1 (bug thật: parent actor → `Auth::guard('student')->user()` null → ownership check bị bỏ, trả bất kỳ transaction theo id) — độc lập với việc feature này không expose cho parent.

### Rate limit (RT-8 phụ + finding cắt)

Student API rate-limit middleware đang comment-out toàn repo (`routes/api/v1/student.php:35,185`). Thêm `RateLimiter::for('merchandise-checkout')` + `throttle:` active trên create/cancel (mẫu limiter `AppServiceProvider:188-293`). Thêm giới hạn quantity per-line + per-student-per-product ở FormRequest (chống 1 SV quét sạch tồn last-unit).

### Notification (RT-4 — seam hợp lệ, không import chéo module)

`App\Modules\Merchandise` **KHÔNG** được `use App\Modules\Notification\...` — fail `DomainBoundaryArchitectureTest`. Đi qua Shared Contract (`app/Shared/Contracts/Notification/StudentNotificationWriter.php`) hoặc domain-event envelope (`PublishDomainEventAction`, `NotificationDomainEventPublisher:44`).

Registry đóng cần đăng ký per-type (không phải one-liner): `NotificationTypeRegistry` (event_name/category/icon/action), `NotificationUrlRegistry` (`:9-34` — deep-link web+mobile, `merchandise.order` chưa có → link chết nếu quên), `EventIntentMapper` (`match` đóng). Nếu bật email (index default: chưa): `NotificationTemplateTypeKey` cần EmailContentRegistry binding + seed row per campus.

Enumerate 7 type §16.1-16.7 với entry registry + rule người nhận per staff-facing (campus nào, permission nào — order detail chứa `shipping_address` PII, là quyết định lộ dữ liệu). Bắn `afterCommit`.

### Backend API

- Student API: store list (derived availability per campus, D2), product detail, create order, my orders + detail, request/direct cancel. Check "đủ Gold" = balance (không hold — D3/D10). Dùng `getBalance(): int` (Phase 1, không phải string cũ).
- Staff API: queue duyệt, approve/reject, set location/deadline, confirm collected, mark shipped, mark overdue, xử lý cancel, gia hạn. Gate `view/approve/..._redemption_order` (verb_noun, Phase 2) + campus scope Policy resolve từ record (RT-13).
- In-flight order khi SV đổi campus (RT-13): order giữ `campus_id` snapshot; queue ownership theo snapshot (staff campus cũ xử lý). Nêu rõ để đơn không rơi khỏi mọi queue.
- Dashboard §4: Current Gold (= balance int), Total Redeemed (tổng Gold đơn không rejected/cancelled — mặc định index), Redemption Orders (count).

### API contract

Viết `docs/api/student/merchandise.md` (canonical contract) — không có thì portal (3b) không có gì code theo. Envelope theo pattern student API hiện có.

## Phase 3b — Student portal (repo FE/student-nuxt)

Thực hiện TRONG `FE/student-nuxt` theo `docs/portal-repos.md` (impact declaration, portal-status.sh, cập nhật contract, lint/typecheck/build portal riêng, git state tách biệt).

- Menu mới "Merchandise Store": Store (cards + detail + cart + checkout: chọn variant/số lượng/method; shipping → ô nhập địa chỉ), Redemption History (bảng + detail + timeline), Gold History (endpoint gold-wallet hiện có).
- **Cart persistence** (RT unresolved): schema không có cart → cart client-side only. Nêu rõ: reconcile lại tồn/giá tại thời điểm submit (snapshot price + re-check stock trong money transaction 3a); UI xử lý stale-price/stale-stock window (báo lỗi, refresh).

## Validation

- 3a Feature tests: tạo đơn atomic (fail giữa chừng → rollback đủ 3), refund đối xứng, **double-refund bị chặn** (conditional status guard + partial unique ledger — RT-7), idempotency replay trả đơn cũ (RT-8), state machine transitions hợp lệ/không, campus scope cross-campus 403, **parent token 403 trên write** (RT-5), snapshot bất biến khi đổi giá/tên sau đặt.
- Race concurrency: theo quyết định Phase 1 RT-10 (second-connection hoặc invariant DB + arch test) — không dựa RefreshDatabase để "chứng minh" lock.
- 3b: portal lint/typecheck/build.
