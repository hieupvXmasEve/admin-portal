# Brainstorm: EGC Hub Architecture (chốt 2026-08-11)

## Bối cảnh
- Swinx: mỗi trường 1 deployment riêng (10+ trường, >2000 SV EGC/kỳ). Ngoài ra có trường chạy hệ thống KHÁC (không phải Swinx) cũng học EGC song song.
- EGC dạy chung cross-school: student nhiều trường xếp cùng lớp, đội GV/cán bộ EGC riêng.
- Hiện trạng: xếp lớp + điểm danh bằng Excel → từng trường nhập lại thủ công.
- Canvas: 1 instance chung cho EGC. Chiều điểm: GV chấm trong Canvas → hệ thống kéo về (`CanvasGradeSyncService::syncStudentGradesFromBulk` ghi `AssessmentComponentDetailScore`).

## Quyết định: App riêng (EGC Hub), KHÔNG deploy thêm instance Swinx

User chốt ưu tiên kiến trúc lâu dài, không tính chi phí công.

Lý do:
1. Swinx thiết kế theo giả định "deployment sở hữu toàn bộ vòng đời student" — side effects (progression events, finance hooks, notifications) phải tắt trên hub nếu dùng Swinx → tax vĩnh viễn, mỗi feature mới của Swinx đe dọa hub.
2. Hub KHÔNG cần grading engine: Canvas = nhập điểm, engine tính điểm/progression/finance ở trường (giữ nguyên). Hub chỉ là operations coordinator → domain nhỏ, ổn định.
3. Hub phục vụ consumer dị chủng (Swinx + repo thứ 3) → public API là sản phẩm, bounded context khớp tổ chức (đội EGC riêng, cross-school).

## Kiến trúc

```
EGC Hub (Laravel + Vue 3 + Inertia, app mới)
  - Xếp lớp, lịch học, điểm danh, phân công GV
  - Canvas orchestration: token DUY NHẤT, provision course/enrollment, kéo điểm
  - Public API /v1 + webhooks + docs; multi-tenant theo trường
       ↑ roster (inbound API)        ↓ lịch/điểm danh/điểm/block result
  Swinx x10+ (adapter module)  |  Repo thứ 3 (adapter của họ)
  Student portal trường: KHÔNG ĐỔI (đọc data adapter đã ghi về bảng chuẩn)
  Canvas: chỉ hub đụng; CanvasCourseMapping phía trường nghỉ hưu sau rollout
```

### System of record
- Trường: identity, enrollment, progression, finance, TÍNH điểm cuối
- Hub: lớp EGC, xếp lớp, buổi học, điểm danh, phân công GV
- Canvas: nhập điểm, assignments, submissions

### Public API (contract trung lập domain, không lộ schema Swinx)
- Inbound: `POST /v1/enrollments` — roster, idempotent upsert
- Outbound: `GET /v1/schedules|attendance|scores|block-results` (scope theo trường)
- Webhooks: `session.scheduled`, `attendance.recorded`, `scores.updated`, `block.completed`
- Auth: client credentials per trường, scope cứng; version additive từ /v1
- Nguyên tắc: Swinx adapter dùng ĐÚNG public API, không cửa sau (dogfooding)

### Identity
- Khóa: `(school, student_code)`. Mã các trường thực tế không trùng nhau.
- Validate trùng mã cross-school → CẢNH BÁO (không chặn): flag API response + màn hình review trên hub.
- Mọi payload luôn kèm cặp `school + student_code`.

### Sync
- Tự động queue-based idempotent + nút manual re-sync/backfill (cùng endpoint).
- Trường đẩy lên hub (trường giữ token hub); hub đẩy webhook xuống + cho pull.
- Quy mô: retry, dead-letter, monitor sync trên hub.

## Phía Swinx (adapter module trong repo này)
- Nhận webhook/pull → materialize LỚP ĐẦY ĐỦ vào bảng chuẩn: CourseOffering + ClassSessions + attendance + AssessmentComponentDetailScore — qua service sẵn có, không insert thô.
- Lý do: `CourseCompletionService:197` check `unit_type==='egc'` → `ProcessEgcCourseResultsAction` → `EgcBlock` → `egc_current_level` → Finance. Toàn pipeline treo trên bảng lớp chuẩn → ghi đúng bảng thì KHÔNG SỬA pipeline, rollout an toàn.
- Roster payload: student_code, tên, trường, campus, `egc_starting_level/current_level/total_levels` (đã có trên `program_enrollments`), block fail chờ retake. Không cần module level-test trên hub v1.
- Đẩy roster tự động theo event enrollment EGC (queue), kèm nút backfill.

## Tài khoản EGC staff
- Tạo mới hoàn toàn trên hub. Không SSO liên hệ thống (over-engineering). Email = khóa tham chiếu mềm.

## Non-goals
- Không SSO, không đụng finance/học phí (per trường), không sửa student portal, không gộp deployment.

## Acceptance
- Cán bộ EGC xếp lớp/điểm danh trên hub, hết Excel.
- Điểm + attendance về đúng bảng chuẩn từng trường; progression/finance chạy nguyên.
- Canvas nhận provision + trả điểm qua hub duy nhất.
- Repo thứ 3 tích hợp được chỉ bằng API docs, không cần hỏi team Swinx.
- Trường không bấm gì vẫn không lệch dữ liệu (sync tự động).

## Port từ Swinx sang hub
- Canvas module: `app/Modules/Academic/Delivery/Support/Canvas/` (HttpClient, ApiService, TokenService, sync services) — port gần nguyên.
- UI components điểm danh/lịch từ `resources/js` — copy chọn lọc.

## Chưa chốt (giải quyết khi plan)
1. Thứ tự rollout: pilot trường nào trước, chạy song song Excel bao lâu.
2. Chi tiết webhook retry/signature (HMAC?), rate limit per client.
3. Hub hosting/domain, CI/CD (tái dùng pattern Docker + dev.sh).
4. Lịch học conflict với lịch chính khóa của trường: hub có cần biết lịch trường để tránh trùng giờ không, hay cán bộ tự né? (nghiệp vụ, hỏi user khi plan)
