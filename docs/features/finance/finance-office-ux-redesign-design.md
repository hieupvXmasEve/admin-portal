# Finance Office — Thiết kế lại UX (Operator Console)

Ngày: 2026-06-15
Trạng thái: Design proposal (đã chốt qua brainstorming, chờ chuyển sang implementation plan)
Phạm vi: Lớp UI/UX của module Finance (`app/Modules/Finance`). **Không đổi money *write* logic** (các Action ghi tiền, Service tính toán, invariant). **Được phép thêm:** (a) read-model endpoints/Query đọc-only phục vụ cockpit/360/search, (b) action adapter mỏng (controller + FormRequest) bọc lại Action ghi đã có. Tức là làm mới lớp trình bày + bổ sung lớp đọc/điều phối mỏng, **không** chạm vào lõi tính tiền.

---

## 1. Bối cảnh & mục tiêu

Module Finance có ~30 màn hình admin (Inertia/Vue) trải trên 8 vùng nghiệp vụ: Sinh phí (EGC/Major/Non-academic/Retake) · DNG payment gateway · Installments · Payments/Allocation/Settlement · Lifecycle Due Exceptions · Billing Operations · Audit/Integrity · Invoices. UI hiện tại tổ chức **theo loại đối tượng** (Charges, Payments, Invoices, DNG Requests, Webhooks... mỗi thứ một màn), buộc nhân viên vận hành phải tự "ghép" câu chuyện tiền của một sinh viên từ nhiều màn hình rời rạc.

**Mục tiêu:** dựng một *operator console* mới (greenfield UI) lấy "việc cần làm" và "sinh viên" làm trung tâm, giảm số click, tăng độ tin cậy của thao tác hàng loạt & hành động phá hủy, và phơi bày minh bạch những gì backend đang xử lý âm thầm.

**Vì sao greenfield UI (không sửa dần code cũ):** phần khó & nhạy cảm nhất — write logic tính tiền, luồng DNG, và **checker toàn vẹn (15 invariant)** — đã *có sẵn và được bảo vệ* ở backend. (Lưu ý: "được bảo vệ bằng checker" ≠ "dữ liệu luôn sạch" — chính Cockpit được thiết kế để *phơi bày* vi phạm invariant/data-health.) Làm mới lớp trình bày là phương án rủi ro thấp nhất, không viết lại lõi tính tiền.

## 2. Quyết định nền (chốt qua brainstorming)

| # | Quyết định | Giá trị đã chọn |
|---|---|---|
| D1 | Người dùng chính | **Nhân viên vận hành tài chính** (back-office, làm cả ngày trong hệ thống) |
| D2 | Nhịp công việc | **Kết hợp tùy thời điểm** — đầu kỳ thiên batch, giữa/cuối kỳ thiên hàng đợi + tra cứu lẻ |
| D3 | Vùng đau | **Cả 4** (Đối soát/phân bổ · Exceptions/Lifecycle · DNG · Sinh phí batch) → vấn đề là *mô hình tổ chức tổng thể*, không phải từng màn |
| D4 | Hướng kiến trúc | **C — Cockpit + Student 360 + Batch Studio** (kiến trúc lai, điều hướng theo công việc) |
| D5 | Pattern phá hủy an toàn | Giữ nguyên **4 lớp** (chặn → tác động → lý do → ack+audit) |
| D6 | Thẩm mỹ & theme | **Sáng, dày dữ liệu** (Swiss/International), data là nhân vật chính; dark theme là tùy chọn sau |

**Hệ quả thiết kế từ D1–D2:** ưu tiên tốc độ, thao tác hàng loạt, mật độ thông tin cao, ít click; "việc cần làm" phải nổi bật; trang chủ là *Home/Hôm nay* thích ứng theo giai đoạn kỳ + ô tìm sinh viên toàn cục luôn sẵn.

## 3. Kiến trúc thông tin & điều hướng

### 3.1. App shell
Top bar **luôn hiện** trên mọi màn:
- **Ô tìm sinh viên toàn cục (⌘K):** mã SV / tên / số invoice / item_id DNG → nhảy thẳng Student 360. Cửa vào số 1.
- **Bộ chọn Kỳ học:** dữ liệu *gắn-kỳ* (sinh phí, due calendar, dashboard theo kỳ) theo kỳ đang chọn; đổi ở một chỗ duy nhất.
  - **Quy tắc không-che-việc-cấp-bách:** các vấn đề *không sạch theo kỳ* — webhook DNG lỗi, tiền chưa phân bổ, invariant critical, audit drift — **không bị ẩn khi đổi kỳ**. Mỗi hàng đợi/số liệu mang **scope badge riêng** ("toàn hệ thống" / "kỳ HK2'25" / "đa kỳ") để NV biết phạm vi mình đang nhìn; bộ chọn kỳ chỉ lọc đúng nhóm gắn-kỳ. **Khóa nghĩa "toàn hệ thống" = "trong campus hiện tại"**, trừ user có quyền all-campus (badge phải nói rõ, tránh hiểu nhầm là cross-campus).
  - **Contract state (cần ở plan):** kỳ đang chọn **sync qua query param + shared Inertia prop** (một nguồn, không để mỗi page tự giữ filter kỳ riêng như hiện nay). Mỗi hàng đợi/widget **khai báo cờ `obeys_semester: true|false`** → engine biết có lọc theo topbar hay không. Đây là việc mới (hiện nhiều page tự có filter kỳ độc lập).
- **Campus + User:** ngữ cảnh phân quyền (backend đã scope theo campus).

### 3.2. Sidebar — tổ chức theo CÔNG VIỆC (5 nhóm thay cho ~20 mục theo đối tượng)

| Nhóm mới | Gom các màn hiện có | Vai trò |
|---|---|---|
| **⌂ Hôm nay** | Dashboard Operations + tổng hợp hàng đợi (mới) | Trang đáp khi đăng nhập — triage |
| **＄ Sinh phí** | Major Charges · Batch Charges · EGC (Generate, Block Results, Retake Adjustments, Carry-forward) · Non-academic | Mọi việc *tạo phí* → Batch Studio |
| **💳 Thu & Đối soát** | DNG Worklist · DNG Payment Requests · DNG Webhook Events · Settlement Worklist · Auto-allocate · Payments · Nhắc nợ/Due Calendar | Mọi việc *thu tiền & khớp nợ* |
| **🛟 Ngoại lệ** | Exceptions Queue · Lifecycle Exceptions · Lifecycle History | Mọi *ca bất thường* cần quyết định |
| **🔎 Tra cứu & Audit** | Audit Workspace · Charge Ledger (global) · Invoices | *Tra soát* — chỉ-đọc, đối chiếu |

