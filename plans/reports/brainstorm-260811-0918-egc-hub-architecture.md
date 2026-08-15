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

## Bổ sung chốt 2026-08-13 (đợt 2)
- Student trên hub: CHỈ tạo qua roster API từ portal. Hub admin không có quyền tạo/sửa danh tính student (portal = master). Student không login hub — bản ghi phục vụ xếp lớp/điểm danh.
- Hub có 2 role: EGC admin (xếp lớp, lịch, phòng, sync monitor, quản GV) + Lecturer (lớp mình dạy, điểm danh, roster, lịch). Tài khoản GV do EGC admin cấp trên hub.
- Phòng học: trường đẩy danh mục phòng lên hub (`POST /v1/rooms`, reference data theo school/campus). Hub xếp lịch gán phòng thật, tự check trùng phòng nội bộ hub. Session sync về trường sở hữu phòng kèm room reference (lịch phòng trường thấy bận); trường khác nhận địa điểm text. Conflict check với booking nội bộ trường = v2.
- Đổi lịch/đổi phòng: thao tác trên hub → webhook `session.updated` → adapter trường bắn notification sẵn có tới student ảnh hưởng. Hub không nhắn student trực tiếp.
- Các trường CÙNG lịch học kỳ → bỏ gap mapping semester, adapter map block↔semester_id thẳng.

## Bổ sung chốt 2026-08-13
- Lịch học: hub KHÔNG biết lịch trường, cán bộ tự né trùng giờ. Đồng bộ lịch = tính năng tương lai (non-goal v1).
- Enrollment lifecycle status qua inbound API: `active | suspended | deferred | withdrawn` + `reason` (vd `unpaid_tuition`). Hub không bao giờ nhận số tiền — chỉ trạng thái đủ điều kiện.
- Suspend (nợ học phí...): flag trong lớp + chặn điểm danh + deactivate Canvas, GIỮ lịch sử attendance/điểm. Không hard-delete. Loại hẳn khỏi lớp = thao tác tay của cán bộ EGC sau khi thấy flag.
- Kỹ thuật bắt buộc trong plan: sandbox hub + API docs công khai cho bên thứ 3; audit log mọi thao tác ảnh hưởng điểm/điểm danh/xếp lớp; Canvas bulk pull phân trang + throttle; cutover backfill block đang học dở + giai đoạn song song Excel.

## Chưa chốt (giải quyết khi plan)
1. Thứ tự rollout: pilot trường nào trước, chạy song song Excel bao lâu.
2. Chi tiết webhook retry/signature (HMAC?), rate limit per client.
3. Hub hosting/domain, CI/CD (tái dùng pattern Docker + dev.sh).
4. PII cross-school chung 1 DB hub: cần thỏa thuận chia sẻ dữ liệu (pháp lý, ngoài code).

## Gap triage 2026-08-13 — scope v1 bổ sung
Bắt buộc v1:
1. Finalize block: quyền `finalize-block` (permission đơn cấp, không cần 2-tier approve) → review → finalize → mới bắn `block.completed`. Trước finalize, điểm không chảy về trường ở mức block result.
2. Buổi nghỉ/học bù/dạy thay: cancel session, tạo session bù, gán GV thay per buổi (ảnh hưởng export giờ dạy) → bắn `session.updated`.
3. Attendance status: `present | absent | late | excused`. CHỐT: excused vẫn TÍNH LÀ NGHỈ trong attendance_rate — chỉ là note để GV đánh giá thái độ học tập. Payload sync xuống mang status đầy đủ.
4. Event log + replay: `GET /v1/events?since=` cho consumer kéo bù sau downtime dài.
5. Đối soát định kỳ: job đêm so count/checksum hub vs từng trường, lệch → alert trên monitor.
6. Capacity per lớp + cảnh báo đầy (waitlist = v2).
7. Chuyển lớp giữa block, lịch sử attendance đi theo student.
8. Canvas provision GV (role Teacher) bên cạnh student.
9. Báo cáo EGC org (pass rate/attendance theo trường-level-GV) + export Excel mọi danh sách.

Bỏ qua v1 (user không veto): xếp lớp tự động, student chuyển trường link hồ sơ, khóa sổ điểm danh theo thời gian, workflow phúc khảo riêng (re-sync cover).

## Bổ sung chốt 2026-08-13 (đợt 3)
- Giờ dạy GV: hub track + audit export tách riêng trên hub. KHÔNG đẩy về trường, không quan tâm ai trả lương (ngoài scope).
- Stack hub chốt: PHP 8.3+/Laravel 12, MySQL 8, Redis queue + Horizon (kiêm sync monitor), Vue 3 + Inertia, Pest, Docker + dev.sh pattern.
- API auth chốt: Sanctum static API key, MỖI TRƯỜNG 1 KEY RIÊNG (không key chung) — hash trong DB, 2 key active/trường để rotate không downtime, track last_used_at. Consumer = tập đóng server-to-server TLS nên bỏ Passport OAuth2 (YAGNI, tránh bắt bên thứ 3 code token-refresh loop); cân nhắc lại chỉ khi mở API cho integrator lạ. Tải sync (~chục nghìn job/tuần, I/O-bound) << năng lực Laravel queue; nghẽn thật = Canvas rate limit + contract design, không phụ thuộc framework. Tính năng tương lai cần throughput đặc thù → sidecar service qua public API, không đập hub.
