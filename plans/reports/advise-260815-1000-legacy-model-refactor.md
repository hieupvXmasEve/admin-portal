# Advise — Refactor legacy Model-first sang kiến trúc module (260815)

Interview 5 vòng, reframing đã xác nhận. Bằng chứng scout: audit `plans/reports/audit-260810-0004-legacy-code-inventory.md`, arch test `tests/Feature/Architecture/DeprecatedModelShimArchTest.php`, lịch sử sweep 30 model (24 shim đã xoá).

## Reframing (đã xác nhận)

- **Vấn đề**: ~70 model ALIVE-LEGACY trong `app/Models` (Model-first, quan hệ Eloquent 2 chiều) cần về đúng module chủ mà không mất thế mạnh transaction + join của monolith 1 DB.
- **Chuẩn chốt**: Mức 3 (lai) — contract cho READ chéo module, event cho WRITE chỉ ở Academic↔Finance (ADR-0026), FK + join nội bộ module giữ nguyên.
- **Không làm**: DDD purity toàn phần, chuẩn bị microservice, đổi schema/bỏ FK, viết lại quan hệ nội bộ module đang chạy tốt.

## 1. Verdict

Hướng refactor hiện tại của repo ĐÚNG, không over-engineered — vấn đề không phải "chọn kiến trúc" mà là "chạy nốt máy đã chạy". Repo đã có recipe proven (30 model sweep, contract pattern, arch test tự thu nhỏ). Quan hệ 2 chiều không phải chướng ngại kỹ thuật: chiều nào cũng phá được bằng contract-read, cái khó duy nhất là THỨ TỰ (phá cạnh trỏ vào hub trước, chuyển hub sau). Rủi ro thật duy nhất đang nằm ở split-brain `EgcRetakeDiscountLink` — đó là bug đang sống, fix trước mọi thứ khác.

## 2. Nên làm (theo thứ tự)

1. **Fix split-brain `EgcRetakeDiscountLink`** — 2 class sống song song ở `app/Models` và `Modules/Finance`, không alias. Finance authoritative: chuyển caller còn lại, xoá bản legacy (hoặc alias tạm nếu caller nhiều).
2. **Dọn nốt 6 shim còn lại** (FormResponse, GoldTransaction, QueryReply, QueryTicket, UploadRecord; ApplicationDocument chờ fraud-design review) — đóng sổ sweep cũ trước khi mở domain mới.
3. **Chạy ~70 model còn lại theo domain batch**, dùng đúng recipe cũ cho MỖI batch:
   - move model → module chủ, để `class_alias` shim ở `app/Models`;
   - thêm batch vào `ALL_MIGRATED_MODELS` + allow-list của `DeprecatedModelShimArchTest`;
   - sweep caller (relation chéo module → xoá, thay bằng contract `app/Shared/Contracts/...` + impl Eloquent trong module chủ + bind ServiceProvider);
   - shrink allow-list, xoá shim, thêm placement arch test cho boundary mới.
4. **Thứ tự domain: ít-coupling trước, Student hub CUỐI.** Gợi ý: Admissions (4) → Finance-còn-lại (8) → StudentRegistry-vệ-tinh (16) → Academic (28, tách batch nhỏ: Curriculum / Assessment / Attendance / Exam) → Student + vệ tinh sát nhất. Mỗi lần một domain khác bỏ import `App\Models\Student` là cạnh vào hub giảm; đến lượt Student thì việc chuyển gần như chỉ là đổi namespace.
5. **Quan hệ 2 chiều — quy tắc quyết định** (đây là "pattern chung" bạn hỏi):
   - Hai model cùng module chủ → chuyển cùng nhau, GIỮ nguyên quan hệ 2 chiều. Không phá gì cả.
   - Khác module, chiều READ → xoá relation phía consumer, giữ cột FK, đọc qua contract (`StudentDirectory::findSummary($id)` kiểu Room/Building đã làm).
   - Khác module, chiều WRITE → gọi Action/Service công khai của module chủ; CHỈ Academic↔Finance dùng event theo ADR-0026.
   - Màn hình tổng hợp/report → query class riêng được phép join DB (như `GetCourseOfferingSurveyQuery`), không chồng contract.

## 3. Không nên làm

- **Không nâng chuẩn lên purity toàn phần** (IDs-only, event mọi nơi): monolith 1 DB + domain nhiều luồng tiền → mất transaction xuyên module, gánh outbox/idempotency, N contract call thay 1 join (đúng kiểu sự cố fee-monitor 4GB). Chỉ đáng khi có roadmap tách deploy — hiện không có.
- **Không chuyển Student sớm** để "giải quyết cái khó nhất trước" — hub nhiều cạnh vào nhất, chuyển sớm nghĩa là sweep toàn codebase một lần; chuyển cuối thì gần như free.
- **Không big-bang** cả 70 model một PR/plan. Mỗi domain batch 1 plan, arch test giữ trạng thái giữa các batch.
- **Không thêm contract khi cả 2 model cùng module** — contract chỉ tồn tại tại boundary thật.
- **Không đụng schema**: giữ FK, giữ tên bảng. Đây là refactor namespace + dependency, không phải migration dữ liệu.
- **Không mở ApplicationDocument shim** trước khi review fraud-path xong (đã ghi nhận gate cũ).

## 4. Có thể tốt hơn / rẻ hơn

