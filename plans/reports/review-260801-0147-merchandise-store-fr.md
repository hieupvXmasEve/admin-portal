# Review: Merchandise Store — Functional Requirement

Nguồn: Notion "MERCHANDISE STORE – FUNCTIONAL REQUIREMENT REVIEW" (đọc full 2026-08-01, gồm cả code blocks: status enums, flows, permission list).
Đối chiếu codebase tại branch `claude/merchandise-store-review-840d9b`.

## Kết luận

Spec ở mức tốt cho FR phase 1: state machine đơn rõ, ledger + stock movement + audit đều yêu cầu đúng chuẩn (before/after, actor, reference), phạm vi loại trừ (§21) hợp lý. **Nhưng chưa implement được ngay.** Có:

- **1 tiền đề sai về hiện trạng hệ thống** (ledger Gold đã tồn tại, spec tưởng chưa có).
- **3 lỗi thiết kế cần sửa trong spec** (product status trộn trạng thái derived với manual; ngữ nghĩa transfer_hold chưa định nghĩa; transfer pending không có expiry → Gold bị giam vô hạn).
- **1 câu hỏi §20 thực chất là blocker** (địa chỉ ship — không chốt thì không build được phương thức shipping).
- Vài gap state machine + rủi ro kỹ thuật phải xử lý khi implement (race condition ví/tồn kho, DB enum).

## 1. Spec vs hiện trạng codebase

### 1.1. Tiền đề sai: ledger Gold ĐÃ tồn tại

Spec §1: "cần bổ sung lịch sử giao dịch Gold, vì số dư ví hiện tại không đủ để phục vụ audit". Thực tế codebase đã có:

- `student_wallets` (migration `2025_09_19_170750`): `student_id` unique, `balance` decimal(10,2), chỉ `updated_at`.
- `gold_transactions` (migration `2025_10_02_110620`): ledger immutable — `amount`, `type` enum(`earn`,`spend`,`adjust`), `source_type` enum(`event`,`reward`,`manual`), `source_id`, `notes`, `created_at`.
- `app/Services/GoldService.php`: `addGold` / `deductGold` / `adjustBalance` đều ghi ledger + update balance trong `DB::transaction`.
- Gold hiện được cộng từ event attendance (`app/Modules/Engagement/Actions/EventParticipationOperations.php`).

→ Bài toán không phải "tạo ledger" mà là **nâng cấp ledger hiện có** lên yêu cầu §12. Ledger hiện thiếu so với spec:

| Yêu cầu §12 | gold_transactions hiện tại |
|---|---|
| 8 loại giao dịch (reward, transfer_hold/release/out/in, redemption, redemption_refund, manual_adjustment) | 3 loại (earn/spend/adjust), **DB enum** — thêm loại phải ALTER enum |
| Số dư trước / sau giao dịch | Không có |
| Người thực hiện (actor) | Không có cột riêng; `adjustBalance` nhét user id vào `source_id` (đang trộn ngữ nghĩa) |
| Reference nghiệp vụ | `source_type`+`source_id` có, nhưng source_type cũng là DB enum |

Quyết định cần chốt: **mở rộng `gold_transactions`** (migrate enum→varchar, thêm `balance_before`, `balance_after`, `performed_by`, map earn→reward, spend→redemption...) hay tạo bảng ledger mới và freeze bảng cũ. Khuyến nghị: mở rộng bảng hiện có — một nguồn sự thật, tránh lịch sử Gold nằm hai nơi. Đồng thời khớp rule dự án "avoid DB enums, prefer varchar + backend allow-list" (memory note — chính migration này đang vi phạm).

### 1.2. Tái sử dụng được (không cần build mới)

