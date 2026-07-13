# Finance Module — Review toàn diện

> **Superseded rule notice (2026-07-12):** Các nhận định trong tài liệu này coi nhiều `student_invoices` cùng `(student_id, semester_id)` là INV-6/double-billing và đề xuất unique constraint đã lỗi thời. Kiến trúc hiện tại cho phép nhiều invoice theo fee stream. `INV-6` được retired để giữ lịch sử mã; `INV-17` kiểm tra invoice line gắn charge sai student/kỳ. Các số liệu INV-6 bên dưới được giữ như bằng chứng lịch sử, không còn là cleanup queue.

Original review date: 2026-06-13
Last updated: 2026-07-12
Status: Review findings (chưa fix) — dùng để theo dõi & xử lý dần
Owner: Finance Module
Phương pháp: trace code thật (evidence-first), mọi phát hiện có `file:line`. Không phán đoán suông.

## Mục đích

Module Finance được phát triển chắp vá qua nhiều đợt → khó kiểm soát, khó tin số liệu sinh ra
có đúng không. Tài liệu này tổng hợp toàn bộ rủi ro về **tính đúng đắn dữ liệu tiền**, toàn vẹn
dữ liệu, bảo mật, và nợ kỹ thuật, kèm cách tự kiểm chứng và lộ trình xử lý.

Mỗi phát hiện có một **ID ổn định (FIN-xx)** để team tham chiếu và đánh dấu trạng thái.

---

## Cập nhật triển khai (2026-06-14) — Story S-002-ledger-source-of-truth

Đã implement (branch `dev`, evidence ở `docs/stories/E-finance-module-review-2026-06/S-002-ledger-source-of-truth/validation.md`):

- **FIN-01 `fixed`** — gộp 3 bản `deriveInvoiceSnapshot` admin (worklist/preview/auto-allocate)
  thành adapter mỏng gọi `SettlementService::deriveInvoiceSnapshot`; bỏ stale-cache `max()`;
  `PaymentService::autoAllocatePayment` chuyển từ charge-centric (`FinanceCharge::balance`) sang
  line-outstanding canonical. (Còn lại: bản Academic `GetStudentFeeSummaryQuery` — student-facing,
  hoãn sang slice portal.)
- **FIN-02 / FIN-03 `fixed`** — thêm backstop lọc discount `status=reversed` trong canonical;
  void line đã loại qua `isBillableActiveLine`; pin bằng test signed-ledger.
- **FIN-11 / DB-09 `fixed` (code-level)** — `lockForUpdate` + recompute-under-lock ở
  `AllocatePaymentAction`, `PaymentService::allocatePayment`, `AutoAllocatePaymentsAction`.
  Idempotency tầng operation, KHÔNG thêm schema (đúng scheduling note epic).
- **DB-01 `fixed`** — `InvoiceGenerationService` `updateOrCreate`→`firstOrCreate`; `amount_snapshot`
  đóng băng; reactivation chỉ khôi phục status.
- **DB-14 / DB-15 / NT4 `fixed`** — đổi tên `student_invoices.{subtotal,discount_total,total_amount,
  paid_amount,paid_at}`→`cached_*` (migration `2026_06_14_000001`), model giữ accessor canonical +
  alias tên cũ (props không đổi); `cached_paid_at` clear khi reversal; thêm command
  `finance:rebuild-invoice-snapshots`. **`status` GIỮ NGUYÊN** (cột lifecycle, không phải cache;
  truth realtime ở `real_time_status`).
- **DB-13 `fixed` (proof)** — thêm `INV-12` (DNG request đã bridge phải khớp amount payment canonical)
  vào `finance:audit-invariants`. KHÔNG đổi KPI aggregation (quyết định double-count vẫn treo).

Audit sau triển khai: mọi invariant tiền = 0 (gồm INV-2 sau khi đổi cột, INV-12 mới). INV-6=28 +
INV-11=3 là nợ dữ liệu cũ — thuộc S-003. Test mới đều xanh; 26 fail còn lại là pre-existing
(web/CSRF + EGC + DngReconciliation signature), không nằm ở ledger math.

---

## Cập nhật triển khai (2026-06-14) — Story S-003-data-guards-and-constraints

Đã implement (branch `dev`, evidence ở `docs/stories/E-finance-module-review-2026-06/S-003-data-guards-and-constraints/validation.md`).
Nguyên tắc: chỉ thêm guard **additive, an toàn**, KHÔNG xóa/merge dữ liệu trùng (đúng epic rule
+ Stop Conditions). MariaDB 11.4 (CHECK enforced), DB charset `utf8mb4` → **DB-25 đã thỏa**.

- **DB-08 / FIN-14 `fixed`** — FK `voucher_applications.invoice_id` + `.finance_charge_id`
  `nullOnDelete` (mig `2026_06_14_000002`). Pre-check 0 orphan.
- **DB-06 `fixed`** — CHECK `payments.amount>0`, `finance_charge_installments.amount>0`,
  và `finance_charges` sign theo charge_type: **debit `>=0`, credit `<=0`, adjustment any**
  (mig `..000003`). Chặn cả HAI chiều sign-flip (debit lưu âm / credit lưu dương) nhưng CHO
  PHÉP 0 (zero không có lỗi dấu; mô hình invoice zero-debt/đã miễn là hợp lệ — vd test skip
  reminder). Legacy negative-debit line (`tuition_term` ÂM) code hiện KHÔNG còn tạo được → CHECK
  chặn tái tạo; 1 test reader netting backstop seed shape đó với `check_constraint_checks=OFF`.
- **DB-05 `fixed`** — CHECK status cho `finance_charge_installments`, `dng_payment_requests`,
  `dng_webhook_events.processing_status` theo PHP value set (mig `..000004`, đúng B5.2). Lifecycle
  review status KHÔNG đụng (thuộc E-finance-lifecycle-exceptions; dead `ignored` chờ product).
- **DB-10 `fixed`** — unique `invoice_discounts(invoice_id, discount_type, reference_key,
  discount_source)` với `reference_key` = generated column `COALESCE(reference_id,0)` (mig
  `..000005`). Sentinel đóng lỗ "MariaDB coi NULL distinct" → chặn 2 discount NULL-ref trùng
  (invoice,type,source); vẫn khớp firstOrNew, không đổi nullability cột.
- **DB-16/17 `fixed`** — thêm composite/non-FK index còn thiếu (mig `..000006`), đã verify không
  trùng FK/unique sẵn có.
- **FIN-13 `fixed`** — `invoice_number` vốn đã unique → thêm retry-on-collision (5 lần) +
  nới entropy generator (6 số random) ở `CreateFinanceChargeAction`.
- **FIN-25 `fixed`** — `FinanceCharge::CHARGE_TYPES` thêm `course_fee`+`bhyt` (+`CREDIT_CHARGE_TYPES`);
  `StudentInvoice::NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES` bỏ `issued`/`void` (không có trong
  DB enum, behavior-preserving); `DngWebhookEvent::STATUS_PENDING` khai báo cho khớp default. Có
  test parity đọc `information_schema` chặn drift tương lai.
- **Tooling (thay cleanup phá hủy)** — command read-only `finance:export-duplicate-finance-data`
  liệt kê từng nhóm INV-6 (kèm line/payment/discount/DNG) + INV-11 để con người chọn canonical.
  DNG linkage lấy CẢ direct (`dng_payment_requests.finance_charge_id`) LẪN pivot
  (`dng_payment_request_charges`, request aggregate có FK trực tiếp NULL) — ghi `link_source` +
  `pivot_amount`; trên data hiện tại lộ 33 DNG request pivot-linked / 31 invoice mà bản direct-only
  bỏ sót → consolidation phải re-point cả DNG linkage, không chỉ line/payment. JSON full row-level
  ids ở `storage/app/finance/`.

**HOÃN (cần quyết định người / ngoài slice):**
- **DB-03** unique `(student_id,semester_id)` + **DB-04** restore unique `payload_hash`: BLOCK bởi
  INV-6=28 / INV-11=3. Export cho thấy đa số là double-bill *đã paid ở cả hai* → chọn canonical là
  quyết định finance, không tự động hóa. Sau khi review + cleanup về 0 mới thêm 2 unique.
- **DB-24** soft-delete (quyết định hoãn), **DB-09** (S-002 không cần),
  lifecycle status CHECK + dead `ignored` (story lifecycle).

Audit sau triển khai = trước: invariant tiền 0, INV-6=28/INV-11=3 vẫn nguyên (guard additive không
đụng data). Test mới 40 xanh; full Finance suite **26 fail = đúng baseline pre-existing S-002** (0
regression mới). `pint` + `git diff --check` sạch. Migrate→rollback→migrate chứng minh down() an toàn.

Hiệu chỉnh sau review (sign CHECK): bản strict `>0/<0` (spec confirm) phá (a) fixture legacy
negative-debit reader và (b) 2 test reminder mô hình invoice zero-debt bằng `tuition_term=0`. Đã
chốt lại theo approval = **debit `>=0` / credit `<=0`** (chặn cả 2 chiều sign-flip, cho phép 0).
Bài học: spec số tiền phải tách "sai dấu" (cần chặn) khỏi "giá trị 0" (hợp lệ); khi strict phá test
hợp lệ thì đó là stop condition cần re-approval, KHÔNG tự ý nới guard.

---

## 1. Chẩn đoán gốc — 4 lỗ hổng kiến trúc lặp lại

Phần lớn các bug riêng lẻ bên dưới là hệ quả của 4 nguyên nhân gốc sau:

1. **Không có nguồn chân lý duy nhất cho "số dư / đã trả / chiết khấu".**
   Cùng một con số được tính ở ≥4 chỗ với logic khác nhau → các màn hình ra số khác nhau cho
   cùng một sinh viên. Đây là lý do cốt lõi khiến không tin được dữ liệu: **bản thân hệ thống
   cũng không thống nhất con số là bao nhiêu.**
2. **Cột tiền denormalized (cache) bị trôi.** `student_invoices.paid_amount/total_amount/status`,
   `voucher_applications.discount_amount`… vừa cache vừa tính lại live; có path cập nhật, có
   path đọc giá trị cũ.
3. **Thiếu hàng rào ở tầng DB.** Sai kiểu cast tiền, thiếu unique constraint, thiếu FK, thiếu
   row-lock → trùng charge, trùng invoice, over-allocation.
4. **Một nghiệp vụ code nhiều bản song song.** Học bổng tính 5 cách (chỗ cap, chỗ không), phí
   EGC vừa hardcode 15M vừa lấy `Unit.base_fee`, 2 action retake, **math preview ≠ math execute**.

---

## 2. Bảng tổng hợp phát hiện

Mức độ: 🔴 CRITICAL · 🟠 HIGH · 🟡 MEDIUM · ⚪ LOW
Cột Status để team cập nhật: `open` / `in-progress` / `fixed` / `wontfix` / `planned` (đã lên kế
hoạch, chưa làm) / `resolved` (đã quyết định, khép lại) / `reframed` (mô tả đã chỉnh sau verify) /
`invalid` (finding sai, bỏ — có thể kèm hành động khác như test).