1. **Rẻ nhất, impact cao nhất**: xoá 6 nhóm DEAD trong audit (GraduationApplication, ProgramMappingService, 2 controller, 2 route file) — diff âm, 0 rủi ro, làm chung PR đầu.
2. **Grep-driven batch cut**: trước mỗi batch, `grep -c 'App\\Models\\X'` để xếp model theo số caller — model 0-5 caller đi trước trong batch, hub để cuối batch. Máy móc, không cần phán đoán.
3. **Tận dụng importer-baseline của arch test làm tracker tiến độ** — không cần bảng tiến độ riêng; con số trong test LÀ tiến độ.
4. **9 model ALIVE-CORE (Campus, User-adjacent, Permission/Role...) có thể ở lại `app/Models` vĩnh viễn** nếu chúng thật sự cross-cutting — đừng ép 100% rỗng nếu 9 cái này phục vụ mọi module. Quyết ở batch cuối, đừng quyết bây giờ.

## 5. My take + lộ trình

Đây là bài toán vận hành, không phải bài toán thiết kế. Kiến trúc đích đã đúng và đã được chứng minh bằng 24 shim đã xoá; đừng phát minh thêm. Lộ trình:

1. PR-0: DEAD cleanup + fix split-brain EgcRetakeDiscountLink.
2. PR-1..n: đóng 5 shim còn lại (trừ ApplicationDocument).
3. Mỗi domain batch = 1 plan (`ak:plan`), theo template recipe ở mục 2.3; thứ tự Admissions → Finance → StudentRegistry → Academic (4 sub-batch) → Student-hub.
4. Sau mỗi batch: chạy arch tests + test module bị đụng (lưu ý baseline đỏ có sẵn: Finance 26 fail, Academic attendance CHECK-constraint — đừng nhầm là regression).
5. Batch cuối: quyết số phận 9 ALIVE-CORE, cập nhật `docs/system-architecture.md` một lần.

## 6. Benefits

- Về một chuẩn duy nhất: mọi model có module chủ, boundary có arch test gác — placement bug không tái sinh.
- Giữ nguyên thế mạnh monolith: transaction tiền bạc + join report vẫn 1 câu SQL.
- Tiến độ đo được bằng test (allow-list → 0), không cần tracking thủ công.
- Student hub chuyển cuối → PR to nhất của cả chương trình lại là PR dễ nhất.

## 7. Trade-offs

- Mức 3 nghĩa là service ĐƯỢC PHÉP gọi ghi chéo module (trừ Academic↔Finance) — coupling ghi vẫn tồn tại, có ý thức. Nếu sau này thật sự tách service, phần này phải làm lại thành event.
- Shim `class_alias` sống tạm trong lúc sweep — codebase có 2 tên cho 1 class trong thời gian batch chạy; arch test là thứ duy nhất giữ kỷ luật.
- Student để cuối nghĩa là `App\Models\Student` còn sống lâu nhất — mọi batch trước phải kỷ luật không thêm import mới vào nó (importer baseline gác việc này).
- 9 ALIVE-CORE có thể khiến `app/Models` không bao giờ rỗng 100% — chấp nhận, miễn là có quyết định ghi lại.

## 8. Work checklist

- [ ] PR-0a: xoá 6 nhóm DEAD theo audit (model, service, 2 controller, 2 route file)
- [ ] PR-0b: fix split-brain `EgcRetakeDiscountLink` — Finance authoritative, xoá/alias bản `app/Models`
- [ ] Đóng 5 shim: FormResponse, GoldTransaction, QueryReply, QueryTicket, UploadRecord (sweep caller → shrink allow-list → xoá)
- [ ] Hoàn tất fraud-design review → đóng shim ApplicationDocument
- [ ] Batch Admissions (4 model): recipe đầy đủ + placement arch test
- [ ] Batch Finance còn lại (8 model): chú ý DeferCase↔FinanceCharge, ScholarshipDefinition
- [ ] Batch StudentRegistry vệ tinh (16 model, chưa gồm Student)
- [ ] Batch Academic (28 model, chia 4 sub-batch: Curriculum / Assessment / Attendance / Exam; ClassSession/ExamRoomSlot→Room qua Facilities contract sẵn có)
- [ ] Batch Student hub + vệ tinh sát nhất (sau khi mọi domain khác hết import `App\Models\Student`)
- [ ] Quyết định 9 ALIVE-CORE (ở lại hay về module) — ghi ADR ngắn
- [ ] Cập nhật `docs/system-architecture.md` một lần ở cuối

## 9. Success metrics

- `grep -rl 'App\\Models\\' app/Modules --include='*.php' | wc -l` = 0 (trừ file được grandfather trong baseline, đích cuối = 0).
- `DeprecatedModelShimArchTest`: allow-list shim = [] và importer baseline = [] (test tự khai báo đây là tín hiệu hoàn tất).
- `ls app/Models/*.php | wc -l` ≤ 9 (chỉ ALIVE-CORE có quyết định ghi lại) — hiện tại: 84.
- Mỗi batch: `./scripts/dev.sh artisan test tests/Feature/Architecture` xanh 100%; test module đụng tới không tăng số fail so với baseline đã ghi (Finance 26 fail / 324 pass).
- 0 file mới xuất hiện dưới `app/Http`, `app/Services`, `app/Models` trong suốt chương trình (placement arch tests gác).

## Unresolved questions

1. 6 shim còn lại: user chưa xác nhận thứ tự/gộp PR — đề xuất mặc định là 5 shim thường trước, ApplicationDocument sau review.
2. Số phận 9 model ALIVE-CORE (Campus, Permission/Role...) — để mở có chủ đích, quyết ở batch cuối.
3. Timeline/nhịp độ (bao nhiêu batch mỗi tuần) — không hỏi trong interview vì không đổi lời khuyên; đặt khi `ak:plan` từng batch.