- **Notification**: `app/Modules/Notification/` (NotificationMessage/Delivery/EventOutbox) — đủ cho 8 nhóm notification §16, kể cả deep-link.
- **Audit**: spatie/laravel-activitylog + pattern `AuditableModel` — phủ yêu cầu §18; reason/campus bổ sung qua custom properties.
- **Permission**: hạ tầng permission + middleware `permission:...` sẵn; route admin đã có `gold.adjust` → hai quyền `gold.view_transactions`, `gold.adjust` trong danh sách §17 phải đối chiếu registry hiện có, tránh tạo trùng.
- **Campus scoping**: pattern per-campus staff (CampusUserRole / access grants) sẵn — yêu cầu "Student Services chỉ quản lý campus được phân quyền" là pattern đã có.
- **Module skeleton**: theo `app/Modules/<X>/` (Actions, Models, Http/Api, Queries, routes) — merchandise nên là module mới `app/Modules/Merchandise/` (hoặc Store), không rải vào `app/Services`.

### 1.3. Nợ hiện trạng phải sửa khi đụng vào

- `StudentWallet` cast `balance` là `integer` trong khi cột decimal(10,2) — bug tiềm ẩn về precision. Nhân tiện chốt luôn: **Gold là số nguyên hay thập phân?** Spec không nói. Khuyến nghị integer (đổi cột), tránh 0.5 Gold.
- `GoldService::deductGold` check balance **không lock row** (`firstOrCreate` → if → `decrement`): hai request song song cùng qua check → âm ví. Với transfer + redemption cùng lúc, đây là lỗi thật. Mọi thao tác trừ/giữ Gold phải `lockForUpdate()` trên wallet row. Tương tự cho trừ tồn kho.
- `bulkAddGold` lồng transaction (ổn với Laravel nesting) — không blocker, chỉ ghi nhận.

## 2. Lỗi thiết kế trong spec (nên sửa spec trước khi code)

### 2.1. Product status trộn trạng thái manual với trạng thái derived

§5 định nghĩa status sản phẩm: `available | out_of_stock | coming_soon | hidden | archived` như một trường trạng thái. Nhưng `out_of_stock` **không thể là trạng thái lưu trên sản phẩm**: tồn kho theo variant × campus (§6, §14). Một sản phẩm có thể hết ở HCM nhưng còn ở HN — status cấp product không biểu diễn được, và nếu để staff set tay thì lệch với tồn thật.

Đề xuất sửa: trường status chỉ giữ trạng thái quản trị (`active | coming_soon | hidden | archived`); `available`/`out_of_stock` là **giá trị derived** per campus từ tồn variant, tính lúc render. Card sinh viên vẫn hiển thị đủ 5 nhãn như spec, chỉ khác nguồn.

### 2.2. Ngữ nghĩa transfer_hold chưa định nghĩa — điểm accounting khó nhất

§12–13: hold khi tạo transfer, release khi từ chối/hủy, out+in khi hoàn tất. Nhưng spec không nói hold có trừ `balance` không:

- **Cách A — hold trừ balance luôn**: `transfer_hold` là entry trừ tiền; khi completed, `transfer_out` là entry 0đ (chỉ đánh dấu) hoặc không cần; khi rejected, `transfer_release` cộng lại. Nhược: `transfer_out` trong bảng §12 mô tả "Trừ Gold khi transfer hoàn tất" → mâu thuẫn, trừ hai lần.
- **Cách B — hold không trừ balance, chỉ giữ**: thêm cột `held_balance` trên wallet (available = balance − held). `transfer_hold`/`transfer_release` chỉ thao tác held; `transfer_out` mới trừ balance thật. Khớp đúng mô tả từng loại trong bảng §12 và khớp §4 ("Gold đang bị giữ không tính vào Gold khả dụng").

Khuyến nghị cách B; ledger entry hold/release ghi `balance_before = balance_after` kèm amount giữ. Bất kể chọn cách nào, **phải viết rõ vào spec** — đây là chỗ dễ sinh bug đối soát nhất.

Hệ quả kéo theo: mọi chỗ check "đủ Gold" (redemption §7, transfer §13.1) phải check **available**, không phải balance. `GoldService::hasSufficientBalance` hiện check balance — phải sửa.