**Nguyên tắc:**
- **Student 360 không phải mục sidebar** — là đích đến từ ô tìm kiếm và từ click bất kỳ dòng nào.
- Danh sách theo-đối-tượng (Payments, Invoices, DNG Requests...) bị "giáng cấp" thành bộ lọc/tra cứu bên trong nhóm, không còn là điểm xuất phát chính.
- Detail backend routes của Charges, Invoices, Payments và DNG Requests **không bị xoá** khi giáng cấp UI; chúng là secondary pages cho deep-link, audit evidence và repair có phân quyền.
- **Hai trục di chuyển song hành:** trục "việc cần làm" (Hôm nay → hàng đợi → việc) và trục "theo sinh viên" (tìm SV → 360). Mỗi điểm đau có mặt ở cả hai trục (vd webhook lỗi ở cả Cockpit lẫn tab DNG của 360).

**Bảng migration menu (việc mới — cần đầy đủ ở plan mục 1):** §3.2 mới nêu nhóm-đích; plan phải có **bảng chi tiết `current item → new group → keep | hide | remove | deep-link`** cho từng mục `menu-sidebar.ts` hiện tại, để không sót/không trùng. Lưu ý đặc biệt:
- **`Discounts & Funding`** đang **nằm ngoài** module Finance nhưng **hiển thị trong sidebar Finance** → quyết định rõ: giữ ở Finance, tách ra, hay deep-link. Đừng mặc định gom vào "Sinh phí".
- Các mục trùng (vd "DNG Due Reminders" xuất hiện ở cả Operations lẫn EGC) → hợp nhất về một đích.

## 4. Cockpit "Hôm nay"

### 4.1. Phân tầng ưu tiên (trên → dưới)
1. **Banner CRITICAL** (chỉ hiện khi có): cảnh báo toàn vẹn tiền (invariant critical) cần xử lý ngay.
2. **KPI ribbon (gọn):** Tổng phải thu · Đã thu (%) · SV chưa sinh phí. Là ngữ cảnh, không phải nhân vật chính.
3. **Cột chính "Cần xử lý"** (2/3): các hàng đợi ưu tiên.
4. **Cột phải (1/3):** "Sức khỏe dữ liệu" + "Theo giai đoạn".

### 4.2. "Cần xử lý" — các hàng đợi (bám sát Query backend)
Danh sách hàng đợi: **Webhook DNG lỗi · Tiền chờ phân bổ · DNG đến hạn · Ngoại lệ lifecycle chờ review · Sai sót sinh phí (missing/retake charge) · Installment đẩy thất bại**.

**Giải phẫu cố định mỗi thẻ hàng đợi:**
- Tiêu đề + icon + số đếm + chấm mức độ (🔴 critical / 🟠 cần làm / ⚪ thường)
- Phân rã 1 dòng theo trạng thái thật (vd webhook: retry được / lệch / chết)
- "Cũ nhất X giờ/ngày" — phơi bày phần đuôi, chống việc cũ bị chìm
- Một nút hành động chính

### 4.3. Cơ chế "click 1 việc" → 2 lối thoát
- Click 1 dòng → **Action Panel** (drawer trượt phải): đủ ngữ cảnh để quyết định tại chỗ + hành động nhanh (Retry / Acknowledge / Phân bổ). Xử lý xong → drawer đóng → **vẫn ở nguyên hàng đợi**.
- Trong Action Panel có **[Mở hồ sơ SV ↗]** → Student 360 khi cần đào sâu.

### 4.4. "Sức khỏe dữ liệu" — 15 invariant thành tín hiệu niềm tin
Đưa **15 invariant** (`FinanceIntegrityAuditor`/`FinanceInvariantRegistry`) lên cockpit: critical → đỏ, high → cam, kèm số mẫu vi phạm và lối tắt một click — **mục tiêu** là sang Audit Workspace *lọc theo phát hiện* (hợp đồng drilldown ở dưới, vì route Audit hiện chưa hỗ trợ lọc này). Cộng chỉ số **"Số dư khớp"** = tỷ lệ invoice *không* lệch cache theo **đúng công thức `SettlementService::snapshotDriftsFromCache`** — tức so **cả** `cached_paid_amount` (vs `snapshot.paid`) **và** `cached_total_amount` (vs `snapshot.net`); chỉ cần một trong hai lệch quá `CACHE_DRIFT_TOLERANCE` là tính "lệch". Mẫu số = tổng invoice active trong phạm vi; tử số = invoice không lệch cả hai. (Nếu chỉ muốn đo paid cache thì phải đổi label, đừng gọi "Số dư khớp".) Denominator phải hiện rõ ở tooltip.

**Hợp đồng drilldown (cần thêm — read-only):** Audit Workspace hiện chỉ nhận `q · target_type · target_id · semester_id · billing_cycle_id` (`FinanceAuditWorkspaceController`), **chưa** đủ để "lọc theo phát hiện invariant". Cần một trong hai (chốt khi làm Cockpit, mục 3):
- (a) **mở rộng route Audit** thêm param `finding_code` (vd `INV-3`) + `scope` (toàn hệ thống/kỳ) + tùy chọn `sample_id`; controller resolve sample → target hiện có; **hoặc**
- (b) tách **"Invariant drilldown"** thành read-model riêng (`Queries/Integrity/*`) trả danh sách mẫu vi phạm theo `finding_code`, từ đó link sang target.

Khuyến nghị (a) để tái dùng tối đa Audit đã có; chỉ là endpoint/param đọc-only, đúng scope §1.