| ID | Mức | Khu vực | Vấn đề | Vị trí | Status |
|----|-----|---------|--------|--------|--------|
| FIN-01 | 🔴 | Balance | 4+ định nghĩa "số dư" khác nhau (3 bản `deriveInvoiceSnapshot` + `PaymentService` charge-centric vs `SettlementService` line-centric) → các màn hình ra số khác nhau | `SettlementService.php:34-39`; `AutoAllocatePaymentsAction.php:165-186`; `ListSettlementWorklistQuery.php:319-342`; `GetStudentBalanceQuery.php:33` | open |
| FIN-02 | 🟡 | Discount | **[REFRAME — xem Phần F]** Ledger có dấu: reversal/release là dòng ÂM nên `SUM(discount_allocations)` đã tự net chiết khấu reversed. KHÔNG miscount như mô tả cũ. Rủi ro còn lại: *defensive gap* — không có backstop lọc `status`, sẽ sai NẾU có path đặt `status='reversed'` mà không ghi dòng release (audit INV-5 = 0, hiện chưa có) | `SettlementService.php:101-114`, `34-41`, `163-165`, `306` | reframed |
| FIN-03 | 🟡 | Allocation | **[REFRAME — xem Phần F]** Tương tự: void line được reverse bằng dòng payment_application ÂM nên đã net. KHÔNG inflate paid như mô tả cũ. Rủi ro còn lại: thiếu backstop lọc `status='active'`, sai NẾU void mà không reverse (audit INV-4 = 0, hiện chưa có) | `SettlementService.php:83-113`, `163-165` | reframed |
| FIN-04 | 🔴 | Discount | Học bổng không cap ≤ charge → `fixed_amount` > học phí tạo balance âm | `CreateFinanceChargeAction.php:75-83` | open |
| FIN-05 | 🔴 | Charge-gen | `createChargeIfNotExists` không lọc `status='active'` → charge void chặn tạo lại VÀ bản void bị gắn vào invoice như dòng active | `GenerateBatchChargesAction.php:398-404` | open |
| FIN-06 | 🟠 | Charge-gen | Phí EGC mâu thuẫn: hardcode `15_000_000` vs `Unit.base_fee` giữa 2 luồng | `GenerateEgcChargesAction.php:18,217` vs `GenerateBatchChargesAction.php:211` | open |
| FIN-07 | 🟡 | Discount | **[REFRAME — xem Phần F5]** KHÔNG tạo dòng discount trùng: `applyScholarship` → `applyInvoiceDiscount` → `createOrRefreshInvoiceDiscount` dùng `firstOrNew(invoice_id, discount_type, reference_id, discount_source)` (`SettlementService:271`) → idempotent. Điểm đúng còn lại: **(a) không cap** học bổng (chung với FIN-04); **(b) `firstOrNew` ghi đè `amount`** mỗi lần refresh → đổi số nếu charge amount đổi | `CreateFinanceChargeAction.php:88-96`; `SettlementService.php:271-284` | open |
| FIN-08 | 🟠 | Precision | Cast tiền không nhất quán: `FinanceCharge.amount` `decimal:0` vs cột DB `decimal(15,2)` vs `installment.amount` `decimal:2` → truncate khi tính % học bổng | `FinanceCharge.php:33` | open |
| FIN-09 | 🟠 | Installment | Không re-validate `sum(installments) == net due` khi áp discount **sau** split → trôi âm thầm | `SplitChargeIntoInstallmentsAction`; `SettleInstallmentFromDngAction` | open |
| FIN-10 | 🟠 | DNG | Pivot làm tròn lệch → `Σ dng_payment_request_charges.amount ≠ dng_payment_requests.amount` → `payment_applications` lệch `payments.amount` | `CreateBatchDngFromChargesAction.php:339-342` | open |
| FIN-11 | 🔴 | Concurrency | Không `lockForUpdate` khi allocate; filter `unapplied>0` nằm ngoài transaction → over-allocation khi webhook + batch chạy song song | `AllocatePaymentAction.php:48,52`; `AutoAllocatePaymentsAction.php:77,113` | open |
| FIN-12 | 🟠 | Void | **[REFRAME — xem Phần F5]** KHÔNG có chuyện "release rồi không re-apply": cả `handle()` là MỘT `DB::transaction` (`:28`), `runForStudents` không nuốt lỗi → throw sẽ rollback nguyên tử toàn bộ. Điểm đúng: **(a) void KHÔNG hủy `finance_charge_installments`** của charge; **(b) auto-reallocate ngay trong flow void** là side-effect rộng, dễ gây phân bổ bất ngờ | `VoidFinanceChargeAction.php:28,116-121` | open |
| FIN-13 | 🟠 | DB guard | Thiếu unique: `student_invoices(student_id,semester_id)` (đã comment out, INV-6 = 28 dup) + `lifecycle_review(dng_payment_request_id)`. **SỬA:** `invoice_number` ĐÃ unique (`2025_10_08_145805:16`, xác nhận local) → vấn đề thật là **generator `time()+rand()` có thể collision → insert fail, CHƯA có retry**, KHÔNG phải "thiếu unique" | `2026_01_18_211838:25`; `CreateFinanceChargeAction.php:218`; mig `2026_06_12_000001` | open |
| FIN-14 | 🟠 | DB guard | Thiếu FK `voucher_applications.invoice_id/finance_charge_id`; `discount_amount` lưu cứng, không tính lại | `VoucherApplication.php` | open |
| FIN-15 | 🟠 | DNG | `SettleInstallmentFromDngAction` không lock installment → webhook + reconciliation double-settle + dispatch trùng `PushNextInstallmentJob` | `SettleInstallmentFromDngAction.php:44-87` | fixed (S-005: lockForUpdate in a txn) |
| FIN-16 | 🔴 | Security | Bỏ qua checksum cho event không có `InvoiceSerialNumber` (`payment_succeeded_without_invoice`); endpoint webhook public → giả webhook để settle charge. **Citation (đã làm rõ):** webhook public ở **`routes/api.php:30-32` (file GỐC)** — KHÁC `app/Modules/Finance/routes/api.php` (web+auth, admin ops) | `DngWebhookService.php:62-71`; `routes/api.php:30-32` (root) | fixed (S-005: checksum on every event incl. Call 1 empty-serial) |
| FIN-17 | 🔴 | DNG | Unique `payload_hash` đã bị drop → không dedup webhook → retry storm + race state machine | mig `2026_03_27_111002:21` | fixed (S-005/DB-04: app dedup + unique restored in `2026_06_14_120000`) |
| FIN-18 | 🟠 | DNG | `cancel_pushed_to_dng` thiếu trong `statusOrder()` → rơi `default => 0` → có thể xử lý nhầm request đã hủy. **MỞ RỘNG:** lỗi này có ở **CẢ HAI** service | `DngReconciliationService.php:208-219` **và** `DngWebhookService.php:326-338` | fixed (S-005: cancel_pushed_to_dng terminal in both services) |
| FIN-19 | 🟠 | DNG | **[REWRITE — xem Phần F]** Pivot KHÔNG nằm ngoài transaction (cùng transaction `processStudent`). Rủi ro thật: **external DNG API call (`insertNewRecord`) chạy khi transaction NGOÀI chưa commit** → rollback (vd `createChargePivots` lỗi) thì DB mất record nhưng DNG đã gọi → phân kỳ DB/DNG. Trái với comment "must commit before calling DNG" | `CreateBatchDngFromChargesAction.php:113,242,245`; `DngPaymentService.php:72,91` | deferred (S-005 decision 2026-06-14: needs outbox/compensation design; conflicts with Replacement-Rule atomicity) |
| FIN-20 | 🔴 | Stub | `FixBillingExceptionAction` luôn trả `{fixed:true}`. **Bug runtime THẬT (không phải giả định):** route `api.finance.operations.fix-exception` đã wired và UI gọi (`ExceptionsQueue.vue:156`) → staff bấm "Fix", nhận "thành công" nhưng KHÔNG sửa gì. (verify `BillingOperationsController@fixException` có trả thẳng stub) | `FixBillingExceptionAction.php:9-17`; `ExceptionsQueue.vue:156` | open |
| FIN-21 | 🔴 | Stub | `ListBillingExceptionsQuery` luôn trả rỗng (body bị comment) | `ListBillingExceptionsQuery.php:14-27` | open |
| FIN-22 | 🟠 | Dead code | `priorityOrder` truyền vào allocation nhưng sort không dùng → ưu tiên loại phí vô tác dụng | `SettlementService.php:435-464` | open |
| FIN-23 | ⚪ | Drift | **[SAI — đã bỏ, xem Phần F]** Charge ĐƯỢC void thật: `ResolveLifecycleDueExceptionAction:178` gọi `CancelDngPaymentRequestAction` và action đó void toàn bộ linked charges (`:130-145`). `CancelDng` vs `CancelDngAndVoidLinkedCharge` chỉ khác permission + audit event. Rủi ro còn lại nhỏ: **no-op âm thầm nếu linkage (pivot/`finance_charge_id`) rỗng** → cần test linkage | `CancelDngPaymentRequestAction.php:130-145`; `ResolveLifecycleDueExceptionAction.php:178` | invalid→test |
| FIN-24 | 🟠 | Duplication | `AcknowledgeLifecycleDueExceptionAction` trùng nhánh `Acknowledge` của `ResolveLifecycleDueExceptionAction`, metadata khác nhau | 2 file | open |
| FIN-25 | 🟡 | Model drift | `'issued'/'void'` không có trong DB enum `student_invoices`; `CHARGE_TYPES` thiếu `bhyt`/`course_fee`; `installment.status` là string thường; enum `Ignored` dead | nhiều file | open |
| FIN-26 | 🟠 | Dashboard | `upcoming_count` đếm trùng item due today (summary `[today,+7]` vs list `[today+1,+7]`) → Σ bucket > tổng | `GetDueItemsSummaryQuery.php:34`; `GetDueInvoicesSummaryQuery.php:24` | open |
| FIN-27 | 🟠 | Performance | `ListDueItemsQuery` load cả bảng vào PHP rồi paginate thủ công → OOM risk | `ListDueItemsQuery.php:56-105` | open |
| FIN-28 | 🟠 | Dashboard | `retake_unpaid_count` so cột thô `paid_amount<total_amount`, mâu thuẫn cách SettlementService tính paid → đếm sai | `GetBillingDashboardStatsQuery.php:113` | open |
| FIN-29 | 🟡 | Performance | N+1: `GetDueInvoicesSummaryQuery` gọi `outstanding_balance` accessor mỗi invoice; `ListDueInvoicesQuery` + `LifecycleDueExceptionRowMapper` cũng N+1 | nhiều query | open |
| FIN-30 | 🟠 | Charge-gen | EGC deferred block dùng `semesterId + 1` (số học ID, không phải kỳ kế tiếp trong lịch học) → FK rác nếu ID không liên tục | `GenerateEgcChargesAction.php:164` | open |
| FIN-31 | 🟡 | Charge-gen | Batch generation bọc tất cả SV trong 1 transaction; lỗi ngoài catch nội bộ → rollback toàn bộ, SV đã thành công không được lưu | `GenerateBatchChargesAction.php:87-368` | open |
| FIN-32 | 🟡 | DNG | `callbackMismatchReasons` không validate `campus_code` → payment sai campus vẫn được nhận nếu trùng student+amount+fee_type | `DngPaymentRequest.php:173-193` | fixed (S-005: campus mismatch defense-in-depth) |
| FIN-33 | ⚪ | DNG | `item_id` chứa `now()->format('YmdHis')` → 2 push trong cùng giây trùng item_id, vỡ resolve webhook | `CreateBatchDngFromChargesAction.php:354` | fixed (random suffix in `buildItemId`; S-005 added regression test) |
| FIN-34 | ⚪ | Audit | `ResolveLifecycleDueExceptionAction::cancelDng` ghi event `CancelRequested` TRƯỚC transaction → rollback thì audit lệch trạng thái review | `ResolveLifecycleDueExceptionAction.php:132-157` | fixed (S-005: attempt events recorded inside the txn) |

---

## 3. Chi tiết theo nhóm

### 3.1 Sai số tiền (ưu tiên #1)

**FIN-01 — Nhiều định nghĩa "số dư".** `deriveInvoiceSnapshot` tồn tại 3 bản: `SettlementService`
(có fallback cộng dòng âm `amount_snapshot` làm discount), `AutoAllocatePaymentsAction` và
`ListSettlementWorklistQuery` (không có fallback đó; bản worklist còn dùng `$storedPaid` thô,
không bọc `max()`). Ngoài ra `PaymentService::autoAllocatePayment` (đường DNG fallback) tính
balance theo `FinanceCharge.balance` (charge-centric) trong khi `SettlementService` tính theo
invoice-line (`getLineOutstandingAmount`). Hệ quả: SV có dòng credit âm (dữ liệu cũ) sẽ hiện
**3 số dư khác nhau** ở trang số dư / worklist / engine allocate.

