# Plan: Merchandise Store (Student Portal)

Status: DRAFT — chờ user chốt. Chưa implement.
Nguồn: FR Notion + review [review-260801-0147-merchandise-store-fr.md](../reports/review-260801-0147-merchandise-store-fr.md) + quyết định user 2026-08-01.

## Quyết định đã chốt (2026-08-01)

| # | Quyết định |
|---|---|
| D1 | Nâng cấp `gold_transactions` hiện có, không tạo bảng ledger mới |
| D2 | Product status chỉ giữ trạng thái quản trị (`active/coming_soon/hidden/archived`); `available/out_of_stock` derived per campus từ tồn variant |
| ~~D3~~ | ~~Bỏ transfer_hold~~ — **superseded bởi D10** (transfer ngoài scope hoàn toàn) |
| ~~D4~~ | ~~Gold transfer = tặng ngay~~ — **superseded bởi D10**. Ledger KHÔNG có transfer types (xem Phase 1) |
| D5 | Shipping = sinh viên **nhập địa chỉ trên đơn**; staff tự gửi qua nền tảng ngoài rồi mark `shipped`. Không build hệ shipping riêng |
| D6 | Fix triệt để bug GoldService (race không lock row + cast integer/decimal) — nằm trong Phase 1 |
| D7 | Snapshot order line gồm cả tên sản phẩm + thuộc tính variant + giá Gold; đổi tên stock movement `redemption_reserved` → `redemption_out`; `pickup_overdue` do **staff đánh tay**, chưa cần cron |
| D8 | Gold là **số nguyên** — chuyển decimal(10,2) → integer. `balance` unsigned, `amount` signed (xem D12). Validate integer toàn bộ gồm `events.gold_reward_amount` + read-side (getBalance→int) |
| D12 | **CẤM nợ Gold** (validate 2026-08-01, đảo hướng RT-2). Balance = `unsignedInteger` (floor DB). Path `reclaimGoldReward` phải **redesign**: clamp về 0 + ghi ledger `write_off` cho phần thiếu, KHÔNG ghi âm nữa. Pre-flight Phase 1 phải xử lý row `balance < 0` hiện có (clamp + write_off backfill) trước khi ALTER unsigned |
| D13 | **Phụ huynh KHÔNG truy cập Store/Gold của con** (validate 2026-08-01, chặt hơn read-only). Mọi endpoint merchandise (read + write) chỉ `student.api.auth`, KHÔNG dùng group `either:parent`. Menu Store không hiển thị cho parent |
| D14 | **Student portal frontend = repo Nuxt riêng `FE/student-nuxt`** (RT-6), theo `docs/portal-repos.md`. Phase 3 tách 3a (backend Swinx + contract `docs/api/student/merchandise.md`) và 3b (portal). `Portal impact: student`. Admin UI vẫn trong repo Swinx (Inertia/Vue) |
| D9 | Ảnh sản phẩm upload vào **folder riêng trong storage của repo** (public disk), không thêm media library |
| D10 | **BỎ toàn bộ Gold Transfer khỏi scope** — không build phase này, để phase cuối cùng hỏi lại user rồi mới cân nhắc |
| D11 | Migrate fresh `db_test` fail ở `canvas_integrations` → điều tra nguyên nhân + fix trong Phase 1 |

## Phases

| Phase | Nội dung | Phụ thuộc |
|---|---|---|
| ✅ [1 — Gold ledger foundation](phase-1-gold-ledger-foundation.md) | **DONE 2026-08-01** — integer ledger + row-lock + audit + reclaim redesign + mint gate. 12/12 test. Report: [phase-1](../reports/cook-260801-1302-merchandise-store-phase-1.md). D11 = invocation fix (`-e DB_CONNECTION=testing`), không đổi code | — |
| [2 — Catalog + Inventory](phase-2-catalog-inventory.md) | merchandise / images / variants / stock_movements, admin CRUD, permissions, audit | 1 |
| [3 — Redemption](phase-3-redemption.md) | 3a: backend orders + state machine + concurrency + staff API + contract; 3b: portal Nuxt store UI (repo FE/student-nuxt) | 1, 2 |
| [4 — Reports](phase-4-reports.md) | Báo cáo §19 + export | 2, 3 + stakeholder chốt |

Gold Transfer: **ngoài scope** (D10) — sau khi xong Phase 4 hỏi lại user trước khi cân nhắc.

## Acceptance criteria tổng