**Hai ràng buộc bắt buộc của drilldown:**
1. **Khóa campus:** `scope="toàn hệ thống"` thực chất là **"trong campus hiện tại"**; chỉ user có quyền all-campus mới thấy đa-campus. Badge phải phản ánh đúng (xem §3.1).
2. **Mapping sample-type → audit target:** sample của invariant **không cùng kiểu** (payment / invoice / charge / webhook event / pivot row...), trong khi Audit chỉ nhận `student|invoice|payment|charge|dng` (`FinanceAuditSearchRequest`). Plan phải kèm **bảng `INV-x → sample id type → audit target`**; sample nào không map được (vd webhook event, pivot row) thì drilldown trỏ tới **list view phù hợp** thay vì Audit graph. Ví dụ khởi điểm:

   | Invariant | Sample id type (theo registry) | Đích drilldown |
   |---|---|---|
   | INV-1/10 | payment.id | Audit `target_type=payment` |
   | INV-2/6/9 | invoice.id | Audit `target_type=invoice` |
   | INV-3/8/13 | charge.id (`fc.id`) | Audit `target_type=charge` |
   | INV-4 | **invoice_line.id** (`il.id`) | resolver `il → charge/invoice` (chưa map thẳng) |
   | INV-5 | **invoice_discount.id** (`idc.id`) | resolver `idc → invoice/affected lines` (chưa map thẳng) |
   | INV-7 | student.id (scholarship) | Audit `target_type=student` |
   | INV-11 | webhook event.id | DNG Webhook Events list (không phải Audit) |
   | INV-12 | **dng_payment_request.id** (`dpr.id`) | Audit `target_type=dng` |
   | INV-14/15 | dng request / pivot | Audit `target_type=dng` (pivot → resolve về dng) |

   *(Đã đối chiếu `FinanceInvariantRegistry`: INV-4=`il.id`, INV-5=`idc.id`, INV-12=`dpr.id` — không map thẳng sang payment/charge nếu chưa có resolver tương ứng.)*

### 4.5. "Theo giai đoạn" — lối tắt thích ứng
Cockpit đọc mốc thời gian kỳ/billing cycle để đoán giai đoạn:
- **Đầu kỳ** → nổi bật *Sinh phí hàng loạt*, *Đẩy DNG hàng loạt*.
- **Giữa/cuối kỳ** → nổi bật *Nhắc nợ*, *Đối soát*, *Phân bổ*.
- Có nút **chuyển giai đoạn thủ công**.

## 5. Student 360

### 5.1. Bố cục: Header (sticky) → 4 thẻ trạng thái → Sổ cái/Dòng thời gian

### 5.2. Header
- **Chip trạng thái kép:** trạng thái học vụ (đang học/...) **và** cờ lifecycle (bảo lưu / thôi học / chuyển trường) — quyết định "có được nhắc nợ/hủy DNG không".
- **4 số dư cốt lõi** từ `SettlementService` (nguồn sự thật): phải thu · đã thu · còn nợ · **dư chưa khớp** (tiền đã vào nhưng chưa phân bổ — tín hiệu hành động).
- **"Thao tác ▾":** gom mọi việc với 1 SV — Tạo phí · Đẩy DNG · Ghi nhận thanh toán · Phân bổ · Nhắc nợ · Tách trả góp.
  - ⚠️ **Một số action chưa có route web sống, phải tạo adapter mới (không reuse route cũ):** "Ghi nhận thanh toán" — `/finance/payments/create` đã bị gỡ (`routes/web.php:197`); cần action adapter + FormRequest mới. Mỗi mục trong menu phải map tới route thật hoặc được đánh dấu "cần tạo mới" ở plan.

### 5.3. Hàng 4 thẻ trạng thái (ảnh chụp nhanh đúng 4 vùng đau + lối vào hành động tại chỗ)

| Thẻ | Trả lời câu hỏi | Hành động nhanh |
|---|---|---|
| **Số dư & phân bổ** | SV này có tiền treo chưa khớp nợ không? | Phân bổ ngay (mở preview khớp) |
| **DNG hiện tại** | DNG đang ở bước nào, chờ gì, lỗi gì? | Chi tiết / Đối soát / Retry |
| **Trả góp** | Còn mấy kỳ, kỳ tới đẩy chưa? | Đẩy kỳ tới |
| **Ngoại lệ** | SV có thuộc diện cần thận trọng? | Review (acknowledge/resolve) |

⚠️ **"Phân bổ ngay → preview khớp" cần tách 2 luồng (hiện đang lẫn):** *manual allocate* hiện **post thẳng** vào payment, không có preview (`PaymentController:196`); còn *preview* hiện chỉ tồn tại cho **auto-allocate** (JSON legacy). Plan phải định nghĩa rõ: (1) **manual allocate preview** — read-model mới cho "1 payment này sẽ khớp vào charge nào"; (2) **auto-allocate preview** — đã có, nhưng đang JSON legacy (xem §8 migrate). Không gộp hai cái làm một.

### 5.4. Cột chính — 2 lăng kính cho cùng một sự thật
- **Sổ cái (mặc định):** gom *Kỳ → Invoice → dòng phí*, mỗi dòng hiện amount/đã trả/giảm/còn + trạng thái, bung ra thấy *trả góp* và *phân bổ*. Trả lời "đúng chưa, còn nợ gì". Bám money-graph + snapshot.
- **Dòng thời gian:** chuỗi sự kiện theo thời gian có dấu (+/−), giữ *reversal* là số âm. Trả lời "chuyện gì đã xảy ra". Bám `FinanceLedgerTimelineBuilder`.

### 5.5. Trực quan hóa DNG 2-call (state stepper)
Trong thẻ DNG và khi bung 1 DNG ở sổ cái:
```
● Tạo ─► ● Đẩy DNG ─► ◐ Đã trả (chưa h.đơn) ─► ○ Đã trả (có h.đơn) ─► ○ Đối soát
 Call 1 (paid_uninvoiced)            Call 2 (paid_invoiced)
```
Một cái nhìn biết *đang ở bước nào · chờ gì · trễ/lỗi không · nút gì khả dụng* (Retry khi webhook lỗi; Hủy kèm lý do chặn nếu có).

### 5.6. Nối hai trục — "đáp xuống có tiêu điểm"
360 nhận tham số `focus=<loại>:<id>` (vd `focus=dng:7781`): mở đúng thẻ, cuộn tới & tô sáng đối tượng, nút hành động sẵn sàng. Hàng đợi và 360 là hai lối vào cùng một việc.

## 6. Batch Studio

### 6.1. Một khuôn wizard 4 bước dùng chung cho MỌI việc hàng loạt
`① Thiết lập → ② Xem trước → ③ Xác nhận → ④ Kết quả`, với stepper trên đầu và **thanh tổng sticky** (số liệu chạy + nút Tiếp). Áp dụng cho: sinh phí, đẩy DNG, nhắc nợ.