### 2.3. Transfer pending không có expiry → Gold bị giam vô hạn

§13: receiver phải xác nhận, không giới hạn gì, và §21 không đề cập auto-expire. Nếu receiver không bao giờ bấm (nghỉ học, không để ý): Gold sender bị giữ vô hạn, chỉ tự cứu bằng cách hủy tay. Kèm rủi ro quấy rối: gửi yêu cầu transfer spam tới sinh viên bất kỳ cùng campus (mỗi request đều bắn notification, không rate limit).

Đề xuất: (a) pending_confirmation tự hủy sau N ngày (vd 7) → release hold + notify; (b) rate limit tạo transfer request (chuẩn security dự án yêu cầu rate limit mọi endpoint — spec "không giới hạn số lần chuyển" nên hiểu là không giới hạn nghiệp vụ, vẫn phải chống spam kỹ thuật); (c) cân nhắc: sinh viên bị khóa transfer có bị chặn **nhận** không — spec chỉ chặn gửi.

## 3. Gap state machine & edge cases

1. **Thoát khỏi `pickup_overdue`**: §10 cho phép gia hạn / hủy, nhưng không định nghĩa transition. Cần: `pickup_overdue → ready_for_collection` (gia hạn), `→ cancelled` (hủy), và **`→ collected`** (sinh viên đến trễ nhưng vẫn nhận — thực tế chắc chắn xảy ra; nếu không cho, staff phải gia hạn trước rồi mới confirm, nên ghi rõ).
2. **Ai/gì bắn `pickup_overdue`**: flow `ready_for_collection ↓ pickup_overdue` + notification §16.7 ngụ ý hệ thống tự đánh dấu sau 14 ngày → cần scheduled job chạy ngày. Spec nói "không tự động hủy/hoàn Gold" nhưng chưa nói rõ việc *đánh dấu* là tự động. Nên ghi rõ: auto-flag, không auto-cancel.
3. **`cancellation_requested` từ `pickup_overdue`**: §11.2 chỉ cho phép từ approved/ready_for_collection, nhưng §10 nói SS "chấp nhận yêu cầu hủy" cho đơn quá hạn → mâu thuẫn nhẹ. Chốt: cho phép request hủy từ cả pickup_overdue.
4. **"Trở về trạng thái trước đó" khi từ chối hủy**: phải lưu `previous_status` trên đơn (hoặc suy từ timeline). Ghi vào data model.
5. **Snapshot chưa đủ**: §15 chỉ yêu cầu snapshot Gold price. Variant/merchandise sửa được (tên, màu, size) và archive được → order line phải snapshot cả **tên sản phẩm + thuộc tính variant** tại thời điểm đổi, không chỉ giá.
6. **Stock movement `redemption_reserved` đặt tên gây hiểu lầm**: §8.1 trừ tồn thật ngay khi tạo đơn (không phải reserve — reject/hủy thì `redemption_refund` cộng lại). Đặt tên `redemption_out` hoặc ghi chú rõ trong spec để dev sau không build hệ reservation riêng.
7. **Hủy là cấp đơn, không cấp dòng**: đơn nhiều sản phẩm nhưng không có partial reject/cancel. Chấp nhận được phase 1, nên ghi rõ "toàn đơn" để tránh hiểu nhầm (liên quan câu hỏi §20 về đổi variant).
8. **Total Redeemed (§4) chưa định nghĩa theo trạng thái**: "đổi thành công" = đơn ở collected/shipped? hay mọi đơn chưa refund (gồm pending_review)? Quyết định ảnh hưởng cả dashboard lẫn report §19. (§20 Q8 mới hỏi Gold vs số món, chưa hỏi theo trạng thái.)
9. **Sinh viên chuyển campus giữa chừng**: đơn pickup ở campus cũ, transfer pending với người khác campus sau khi chuyển. Đề xuất: điều kiện campus check tại thời điểm tạo; đơn/transfer đang chạy giữ nguyên, xử lý tay.
10. **Wallet chưa tồn tại**: `getOrCreateWallet` đã lazy-create — receiver chưa từng có ví vẫn nhận được. OK, chỉ cần giữ pattern.

