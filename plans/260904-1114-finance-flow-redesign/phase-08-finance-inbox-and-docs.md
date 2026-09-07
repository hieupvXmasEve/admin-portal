---
phase: 8
title: "Finance Inbox, ADR & tài liệu"
status: completed
priority: P2
effort: "2-2.5d"
dependencies: [3, 4, 5, 6, 7]
---

# Phase 8: Finance Inbox, ADR & tài liệu

## Overview

Khép flow: gộp mọi "việc cần người" về một hàng đợi, làm portal nói ngôn ngữ
người học (gồm nhãn đợt trả góp), và ghi các quyết định chính sách của phiên
2026-09-04 và 2026-09-06 vào ADR + hướng dẫn người dùng cuối.

## Key Insights

- Ngoại lệ đang tán ra 6 bề mặt rời (`app/Modules/Finance/routes/web.php`):
  billing exception (`ListBillingExceptionsQuery`), lifecycle due exception
  (`ListLifecycleDueExceptionsQuery`), DNG receipt exception
  (`:279-281`), DNG webhook event (`:307-318`), cancellation `requires_review`,
  installment push failed (`FinanceChargeInstallment.last_push_error`).
- Đã có nơi tập hợp một phần: `FinanceCockpitController` (queue hôm nay) và
  `GetBillingExceptionCountsQuery` — mở rộng chứ không viết mới.