### 6.2. ① Thiết lập
- Kỳ học (mặc định theo top bar) · Loại phí · Phạm vi SV (Tất cả đủ điều kiện / **Danh sách CSV** / Theo bộ lọc).
- **Giới hạn batch & chunking:** đẩy DNG hiện giới hạn **tối đa 100 SV/request** (`StoreBatchDngFromChargesRequest:23`). UI phải **chunk** batch lớn (chia lô + tiến độ theo lô) và nêu rõ ngưỡng cho mỗi loại batch — không để user submit vượt rồi nhận 422.
- Non-academic: tải CSV + *Tải mẫu* + kiểm tra định dạng mã SV khi upload.
- Đẩy DNG: chọn fee_type + **(thận trọng) ghi đè số tiền**. Nhắc nợ: chọn SV/PH + mẫu email.
  - ⚠️ **Override là luồng nguy hiểm, không mô tả nhẹ:** nếu số ghi đè **lệch** computed sum (≥1 VND), backend coi là **ad-hoc** và **bỏ installment linkage** (`CreateBatchDngFromChargesAction:173`) → khoản này **không auto-settle installment**, phải **đối soát thủ công**. UI phải cảnh báo rõ điều này khi user nhập số lệch, không để hiểu là "chỉnh số cho tiện".

### 6.3. ② Xem trước (ngôi sao)
**Cam kết niềm tin:** preview chạy **cùng bộ máy tính** với lúc chạy thật (chung resolver học bổng/voucher/phí, vd `ScholarshipDiscountResolver`) → con số preview = con số commit, không lệch.

**Hợp đồng an toàn preview (BẮT BUỘC — chống lệch giữa lúc xem và lúc chạy):** giữa preview và commit, dữ liệu nguồn (học bổng/voucher/phí/đăng ký) có thể đổi → "đã rà preview" trở nên vô nghĩa nếu không khóa. Cơ chế **mượn tiền lệ** *Student Action Import* (bind `user_id + preview_token + file_hash + shared_upload_record_id` — tức token + hash *file upload*), **nhưng finance nâng cấp** thành **hash tập kết quả đã resolve** + recompute-compare (mạnh hơn vì batch finance không phải lúc nào cũng từ file upload, và dữ liệu nguồn động hơn):
1. Bước ② trả về **preview token** gắn với: `user_id` + tham số scope (kỳ/loại phí/phạm vi) + **content hash trên canonical resolved payload** — KHÔNG chỉ amount/label (đổi nguồn nhưng ra cùng số tiền vẫn phải bị phát hiện). Mỗi dòng đưa vào hash đầy đủ: `student_id · semester_id · charge_type · source_type/source_id · fee config fingerprint · scholarship_id/voucher_id (đã áp) · gross/discount/net · idempotency key · warning_codes`. **Lưu ý:** `TuitionPlan`/`TuitionPlanTerm` **không có cột version** → "fee config fingerprint" = `config_id + updated_at` (hoặc hash canonical của bộ config/terms áp dụng), không giả định có trường `version`. Hash toàn tập (sorted) → một fingerprint duy nhất.
2. Token lưu **hash *từng dòng*** (per-line), không chỉ một fingerprint toàn tập — vì user có thể **loại trừ cảnh báo** ở bước ③ (so hash toàn tập sẽ luôn fail). Commit **gửi token + đúng subset dòng đã chọn**.
3. Backend **recompute-and-compare trên đúng subset**: resolve lại từng dòng được chọn, so per-line hash. **Khớp toàn bộ subset** → commit. **Bất kỳ dòng nào lệch** → chặn commit, hiện diff "X dòng đã thay đổi từ lúc xem", buộc xem lại preview. Không bao giờ ghi mù theo snapshot cũ.
4. Token **dùng một lần**, có TTL ngắn (vd 30 phút) — chống commit lại từ tab cũ.

- **Phân loại kiểu "diff" 4 nhãn:** 🟢 Tạo mới / 🔵 Cập nhật-gộp / ⚪ Bỏ qua (+lý do) / 🟠 Cảnh báo (+lý do).
- **Lọc nhanh** ("chỉ cảnh báo / chỉ bỏ qua"), tìm 1 SV, **Xuất Excel** preview (`GenerateChargesPreviewExport`).

### 6.4. ③ Xác nhận (chốt an toàn)
- Tóm tắt 1 dòng (số phí · tổng tiền · số SV · bỏ qua · cảnh báo).
- **Cho loại trừ trước khi chạy:** bỏ chọn nhóm 🟠 để xử lý riêng, chỉ commit phần sạch.
- Batch lớn → tick xác nhận chủ động ("Tôi đã rà preview").
- **Trấn an chạy lại — *scope theo từng loại batch* (không nói chung chung):**
  - *Sinh phí:* an toàn **với rerun tuần tự** — phí trùng tự bỏ qua nhờ **check-then-create** (`GenerateMajorChargesAction:66,89`). ⚠️ Đây **không** phải đảm bảo concurrency: không có lock/unique theo student+kỳ+loại → chạy song song có thể tạo trùng. UI phải tránh submit batch chồng nhau (disable khi đang chạy).
  - *Đẩy DNG:* chạy lại **có thể hủy DNG cũ** cùng fee_type+SV rồi tạo DNG mới (`CreateBatchDngFromChargesAction:203`) — KHÔNG vô hại, phải cảnh báo.
  - *Nhắc nợ:* chạy lại **gửi lại email** — cần chặn double-send (theo `last_reminder_at`/xác nhận).

### 6.5. ④ Kết quả (hành động được)
⚠️ **Khớp atomicity hiện tại — *single transaction envelope*, KHÔNG phải all-or-nothing:** `GenerateBatchChargesAction` (`:90→:377`) và `GenerateMajorChargesAction` (`:62→:134`) mở **một transaction cho cả batch**, nhưng **lỗi từng SV được catch & skip** ở inner try/catch (`:93`/`:372`) rồi vẫn `commit` cuối (`:377`) → **phần thành công vẫn được ghi**; chỉ rollback toàn batch khi lỗi *thoát* inner catch (bất thường). Nên:
- **v1:** submit **đồng bộ** → màn Kết quả hiện **summary** (✅ thành công / ⚪ bỏ qua / 🔴 lỗi) + tải báo cáo; mỗi lỗi nối về Student 360. *Giữ nguyên mô hình single-transaction-envelope hiện có* (không tự ý đổi sang per-SV transaction).
- **Story riêng (sau):** chuyển sang **per-student transaction + tiến trình trực tiếp** (chunk/queue) — đây là thay đổi atomicity backend, phải có spec riêng, không gộp vào đợt UI này.