## 4. Rủi ro kỹ thuật khi implement

- **Race conditions (nghiêm trọng nhất)**: trừ Gold + trừ tồn kho + ghi before/after đều phải serialize per-row (`lockForUpdate` ví và inventory row trong cùng transaction). Không lock thì `balance_before/after` trong ledger cũng sai chứ không riêng gì âm ví. GoldService hiện tại chưa lock (đã xác nhận trong code).
- **Tồn kho không âm**: ngoài check ứng dụng, thêm ràng buộc DB (unsigned + CHECK hoặc conditional update `WHERE quantity >= ?`) làm lưới cuối.
- **DB enum**: không lặp lại lỗi của `gold_transactions` — mọi bảng mới (order status, transfer status, movement type, ledger type) dùng varchar + allow-list backend theo rule dự án.
- **Thứ tự transaction §8.1**: tạo order → trừ Gold → trừ stock trong một DB transaction — đúng; lưu ý notification bắn **sau commit** (afterCommit) tránh notify cho transaction rollback.
- **Ảnh sản phẩm**: spec yêu cầu quản lý nhiều ảnh — chưa xác nhận hạ tầng media/upload hiện có trong scout này; cần kiểm tra pattern upload hiện hành trước khi thiết kế.

## 5. Bổ sung cho danh sách câu hỏi stakeholder (§20)

§20 đã tốt. Nâng cấp + thêm:

- **Q3 (địa chỉ ship) là blocker, không phải câu hỏi thường**: chưa chốt thì không thể build phương thức shipping — đề xuất phase 1 chỉ pickup, shipping phase 1.5 sau khi chốt.
- Transfer pending có tự hết hạn không? Sau bao nhiêu ngày? (mục 2.3)
- Sinh viên bị khóa transfer có bị chặn nhận Gold không?
- Gold là số nguyên hay thập phân?
- Total Redeemed tính theo trạng thái đơn nào? (mục 3.8)
- Sinh viên đến nhận sau khi quá hạn: staff confirm thẳng hay phải gia hạn trước? (mục 3.1)
- Hiển thị số tồn chính xác cho sinh viên (§5) hay chỉ còn hàng/hết hàng? (tránh sinh viên canh stock)

## 6. Đề xuất phasing

1. **Phase 1a — nền Gold**: migrate `gold_transactions` (varchar types, balance_before/after, performed_by), thêm `held_balance`, sửa cast + lock trong GoldService. Đây là móng của mọi thứ, làm trước, test kỹ.
2. **Phase 1b — catalog + inventory**: merchandise, variant, images, stock movement, admin CRUD + permission.
3. **Phase 1c — redemption (pickup only)**: order, state machine, duyệt/hủy, notification, redemption history.
4. **Phase 2 — transfer Gold**: hold/confirm flow, lock per-student, expiry.
5. **Phase 2+ — shipping method + reports/export**: sau khi chốt Q3/Q5 §20.

Lý do tách transfer khỏi phase 1: redemption tự đứng được, transfer phụ thuộc ngữ nghĩa hold (2.2) — phần dễ sai nhất, không nên ghép chung một đợt release.

## Unresolved questions

1. Mở rộng `gold_transactions` hay tạo bảng ledger mới? (khuyến nghị mở rộng — cần user chốt)
2. Ngữ nghĩa hold: cách A hay B (mục 2.2 — khuyến nghị B: `held_balance`, hold không trừ balance)?
3. Gold integer hay decimal?
4. Transfer pending expiry — có, và bao nhiêu ngày?
5. Hạ tầng upload/media cho ảnh sản phẩm — pattern hiện có là gì (chưa scout tới)?
6. Toàn bộ câu hỏi §20 của spec vẫn chờ stakeholder, trong đó Q3 (địa chỉ ship) chặn phương thức shipping.