- Mọi thay đổi balance/tồn kho (entry MỚI) có ledger entry kèm before/after + `performed_by`; entry lịch sử cũ để null (RT-11 — không claim "mọi thay đổi" gồm cả cũ).
- Tồn kho VÀ ví không âm (unsigned column + conditional update + guard app). Ví không overspend redemption (lock ví + check dưới lock); path reclaim clamp về 0 + `write_off` (D12), không ghi âm. Race đảm bảo bằng lock + conditional update; **verify bằng test second-connection HOẶC invariant DB + arch test, KHÔNG bằng RefreshDatabase** (RT-10).
- Reconciliation: `wallet.balance == SUM(ledger.amount)` per wallet (command/test — RT-11).
- Tạo đơn: order + trừ Gold + trừ tồn trong 1 DB transaction, rollback toàn bộ khi lỗi; idempotency_key chống double-submit (RT-8); mọi transition lock order row + conditional status guard chống double-refund (RT-7); lock order thống nhất ví→variant(id ASC) + retry deadlock (RT-15).
- Endpoint mint Gold (`adjust`) có gate quyền + authorize() thật (RT-1). Mọi endpoint merchandise chỉ SV, parent 403 hoàn toàn (D13).
- Đơn cũ giữ nguyên hiển thị khi merchandise/variant đổi tên/giá/archive (snapshot tên+variant+giá).
- Sinh viên chỉ thấy/đổi merchandise campus mình; staff chỉ thao tác campus được phân quyền qua `CampusPermissionReader` + Policy resolve campus từ record, KHÔNG session (RT-13).
- Notification qua Shared Contract/domain-event (không import chéo module — RT-4), bắn afterCommit, registry đăng ký đủ per-type, deep-link đúng.
- Permission dạng `verb_noun`, đăng ký `config/permission.php` + seeder + sync, KHÔNG dot-namespace (RT-1/RT-10).
- Upload ảnh qua module Upload có sẵn + disk env-switchable, KHÔNG hand-roll/hardcode public disk (RT-12).
- Mọi bảng mới dùng varchar + backend allow-list, không DB enum.

## Cách chạy test trong worktree (đã verify)

Container dev mount repo chính, vendor là named volume → chạy test worktree bằng container riêng:

```bash
docker run --rm -v <worktree>:/app -v swinx-dev_composer_vendor:/app/vendor --network swinx-dev_default -w /app swinx-dev-app:latest php artisan test <path>
```

Cần `.env` trong worktree (cp từ `.env.example` + `php artisan key:generate`) — đã tạo sẵn. DB test = `db_test` trên `swinx-db-dev` (an toàn, không đụng `asia`). Full migrate từng fail ở `2025_01_26_100000_create_canvas_integrations_table.php` — điều tra + fix trong Phase 1 (D11).

## Mặc định cho câu hỏi §20 chưa có trả lời (làm theo mặc định, đổi được sau)

- Duyệt đơn không cần lý do; chỉ reject bắt buộc lý do.
- Mọi merchandise đều cho cả pickup lẫn shipping (chưa config per sản phẩm).
- Địa chỉ ship nhập tại mỗi đơn (theo D5), không xác nhận lại, không phí/Gold bổ sung.
- Không đổi variant sau khi đặt — hủy rồi đặt lại.
- Total Redeemed = tổng Gold các đơn chưa bị hoàn (mọi trạng thái trừ rejected/cancelled).
- Phase đầu: notification portal, chưa gửi email song song; chưa export Excel.

## Red Team Review

### Session — 2026-08-01
3 reviewer hostile (Security Adversary, Failure Mode Analyst, Assumption Destroyer), Standard verification tier (Fact Checker + Contract Verifier). 30 finding thô → dedupe/cap 15. **User ACCEPT cả 15.** Nhiều finding được 2-3 reviewer độc lập grep-verify trùng nhau (tín hiệu mạnh). Root cause chung: bản draft trước tự khẳng định "pattern hiện có" / "đã tồn tại" mà chưa grep — nhiều claim sai sự thật codebase.

**Breakdown:** 7 Critical, 8 High.