⚠️ **Atomicity ở trên chỉ đúng cho *sinh phí*. DNG & nhắc nợ KHÁC hẳn:** `CreateBatchDngFromChargesAction` xử lý **per-student** và **có thể partial success** (`:68`, `:114`); nhắc nợ cũng per-recipient. Vì vậy màn Kết quả của DNG/nhắc nợ phải định nghĩa **riêng**: trạng thái từng SV (thành công/lỗi/bỏ qua), **retry đúng subset lỗi** (không chạy lại cả lô), và xử lý partial-failure rõ ràng — khác mô hình *single transaction envelope* của sinh phí (DNG là per-student transaction thực sự, partial success ở tầng commit chứ không chỉ ở tầng catch).

### 6.6. Ba việc batch dùng chung khuôn

| Việc | Bước ① đặc thù | Preview hiện gì |
|---|---|---|
| **Sinh phí** | kỳ + loại phí + phạm vi | phí sẽ tạo/gộp/bỏ + giảm giá |
| **Đẩy DNG** | fee_type + ghi đè tiền (cảnh báo lệch) | SV + số tiền đẩy + **DNG cũ sẽ bị hủy** + cờ "lệch → đối soát thủ công" |
| **Nhắc nợ** | SV/PH + mẫu email | ai được gửi · ai bị bỏ (no email / lifecycle / hết nợ) |

## 7. Bộ pattern dùng chung & ngôn ngữ thị giác

### 7.1. Sáu pattern tái sử dụng

| # | Pattern | Dùng ở đâu | Trị đau gì |
|---|---|---|---|
| 1 | **Sổ cái có dấu** (timeline +/−, giữ reversal âm) | 360 · Audit | "Chuyện gì xảy ra với khoản tiền" |
| 2 | **State stepper** (máy trạng thái trực quan) | Cockpit · 360 · DNG detail | DNG 2-call "đang ở đâu" |
| 3 | **Preview → Confirm → Result** | Batch Studio · mọi việc rủi ro | "Tin preview trước khi chạy" |
| 4 | **Hành động phá hủy an toàn** (4 lớp) | 360 · Ngoại lệ · DNG | "Quyết định hủy/void an toàn" |
| 5 | **Action Panel (drawer)** xử-lý-tại-chỗ | Cockpit hàng đợi | Ít click, không mất ngữ cảnh |
| 6 | **Bảng dữ liệu dày chuẩn** | mọi danh sách | Quét nhanh, thao tác hàng loạt |

### 7.2. Pattern "Hành động phá hủy an toàn" (4 lớp — D5)
1. **Chặn:** hiện blocking reasons từ backend (vd "đã có payment bridged"), ẩn/khóa nút nếu bị chặn (bám `LifecycleDueExceptionRowMapper`).
2. **Tác động:** impact preview cụ thể (vd "Void 2 charge liên kết −4.0tr · Hủy 1 đăng ký học lại") — bám `CancelDngPaymentRequestAction`.
3. **Lý do bắt buộc** (`resolution_reason`).
4. **Ack + audit (sink *tùy luồng*, không nói chung chung):** tick "không hoàn tác"; báo "đã ghi vào lịch sử". **Sink khác nhau:**
   - *Luồng lifecycle* (`ResolveLifecycleDueExceptionAction:143`) → ghi **lifecycle review events**.
   - *DNG cancel thường* từ DNG list/360 (`DngPaymentRequestController`) → **KHÔNG** đi qua lifecycle events; audit bằng **cancel payload/response** lưu trên DNG request + action log/event tương ứng. Plan phải đảm bảo *cả hai* luồng đều có audit trail (đừng giả định mọi cancel đều ghi lifecycle events).

**Phân tầng theo mức nguy hiểm** (đúng `isDestructive()` đã có): Acknowledge (nhẹ, 1 click) ≠ Giữ-nợ/Route-settlement (vừa) ≠ Hủy/Void (nặng, đủ 4 lớp).

**Ma trận action → quyền → blocking reason** (quyền lấy *đúng tên thật* từ `routes/web.php`; `cancel_dng`/`void_charges` ở các phần khác là *action enum*, KHÔNG phải tên quyền):

| Action (UI) | Permission thật | Blocking reason (chặn ở lớp ①) |
|---|---|---|
| **Hủy DNG** (MỘT action; tự void linked charges *nếu có* — `CancelDngPaymentRequestAction:119` luôn void khi tồn tại) | *không có* linked charges → `create_finance_payments`; ***có* linked charges → `create_finance_payments` + `void_finance_charges` + bắt buộc impact/ack** | đã có payment bridged · status ∉ {pending, pushed_to_dng} |
| Retry webhook DNG | `create_finance_payments` | **chỉ** chặn khi event đã `processed` (đã sinh Payment canonical); mọi trạng thái khác — kể cả mismatch/skipped/failed — đều retry được (đúng `RetryDngWebhookEventAction:31` + `Show.vue:58`) |
| Void charge / void invoice line | `void_finance_charges` | charge đã void · đang có DNG chờ thanh toán (phải hủy DNG trước) |
| ↳ *(không phải action riêng)* | Lifecycle enum `cancel_dng` vs `cancel_dng_and_void_linked_charge` = **cùng action trên, phân theo điều kiện có/không linked charges** — UI **không** trình bày như 2 lựa chọn độc lập của user | — |
| Phân bổ / auto-allocate | `allocate_finance_payment` | payment student ≠ invoice student · không còn unapplied |

Nút trong "Thao tác ▾" của Student 360 và trong Action Panel **render theo quyền**: thiếu quyền → ẩn; đủ quyền nhưng có blocking reason → hiện disabled kèm lý do (không bao giờ để nút "bấm được nhưng chắc chắn fail").

**Hai gap cần đóng ở plan (không reuse route cũ):**
- ⚠️ *Gap quyền:* route cancel hiện **chỉ** gate `create_finance_payments` (`routes/web.php:224`) dù action có thể void charge bên trong → **action adapter mới phải thêm gate `void_finance_charges`** khi DNG có linked charges.
- ⚠️ *Gap UX:* DNG cancel hiện là **confirm 1 bước** (`DngPaymentRequests/Index.vue:159`), chưa có reason/impact/ack. Pattern phá hủy 4 lớp ở đây là **việc mới**: cần thêm **FormRequest + adapter** (reason, impact preview, ack), không tái dùng confirm cũ.
- ⚠️ *Gap quyền — luồng lifecycle:* route `lifecycle-exceptions.resolve` (`routes/web.php:117`) chỉ gate `view_finance_operations_due_calendar`, nhưng action `cancel_dng`/`cancel_dng_and_void_linked_charge` ở đây vẫn gọi `CancelDngPaymentRequestAction` (luôn void linked charges nếu có — `:127`) qua `ResolveLifecycleDueExceptionAction:77`. Plan phải đóng quyền cho **cả luồng lifecycle** (yêu cầu `create_finance_payments` + `void_finance_charges` khi destructive), không chỉ adapter DNG thường.