**FIN-02, FIN-03 — [REFRAME sau counter-review, xem Phần F].** Mô tả cũ ("reversed/void vẫn bị
tính → nợ ít hơn thực") là **OVERSTATED**. Ledger có DẤU: `createPaymentApplication` ghi reversal
= `-abs` (`SettlementService.php:163-165`), `releaseLineDiscounts` ghi release = `-abs`
(`:306`). Nên `SUM(payment_applications)` và `SUM(discount_allocations)` **đã tự net** các
reversal/void — không bị tính dư. Audit thực tế: INV-4 = 0, INV-5 = 0 (không có dòng "treo").
Rủi ro **thật** chỉ còn là *defensive gap*: code không có backstop lọc `status='active'` /
`status!='reversed'`, nên SẼ sai nếu tồn tại một path đổi status mà KHÔNG ghi dòng offsetting.
→ Việc cần làm: thêm filter `status` như defense-in-depth + viết test kiểm path reversal/void luôn
ghi dòng âm. (Khác với **FIN-01** vốn vẫn đúng: 3 bản `deriveInvoiceSnapshot` lệch nhau ở fallback
dòng `amount_snapshot` âm — không liên quan signing.)

**FIN-04, FIN-07 — Học bổng.** Vấn đề thật là **không cap** discount theo amount, xuất hiện ở
**nhiều path**: `CreateFinanceChargeAction::applyScholarship` (78-82), `GenerateBatchChargesAction`,
và cả **preview/batch generation path**. `GenerateMajorChargesAction` thì cap (`max(0, min(base,
discount))`) và có guard. → logic học bổng phân mảnh (chỗ cap chỗ không), kết quả phụ thuộc luồng
nào tạo charge. **Lưu ý (sửa sau counter-review):** KHÔNG có "trùng dòng discount" — `firstOrNew`
ở `createOrRefreshInvoiceDiscount` chống trùng; rủi ro là `amount` bị ghi đè khi refresh.

**FIN-06 — Phí EGC.** Hai luồng sinh charge EGC dùng hai nguồn số tiền khác nhau. Cần chốt
nguồn chuẩn (xem Câu hỏi mở).

**FIN-08 — Cast tiền.** `'amount' => 'decimal:0'` cắt phần thập phân khi đọc, trong khi cột DB
giữ 2 chữ số. Với VND (số nguyên) phần lớn vô hại nhưng tính `amount * pct / 100` cho học bổng
% sẽ lệch tới 1 đơn vị một cách hệ thống.

**FIN-09 — Installment.** Lúc split đã validate khớp bằng cents (tốt). Nhưng nếu áp discount
**sau** split thì `net target` đổi mà tổng installment không đổi → trôi.

**FIN-10 — DNG pivot.** Phân bổ theo tỉ lệ `round(total*balance/totalBalance,2)` để lại lẻ →
tổng pivot có thể ≠ tổng request; allocate dùng `link.amount` từng charge → `payment_applications`
lệch `payments.amount`.

### 3.2 Toàn vẹn dữ liệu & đồng thời

**FIN-11** — không có path nào lock `Payment`/`InvoiceLine` trước khi đọc `unapplied_amount`/
outstanding rồi ghi. `SplitCharge`/`PushNextInstallment` đã lock charge đúng cách — nhưng
allocation thì chưa. Webhook DNG + batch auto-allocate chạy song song có thể cùng allocate.

**FIN-12** — `VoidFinanceChargeAction` gọi `autoAllocatePaymentsAction->runForStudents()` (vốn
tự mở `DB::transaction`) bên trong transaction void → savepoint lồng nhau; inner fail thì
release đã xảy ra nhưng re-apply không, và outer vẫn commit. Đồng thời không hủy installments.

**FIN-13, FIN-14** — thiếu hàng rào DB cho phép trùng/orphan.

### 3.3 Bảo mật & độ tin cậy DNG

**FIN-16 (CRITICAL security)** — checksum chỉ verify khi có `InvoiceSerialNumber`. Event xác
nhận thanh toán lần đầu (`payment_succeeded_without_invoice`) bỏ qua checksum hoàn toàn, mà
endpoint webhook là public (chỉ throttle 60/1min). → có thể giả webhook để settle charge.
**Chính xác hơn (tighten):** attacker không chỉ bỏ qua checksum — còn phải biết/đoán request khớp
`ItemId` + `StudentId` + `amount` + `fee_type` (vì `callbackMismatchReasons` validate các trường
này). Vẫn là rủi ro thật (các trường này có thể quan sát/đoán), nhưng không phải "gửi gì cũng settle".
**Vị trí (đã làm rõ):** webhook public ở **`routes/api.php:30-32` (file GỐC, no auth)** — đừng nhầm
với `app/Modules/Finance/routes/api.php` (web+auth, admin operations).
**Điểm tốt:** `DngChecksumService` dùng `hash_equals` (constant-time) và secret lấy từ config.

**FIN-17** — sau khi drop unique `payload_hash`, mọi webhook trùng tạo row + job mới. Bảo vệ
còn lại chỉ là `lockForUpdate` trong `bridgeToPayment`; event trùng vẫn gây `transitionTo`
throw → retry 5 lần.

### 3.4 Code chắp vá (stub / dead / drift)

**FIN-20, FIN-21** — hai stub gắn (hoặc có khả năng gắn) route trả kết quả giả. Cần implement
hoặc trả 501 / gỡ route.

**FIN-22** — `priorityOrder` là tham số chết: ưu tiên loại phí admin cấu hình không có tác dụng.

**FIN-23 — [SAI, đã bỏ sau counter-review, xem Phần F].** Charge ĐƯỢC void thật:
`ResolveLifecycleDueExceptionAction:178` gọi `CancelDngPaymentRequestAction`, action này void toàn
bộ linked charges qua `voidChargeAction->handle()` (`CancelDngPaymentRequestAction.php:130-145`).
Cờ `requireVoidPermission` (cho `CancelDngAndVoidLinkedCharge`) chỉ thêm permission gate + audit
event `Void*`; việc void xảy ra ở CẢ HAI nhánh. Rủi ro còn lại nhỏ: nếu request không có linkage
(pivot `chargeLinks` hoặc `finance_charge_id`) thì `voidLinkedChargesAndRegistrations` no-op âm
thầm (`:123-125`) → cần test đảm bảo linkage được populate.

### 3.5 Hiệu năng & số liệu dashboard

**FIN-26** — boundary `upcoming` lệch giữa summary và list → đếm trùng. Sửa: summary dùng
`$today->copy()->addDay()` làm mốc đầu để khớp list.

**FIN-27, FIN-29** — vài query load toàn bộ vào PHP / N+1; cần paginate ở DB và eager-load.

**FIN-28** — `retake_unpaid_count` dùng cột thô thay vì SettlementService → mâu thuẫn toàn cục.

---

## 4. Tự kiểm chứng dữ liệu production (SQL bất biến)

Mỗi query trả **0 dòng nếu dữ liệu đúng**. Ra dòng = tìm thấy bản ghi đang sai.

```sql
-- INV-1: payment áp vượt quá số tiền payment
SELECT p.id, p.amount, SUM(pa.amount) allocated
FROM payments p JOIN payment_applications pa ON pa.payment_id=p.id
GROUP BY p.id HAVING allocated > p.amount;

-- INV-2: paid_amount cache lệch tính live (chỉ dòng active) — liên quan FIN-01/03
SELECT si.id, si.paid_amount cached, COALESCE(SUM(pa.amount),0) live
FROM student_invoices si
LEFT JOIN invoice_lines il ON il.invoice_id=si.id AND il.status='active'
LEFT JOIN payment_applications pa ON pa.invoice_line_id=il.id
GROUP BY si.id HAVING ABS(cached-live) > 0.01;

-- INV-3: 1 charge active dương phải có đúng 1 dòng invoice active
SELECT fc.id, COUNT(il.id) n FROM finance_charges fc
LEFT JOIN invoice_lines il ON il.charge_id=fc.id AND il.status='active'
WHERE fc.status='active' GROUP BY fc.id HAVING n != 1;

-- INV-4: payment còn dính trên dòng đã void (lẽ ra phải reverse) — FIN-03
SELECT il.id, SUM(pa.amount) net FROM invoice_lines il
JOIN payment_applications pa ON pa.invoice_line_id=il.id
WHERE il.status='void' GROUP BY il.id HAVING net > 0.01;

-- INV-5: discount đã reversed nhưng allocation vẫn còn — FIN-02
SELECT id.id, SUM(da.amount) net FROM invoice_discounts id
JOIN discount_allocations da ON da.invoice_discount_id=id.id
WHERE id.status='reversed' GROUP BY id.id HAVING net > 0.01;

-- INV-6: trùng invoice cùng student+semester — FIN-13
SELECT student_id, semester_id, COUNT(*) FROM student_invoices
GROUP BY student_id, semester_id HAVING COUNT(*) > 1;

-- INV-7: trùng student trong scholarship awards
SELECT student_id, COUNT(*) FROM student_scholarship_awards
GROUP BY student_id HAVING COUNT(*) > 1;

-- INV-8: balance âm (discount > charge) — FIN-04
SELECT fc.id, fc.amount FROM finance_charges fc
WHERE fc.status='active' AND fc.amount > 0
  AND (fc.amount
       - COALESCE((SELECT SUM(pa.amount) FROM invoice_lines il
                   JOIN payment_applications pa ON pa.invoice_line_id=il.id
                   WHERE il.charge_id=fc.id AND il.status='active'),0)
       - COALESCE((SELECT SUM(da.amount) FROM invoice_lines il
                   JOIN discount_allocations da ON da.invoice_line_id=il.id
                   WHERE il.charge_id=fc.id AND il.status='active'),0)) < 0;
```

> **Đã đóng gói** (read-only) tại `app/Console/Commands/AuditFinanceInvariants.php` — **11
> invariants** (INV-1..8 ở trên + INV-9 `invoice_number` trùng, INV-10 payment ≤ 0, INV-11 webhook
> `payload_hash` trùng). Chạy: `./scripts/dev.sh artisan finance:audit-invariants --sample`.
> Khối SQL phía trên giữ lại để tham khảo logic; nguồn chuẩn để chạy là command.

---

## 5. Lộ trình xử lý đề xuất

Thứ tự tối ưu để vừa giảm rủi ro vừa gỡ rối gốc:

1. **Chốt một nguồn chân lý balance** — **core: FIN-01** (gộp 3 bản `deriveInvoiceSnapshot`, bỏ
   `PaymentService::autoAllocatePayment` cũ, mọi nơi đi qua một `SettlementService`). **Đây là việc
   gỡ rối lớn nhất.** Kèm theo (defense-in-depth, KHÔNG phải bug tiền core): **FIN-02/03** — thêm
   backstop lọc `status='active'`/`!='reversed'` + test bất biến "mọi void/reverse ⇒ có dòng âm".
2. **Vá các lỗi sai tiền còn lại** — FIN-04 (cap học bổng), FIN-05 (lọc active), FIN-06 (chốt
   nguồn phí EGC), FIN-07 (duplicate-guard), FIN-08 (cast), FIN-09/10 (installment/pivot).
3. **Thêm hàng rào DB** — FIN-13 (unique `(student_id,semester_id)` + review; `invoice_number` đã
   unique → chỉ thêm retry-on-collision cho generator), FIN-14 (FK), FIN-11 (`lockForUpdate`),
   FIN-12 (void an toàn + hủy installments).
4. **Bịt lỗ DNG** — FIN-16 (auth + checksum mọi event), FIN-17 (khôi phục dedup), FIN-15/18/19.
5. **Dọn code ảo** — FIN-20/21/22. (**FIN-23 KHÔNG thuộc đây** — finding đã invalid; việc thực là
   thêm regression test đảm bảo linkage charge↔DNG được populate, xem Phần F.)
6. **Sửa số dashboard & hiệu năng** — FIN-26/27/28/29.

Mỗi bước nên kèm test (unit cho math, integration cho allocate/void/webhook) và chạy lại bộ
SQL bất biến ở mục 4 để xác nhận không phát sinh trôi dữ liệu.

---

## 6. Câu hỏi chưa giải quyết (cần quyết định nghiệp vụ)

1. **Phí EGC**: `15_000_000` hardcode hay `Unit.base_fee` là chuẩn? (FIN-06)
2. DNG due items và Invoice due items có hiển thị cùng một dashboard / cộng vào một KPI "tổng
   nợ" không? Nếu có → đang double-count theo SV (chúng là 2 "rail" tách biệt, không reconcile).
3. Có cần cơ chế tự động đánh `Resolved` cho lifecycle review khi DNG request rời
   `pushed_to_dng` qua webhook thường không? Hiện không có → review desync với thực tế.
4. Cho phép áp lại `egc_exempt_credit` / học bổng sau khi void là cố ý hay lỗ hổng? (FIN-25 area)
5. `LifecycleDueExceptionReviewStatus::Ignored` giữ lại cho tương lai hay xóa? (dead code)

---

## Phụ lục — phạm vi đã review

- Data model: `FinanceCharge`, `FinanceChargeInstallment`, `StudentInvoice`, `InvoiceLine`,
  `Payment`, `PaymentApplication`, `DiscountAllocation`, voucher/scholarship, + migrations liên quan.
- Charge generation: EGC, Major, NonAcademic (BHYT), Retake, batch + preview queries.
- Payment/allocation/settlement: `AllocatePaymentAction`, `AutoAllocatePaymentsAction`,
  `PaymentService`, `SettlementService`, `InvoiceGenerationService`, void/installment.
- DNG: webhook controller/job, services (client/payment/webhook/reconciliation/checksum),
  models, batch push, reconciliation.
- Billing operations: dashboard stats/students, due items/invoices, lifecycle due exceptions
  (S-001/S-002), reminders, history.

---
---

# PHẦN B — Review Database / Schema & Kiến trúc dài hạn

Last updated: 2026-06-13 (bổ sung)
Phạm vi: 38+ migration finance + đối chiếu với query thật. Mục tiêu: chỉ ra vấn đề schema và
đề xuất **kiến trúc ổn định lâu dài**.

## B0. Bối cảnh quan trọng

Team đã có bản thiết kế ledger đúng đắn: `docs/features/finance/tuition-settlement-model-v2.md`.
Phần lớn vấn đề KHÔNG phải sai thiết kế, mà là:
1. **Thực thi dở dang** — "Phase 4" (ngừng sinh negative charge) chưa làm → tồn tại 2 đường tính
   chiết khấu song song.
2. **Thiếu hàng rào DB** — quá nhiều ràng buộc toàn vẹn bị phó mặc cho tầng application.
3. **Snapshot không thực sự đóng băng** — cột "snapshot" vẫn bị ghi đè.

## B1. Nguyên tắc cast tiền (điểm tốt)

Mọi cột tiền đều là `decimal(15,2)` nhất quán trên tất cả bảng (không có integer/`decimal(12,2)`
lẫn lộn ở tầng DB). **Lưu ý:** sai lệch nằm ở tầng PHP cast `decimal:0` trên `FinanceCharge.amount`
(xem FIN-08), không phải ở cột DB.

## B2. Bảng phát hiện tầng DB (ID `DB-xx`)

🔴 CRITICAL · 🟠 HIGH · 🟡 MEDIUM · ⚪ LOW

| ID | Mức | Nhóm | Vấn đề | Vị trí | Status |
|----|-----|------|--------|--------|--------|
| DB-01 | 🔴 | Snapshot | **`invoice_lines.amount_snapshot` KHÔNG immutable** — `updateOrCreate` ghi đè mỗi lần refresh invoice → phá snapshot lịch sử; payment đã áp đúng có thể thành "overpaid" khi snapshot co lại | `InvoiceGenerationService.php:83-96` | open |
| DB-02 | 🔴 | Model | **Dual-path chiết khấu** (negative charge lines vs `discount_allocations`); guard `=== 0.0` mong manh → Phase 4 của v2 chưa hoàn thành | `SettlementService.php:36-41` | open |
| DB-03 | 🔴 | Constraint | Thiếu unique `student_invoices(student_id, semester_id)` (đã comment out) → double-billing | `2026_01_18_211838:25` | open |
| DB-04 | 🔴 | Constraint | Idempotency webhook `dng_webhook_events.payload_hash` unique đã bị drop (= FIN-17, góc DB) | `2026_03_27_111002:20` | fixed (S-005: `2026_06_14_120000` collapses exact-dup retries then restores unique) |
| DB-05 | 🔴 | Type | `status` là `string` thường ở `finance_charge_installments(20)`, `dng_payment_requests(30)`, `dng_webhook_events.processing_status(30)`, `lifecycle_reviews(32)` → không ràng buộc giá trị | nhiều migration | open |
| DB-06 | 🔴 | Constraint | Không có CHECK ràng buộc dấu tiền: `finance_charges.amount` (credit phải < 0), `payments.amount > 0` → mis-sign âm thầm thành debit | `2026_01_17_100000:32` | open |
| DB-07 | ✅ | Constraint | `student_scholarship_awards`: unique 1 cột `student_id` (1 học bổng/SV) — **ĐÚNG nghiệp vụ, giữ nguyên** (đã xác nhận 2026-06-13). Tùy chọn: đổi tên index cho rõ | `2025_10_07_100001:27` | resolved |
| DB-08 | 🟠 | FK | Thiếu FK `voucher_applications.invoice_id` & `finance_charge_id` (chỉ index/không gì) → orphan | `2026_01_19_133749:28,36` | open |
| DB-09 | 🟠 | Idempotency | **[REFRAME #2 — xem Phần F5]** KHÔNG dùng unique trên ledger rows — cả natural pair LẪN `(source_ref_type, source_ref_id)` đều SAI: một thao tác void ghi NHIỀU dòng release cùng `(source_ref_type=VoidFinanceChargeAction, source_ref_id=charge_id)` (`releaseLinePayments:205`, `releaseLineDiscounts:303` loop). Append-only + 1 op → N dòng ⇒ unique natural-key là **sai công cụ**. Idempotency phải ở **tầng operation**: `lockForUpdate` + check-before-write (FIN-11), hoặc cột `idempotency_key`/operation-token riêng per thao tác | `SettlementService.php:205,303`; `VoidFinanceChargeAction.php:50-68` | open |
| DB-10 | 🟠 | Constraint | Thiếu unique `invoice_discounts(invoice_id, discount_type, reference_id)` → áp trùng học bổng/voucher | `2025_10_08_145820` | open |
| DB-11 | ⚪ | FK | **[INVALID trên local — xem Phần F4]** FK `student_invoices_billing_cycle_id_foreign` VẪN tồn tại (`SHOW CREATE TABLE` local; FK gốc `2025_10_08_145805:18`, sau chỉ `->change()` nullable không rớt FK trên Laravel 13 native schema). Claim "rớt FK do Doctrine" sai ở env này → chỉ giữ "verify FK trên từng env" | `SHOW CREATE TABLE student_invoices` (local) | invalid |
| DB-12 | 🟠 | Model | 3 cột amount cho cùng nghĩa vụ: `charge.amount` / `installment.amount` / `dng.amount` → trôi khi discount đổi sau push (DNG đã được báo số sai) | nhiều | open |
| DB-13 | 🟠 | Model | 2 "rail" `DngPaymentRequest` vs `StudentInvoice` không có reconciliation check | — | open |
| DB-14 | 🟠 | Cache | Cột cache `student_invoices.*` không đặt tên `cached_*` → dev mới tưởng là nguồn chân lý; `ListSettlementWorklistQuery`/`PreviewAutoAllocateQuery` trộn cache cũ qua `max()` → số sai theo chiều stale | `ListSettlementWorklistQuery.php:333-340` | open |
| DB-15 | 🟠 | Cache | `student_invoices.paid_at` không bị clear khi payment reversal → invoice hiện `paid_at` dù đã hết "paid" | `SettlementService.php` recalc | open |
| DB-16 | 🟠 | Index | Thiếu composite index nóng: `student_invoices(semester_id,status,due_date)`, `(student_id,semester_id)`; `dng_payment_requests(status,due_date)`, `(student_id,status)` | nhiều | open |
| DB-17 | 🟡 | Index | **[TRIM — xem Phần F4]** Các cột FK (`dng_payment_request_id`, `payment_id`, `finance_charge_id`…) ĐÃ được MySQL tự index qua `constrained()` (xác nhận `SHOW INDEX` local). Chỉ còn THIẾU index thật: **`dng_payment_requests.dng_transaction_id`** (string, không FK) + các **composite** ở DB-16. Bỏ đề xuất index trùng cho cột FK | `SHOW INDEX` (local) | open |
| DB-18 | 🟠 | Migration | Backfill `2026_05_25_000002` gọi model accessor → N+1 (~1000 query/chunk) + coupling vào app code + `down()` no-op (mất data khi rollback) | `2026_05_25_000002:62,98` | open |
| DB-19 | 🟡 | Migration | ENUM sửa bằng raw `ALTER MODIFY COLUMN` (MySQL-only, table-lock trên bảng lớn, forward-only, phải liệt kê lại toàn bộ giá trị → dễ mất value nếu sót) | `2026_05_05_061055`; `2026_05_18_000001`; `2026_04_20_000001` | open |
| DB-20 | 🟡 | Migration | `2026_01_18_211838` drop 5 cột tiền rồi `2026_03_25_000005` re-add → không zero-downtime; giá trị lịch sử mất qua các vòng | 2 migration | open |
| DB-21 | 🟡 | Convention | on-delete không nhất quán: `dng_payment_requests.student_id/payment_id/semester_id` = RESTRICT, nơi khác CASCADE/SET NULL → khó xóa/orphan khó đoán | nhiều | open |
| DB-22 | 🟡 | Query | Nhiều query load cả bảng vào PHP rồi phân trang/sum trong PHP (dashboard stats, settlement worklist, due items, due-invoices total) → cần **rewrite**, index không cứu | xem FIN-27/29 + bảng index | open |
| DB-23 | 🟡 | Performance | `whereHas`/`whereDoesntHave` tạo correlated subquery (lifecycle predicate, lọc campus) — cần index `students.status` hoặc đổi sang JOIN | `LifecycleDueItemPredicate.php:27,37` | open |
| DB-24 | 🟠 | Safety | Không soft-delete cho `payments`, `student_invoices` → có thể hard-delete không dấu vết. **QUYẾT ĐỊNH: BẬT soft-delete** (xem B5.3, lưu ý filter `deleted_at` trong query thô) | nhiều | planned |
| DB-25 | ⚪ | Charset | Không khai báo charset/collation rõ ràng — nếu DB default là latin1 thì cột text (mô tả, ghi chú) hỏng tiếng Việt | tất cả | open |

## B3. Kiến trúc mục tiêu — ổn định lâu dài (7 nguyên tắc)

Đây là phần trả lời trực tiếp yêu cầu "kiến trúc sử dụng lâu dài ổn định".

**NT1 — Ledger bất biến là NGUỒN CHÂN LÝ DUY NHẤT.**
4 bảng append-only: `finance_charges` (sự kiện phí/credit), `payments` (thu tiền),
`payment_applications` (chi tiền vào line), `discount_allocations` (chi chiết khấu). Reversal =
dòng âm MỚI, không bao giờ UPDATE/DELETE dòng cũ. Mọi số dư = `SUM(ledger)` và **chỉ tính ở một
chỗ** (`SettlementService::deriveInvoiceSnapshot`). → fix gốc FIN-01.

**NT2 — Snapshot phải thực sự đóng băng.**
`invoice_lines.amount_snapshot` thành immutable: dùng `firstOrCreate` (không update field tiền
khi row đã tồn tại). Sửa charge = **void + tạo lại**, không ghi đè snapshot. → fix DB-01.

**NT3 — Bỏ đường negative-charge (hoàn thành Phase 4 của v2).**
Khai tử các `charge_type` credit (`scholarship_credit`, `voucher_credit`, `defer_credit`) như
dòng settlement. Mọi chiết khấu sống trong `invoice_discounts` + `discount_allocations`. Loại bỏ
hoàn toàn dual-path. → fix DB-02 + đơn giản hóa toàn bộ.

**NT4 — Cache minh bạch + có lệnh rebuild.**
Đổi tên `student_invoices.{subtotal,discount_total,total_amount,paid_amount,status,paid_at}` →
`cached_*`, read-only, CHỈ `recalculateInvoiceSnapshot` được ghi. Thêm command
`finance:rebuild-invoice-snapshots` dựng lại từ ledger. Clear `paid_at` khi reversal. → fix DB-14/15.

**NT5 — DB tự bảo vệ, không phó mặc application.**
- UNIQUE: `student_invoices(student_id,semester_id)`, `invoice_number`, scholarship award,
  webhook idempotency, payment/discount application, `invoice_discounts(invoice,type,ref)`.
- CHECK: dấu tiền theo loại charge, `payments.amount > 0`, `installment.amount > 0`.
- MỌI cột status: **PHP backed enum + DB CHECK constraint** (chốt tại B5.2; KHÔNG raw ENUM, KHÔNG lookup table).
- FK đầy đủ + on-delete nhất quán (credit/cha-con → cascade; tham chiếu mềm → set null).

**NT6 — DNG chỉ là tầng giao thức cổng.**
`dng_payment_requests.amount` = "số đã đẩy lên cổng", KHÔNG phải nguồn chân lý nợ. Sau khi bridge,
balance luôn tính từ ledger. Thêm job reconciliation đối chiếu `SUM(dng paid)` vs
`SUM(payment_applications dng-sourced)` theo SV để lộ sai lệch bridge. → fix DB-13.

**NT7 — Migration tự chứa & an toàn.**
Backfill dùng raw `DB::table()` (không model accessor), chạy batch + transaction/advisory lock.
Tránh drop cột đang đọc (dùng expand→migrate→contract cho rolling deploy). Thay raw ALTER ENUM bằng
**PHP backed enum + DB CHECK constraint** (thống nhất với quyết định B5.2 — KHÔNG lookup table) để
tránh table-lock & forward-only. → fix DB-18/19/20.

### Sơ đồ nguồn chân lý mục tiêu

```
NGUỒN CHÂN LÝ (immutable ledger, append-only)
  finance_charges ──┐
                    ├─ invoice_lines (amount_snapshot = ĐÓNG BĂNG)
  payments ─────────┤        ├─ payment_applications (application/reversal = dòng ±)
                    │        └─ discount_allocations (allocation/release/reversal = dòng ±)
  invoice_discounts ┘
        │
        ▼ tính 1 chỗ duy nhất
  SettlementService::deriveInvoiceSnapshot()  → balance / paid / discount / status

CACHE (rebuild được, read-only)            GIAO THỨC CỔNG (không phải nguồn chân lý)
  student_invoices.cached_*                  dng_payment_requests / installments
  (chỉ recalculate ghi)                      (amount = số đẩy lên cổng; reconcile về ledger)
```

## B4. Việc cần THÊM/SỬA — danh sách migration tiến (additive, không rollback lịch sử)

Chạy được tiến về phía trước, an toàn. **Tiền điều kiện: chạy query kiểm tra trùng/sai trước khi
thêm constraint** (dùng bộ SQL bất biến ở Mục 4 Phần A).

| Ưu tiên | Migration đề xuất | Nội dung |
|---------|-------------------|----------|
| P1 | `add_unique_student_semester_to_student_invoices` | `unique(student_id, semester_id)` (DB-03) |
| P1 | `restore_unique_payload_hash_on_dng_webhook_events` | khôi phục `unique(payload_hash)` (DB-04) |
| ~~P1~~ → Code | idempotency ledger | **KHÔNG thêm unique trên `payment_applications`/`discount_allocations`** (một op ghi nhiều dòng cùng source_ref). Enforce ở tầng operation: `lockForUpdate` + check-before-write (FIN-11); nếu cần guard DB thì thêm cột `idempotency_key`(operation-token) unique riêng (DB-09) |
| P1 | `add_check_constraints_finance` | CHECK `payments.amount>0`, `installment.amount>0`. **`finance_charges.amount`**: end-state `> 0` sau khi bỏ credit charge (NT3); giai đoạn quá độ còn credit type → CHECK theo `charge_type` (debit `>0`, credit `<0`) (DB-06) |
| P2 | `add_fks_to_voucher_applications` | FK `invoice_id` SET NULL, `finance_charge_id` SET NULL (DB-08) |
| P2 | `add_status_check_dng_payment_requests` | varchar + CHECK theo PHP enum (audit distinct values trước) (DB-05) |
| P2 | `add_status_check_finance_charge_installments` | varchar + CHECK theo PHP enum (DB-05) |
| P2 | `add_unique_invoice_discounts_ref` | unique `(invoice_id, discount_type, reference_id)` (DB-10) |
| P2 | `add_missing_indexes_finance` | xem danh sách `CREATE INDEX` P1/P2 bên dưới (DB-16/17) |
| P2 | `add_soft_deletes_payments_invoices` | `softDeletes()` cho `payments`, `student_invoices` (+ filter `deleted_at` trong SettlementService) (DB-24) |
| P3 | `normalize_on_delete_dng_fks` | thống nhất on-delete (DB-21) |
| P3 | `add_unique_open_review_per_dng_request` | generated column hoặc app-lock (FIN-13/DB) |
| Code | thêm PHP backed enum cast cho mọi cột status finance | nguồn giá trị duy nhất, đồng bộ với CHECK (DB-05) |
| Code | refactor backfill → raw `DB::table` | bỏ N+1 + coupling (DB-18) |
| Code | `InvoiceGenerationService` `updateOrCreate`→`firstOrCreate` | đóng băng snapshot (DB-01) |

### Index ưu tiên cao (sẵn dùng)

```php
// P1
Schema::table('student_invoices', function (Blueprint $t) {
    $t->index(['semester_id','status','due_date'], 'si_semester_status_due_idx');
    $t->index(['status','due_date'], 'si_status_due_idx');
    // KHÔNG thêm index ['student_id','semester_id'] — đã được cover bởi UNIQUE(student_id, semester_id)
    // ở migration P1 (unique index phục vụ luôn lookup). Chỉ thêm nếu unique chưa áp.
});
Schema::table('dng_payment_requests', function (Blueprint $t) {
    $t->index(['status','due_date'], 'dng_pr_status_due_idx');
    $t->index(['student_id','status'], 'dng_pr_student_status_idx');
    $t->index('dng_transaction_id', 'dng_pr_txn_id_idx');
});
// LƯU Ý: các cột FK (dng_webhook_events.dng_payment_request_id, dng_payment_requests.payment_id /
// .finance_charge_id, finance_charge_installments.dng_payment_request_id) ĐÃ được MySQL tự index
// qua constrained() — ĐỪNG thêm lại (xác nhận SHOW INDEX local). Chỉ thêm composite + cột non-FK.

// P2 (chỉ composite + cột non-FK còn thiếu)
Schema::table('dng_payment_requests', fn (Blueprint $t) =>
    $t->index(['student_id','fee_type','status'], 'dng_pr_student_fee_status_idx'));
Schema::table('invoice_lines', fn (Blueprint $t) =>
    $t->index(['charge_id','status'], 'il_charge_status_idx'));
```

### Query phải REWRITE (index không cứu — DB-22)

- `GetBillingDashboardStatsQuery.php:53` — thay vòng lặp `deriveInvoiceSnapshot` trong PHP bằng SQL aggregate.
- `GetBillingDashboardStudentsQuery.php:37-94` — paginate ở DB trước, eager-load theo trang.
- `ListSettlementWorklistQuery.php:77` — paginate ở DB / pre-aggregate per student.
- `GetDueInvoicesSummaryQuery.php:37-41` — dùng `SUM(cached_total - cached_paid)` thay `.get()->sum(accessor)`.
- `GetLifecycleDueExceptionSummaryQuery.php:37-49` — gộp 5 `count()` thành 1 `GROUP BY students.status`.
- `ListDueItemsQuery.php:56-94` — `->paginate()` ở DB thay vì map-rồi-phân-trang trong PHP.

## B5. Quyết định kiến trúc đã chốt (2026-06-13)

1. **DB-07 — KHÔNG đổi.** Unique trên 1 cột `student_id` (1 học bổng/SV) là **đúng nghiệp vụ
   hiện tại**. Giữ nguyên; chỉ nên đổi tên index cho đỡ gây hiểu nhầm (tùy chọn). → DB-07 đóng.

2. **Status → dùng PHP backed enum + DB CHECK constraint (KHÔNG dùng lookup table cho phần lớn).**
   Người dùng cho phép "chuyển nếu tốt". Khuyến nghị của tôi: **không** dùng lookup table + FK cho
   các status code-driven vì nó thêm JOIN và lễ nghi không cần thiết. Cách **tốt nhất** cân bằng
   cả 3 yếu tố (an toàn DB / dễ mở rộng / không table-lock):
   - Mỗi status có **một PHP backed enum** (`string`) là nguồn giá trị duy nhất, cast trên model.
   - Cột DB là `varchar` + **CHECK constraint** liệt kê giá trị hợp lệ (MySQL 8.0.16+).
   - Thêm giá trị = cập nhật enum PHP + 1 migration drop/re-add CHECK (nhanh, không rewrite bảng
     như `MODIFY ENUM`, không forward-only).
   - **Ngoại lệ dùng lookup table** chỉ khi status cần metadata runtime (nhãn hiển thị, thứ tự,
     cờ `is_terminal`) hoặc admin sửa được lúc chạy — finance hiện KHÔNG cần.
   → thay thế hướng "ENUM column" cũ ở NT5; áp cho DB-05/DB-19.

3. **DB-24 — BẬT soft-delete** cho `payments` và `student_invoices`.
   **Lưu ý triển khai bắt buộc:** soft-delete chỉ là *lưới an toàn chống xóa nhầm* — thao tác
   "hủy" nghiệp vụ vẫn dùng pattern void/status (immutable ledger), KHÔNG dùng delete. Khi bật:
   - Mọi query tổng hợp bằng raw `DB::table()` trong `SettlementService` PHẢI thêm
     `whereNull('deleted_at')` (global scope của Eloquent KHÔNG áp cho query builder thô) — nếu
     không, payment/invoice đã soft-delete vẫn bị cộng vào balance.
   - Cân nhắc bật cho cả `finance_charges` để đồng bộ (hiện chỉ có void-status).

---
---

# PHẦN C — Review UI / Vòng đời UI (staff · BOD · admin)

Last updated: 2026-06-13 (bổ sung)
Phạm vi: 31 file Vue dưới `resources/js/pages/Finance/**` + `components/finance` + menu IA +
mapping role↔permission. Chấm theo chuẩn `swinx-frontend` (skill) + UX.

## C0. Phát hiện bao trùm

Frontend phản chiếu đúng vấn đề backend: **chắp vá nhiều thế hệ pattern cùng tồn tại.**
- **5 cách build bảng lọc khác nhau:** `useDataTable` (chuẩn mới), `useServerTableQuery` (cũ),
  `useInertiaFilters` (cũ hơn), `useTableFilters`, và `ref`+`router.get`+`setTimeout` thủ công.
- **3 bản `formatCurrency`** (`utils/format.ts` chuẩn, `types/finance.ts` trùng, inline), 3 bản
  `formatDate`, 3 kiểu status badge → không có "finance UI kit" dùng chung.
- App xây **operator-first**: mọi trang đều là màn thao tác; **chưa có view read-only cho BOD**.

## C1. Vai trò ↔ quyền ↔ UI (thực trạng)

Roles (seed `RoleAndPermissionSeeder.php:67-170`):

| Role | Mã | Quyền finance được seed | Thực tế dùng được UI finance |
|------|----|------------------------|------------------------------|
| Admin | `super_admin` | **TẤT CẢ** | Toàn bộ |
| BOD / Giám đốc | `giam_doc_dao_tao` | **KHÔNG có quyền finance nào** | Không mở được trang finance nào |
| Trưởng phòng | `truong_phong` | chỉ EGC (view/generate/sync/retake) | Chỉ EGC |
| Staff / Cán bộ | `can_bo` | chỉ EGC | Chỉ EGC |

**UI-ROLE-1 🔴 (product):** "staff/BOD/admin" như đề bài KHÔNG khớp mô hình hiện tại. Theo seed,
chỉ `super_admin` dùng được billing dashboard / settlement / payments / invoices / DNG. Staff
(`can_bo`) và BOD (`giam_doc_dao_tao`) **không có quyền** cho phần lớn UI finance. → Cần định
nghĩa lại bộ quyền cho từng role *trước khi* bàn UX, nếu không UI tốt cũng không ai (ngoài admin) mở được.

**UI-ROLE-2 🟠:** Không tồn tại view read-only cho BOD. Mọi trang Operations đều có nút hành động
(generate/fix/apply/push/gửi email/cancel DNG/void). Không có chế độ "viewer", không có dashboard
tổng hợp KPI nhiều kỳ không kèm worklist. Billing Dashboard gần giống nhưng bảng worklist + Quick
Actions bên dưới biến nó thành trang operator (`Operations/Dashboard.vue:376-415,488`).

## C2. Vòng đời UI & điều hướng (lifecycle navigation)

Domain lifecycle: **charge → invoice → DNG push → payment → settlement → exception.** UI bị **đứt
mạch** giữa các trang — staff không thể đi xuyên một giao dịch.

| ID | Mức | Đứt mạch | Vị trí |
|----|-----|----------|--------|
| UI-LC-1 | 🟠 | **Charge Show không link tới Invoice** của nó (thiếu `invoice_id` trong props) | `Charges/Show.vue` |
| UI-LC-2 | 🟠 | **Payment Show không link ngược về Invoice** (`invoice_number` chỉ là text) | `Payments/Show.vue:88` |
| UI-LC-3 | 🟠 | **Settlement worklist** số invoice là text thường, không link tới invoice/charge | `Settlement.vue:361-413` |
| UI-LC-4 | 🟡 | Installment hiện `#dng_payment_request_id` text, không click được | `Charges/Show.vue:395` |
| UI-LC-5 | 🟡 | Dashboard hàng SV → link sang phí *học vụ* (`finance.students.charges`), không phải billing của SV → dead-end | `Operations/Dashboard.vue:573` |
| UI-LC-6 | 🟡 | ExceptionsQueue "Fix now" xong ở lại list, không link tới charge/invoice vừa tạo | `ExceptionsQueue.vue:152-181` |
| UI-LC-7 | 🟡 | Thiếu breadcrumb/back ở Dashboard, ExceptionsQueue, DueCalendar, GenerateCharges; back về index cố định, mất ngữ cảnh nếu vào từ trang SV | nhiều |
| UI-IA-1 | 🟡 | "Due Calendar" đặt tên sai — không có calendar/timeline, chỉ là list DNG request | `DueCalendar.vue:317` |

→ Hệ quả: để audit 1 giao dịch (charge → invoice → payment → DNG), staff phải mở 3-4 lần điều
hướng rời rạc. **Hoàn thiện "tam giác" charge↔invoice↔payment + link DNG là việc UX quan trọng nhất.**

## C3. Tuân thủ chuẩn frontend (swinx-frontend)

| ID | Mức | Vi phạm | Vị trí |
|----|-----|---------|--------|
| UI-STD-1 | 🟠 | **5 thế hệ composable bảng/lọc cùng tồn tại** (xem C0). Cần đồng bộ về `useDataTable` | toàn cụm |
| UI-STD-2 | 🟠 | **`ExceptionsQueue` "Fix now" dùng raw `fetch()` + tự set CSRF** — bỏ qua Inertia router/useForm. (**SỬA:** URL dùng `route('api.finance.operations.fix-exception', ...)` đúng chuẩn, KHÔNG phải literal) | `ExceptionsQueue.vue:156-180` |
| UI-STD-3 | 🟠 | **`window.confirm()`** cho retry webhook (phải dùng Pinia confirm store) | `DngWebhookEvents/Show.vue:71` |
| UI-STD-4 | 🟡 | **[REFRAME — xem Phần F5]** Chiến lược flash/toast KHÔNG nhất quán (lẫn `Inertia::flash`, legacy `->with('success')`, toast thủ công). **Toast đúp CHỈ xảy ra khi endpoint dùng `Inertia::flash()` + `useFlashToast` bridge** — phải kiểm TỪNG endpoint, không kết luận blanket. Nhiều controller finance dùng legacy `->with` (vd EGC store) → toast thủ công ở đó KHÔNG đúp. (`SplitInstallments:186` KHÔNG phải `toast.success` — chỉ `toast.error` ở onError) | cần audit per-endpoint |
| UI-STD-5 | 🟠 | **`console.log(date)` trong `utils/format.ts:18`** chạy mọi lần render ngày (prod); `console.error` ở DueCalendar:264, ExceptionsQueue:176 | nhiều |
| UI-STD-6 | 🟠 | **`Settlement.vue` thiếu mount `AppLayout`** + dùng `toast` mà không import → lỗi runtime khi chọn 0 SV | `Settlement.vue:159` |
| UI-STD-7 | 🟡 | Export dựng URL bằng nối chuỗi + `window.location.href` thay vì `route()`/Inertia | `Invoices/Index.vue:86-94` |

## C4. An toàn & hành động nguy hiểm

| ID | Mức | Vấn đề | Vị trí |
|----|-----|--------|--------|
| UI-SAFE-1 | 🔴 | **Void Charge không hiện tác động tài chính** trước khi confirm (số tiền, đã trả, allocations, installment đã trả) — chỉ ô nhập lý do trống | `Charges/Show.vue:207-238` |
| UI-SAFE-2 | 🔴 | **Form allocate thủ công ở Payments/Show là "Temporary UI"** — nhập `charge_id` thô, không lọc theo SV, không validate, không confirm → dễ misallocate tiền | `Payments/Show.vue:197-216` |
| UI-SAFE-3 | 🟠 | **[REFRAME — xem Phần F5]** Hardcode `15_000_000` ở preview (ĐÚNG). **SỬA phạm vi:** backend `store` recompute TOÀN BỘ eligible theo filter (`EgcChargeGenerationController:79-97`), `students` payload chỉ là **override `block_count`** — KHÔNG phải "chỉ tạo cho current page". Bug thật: override `block_count` chỉ page-local; trang ẩn dùng default `max_chargeable_blocks` → preview/amount dễ misleading | `GenerateCharges.vue:358`; `EgcChargeGenerationController.php:79-97` |
| UI-SAFE-4 | 🟠 | **Bulk action không confirm:** Settlement "Apply selected", ExceptionsQueue "Fix now", DueCalendar gửi nhắc (email không hoàn tác), AutoAllocateDialog execute thẳng, retry push | nhiều |
| UI-SAFE-5 | 🟠 | RetakeAdjustments hardcode `7_500_000`; DNG retry cho phép trên event checksum sai (không thể thành công) | `RetakeAdjustments.vue:191`; `DngWebhookEvents/Show.vue:56` |

## C5. Hiển thị tiền & nhất quán

| ID | Mức | Vấn đề | Vị trí |
|----|-----|--------|--------|
| UI-MON-1 | 🟠 | **3+ bản `formatCurrency`**; bản ở `Major/GenerateCharges` để 2 số lẻ → "5.000.000,00 ₫" lệch các trang khác | `types/finance.ts:191`, `utils/format.ts:3`, `AutoAllocate.vue:99`, `Settlement.vue:94`, `DngWorklist.vue:285`, `Major/GenerateCharges.vue:153` |
| UI-MON-2 | 🟡 | Số dư âm (credit/overpaid) không tô màu nhất quán: Charges/Show có, Invoices/Show & Index không | `Invoices/Show.vue:254` |
| UI-MON-3 | 🟡 | Thẻ thống kê phạm vi *trang hiện tại* nhưng dễ đọc nhầm thành tổng nợ SV | `Charges/Index.vue:139-153` |
| UI-MON-4 | 🟡 | SplitInstallments dùng `<Input type=number>` thô (không tách nghìn) → dễ nhập nhầm 5M vs 50M | `SplitInstallmentsModal.vue:228` |
| UI-MON-5 | ⚪ | "Chưa phân bổ" (unapplied) tô xanh (gợi ý tốt) trong khi đây là trạng thái cần xử lý — nên amber | `Payments/Index.vue:264` |
| UI-CON-1 | 🟡 | 3 chiến lược status badge (class string / variant phi chuẩn / `variant as any`); không có `StatusBadge` dùng chung; DNG hiện status `snake_case` thô | nhiều |
| UI-CON-2 | 🟡 | Lẫn lộn tiếng Việt/Anh trong cùng cụm (Charges VN, Invoices EN, Payments mixed) | nhiều |
| UI-CON-3 | 🟡 | 3 nguồn `formatDate`; header size, filter bar, checkbox (native vs `<Checkbox>`) không nhất quán | nhiều |

## C6. Đánh giá vòng đời UI theo từng vai trò

**Staff (cán bộ thu/vận hành):** thiếu một "trang điều phối" rõ ràng để bắt đầu ngày làm việc; các
trang siloed (C2) buộc nhảy qua lại; nhiều hành động hằng ngày (settlement apply, gửi nhắc, fix
exception) không confirm/không báo kết quả rõ. Hiện `can_bo` còn chưa có quyền mở các trang này.

**BOD (giám đốc):** **không có gì** — vừa không có quyền (C1), vừa không có view oversight read-only
(C2-ROLE-2). Đây là khoảng trống lớn nhất so với đề bài.

**Admin (super_admin):** mở được tất cả; nhưng gánh các rủi ro an toàn (C4) và sự thiếu nhất quán
(C3/C5) ở mức cao nhất vì là người dùng duy nhất chạm tới mọi trang.

## C7. Khuyến nghị UI (ưu tiên)

1. **Định nghĩa lại bộ quyền theo vai trò** (UI-ROLE-1): cấp quyền finance phù hợp cho `can_bo`
   (vận hành), `truong_phong`/`giam_doc_dao_tao` (oversight). Không sửa cái này thì UX vô nghĩa.
2. **Hoàn thiện tam giác điều hướng charge↔invoice↔payment + link DNG** (UI-LC-1..4) — việc UX
   tác động lớn nhất cho staff.
3. **Vá an toàn hành động tiền:** impact preview khi void (UI-SAFE-1); thay form allocate tạm bằng
   selector + confirm (UI-SAFE-2); sửa bug phạm vi + hardcode EGC (UI-SAFE-3, gắn FIN-06); thêm
   confirm cho mọi bulk action (UI-SAFE-4).
4. **Chuẩn hóa hạ tầng UI finance:** gộp về `useDataTable`, một `formatCurrency`/`formatDate`, một
   `StatusBadge`, xóa `console.log`, sửa `Settlement.vue` (layout + import toast), bỏ raw `fetch()`
   và `window.confirm()` (UI-STD-1..6).
5. **Tạo view BOD read-only** (UI-ROLE-2): trang tổng hợp KPI nhiều kỳ (tổng nợ, quá hạn, exception)
   không có nút hành động — hoặc ẩn worklist/Quick Actions với user không có quyền action.
6. Thống nhất ngôn ngữ (chọn 1: VN hoặc EN) toàn cụm finance (UI-CON-2).

## C8. Quyết định UI đã chốt (2026-06-13)

1. **Vai trò: chủ trương admin & staff NGANG NHAU** (cố ý — để staff chịu trách nhiệm thao tác).
   → KHÔNG cần tách quyền admin/staff. **NHƯNG UI-ROLE-1 GIỮ `open`** (không hạ mức) vì **seed hiện
   tại MÂU THUẪN với chủ trương:** `RoleAndPermissionSeeder.php:154` cấp `can_bo` chỉ nhóm EGC, không
   có billing/payments/invoices/DNG. → Phải **audit role thật ở prod** rồi mới chốt: staff đang gán
   `super_admin` (đã ngang) hay `can_bo` (cần bổ sung full finance permissions)? Đây là *khoảng cách
   giữa quyết định sản phẩm và trạng thái code* — chưa đóng được tới khi xác minh. (C10)
2. **BOD = role MỚI cần thêm**, chỉ đọc (oversight). Không thao tác. Xem C9.
3. **Ngôn ngữ: toàn bộ UI finance dùng Tiếng Việt**, thuật ngữ đúng ngữ cảnh giáo dục. → UI-CON-2
   chốt hướng: dịch các trang đang để tiếng Anh (Invoices, một phần Payments) sang tiếng Việt theo
   bộ thuật ngữ ở C8.1 bên dưới. Không lẫn lộn.

### C8.1 Bộ thuật ngữ tiếng Việt (giáo dục) — dùng nhất quán

| Khái niệm | Thuật ngữ chuẩn |
|-----------|-----------------|
| invoice | Hóa đơn |
| charge | Khoản phí |
| tuition_term | Học phí kỳ |
| egc_level_fee | Phí EGC |
| retake_fee | Phí học lại |
| exam_resit_fee | Phí thi lại |
| bhyt | Bảo hiểm y tế (BHYT) |
| non-academic / course_fee | Phí khác |
| scholarship | Học bổng |
| voucher / discount | Miễn giảm |
| payment | Khoản thu / Thanh toán |
| allocation | Phân bổ |
| installment | Đợt trả góp |
| balance / outstanding | Dư nợ (còn phải thu) |
| paid | Đã thu |
| revenue | Doanh thu |
| collection rate | Tỷ lệ thu |
| due / overdue | Đến hạn / Quá hạn |
| semester | Học kỳ |
| void | Hủy (thu hồi) |
| settlement | Đối soát / Tất toán |

## C9. Spec — Trang BOD Oversight (chỉ đọc, có chart)

Mục tiêu: lãnh đạo xem **tổng thể**, **so sánh giữa các kỳ**, **nguồn doanh thu** và **dư nợ hiện
tại** theo từng kỳ hoặc toàn bộ. Không có nút hành động.

> ⚠️ **Phụ thuộc:** độ chính xác phụ thuộc **FIN-01** (gộp nguồn chân lý balance) + **dọn INV-6**
> (28 invoice trùng, nếu không chart sẽ cộng kép doanh thu/dư nợ). Mọi số liệu PHẢI tính bằng **SQL
> aggregate** (không vòng lặp PHP — xem DB-22), tốt nhất đọc từ cột `cached_*` sau khi NT4 hoàn tất.

### Bộ lọc
- Chọn **một học kỳ** hoặc **"Tất cả các kỳ"**; (tùy chọn) lọc theo campus/chương trình.

### KPI cards (theo phạm vi lọc)
1. **Tổng phải thu** (đã trừ miễn giảm) = Σ khoản phí active − Σ miễn giảm.
2. **Đã thu** = Σ phân bổ thu ròng (application − reversal).
3. **Dư nợ** = Tổng phải thu − Đã thu.
4. **Tỷ lệ thu** = Đã thu / Tổng phải thu (%).
5. **Quá hạn** = Σ dư nợ của hóa đơn/khoản đã quá hạn `due_date`.
6. **Số SV còn nợ**.

### Charts
1. **So sánh giữa các kỳ** (bar nhóm): mỗi kỳ 3 cột — Phải thu / Đã thu / Dư nợ.
2. **Tỷ lệ thu theo kỳ** (line): xu hướng % thu qua các kỳ.
3. **Nguồn doanh thu** (stacked bar hoặc donut): bóc tách theo loại phí (Học phí kỳ / Phí EGC /
   Phí học lại / Phí thi lại / BHYT / Phí khác) — cho kỳ đang chọn, hoặc theo kỳ nếu "Tất cả".
4. **Cơ cấu dư nợ** (stacked / aging): dư nợ theo loại phí và/hoặc mức quá hạn (chưa đến hạn /
   1-30 / 31-60 / >60 ngày).

### Nguồn dữ liệu (bám ledger — sau khi NT1 ổn định)
- **Doanh thu / Đã thu**: `payment_applications` (ròng) → `invoice_lines` → `finance_charges` →
  group theo `semester_id`, `charge_type`.
- **Phải thu (billed)**: `finance_charges` (active, amount>0) − `discount_allocations` ròng, group kỳ.
- **Dư nợ**: Phải thu − Đã thu (mức kỳ / mức toàn bộ).
- **Nguồn doanh thu**: group theo `charge_type` (map nhãn theo C8.1).
- **Quá hạn**: lọc `due_date < hôm nay` ở mức hóa đơn/DNG.

### Quyền & điều hướng
- Permission mới `view_finance_bod_overview`, gán cho role BOD (chỉ đọc).
- Mọi số trên chart có thể **drill xuống** danh sách chi tiết (tái dùng các trang list hiện có ở
  chế độ read-only) — nhưng KHÔNG có nút action.
- Tuân chuẩn `swinx-frontend`: `useDataTable` cho list drill-down, `Inertia::defer` cho dữ liệu
  chart nặng, một `formatCurrency` dùng chung.

### Thư viện chart (đã xác định)
Dự án dùng **Chart.js + vue-chartjs**, đã có component dùng lại: `@/components/ui/chart/BarChart.vue`,
`LineChart.vue`, `DoughnutChart.vue`, `ChartTooltip.vue` (xem mẫu ở `components/dashboard/Chart*.vue`).
→ Trang BOD **tái dùng các component này**, không thêm thư viện mới. Map chart:
- So sánh giữa các kỳ → `BarChart` (grouped); Tỷ lệ thu → `LineChart`; Nguồn doanh thu → `DoughnutChart`
  hoặc `BarChart` stacked; Cơ cấu dư nợ → `BarChart` stacked.

## C10. Câu hỏi UI còn lại
- Staff hiện được gán role nào trong prod (`can_bo` hay `super_admin`)? Cần để biết có phải bổ sung
  quyền finance cho `can_bo` hay không.

---
---

# PHẦN D — Lộ trình tổng hợp (A + B + C)

Last updated: 2026-06-13

**Nguyên tắc sắp xếp:** KHÔNG làm big-bang cả 3 mảng. **DB + Logic phải gộp chung** (cùng động vào
`SettlementService` + cùng bảng). **UI đi sau khi số đã đúng** — trừ nhóm fix an toàn độc lập làm
song song ngay. Không thêm ràng buộc DB trước khi dọn dữ liệu bẩn.

| Pha | Nội dung | Mảng | ID liên quan | Phụ thuộc |
|-----|----------|------|--------------|-----------|
| **0. Audit + chặn chảy máu** | Chạy `finance:audit-invariants` (11 invariants, đã có — xem D1) trên snapshot prod khi có dữ liệu. Fix UI an toàn độc lập: impact preview khi void, gỡ/khóa form allocate tạm, confirm bulk action, fix EGC hardcode+bug phạm vi, sửa `Settlement.vue`, xóa `console.log` | UI(safety)+đo | INV-1..11, UI-SAFE-1/2/3/4, FIN-06, UI-STD-5/6 | — (làm ngay) |
| **1. Lõi: nguồn chân lý + hàng rào DB** | **Core: FIN-01** gộp `deriveInvoiceSnapshot` + bỏ path balance cũ + đóng băng `amount_snapshot` + bỏ negative-charge (Phase 4). **Defensive: FIN-02/03** thêm backstop lọc status + test. Thêm P1 UNIQUE/CHECK/FK/lockForUpdate **sau khi data đã dọn** (INV-6/11) | **DB+Logic** | core FIN-01; def. FIN-02/03; FIN-11, DB-01/02/03/04/06/09, NT1-5 | Pha 0 |
| **2. Tiền còn lại + DNG** | Cap học bổng, regen charge, drift installment; bảo mật DNG (checksum + dedup) + reconciliation | DB+Logic | FIN-04/05/07/09/10/15/16/17, DB-12/13 | Pha 1 |
| **3. Chuẩn hóa UI + điều hướng** | Gộp `useDataTable`, một `formatCurrency`/`formatDate`/`StatusBadge`, dịch tiếng Việt (C8.1), hoàn thiện tam giác charge↔invoice↔payment | UI | UI-STD-1/4/7, UI-MON-1, UI-CON-1/2/3, UI-LC-1..7 | Pha 1 |
| **4. BOD Oversight** | Dashboard chart (C9) trên aggregate đáng tin + cột `cached_*` | UI+Logic | C9, DB-22, NT4 | Pha 1–3 |

**Song song được:** Pha 0 bắt đầu ngay, song song chuẩn bị Pha 1. Pha 1→2→4 tuần tự bắt buộc.
Pha 3 xen kẽ sau Pha 1.

**Câu trả lời ngắn:** Logic+DB trước (gộp, Pha 1, vì là gốc của "không tin số liệu"); UI sau — trừ
nhóm safety độc lập ở Pha 0.

## D1. Kết quả audit local (2026-06-13)

Chạy `./scripts/dev.sh artisan finance:audit-invariants --sample` trên **local snapshot** (theo
owner: local hiện giống prod, prod chưa phát sinh phí mới; **chưa ghi nguồn/timestamp/lệnh restore
của snapshot** — cần bổ sung nếu dùng làm bằng chứng chính thức). Context: 996 charges, 524
payments, 552 invoices, 985 lines, 395 discounts.

| Invariant | Kết quả | Ghi chú |
|-----------|---------|---------|
| INV-1 over-allocation | ✅ 0 | |
| INV-2 paid_amount cache drift | ✅ 0 | cache hiện khớp live |
| INV-3 charge↔1 active line | ✅ 0 | |
| INV-4 payment trên void line | ✅ 0 | |
| INV-5 discount reversed còn allocation | ✅ 0 | |
| INV-6 **invoice trùng (student+kỳ)** | ❌ **28 nhóm / +32 invoice dư** | chủ yếu `[paid,paid]`; 52/60 có dòng active → double-billing đã xảy ra |
| INV-7 scholarship trùng | ✅ 0 | |
| INV-8 balance âm | ✅ 0 | |
| INV-9 invoice_number trùng | ✅ 0 | |
| INV-10 payment ≤ 0 | ✅ 0 | |
| INV-11 **webhook payload_hash trùng** | ❌ **3** | mất idempotency (DB-04) đã xảy ra |

**Diễn giải:**
- **Arithmetic/ledger invariants PASS** (over-allocation, cache drift, void/reversed netting,
  balance âm) → số học từng hóa đơn nhất quán. **Uniqueness/idempotency invariants FAIL** (INV-6, INV-11).
- **KHÔNG kết luận "tiền hoàn toàn đúng".** Double-billing (INV-6: 28 nhóm `[paid,paid]` có dòng
  active) nghĩa là một số SV có 2 hóa đơn đã trả cho cùng kỳ → **có thể đã thu trùng**. Tính đúng
  về tiền của các nhóm này **chưa xác định** — cần đối soát từng nhóm (2 hóa đơn trùng nội dung =
  thu thừa; hay bổ trợ nhau do race tách hóa đơn). Đây là việc của Pha 1.
- **Lưu ý:** FIN-01 (3 bản `deriveInvoiceSnapshot` lệch nhau) là **rủi ro code tiềm ẩn**, chỉ kích
  hoạt với data shape cụ thể (dòng credit âm không có discount_allocation). Data hiện chưa trúng
  shape đó → vẫn phải fix để chặn drift tương lai, nhưng hiện CHƯA tạo số sai trong kho.
- **Nợ cụ thể phải dọn:** 28 nhóm invoice trùng (mang phí active, đã thanh toán → cần **đối soát
  từng nhóm**: chọn invoice canonical, void/gộp cái còn lại, kiểm tra payment khớp — KHÔNG xóa hàng
  loạt) + 3 webhook trùng (an toàn để gộp/đánh dấu). Phải dọn xong rồi mới thêm `UNIQUE(student_id,
  semester_id)` và khôi phục `UNIQUE(payload_hash)`.

---
---

# PHẦN E — UI: làm mới hoàn toàn hay sửa code cũ? (đánh giá)

Last updated: 2026-06-13. Đánh giá theo tiêu chí **chất lượng/đúng đắn** (không xét nguồn lực/thời gian).

## E0. Kết luận

**KHÔNG làm mới toàn bộ. Chiến lược lai — "Dựng nền dùng chung mới → refactor trang cũ lên nền đó →
xây mới những bề mặt thật sự thiếu (BOD) + viết lại vài trang hỏng".** Đây vừa nhanh hơn vừa chất
lượng hơn greenfield, vì:
- **IA hiện tại ĐÚNG** (charges / invoices / payments / operations / EGC / DNG map đúng domain) —
  cái sai là *tính nhất quán hạ tầng* + *thiếu BOD* + *điều hướng đứt*, đều sửa được tại chỗ.
- Các trang cũ **đang encode hành vi domain thật** + tiêu thụ contract backend ổn định (routes,
  props, FormRequest). Viết lại từ 0 sẽ phải tái suy luận các luồng này → tái sinh bug edge-case,
  không nhanh hơn cũng không tốt hơn.
- Vấn đề lớn nhất (5 cách build bảng, 3 formatCurrency…) được giải bằng **chuẩn hóa hạ tầng** —
  cùng một khối lượng việc dù làm tại chỗ hay greenfield, nhưng tại chỗ rủi ro thấp hơn.

Greenfield chỉ "tốt hơn" khi IA hỏng tận gốc — ở đây không phải.

## E1. Quyết định theo từng bề mặt

| Bề mặt | Quyết định | Lý do |
|--------|-----------|-------|
| **Finance UI foundation** (kit dùng chung) | **XÂY MỚI** | Chưa tồn tại. Gồm: 1 pattern list trên `useDataTable`, 1 `formatCurrency`/`formatDate`, 1 `StatusBadge`, component hiển thị tiền/credit, dialog confirm-có-impact, bản đồ thuật ngữ VN (C8.1). Nhỏ, đòn bẩy cao |
| Charges, Invoices, Payments index/show | **REFACTOR lên nền** | Luồng đúng, chỉ cần đổi hạ tầng + dịch VN + thêm link điều hướng + guard |
| EGC pages | **REFACTOR lên nền** | Tương tự; thêm fix bug phạm vi + bỏ hardcode |
| DNG pages | **REFACTOR lên nền** | Đổi `window.confirm`→Pinia, badge nhãn hóa |
| **Payments/Show form allocate ("Temporary UI")** | **VIẾT LẠI** | Là stub nguy hiểm — thay bằng selector charge theo SV + confirm |
| **ExceptionsQueue** (raw `fetch`) | **VIẾT LẠI** | Bỏ fetch thủ công, dựng lại trên `useDataTable` + Inertia |
| **Settlement.vue** (thiếu layout, lỗi runtime) | **VIẾT LẠI** | Lỗi cấu trúc, viết lại sạch hơn vá |
| **BOD Oversight** (C9) | **XÂY MỚI** | Chưa có; build fresh trên nền + Chart.js sẵn có |
| (tùy chọn) **Staff daily console** | XÂY MỚI nếu cần | Trang điều phối khởi đầu ngày cho staff |

## E2. Thứ tự (khớp Pha 3–4 ở Phần D)
1. Xây **foundation kit** trước (chặn việc tái tạo chắp vá).
2. Refactor lần lượt từng trang lên kit (ưu tiên trang nhiều người dùng: Charges/Invoices/Payments).
3. Viết lại 3 trang hỏng.
4. Xây BOD Oversight trên số liệu đã đáng tin (sau Pha 1–2).

---
---

# PHẦN F — Hiệu chỉnh sau counter-review (2026-06-13)

Owner đối chiếu lại tài liệu với code thật. Đã verify từng điểm bằng code; kết luận:

| Điểm | Phán xử | Bằng chứng | Hành động |
|------|---------|-----------|-----------|
| **FIN-23** | ❌ **SAI** | `CancelDngPaymentRequestAction.php:130-145` void TẤT CẢ linked charges qua `voidChargeAction->handle()`; `ResolveLifecycleDueExceptionAction:178` gọi action này. Cờ `requireVoidPermission` chỉ thêm permission+audit | Bỏ finding; còn lại: test linkage (pivot/`finance_charge_id`) không rỗng |
| **FIN-19** | 🔧 **REWRITE** | Pivot ở trong cùng transaction (`CreateBatchDngFromChargesAction.php:113→245`). Rủi ro thật = external DNG call (`DngPaymentService.php:91`) trong transaction ngoài chưa commit; trái comment `:72` | Đổi mô tả sang "external side-effect trong transaction" |
| **FIN-16** | ✅ đúng / 📝 làm rõ citation | Webhook public ở `routes/api.php:30-32` (GỐC, no auth). `app/Modules/Finance/routes/api.php` là web+auth admin. Checksum-bypass vẫn đúng (`DngWebhookService.php:62`) | Chú thích rõ file gốc vs module |
| **FIN-18** | ➕ **MỞ RỘNG** | `DngWebhookService.php:326-338` cũng thiếu `cancel_pushed_to_dng` → `default => 0`, không chỉ reconciliation | Thêm service thứ 2 vào phạm vi |
| **Status (B5 vs NT7)** | ❌ **mâu thuẫn nội bộ** | B5.2 chốt enum+CHECK; NT7 (cũ) còn ghi "chuyển dần sang lookup table" | Sửa NT7 về enum+CHECK |
| **C8 / UI-ROLE-1** | 🔧 **giữ `open`** | Chủ trương admin=staff nhưng seed `RoleAndPermissionSeeder.php:154` cấp `can_bo` chỉ EGC → mâu thuẫn chưa giải | Không hạ mức; chờ audit role prod (C10) |
| **INV-7 / INV-8 / signed-ledger** | 🔍 dẫn tới reframe FIN-02/03 | `SettlementService.php:163-165` (payment reversal = `-abs`), `:306` (discount release = `-abs`) → **ledger CÓ DẤU, SUM tự net**. INV-8 đúng (không cần join status). INV-5/INV-4 audit = 0 xác nhận | Reframe FIN-02/03 (xem dưới) |

## F1. Hệ quả quan trọng nhất: FIN-02 & FIN-03 bị OVERSTATED

Điểm INV-8 của owner kéo theo một nhận thức sâu hơn: **ledger thanh toán/chiết khấu là append-only
CÓ DẤU** — reversal/void/release được ghi thành **dòng ÂM mới**, không phải đổi tại chỗ. Do đó:
- `SUM(payment_applications.amount)` và `SUM(discount_allocations.amount)` **đã tự loại trừ** các
  khoản reversed/void → KHÔNG "tính dư" như FIN-02/03 mô tả.
- Audit thực tế khẳng định: **INV-4 = 0** (không payment treo trên void line), **INV-5 = 0** (không
  discount reversed còn dư).
- ⇒ FIN-02/03 hạ từ 🔴 xuống 🟡 **"defensive gap"**: balance đúng *miễn là* mọi path void/reverse
  đều ghi dòng âm offsetting. Chỉ thành bug nếu có path đổi `status` mà KHÔNG ghi dòng âm. Việc cần
  làm: thêm filter `status` làm backstop + test bất biến "mọi void/reverse ⇒ có dòng âm".
- **FIN-01 KHÔNG bị ảnh hưởng** và vẫn đứng vững: 3 bản `deriveInvoiceSnapshot` lệch nhau ở *fallback
  dòng `amount_snapshot` âm* (legacy credit lines), không liên quan tới signing — đây là rủi ro
  thật, là gốc của "số dư mỗi màn khác nhau".

## F2. Ghi chú về bộ SQL invariant (Mục 4)
- **INV-8 đúng như đang viết** nhờ ledger có dấu (không cần join `invoice_discounts.status`).
- **INV-7** (trùng học bổng) **dư thừa** vì DB-07 đã có `UNIQUE(student_id)` enforce — nhưng giữ
  lại như guard rẻ (phát hiện nếu ai đó drop constraint). Không phải "bất biến mâu thuẫn".

## F3. Phần backbone giữ nguyên (owner xác nhận, tôi đồng ý)
**FIN-01** (đa nguồn tính balance), **FIN-11** (thiếu lock khi allocate), **FIN-20/21** (stub),
**FIN-26/27** (Due Calendar summary≠list + manual pagination), **UI-STD-6** (`Settlement.vue` dùng
`toast` không import + không mount `AppLayout`) — tất cả verified, giữ nguyên mức.

> Bài học quy trình: các finding loại "code KHÔNG làm X" cần trace tới tận action được gọi (không
> dừng ở caller). FIN-23 sai vì dừng ở `ResolveLifecycleDueExceptionAction` mà không mở
> `CancelDngPaymentRequestAction`. Các bug "do thiếu filter status" cần kiểm mô hình ledger trước
> (append-only có dấu thì SUM đã net).

## F4. Vòng counter-review #2 — DB constraints / index / idempotency (2026-06-13)

Owner đối chiếu đề xuất DB với migration + `SHOW CREATE TABLE`/`SHOW INDEX` local. Tất cả 5 điểm
ĐÚNG (verified):

| Điểm | Phán xử | Bằng chứng | Sửa thành |
|------|---------|-----------|-----------|
| **DB-09** | ❌ đề xuất sai mô hình (lần 1) → **vẫn sai (lần 2, xem F5)** | `SettlementService:167` create dòng mới; **và** `(source_ref_type, source_ref_id)` cũng không đủ định danh — 1 op void ghi N dòng cùng cặp đó | (vòng F4 đề "source_ref pair" — **F5 sửa tiếp**: bỏ hẳn DB-unique, idempotency ở tầng operation) |
| **FIN-13 (invoice_number)** | ❌ sai | `2025_10_08_145805:16` `->unique()`; local có `student_invoices_invoice_number_unique` | "đã unique → cần retry-on-collision cho generator". Giữ các unique còn thiếu thật `(student_id,semester_id)`+review |
| **DB-11** | ❌ false-positive | `SHOW CREATE TABLE` local: `student_invoices_billing_cycle_id_foreign` còn nguyên; `->change()` nullable không rớt FK trên Laravel 13 | → `invalid`, chỉ "verify per env" |
| **DB-17** | 🔧 trim | `SHOW INDEX` local: 4 cột FK đều INDEXED tự động qua `constrained()`; chỉ `dng_transaction_id` NO INDEX | Bỏ index trùng cho cột FK; giữ `dng_transaction_id` + composite (DB-16) |
| **DB-06/B4 CHECK** | 🔧 mạnh hơn | NT3 chốt bỏ negative-charge; B4 cũ chỉ `amount<>0` (quá yếu) | end-state `amount>0`; quá độ → CHECK theo `charge_type` |

> Bài học #2: **đề xuất "thêm constraint/index" phải kiểm schema thật trước** (`SHOW CREATE/INDEX`),
> vì `constrained()` đã auto-index FK và nhiều unique đã có. Và **đề xuất constraint phải nhất quán
> với mô hình dữ liệu** đã chốt — unique trên natural pair mâu thuẫn với ledger append-only (chính
> là mô hình dùng để reframe FIN-02/03 ở F1). Idempotency của ledger phải theo *operation id*, không
> theo *trạng thái hàng*.

## F5. Vòng counter-review #3 — call-chain & prescription (2026-06-13)

Owner trace call-chain thật. Tất cả 8 điểm + 3 điểm siết ĐÚNG (verified bằng code):

| Điểm | Phán xử | Bằng chứng | Sửa thành |
|------|---------|-----------|-----------|
| **DB-09** | ❌ reframe F4 **vẫn sai** | `releaseLinePayments:205` & `releaseLineDiscounts:303` loop tạo N dòng cùng `(source_ref_type, source_ref_id)` cho 1 op void | Bỏ HẲN DB-unique trên ledger; idempotency ở tầng operation (lockForUpdate / idempotency-token) |
| **FIN-07** | ❌ "trùng row" sai | `createOrRefreshInvoiceDiscount:271` dùng `firstOrNew(invoice,type,ref,source)` → idempotent | Giữ: no-cap + `amount` bị ghi đè khi refresh |
| **FIN-12** | ❌ "release không re-apply" sai | `handle()` 1 `DB::transaction` (`:28`); `runForStudents` không nuốt lỗi → throw rollback nguyên tử | Giữ: void không hủy installments + side-effect auto-reallocate |
| **UI-STD-4** | 🔧 quá rộng | Nhiều controller dùng legacy `->with` (EGC store `:121`); `SplitInstallments:186` KHÔNG phải toast.success | Toast đúp chỉ khi endpoint dùng `Inertia::flash` + bridge → audit per-endpoint |
| **UI-SAFE-3** | ❌ "chỉ current page" sai | `EgcChargeGenerationController:79-97` recompute toàn bộ eligible; `students`=override block_count | Bug thật: override page-local, trang ẩn dùng default → preview misleading. 15M đúng |
| **B4 index** | 🔧 thừa | Non-unique `(student_id,semester_id)` trùng UNIQUE cùng cặp | Bỏ index non-unique |
| **UI-STD-2** | 🔧 sai chi tiết | `ExceptionsQueue:156` dùng `route(...)`, không literal | Bỏ "URL literal"; giữ raw fetch + manual CSRF |
| **Cross-ref F2** | ✅ đúng | DB-09/11/17 ghi "F2" nhưng counter-review ở F4 | Sửa → F4 (và DB-09 → F5) |
| **Siết: FIN-04/07 cap** | ✅ | no-cap có ở cả preview/batch | Mở rộng phạm vi |
| **Siết: FIN-20** | ✅ | route wired + UI gọi (`ExceptionsQueue:156`) | "bug runtime thật", không phải giả định |
| **Siết: FIN-16** | ✅ | `callbackMismatchReasons` validate ItemId/StudentId/amount/fee_type | Nêu rõ attacker cần khớp các trường này |

> Bài học #3: nguy hiểm nhất là các finding **"code KHÔNG làm X"** và **prescription** — phải trace
> tới call-chain thật (`applyScholarship`→`firstOrNew`; `handle()` 1 transaction; controller
> recompute) trước khi kết luận. Review mạnh ở *quan sát bất nhất* (giữ vững), yếu ở *suy luận hành
> vi* khi không mở hết call-chain. **Prescription cần độ chặt cao hơn observation.**

### Trạng thái baseline sau 3 vòng
Backbone observation vẫn vững: FIN-01 (đa nguồn balance), FIN-11 (lock), FIN-20/21 (stub, nay xác
nhận live), FIN-26/27 (Due Calendar), UI-STD-6 (`Settlement.vue`), DB-03/04 (unique invoice/webhook
thiếu — INV-6/11 chứng minh). Các prescription DB (DB-09 idempotency, index, CHECK) đã được sửa cho
khả thi & nhất quán mô hình.