| # | Finding | Sev | Applied |
|---|---------|-----|---------|
| RT-1 | `gold.adjust`/`gold.view_transactions` không tồn tại; endpoint mint Gold không gate quyền | Critical | P1 (đóng lỗ + gate), P2 (permission thật) |
| RT-2 | `unsignedInteger` balance đụng path reclaim ghi âm có chủ ý → crash | Critical | P1 (redesign reclaim clamp+write_off, D12 validate) |
| RT-3 | `adjustBalance` "giữ default param" không bảo toàn ngữ nghĩa; actor null; caller list thiếu | Critical | P1 (performed_by bắt buộc, 7 caller) |
| RT-4 | `Merchandise` import `Notification` fail arch test; registry đóng; provider chưa đăng ký | Critical | P2 (provider), P3 (Shared Contract seam) |
| RT-5 | Route student cho parent act-as → phụ huynh tiêu Gold con | Critical | P3 (write chỉ SV, D13) |
| RT-6 | Phase 3 frontend sai nền tảng — portal là repo Nuxt riêng | Critical | P3 (tách 3a/3b, D14) |
| RT-7 | Refund/cancel không lock order + không idempotent transition → hoàn Gold 2 lần | Critical | P3 (lock + conditional guard + partial unique) |
| RT-8 | Không idempotency key tạo đơn → double-spend | High | P3 (idempotency_key) |
| RT-9 | Mã đơn sequential dễ đụng độ; repo có pattern FIN-13 random+retry | High | P3 (FIN-13 pattern) |
| RT-10 | Test race dùng RefreshDatabase không chứng minh được lock | High | P1 (second-connection/invariant), acceptance criteria sửa |
| RT-11 | D8 chưa trọn: events.gold_reward_amount decimal, getBalance() string; thiếu reconciliation invariant | High | P1 (đủ read-side + invariant) |
| RT-12 | D9 hand-roll upload thay module Upload có sẵn; hardcode public disk | High | P2 (module Upload + named disk) |
| RT-13 | "Campus scoping access grants" lẫn cơ chế; check dựa session record-blind | High | P2/P3/P4 (CampusPermissionReader + Policy từ record) |
| RT-14 | Migration gộp 6 ALTER 1 file, DDL không transactional, lý do ".00" sai | High | P1 (tách per-table + idempotent + pre-flight) |
| RT-15 | Deadlock: lock order chưa xác định qua nhiều path | High | P2/P3 (ví→variant id ASC + retry) |

### Whole-Plan Consistency Sweep
- Files reread: index.md, phase-1..4.
- Decision deltas: D8 unsigned→signed (D12 mới); permission dot→verb_noun; frontend→repo Nuxt riêng (D14); parent→read-only (D13); upload→module có sẵn; migration→tách per-table.
- Reconciled: bỏ mọi "unsigned = lưới chống âm ví", "gold.adjust ĐÃ tồn tại", "pattern Finance/Events dưới Admin/", "hold" (transfer đã ngoài scope D10). Acceptance criteria + Phases table cập nhật khớp.
- Unresolved contradictions: 0.

## Validation Log

### Session — 2026-08-01 (validate, 4 câu)
Red Team Review đã có + verification evidence → skip verification pass (Step 2.5 guard), interview thẳng các quyết định sản phẩm chỉ user quyết được.

| Câu | Quyết định | Đổi plan? |
|---|---|---|
| Gold debt | **Cấm nợ Gold** → D12 đảo: balance unsigned + redesign reclaim clamp-to-0 + ledger `write_off` + pre-flight clamp row âm | CÓ — Phase 1 |
| Parent access | **Không truy cập gì** (chặt hơn read-only) → D13: mọi endpoint merchandise chỉ SV | CÓ — Phase 3 |
| Total Redeemed | Mọi đơn chưa hoàn (giữ mặc định) | Không |
| Shipping address | SV nhập tay mỗi đơn (giữ D5) | Không |

### Whole-Plan Consistency Sweep (validate)
- Files reread: index.md, phase-1..4.
- Deltas: signed→unsigned balance + reclaim redesign + `write_off` type (D12); parent read-only→no-access (D13).
- Reconciled: D12/D13 index, Phase 1 (schema/reclaim/pre-flight/adjustBalance guard/allow-list `write_off`), Phase 3 (actor section), acceptance criteria (2 dòng). Bỏ mọi "signed/cho phép âm/reclaim ghi âm", "parent read-only".
- Unresolved contradictions: 0.

## Unresolved questions

Không còn câu hỏi CHẶN. Còn lại là dữ liệu vận hành + scope tương lai:

1. **`SELECT COUNT WHERE amount != FLOOR`** + **`WHERE balance < 0`** trên production ra bao nhiêu, xử lý cụ thể (làm tròn/write_off) — pre-flight Phase 1.
2. **Row count `gold_transactions` + maintenance window** cho ALTER — chốt trước deploy Phase 1.
3. **D11 migrate fail** timebox 2h (nghi enum/strict-mode `canvas_integrations`); quá thì tách task riêng.
4. **Cart** client-side only, reconcile stale price/stock lúc submit — xác nhận UX Phase 3b.
5. Các mặc định §20 còn lại chờ stakeholder (approve có cần lý do, config ship per sản phẩm...) — không chặn.
6. Gold Transfer ngoài scope (D10) — hỏi lại sau Phase 4.