### 7.3. Bảng dữ liệu dày chuẩn
- Phân trang/sắp xếp/lọc phía server, header dính, số căn phải + tabular figures, chip trạng thái nhất quán.
- Click dòng → 360/chi tiết; chọn nhiều dòng → thao tác hàng loạt (nối vào Batch Studio).
- Trạng thái rỗng/đang tải/lỗi luôn tường minh (đúng DoD trong `docs/design-guidelines.md`).

### 7.4. Hệ màu ngữ nghĩa (1 nghĩa duy nhất toàn app)

| Màu | Nghĩa | Ví dụ |
|---|---|---|
| 🔴 Đỏ | Chặn / nguy cấp / invariant critical | charge thiếu line, webhook chết |
| 🟠 Cam | Cần hành động | tiền chờ phân bổ, quá hạn, cảnh báo preview |
| 🔵 Xanh dương | Đang xử lý / thông tin | DNG chờ Call 2, "gộp invoice" |
| 🟢 Xanh lá | Tốt / hoàn tất | đã tất toán, số dư khớp |
| ⚪ Xám | Trung tính / bỏ qua | skip có lý do |

Tiền: VND, căn phải, credit/âm tô khác màu + dấu (−). Màu không bao giờ mang 2 nghĩa.

### 7.5. Hướng thẩm mỹ & mật độ (D6)
**Swiss/International + data-dense:** lưới chặt, phân cấp bằng tương phản cỡ chữ & trọng lượng, ít đổ bóng/trang trí, dữ liệu là nhân vật chính. Nền **sáng, tương phản cao** mặc định (phiên dài, đỡ mỏi mắt, in được). Dùng Tailwind v4 tokens hiện có (`resources/css/app.css`). Dark theme là tùy chọn sau.

## 8. Ràng buộc kỹ thuật & hợp đồng frontend

> **SSOT (theo `AGENTS.md`, là single source of truth của repo):** `AGENTS.md` + `docs/rules/*` + `docs/inertiajs-vue-info.md` + `docs/design-guidelines.md`. Skill `swinx-frontend` chỉ là *historical shorthand*, **không override docs**; `.claude/`/`.agents/` không được dùng làm workflow input (AGENTS.md §Retired Workflows). Khi tài liệu này lệch docs → **docs thắng**.

**Stack & nền:** Vue 3 + TS + Inertia v3 + Tailwind v4. Layout `resources/js/layouts/AppLayout.vue`; cập nhật `resources/js/constants/menu-sidebar.ts` sang IA 5 nhóm, giữ render-theo-quyền. Route qua `route()` helper / `systemRoutes` — **không literal URL** (`docs/rules/frontend.md` §7).

**Hợp đồng form/action — trục phân biệt: *điều hướng Inertia* vs *gọi JSON không điều hướng*:**

| Loại thao tác | Cơ chế (theo docs) | Nguồn |
|---|---|---|
| Form action **web route, có điều hướng** (settlement apply, đẩy/hủy/retry DNG, resolve lifecycle, void, split...) | `useForm` (`@inertiajs/vue3`)/`router.post`; lỗi → `form.errors.*`; backend `FormRequest → Action::run() → Inertia::flash()->back()` | design-guidelines (Inertia page model); frontend.md §flash |
| **Modal/drawer form gọi JSON API, KHÔNG điều hướng** | `vee-validate` + Zod + **`useApi`/`useApiRequest`**; backend `ApiResponse::success()` | design-guidelines §5; `docs/RULES_vue-form-useApi.md`; docs/rules/api-interaction.md |
| **Modal/drawer CRUD theo route** (điều hướng) | route-based modal `<ModalLink>`+`<Modal ref>` + `useForm` | design-guidelines; inertiajs-vue-info |
| **Action Panel** Cockpit / panel chi tiết 360 | Slideover; nếu submit action web-route → `useForm`; nếu chỉ đọc/JSON → `useApi` | inertiajs-vue-info |
| **Phá hủy 4 lớp** (hủy DNG/void: lý do + impact + ack; submit action web-route) | modal/slideover + `useForm` (impact nạp từ controller; blocking + recompute kiểm phía server) | — |
| **Acknowledge nhẹ** (1-click, không nhập liệu) | confirm dialog (Pinia) | — |
| **Đọc JSON / search ⌘K / recompute-compare** | `useApi`/`useApiRequest` — **không `axios`** | docs/rules/api-interaction.md §2; frontend.md §7 |

> Lưu ý transport (theo `frontend.md §5`, *không trộn pattern*): modal/drawer **Inertia có redirect** dùng `useForm` + modal ref (giống form trang); **chỉ** modal/drawer **JSON không điều hướng** mới dùng `useApi`/`useApiRequest`. Repo **cấm `axios` trực tiếp** và **`response()->json()`** (dùng `ApiResponse`). Tình huống "router.post đóng modal chồng" xử lý ở tầng triển khai (theo pattern `@inertiaui/modal-vue`), không phải lý do để chuyển action web-route sang JSON.

**Hợp đồng tải dữ liệu:** danh sách/lọc/sắp xếp/phân trang → **`useDataTable`** (`docs/rules/filtering.md`; không `useInertiaFilters`/`useServerTableQuery`). Prop nặng (audit graph/timeline/warnings, dashboard stats) → `Inertia::defer(fn)` + `<Deferred>`. Cockpit live queues → `usePoll` **có cấu hình rõ:** interval mặc định ~60–120s (không spam query nặng), **backoff khi tab nền** (usePoll tự throttle), và **nút "Làm mới" thủ công**; các query đếm hàng đợi phải nhẹ (đếm/aggregate, không kéo full rows). Danh sách dài → `Inertia::scroll(cursorPaginate)` + `<InfiniteScroll>`. Flash mới → `Inertia::flash()` đọc `page.flash` (giữ tương thích legacy `back()->with()` → `page.props.flash`). Tham chiếu API Inertia v3: `docs/inertiajs-vue-info.md`.