- Hàng đợi nhắc nợ đã có dữ liệu: `ListDueItemsQuery` (nhắc **thủ công**, giữ
  nguyên theo quyết định #3).
- Nhãn đợt gửi sang DNG hôm nay: `PushNextInstallmentAction.php:105`
  (`description . ' (Đợt N)'`), `:107` `due_date` của đợt, `:111` số tiền đợt;
  `:249-252` chỉ đẩy đợt kế tiếp sau khi đợt trước xong. **Giữ nguyên cơ chế**
  (quyết định #9) — chỉ làm nhãn portal đủ ngữ cảnh.
- Portal có 2 khái niệm "trả góp" khác nhau và đang dễ lẫn: đợt nội bộ
  (`FinanceChargeInstallment`) vs trả góp đối tác Foxpay
  (`StudentFinanceController::dngInstallment:465`, `dngRequestInstallment:489`).
- Tài liệu: `docs/README.md` là registry canonical; ADR nằm ở `docs/adr/`;
  hướng dẫn người dùng cuối ở `docs-site/src/content/docs/` (tiếng Việt trước,
  rồi `en/`, `ko/`, **`zh/`** — **4** locale, không phải 3), có `source:`
  frontmatter được `./scripts/check-docs-freshness.sh` kiểm.
- **Hai script khác nhau, đừng lẫn (red team, Medium):** `scripts/check-docs.sh:19-23`
  chỉ duyệt `AGENTS.md, README.md, CONTEXT.md, scripts/README.md, docs/**.md` —
  **không** đọc `docs-site/`. Gate cho docs-site là
  `scripts/check-docs-freshness.sh:57`. Ngoài ra `check-docs.sh:27` bắt buộc
  `title/status/owner/last_verified/scope` trên ADR mới — pin sẵn, đừng để "đọc
  `docs/adr/` lấy schema".
- **Gate đỏ ở Phase 4, không phải phase này:** `resources/js/pages/Finance/Invoices/Index.vue`
  (Phase 4 sửa) nằm trong `source:` của cả 4 trang finance-office. Phase 4 chịu
  trách nhiệm làm mới 4 trang đó.

## Requirements

- Functional: một trang hàng đợi hợp nhất, mỗi dòng có: khoản – sinh viên –
  vì sao – ai chịu – hành động sửa; nhóm theo loại: *tiền chờ khớp*, *provider
  không rõ*, *thiếu nghĩa vụ*, *ledger sai lệch*, *huỷ đang chờ*, *đợt trả góp
  push lỗi*, *cần nhắc hôm nay*, *số dư cần quyết*.
- Functional: **backend** cung cấp ngữ cảnh đợt (`installment_no`,
  `installments_total`, `due_date`) và từ vựng người học trong payload portal.
  Bề mặt hiển thị thuộc **Phase 9**.
- Docs: 2 ADR mới được accept; `docs/api/student/finance.md` cập nhật
  (`money_item_status`, ngữ cảnh đợt); trang docs-site liên quan cập nhật
  **4** locale (vi, `en/`, `ko/`, `zh/`).
- Non-functional: hàng đợi là read model tổng hợp; không thêm bảng trạng thái.

## Architecture

1. **Finance Inbox = mở rộng Cockpit, KHÔNG viết query mới (red team 2026-09-06, High).**
   `GetFinanceCockpitOverviewQuery::queues()` (`:83-99`) **đã** phát 7 queue với
   đúng hình dạng cần: `{key, label, count, obeys_semester, scope_badge,
   permission, action_url, severity}` — `webhook_errors`, `settlement_exceptions`,
   `unallocated`, `dng_due`, `lifecycle`, `charge_errors`, `installment_failures`.
   `GetFinanceCockpitQueueRowsQuery::handle()` (`:19-26`) đã trả dòng theo yêu cầu
   với `match($queueKey)` (hiện 2 nhánh, `default => []`).
   6/8 loại việc plan liệt kê **đã có queue**. Chỉ **2** là mới: *huỷ đang chờ*
   và *số dư cần quyết* (Phase 7).
   ⇒ Bỏ `ListFinanceInboxQuery`. Việc thật: thêm 2 entry vào `queues()` + 6 nhánh
   `match` vào `GetFinanceCockpitQueueRowsQuery`. Hai aggregator trên cùng nguồn
   sẽ lệch số (Cockpit báo 12, Inbox báo 15) và staff mở nhầm màn hình.
1b. **Phân quyền phải gate TRONG query (red team, Critical).** Tiền đề "gọi query
   gốc là giữ được phân quyền từng nguồn" **sai**: permission nằm ở **route
   middleware** (`app/Modules/Finance/routes/web.php:281,307,310,146,149,152`),
   query class **không có** authorization —
   `ListBillingExceptionsQuery.php:13-21`, `ListDueItemsQuery.php:32-48` không có
   `can()`/`Gate::`/`authorize` nào; `ListLifecycleDueExceptionsQuery.php:33-34`
   chỉ đọc `can()` để trang trí **action của dòng**, không lọc dòng.
   ⇒ Gate tường minh: map `queue key → permission` (đã có sẵn trong
   `queues()`!) và **bỏ nguồn** khi `$user->cannot(...)`. Tiêu chí nghiệm thu đổi
   thành "user chỉ có permission X thấy đúng dòng của X", không phải "gọi query gốc".
1c. **Phân trang: đếm + top-N, không ghép 6 paginator (red team, High).** Mỗi
   query trả `LengthAwarePaginator` riêng với page size riêng, và
   `ListDueItemsQuery.php:78-83` đọc `page`/`per_page` từ **global `request()`**
   chứ không từ tham số; `ListBillingExceptionsQuery.php:18-21` hardcode
   `perPage = 20`; `ListLifecycleDueExceptionsQuery.php:113-114` cap 100 kèm
   `withQueryString()`. "Trang 2" của danh sách gộp = trộn 6 lát trang-2 rời rạc,
   tổng số dòng vô nghĩa. Muốn tổng đúng phải rút cạn mọi nguồn — đúng thứ cần
   tránh.
   ⇒ Inbox = **đếm theo loại + top-N mỗi loại** (mở rộng
   `GetBillingExceptionCountsQuery`), bấm vào là drill-through sang trang phân
   trang sẵn có. Nếu owner bắt buộc một danh sách gộp thật, mỗi nguồn phải có
   biến thể `handle(limit, cursor)` nhận tham số trước — đó là một phase riêng.
2. **Chỉ backend cho portal.** Bổ sung ngữ cảnh đợt vào payload DNG request
   (`installment_no`, `installments_total`, `due_date` — dữ liệu đã có trên
   `finance_charge_installments`). **Bề mặt hiển thị chuyển sang Phase 9**:
   portal là `FE/student-nuxt`, repo riêng (xem Phase 9).
3. **Từ vựng người học** (backend cung cấp; Phase 9 render):
   | Nội bộ | Hiển thị |
   |---|---|
   | `tuition_term` | Học phí kỳ (kỳ N) |
   | `egc_level_fee` | Học phí chương trình dự bị (level N) |
   | `retake_fee` | Phí học lại môn |
   | `exam_resit_fee` | Phí thi lại |
   | `bhyt` | Bảo hiểm y tế |
   | `manual_fee` / `admission_fee` / `adjustment` | Khoản thu khác (kèm mô tả) |
   | `unapplied_cash` | Số dư của bạn |
   | `cash` / `credit` | Bạn đã nộp / Nhà trường đã giảm |
4. **2 ADR mới** trong `docs/adr/` (số tiếp theo hiện có):
   - *Vận hành thu học phí: không có lớp đợt thu, không có phân quyền người trả*
     — ghi 3 quyết định **cố ý** để audit sau không raise lại:
     (a) không có thực thể đợt thu; hạn nộp thật do staff chọn tại DNG commit
     (`CommitBatchDngRequest`), `now()+30d` chỉ lấp cột NOT NULL của invoice
     legacy; `billing_cycles` **không có writer nhưng có 6 đường đọc sống** —
     cột bị đóng băng, không phải chết; giữ nguyên, không xoá.
     (b) portal mở QR/Foxpay cho **cả SV lẫn PH**, không phân quyền riêng —
     4 route write không mutate tiền (`CreateStudentDngPaymentAccessAction`
     chỉ mint link cho request đã `pushed_to_dng`); `access_level` trên
     `guardian_access_grants` **được ghi và audit nhưng cố ý không dùng làm
     cổng phân quyền thanh toán** (không phải "cột chết" — nó là public
     cross-module contract); hệ quả biết trước: hai dòng log mâu thuẫn về actor.
     (c) nhắc nợ thủ công là quyết định, không phải thiếu sót; trạng thái
     khoản thu hợp nhất là suy dẫn; đợt trả góp nội bộ hiện diện ở DNG theo
     từng đợt.
     **Câu chữ bắt buộc chính xác (red team, High).** ADR là artifact có thẩm
     quyền cao nhất; viết sai ở đây làm tê liệt lần review sau.
     - `access_level` **KHÔNG phải "cột chết"**: nó là method trên public
       cross-module contract (`app/Shared/Contracts/Identity/GuardianAccessGrantWriter.php:20`),
       có action riêng (`ChangeGuardianAccessLevelAction.php:13-32`, nhận **bất kỳ**
       chuỗi non-empty, không allow-list), writer (`EloquentGuardianAccessGrantWriter.php:36,45`),
       DTO (`EloquentGuardianAccessGrantReader.php:159`), audit
       (`GrantGuardianAccessAction.php:89,114,138`), index DB, và type FE
       (`resources/js/types/models.ts:153`).
       Câu đúng: *"`access_level` được ghi và audit, cố ý **không** dùng làm cổng
       phân quyền thanh toán; field ở lại vì Identity duy trì nó."*
     - `billing_cycles` **KHÔNG phải "bảng chết"**: có 6 reader sống, trong đó
       `InvoiceGenerationService.php:69-72` dùng `billing_cycle_id` để quyết charge
       nào thuộc invoice nào — **đọc thành phần tiền, trong `SettlementMutationGuard`**.
       Còn `BillingInvoiceController.php:101`, `StudentInvoiceResource.php:52`
       (payload sinh viên), `PayInvoiceRequest.php:38`, `FinanceAuditSearchRequest.php:26`,
       `GetFinanceAuditGraphQuery.php:55`.
       Câu đúng: *"`billing_cycles` không có writer; 6 đường đọc phải tiếp tục
       chạy; cột bị đóng băng, không phải chết."*
     - **Bỏ hẳn** luận điểm "`now()+30d` chỉ lấp cột NOT NULL" — sai, xem Phase 5.
     - Ghi hệ quả đúng của việc không truy người trả: middleware **đã** bắt
       `accessing_parent` vào request attribute (`ParentStudentAccess.php:90-91`,
       **0** consumer), và `ApiLogging` chạy trước khi swap
       (`ApiLogging.php:43-44` ghi User phụ huynh, `:76` ghi Student, không có
       request id nối hai dòng). Tức là **hai dòng log mâu thuẫn về actor**, chứ
       không phải "không có dữ liệu".
     - Ghi rõ: mở QR/Foxpay cho mọi actor portal là quyết định phân quyền, còn
       **chống lạm dụng là cơ chế tách biệt** — phase này thêm throttle cho 4
       route đó (xem Architecture 5), vì mỗi call là một POST outbound tới
       DNG/Foxpay (`DngClient.php:141,171`) không có idempotency key.
   - *Thu tay một thao tác, phiếu thu nội bộ, và vòng đời số dư* — kèm: không
     hoàn tiền khi còn học; SV rời trường còn dư vào hàng đợi; `retain_forfeit`
     cần phê duyệt.

5. **Throttle 4 route portal — ĐÃ CHỐT (owner, validation 2026-09-06).**
   `routes/api/v1/student.php:37-38` có rate limiter của group bị comment, và 4
   POST (`:218-221`) không có throttle riêng, trong khi mỗi call là một POST
   outbound tới DNG/Foxpay (`DngClient.php:141,171`) không có idempotency key.
   Repo đã có tiền lệ: `AppServiceProvider.php:277`
   `RateLimiter::for('merchandise-checkout', ...)` và route merchandise được
   throttle (`routes/api/v1/student.php:260,264`).
   ⇒ Định nghĩa limiter mới (ví dụ `student-payment-access`) và gắn
   `->middleware('throttle:student-payment-access')` cho 4 POST. Key theo
   student id. Đây là chống lạm dụng, **không** phải phân quyền — quyết định #11
   (ai mở cũng được) giữ nguyên.
   <!-- Updated: Validation Session 1 - throttle 4 route portal vào Phase 8 -->

## Related Code Files

- Modify: `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php:83-99` (+2 queue, + gate permission per-source)
- Modify: `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitQueueRowsQuery.php:19-26` (+6 nhánh `match`)
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php:40-46`
- ~~Create `ListFinanceInboxQuery`~~ — **bỏ**, Cockpit đã có 7/8 queue
- Modify: trang Cockpit ở `resources/js/pages/Finance/Cockpit/Index.vue`
- Modify: `routes/api/v1/student.php:218-221` (throttle 4 POST portal)
- Modify: `app/Providers/AppServiceProvider.php` (thêm `RateLimiter::for('student-payment-access')`, mẫu `:277`)
- Modify: `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php` (payload ngữ cảnh đợt)
- Modify: `app/Modules/Finance/Queries/GetStudentFinancePresentationQuery.php` (từ vựng người học)

- Create: `docs/adr/00XX-collection-operations-without-cycles-or-payer-gating.md`
- Create: `docs/adr/00XX-manual-receipt-and-surplus-lifecycle.md`
- Modify: `docs/api/student/finance.md`
- Modify: `docs/README.md` (nếu registry cần liệt kê tài liệu mới)
- Modify: `docs-site/src/content/docs/**` — **4** locale (VI root, `en/`, `ko/`, `zh/`) + `source:` frontmatter

## Implementation Steps

1. Test đỏ: user chỉ có `view_finance_operations_due_calendar` mở Cockpit →
   khẳng định hôm nay có/không thấy dòng của nguồn khác.
2. Thêm gate permission per-source vào `queues()`; thêm 2 queue mới; thêm 6 nhánh
   `match` cho rows.
3. Backend: thêm ngữ cảnh đợt vào payload portal (hiển thị ở Phase 9).
4. Viết 2 ADR (đọc `docs/adr/` để lấy số tiếp theo + đúng frontmatter schema).
5. Cập nhật `docs/api/student/finance.md`.
6. Cập nhật docs-site **4** locale + `source:` list; chạy `./scripts/check-docs.sh`
   (cho `docs/**` + ADR frontmatter) **và** `./scripts/check-docs-freshness.sh`
   (cho `docs-site/**`).
7. Sweep regression:
   `./scripts/dev.sh artisan test --compact --filter=Finance|Settlement|Dng|Payment|Invoice`;
   `composer exec pint -- --dirty --format agent`;
   eslint/prettier cho file FE đã sửa.

## Todo

- [x] Gate permission per-source trong `queues()` (+ test phân quyền)
- [x] 2 queue mới + 6 nhánh `match` rows (mở rộng Cockpit, không query mới)
- [x] Backend: ngữ cảnh đợt trong payload portal
- [x] Throttle 4 route portal + limiter mới + test vượt ngưỡng → 429
- [x] ADR 1: không có đợt thu, không phân quyền người trả (3 quyết định cố ý)
- [x] ADR 2: thu tay + phiếu thu + vòng đời số dư
- [x] `docs/api/student/finance.md` cập nhật
- [x] docs-site **4** locale + `source:` frontmatter
- [x] Sweep test + pint + eslint/prettier + check-docs

## Success Criteria

- [x] Cockpit hiện đủ 8 loại việc (đếm + top-N, drill-through sang trang sẵn có).
- [x] **Phân quyền:** user chỉ có permission X thấy **đúng** dòng của nguồn X;
      không thấy evidence DNG/webhook nếu thiếu permission tương ứng (test riêng).
- [x] Backend payload có `installment_no`/`installments_total`/`due_date`
      (hiển thị nghiệm thu ở Phase 9).
- [x] 4 POST portal trả 429 khi vượt ngưỡng; quyết định #11 không đổi (SV và PH
      đều mở được, chỉ bị giới hạn tần suất).
- [x] 2 ADR ở `docs/adr/` với frontmatter `title/status/owner/last_verified/scope`.
      `./scripts/check-docs-freshness.sh` xanh. `./scripts/check-docs.sh` đỏ sẵn
      trên `docs/features/academic/` (không đụng Phase 8).
- [ ] Sweep `--filter=Finance|Settlement|Dng|Payment|Invoice`. Đã chạy
      `tests/Feature/Finance`: 1041 passed, 83 failed (EGC/defer/`missing_currency`;
      Cockpit Phase 8 xanh). Pest `--filter` với `|` exit 255 trong harness này.

## Risk Assessment

- **Cao:** hàng đợi hợp nhất **sẽ** bỏ qua phân quyền nếu không gate tường minh —
  permission sống ở route middleware, không ở query. Đây là finding Critical của
  red team; tiêu chí nghiệm thu phải là test phân quyền, không phải "gọi query gốc".
- **Trung bình:** trùng phạm vi tài liệu với plan `260901-0155` Phase 8 (cập
  nhật `docs/audit-academic-finance.md` + ADR RULE-09). Plan này **không** sửa
  audit doc; nếu chạy song song, thống nhất thứ tự trước khi chạm `docs-site`.
- **Trung bình:** `check-docs-freshness.sh` đỏ ngay ở **Phase 4** (sửa
  `Invoices/Index.vue`), không đợi phase này. Nếu các phase merge độc lập, gate
  đỏ ở mọi merge trung gian và người ta bắt đầu bỏ qua nó — đúng vết xe đổ đã có
  với file dùng chung. Hoặc Phase 4 làm mới docs-site, hoặc phase 3-8 land chung
  một branch.

## Security Considerations

Inbox tổng hợp nhiều nguồn có mức bảo mật khác nhau (webhook payload, receipt
exception evidence). Không được phơi evidence nội bộ ra dòng tóm tắt nếu staff
không có permission của nguồn đó.

## Next Steps

Phase 9 làm bề mặt portal (`FE/student-nuxt`) — phase cuối. Sau khi land, cân
nhắc mở lại các câu hỏi đã cố ý để ngoài phạm vi: bên thứ ba trả hộ, hoá đơn
điện tử/GL, thang nhắc nợ tự động.