**Read-model mới (theo scope mở rộng P1):** cockpit cần Query tổng hợp hàng đợi; search cần resolver định danh; 360 cần aggregate đọc-only. Tất cả là **Query/endpoint đọc-only** đặt trong `app/Modules/Finance/Queries|routes`, tái dùng tối đa cái đã có (`Preview*Query`, `SettlementService`, `GetFinanceAuditGraphQuery`, `FinanceLedgerTimelineBuilder`, `FinanceInvariantRegistry`, các `List*Query`) — **UI không tính lại logic tiền**.

**Legacy cần migrate (nếu Cockpit/360 tái dùng):** một số endpoint finance còn `response()->json()` thay vì `ApiResponse` — vd auto-allocate preview (`PaymentController:231`), DNG data (`:74`). Nếu surface mới gọi chúng, **plan phải migrate sang `ApiResponse::success()`** (đừng giả định đã chuẩn).

**Route constants (việc mới — bắt buộc):** doc cấm literal URL, nhưng `menu-sidebar.ts` finance hiện **toàn literal** (vd `/finance/audit`, `menu-sidebar.ts:319`) và **chưa có** `financeRoutes` helper. Plan phải có **task tạo finance route constants/Wayfinder** trước khi dựng IA mới.

**Quyền — đặt tên rõ cho từng surface mới (không reuse quyền quá rộng):**
- **Cockpit:** quyền xem riêng (vd `view_finance_cockpit`) — *không* mặc định gộp vào `view_finance_operations_dashboard`; mỗi widget vẫn check quyền nguồn của nó (vd webhook cần `view_finance_dng_webhook_events`).
- **Student 360:** quyền đọc tổng hợp (vd `view_finance_student_overview`); từng action trong "Thao tác ▾" gate theo §7.2.
- **Batch Studio:** gate theo *từng action* (sinh phí `create_finance_charges`/`generate_egc_finance_charges`; đẩy DNG `create_finance_payments`; nhắc nợ theo quyền operations) — không một quyền "batch" chung.
- **Invariant drilldown:** đọc cần `view_finance_audit_workspace`; scope đa-campus cần quyền all-campus.
- **Đường seed/sync (bắt buộc khi thêm quyền mới):** mỗi quyền mới cần task **cập nhật `config/permission.php` + chạy sync permission + cập nhật role mapping** (không chỉ đặt tên trong doc). "all-campus" cũng phải có **tên quyền/role cụ thể** (vd `view_finance_all_campus`) — hiện doc mới nói khái niệm.
- Tên quyền mới là **đề xuất**, chốt khi làm từng story; mọi route vẫn tôn trọng `can:` đã có (ma trận §7.2).

**Portal impact: none** — đợt này chỉ Finance Office web console; không đụng student/lecturer Nuxt portals (theo metadata AGENTS workflow).

## 9. Ngoài phạm vi (non-goals)
- Không đổi **money write logic** (Action ghi tiền, Service tính toán, invariant). *Được phép* thêm Query/endpoint đọc-only + action adapter mỏng bọc Action ghi đã có (xem §1 scope) — nhưng không viết lại lõi tính tiền.
- Không thiết kế cổng sinh viên/phụ huynh trong đợt này (StudentFinanceController API giữ nguyên; chỉ Finance Office console).
- Không di trú auth Finance API (`web`+`auth` → Sanctum/actor) — drift đã biết, xử lý riêng.
- Chưa làm dark theme ở bản đầu.

## 10. Thứ tự triển khai đề xuất (mỗi mục 1 spec → plan riêng)

> **Sửa dependency theo review P3:** global search (mục 1) nhảy tới Student 360, nhưng 360 đầy đủ ở mục 2. Giải: mục 1 dựng **360 route shell tối thiểu** (định danh + 4 số dư từ `SettlementService` + sổ cái cơ bản) làm đích hợp lệ cho search; mục 2 *bồi đắp* shell đó (4 thẻ trạng thái, dòng thời gian, state stepper, hành động phá hủy) — không đổi URL/đích.

1. **App shell + IA 5 nhóm + tìm SV toàn cục + 360 route shell tối thiểu** (nền cho mọi thứ; search có đích thật ngay).
2. **Student 360 đầy đủ** (bồi đắp shell ở mục 1; nhiều pattern dùng chung sinh ra ở đây: sổ cái có dấu, state stepper, hành động phá hủy an toàn).
3. **Cockpit "Hôm nay"** (hàng đợi + sức khỏe dữ liệu + Action Panel slideover; tái dùng pattern từ 360).
4. **Batch Studio** (wizard Preview→Confirm→Result + preview-token contract; sinh phí trước, rồi DNG & nhắc nợ).
5. **Tra cứu & Audit** (ledger/invoice lookup; phần lớn tái dùng pattern sẵn có).

> **Dependency phụ (Cockpit ↔ Audit):** Cockpit (mục 3) cần **invariant drilldown** (§4.4), nhưng "Tra cứu & Audit" ở mục 5. Nếu chọn **option (a) mở rộng route Audit**, phần param `finding_code/scope/sample_id` + bảng sample-type-mapping **tối thiểu phải nằm trong story Cockpit (mục 3)** — không đẩy sang mục 5. Nếu chọn **option (b) read-model riêng**, read-model đó cũng thuộc story Cockpit. Audit graph đầy đủ (mục 5) chỉ là *đích* của drilldown.

## 11. Câu hỏi còn mở
- Quy mô/volume thực tế mỗi kỳ (số SV, số giao dịch DNG/ngày) — để chốt ngưỡng virtualization/pagination cho bảng dày. *Chưa hỏi; sẽ xác nhận ở bước plan của mục 1–2.*
- Cách xác định "giai đoạn kỳ" ở Cockpit: suy ra từ lịch billing cycle hay cho admin đặt tay? *Đề xuất: suy ra + cho override; chốt khi làm Cockpit.*
- Có cần "4-eyes" (người thứ hai duyệt) cho thao tác phá hủy nặng nhất không? *D5 hiện không yêu cầu; để ngỏ.*

## 12. Nhật ký sửa theo review (2026-06-15)
Đóng 3 hợp đồng + fix nhỏ (giữ nguyên ý tưởng UI):
- **P1 scope:** §1 + §9 — đổi sang "không đổi money write logic; được thêm read-model + action adapter mỏng".
- **P1 preview safety:** §6.3 — thêm hợp đồng **preview token + content hash + recompute-and-compare** trước commit (theo tiền lệ Student Action Import).
- **P2 form/action contract:** ⚠️ *Đã bị thay thế bởi Vòng 2 — đừng bám entry này.* (Vòng 1 từng neo vào skill `swinx-frontend` và `useHttp`, và bỏ vee-validate; Vòng 2 sửa: neo vào docs, dùng `useApi`/`useApiRequest`, và **giữ** vee-validate cho modal/drawer JSON theo design-guidelines §5.)
- **P2 permissions:** §7.2 — sửa tên quyền thật (`create_finance_payments`, `void_finance_charges`) + thêm ma trận action→quyền→blocking.
- **P2 semester scope:** §3.1 — thêm quy tắc scope badge; critical/global không bị ẩn khi đổi kỳ.
- **P3 dependency:** §10 — mục 1 dựng 360 route shell tối thiểu làm đích cho search; mục 2 bồi đắp.

**Vòng 2 (sửa theo review thứ hai):**
- **SSOT:** §8 — lật lại: SSOT là `AGENTS.md` + `docs/rules/*` + `docs/inertiajs-vue-info.md` + `docs/design-guidelines.md`; `swinx-frontend` chỉ là shorthand, không override docs.
- **JSON transport:** §8 — chuyển `useHttp` → `useApi`/`useApiRequest` (chuẩn repo); bỏ `axios` cho nested modal (repo cấm axios); reintroduce `vee-validate`+Zod cho *modal/drawer gọi JSON* đúng design-guidelines §5. Trục phân biệt = điều hướng Inertia vs JSON không điều hướng.
- **Retry blocking:** §7.2 — sửa thành "chỉ chặn khi `processed`" (đúng `RetryDngWebhookEventAction:31` + `Show.vue:58`).
- **Preview citation:** §6.3 — nêu đúng tiền lệ (token + *file* hash), ghi rõ finance *nâng cấp* thành resolved-content hash.

**Vòng 3 (sửa theo review thứ ba):**
- **Modal contract:** §8 lưu-ý-transport — bỏ câu quá rộng; chốt đúng `frontend.md §5`: modal Inertia (redirect) → `useForm`; chỉ modal JSON không điều hướng → `useApi`.
- **Audit drilldown:** §4.4 — thêm hợp đồng read-only (mở rộng param `finding_code/scope/sample_id` *hoặc* read-model "Invariant drilldown") vì route Audit hiện chưa lọc theo invariant.
- **Preview hash:** §6.3 — định nghĩa hash trên *canonical resolved payload* đầy đủ (id nguồn/fee version/discount ids/idempotency/warning), không chỉ amount+label.
- **Nhật ký:** đánh dấu entry P2-form Vòng 1 là *superseded*.
- **Mục tiêu §1:** làm mềm — "checker + write logic đã có/được bảo vệ", không ngụ ý dữ liệu luôn sạch.

**Vòng 4 (review pass đầy đủ — siết khớp backend thật):**
- *Batch atomicity* §6.5: v1 giữ transaction cả-batch hiện có + summary; per-SV tx/live progress = story riêng.
- *Rerun scope* §6.4: tách theo loại (sinh phí an toàn / đẩy DNG hủy DNG cũ / nhắc nợ gửi lại).
- *DNG override* §6.2/§6.6: cảnh báo rõ override lệch = ad-hoc, bỏ installment linkage, đối soát thủ công.
- *Quyền hủy DNG* §7.2: thêm `void_finance_charges` khi có linked charges; ghi 2 gap (route gate + confirm 1-bước).
- *Invariant drilldown* §4.4: khóa campus + bảng sample-type→audit-target; làm tối thiểu trong story Cockpit (§10).
- *Preview hash* §6.3: per-line hash, compare đúng subset đã chọn.
- *360 actions* §5.2: "Ghi nhận thanh toán" cần adapter mới (route cũ đã gỡ).
- *Allocate preview* §5.3: tách manual-allocate-preview vs auto-allocate-preview.
- *Topbar semester* §3.1: contract query-param + shared prop + cờ `obeys_semester`.
- *Legacy/Route/Permission/Portal* §8: migrate `response()->json()`; task tạo `financeRoutes`; đặt tên quyền từng surface; `Portal impact: none`.
- *Menu migration* §3.2: cần bảng `current→group→keep/hide/remove/deep-link`; chú ý `Discounts & Funding`.
- *Hygiene* §4.4/§8: định nghĩa denominator "Số dư khớp"; `usePoll` interval/backoff/manual; batch limit 100 + chunking; sửa path `docs/rules/api-interaction.md`.

**Vòng 5 (siết factual cuối):**
- *Lifecycle cancel quyền* §7.2: thêm gap luồng `lifecycle-exceptions.resolve` (chỉ gate due_calendar nhưng void được charge) — đối chiếu `ResolveLifecycleDueExceptionAction:77` + `CancelDngPaymentRequestAction:127`.
- *Mapping invariant* §4.4: sửa `INV-12→dng`, `INV-4→invoice_line`, `INV-5→invoice_discount` (đối chiếu registry `:74/:89/:192`).
- *Số dư khớp* §4.4: dùng đúng `snapshotDriftsFromCache` (cả `cached_paid` + `cached_total`).
- *DNG/nhắc nợ result* §6.5: định nghĩa riêng — per-student, partial success, retry subset lỗi (đối chiếu `CreateBatchDngFromChargesAction:68/:114`).
- *Rerun sinh phí* §6.4: "an toàn với rerun **tuần tự**", không phải concurrency (check-then-create, không lock/unique — `:66/:89`).
- *Preview hash* §6.3: bỏ "version", dùng `config_id + updated_at`/fingerprint (TuitionPlan không có cột version).
- *Quyền seed/sync* §8: thêm task `config/permission.php` + sync + role; đặt tên quyền all-campus.
- *Topbar label* §3.1: khóa nghĩa "toàn hệ thống = trong campus hiện tại".

**Vòng 6 (siết factual — atomicity & cancel semantics):**
- *DNG cancel matrix* §7.2: gộp thành **một action có điều kiện** (luôn void linked charges nếu có — `CancelDngPaymentRequestAction:119`); enum `cancel_dng`/`cancel_dng_and_void` = nhánh điều kiện, không phải 2 lựa chọn user.
- *Audit sink* §7.2 lớp 4: tách sink — lifecycle → review events; DNG cancel thường → cancel payload/response + action log (không phải mọi cancel đều ghi lifecycle events).
- *Batch atomicity* §6.5: sửa "all-or-nothing" → **single transaction envelope** (per-SV catch & skip, phần thành công vẫn commit — `:90/:93/:372/:377`).

> Sau khi spec được chốt (trước khi thành implementation plan): **rút gọn §12** hoặc tách thành `CHANGELOG` riêng để phần thiết kế chính dễ đọc.
