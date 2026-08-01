# Phase 1 — Gold Ledger Foundation

> **Status: DONE (2026-08-01).** Implemented + 12/12 test + code-reviewed. Completion report: [cook-260801-1302-merchandise-store-phase-1](../reports/cook-260801-1302-merchandise-store-phase-1.md). Key deltas from draft: (1) D11 root cause = Telescope migration on default connection, fix is invocation `-e DB_CONNECTION=testing` (no code change, not canvas enum); (2) reclaim + write_off unified into atomic `GoldService::reclaimGold` (single lock, closes a TOCTOU); (3) migration `down()` keeps varchar (reverting to enum would corrupt new values); (4) `adjust_gold_wallet` seeded config-driven (secure-default Super Admin only). Race proven structurally (arch test on lock path), not via second connection.

Móng của mọi phase sau. Hoàn thành + test kỹ trước khi làm tiếp. Phase này đụng vào tiền thật (Gold ledger đang chạy production) → làm cẩn thận, test kỹ, không regress.

## Context (đã verify bằng grep trong red-team)

- `gold_transactions` (migration `2025_10_02_110620`): type/source_type là **DB enum**, thiếu balance before/after, thiếu actor (adjustBalance nhét user id vào `source_id`).
- `app/Services/GoldService.php`: check balance không lock row → 2 request song song cùng qua check → âm ví (bug D6).
- `app/Models/StudentWallet.php:28`: cast `balance => integer` trong khi cột decimal(10,2) (bug D6).
- **Endpoint mint Gold hiện KHÔNG có gate quyền nào** (RT-1): route `POST /wallet/gold/students/{student}/adjust` (`routes/api/admin.php:87`) nằm trong group `['web','auth']`, không có `can:`; `AdjustWalletBalanceRequest::authorize()` return `true` cứng. Bất kỳ user staff nào cũng mint được Gold cho bất kỳ SV nào. Phase 1 phải đóng lỗ này.
- **Negative balance là hành vi CÓ CHỦ Ý đang tồn tại** (RT-2): `EventParticipationOperations::reclaimGoldReward` (`app/Modules/Engagement/Actions/EventParticipationOperations.php:425-438`) cố tình gọi `adjustBalance(student, -amount)` để ghi âm khi thu hồi Gold SV đã tiêu (SV "nợ" Gold). Hệ thống hiện dung nạp Gold debt.

## Quyết định schema (D12 — validate: CẤM nợ Gold)

**`balance` → `unsignedInteger`** (floor DB, cấm âm). **`amount` → `integer` SIGNED** (spend là amount âm). User chốt cấm nợ Gold → không còn xung đột RT-2 vì path reclaim được **redesign để không bao giờ ghi âm** (xem dưới). Unsigned balance giờ an toàn + là lưới chống âm ví ở tầng DB.

### Redesign `reclaimGoldReward` (bắt buộc trước khi ALTER unsigned — RT-2 + D12)

`EventParticipationOperations::reclaimGoldReward` (`:425-438`) hiện gọi `adjustBalance(-amount)` ghi âm khi SV đã tiêu hết. Với unsigned + strict mode → crash (SQLSTATE 22003). Đổi thành:
- Thu hồi `min(balance, amount)` (clamp về 0, không âm).
- Phần thiếu `amount - balance` ghi ledger type mới **`write_off`** (thêm vào allow-list) — audit rõ SV bị mất bao nhiêu Gold không thu hồi được, balance không âm.
- Test: SV balance 10, reclaim 50 → balance = 0, 1 entry spend/adjust -10 + 1 entry write_off ghi nhận 40 thiếu.

### Pre-flight bổ sung (D12): row balance âm hiện có

`SELECT COUNT(*), SUM(balance) FROM student_wallets WHERE balance < 0` — path reclaim cũ có thể đã tạo balance âm. Trước khi ALTER unsigned: clamp các row này về 0 + ghi `write_off` backfill cho phần âm (giữ audit), nếu không ALTER unsigned fail. Ghi lại số row + tổng đã write-off.

## DB changes (RT-14: tách migration per-table, idempotent, có pre-flight)

Tách thành **migration riêng cho từng bảng** để fail giữa chừng có ranh giới rõ (MariaDB DDL không transactional — không rollback nửa chừng). Mỗi ALTER guard idempotent bằng `information_schema` theo pattern `database/migrations/2026_06_14_000003_add_finance_money_sign_checks.php`.

**Pre-flight gate (chạy + ghi lại kết quả TRƯỚC khi ALTER):**
- `SELECT COUNT(*) FROM gold_transactions WHERE amount != FLOOR(amount)` — kỳ vọng có thể > 0 (manual adjustment nhận 2 số thập phân; `events.gold_reward_amount` là decimal — RT-14 bác lý do "toàn .00"). Nếu > 0: chốt rule (làm tròn/ghi log) trước khi convert.
- `SELECT COUNT(*) FROM student_wallets WHERE balance != FLOOR(balance)` — tương tự.
- Ghi lại row count `gold_transactions` + ước lượng thời gian ALTER (bảng index `(student_id, created_at)`, lock window). Nêu maintenance window nếu bảng lớn.

**Migration A — `gold_transactions`:**
- `type` enum → `varchar(32)`; allow-list backend: giữ `earn/spend/adjust` + thêm `reward`, `redemption`, `redemption_refund`, `manual_adjustment`, `write_off` (D12 — phần Gold không thu hồi được). KHÔNG transfer types (ngoài scope D10).
- `source_type` enum → `varchar(32)`; thêm `redemption_order` khi cần.
- `amount` decimal(10,2) → `integer` signed (spend/write_off là amount âm).
- Thêm `balance_before`, `balance_after` `unsignedInteger` nullable (balance không âm — D12; entry lịch sử null, không backfill — xem RT-11 về invariant).
- Thêm `performed_by` FK nullable → `users`.

**Migration B — `student_wallets`:**
- `balance` decimal(10,2) → `unsignedInteger` (D12 — floor DB cấm âm). Chạy pre-flight clamp row âm TRƯỚC (xem trên).

Deploy order: expand → migrate → contract; code phải tolerate cả hai shape trong lúc chuyển. Không DB enum cho cột mới.

## Fix migrate db_test (D11, RT unresolved)

Full migrate fresh `db_test` fail ở `2025_01_26_000000...canvas_integrations`. Migration đó dùng `enum('sync_status', [...])` (`:30`) → **nghi vấn đầu tiên: MariaDB enum/strict-mode**. Đầu phase: `migrate:fresh` trên db_test (KHÔNG đụng `asia` — memory `swinx-env-testing-targets-dev-db`), đọc lỗi thật, xác nhận root cause. **Timebox 2h**; nếu quá → tách thành task riêng, không chặn cả phase (chỉ chặn phần chạy test tích hợp). Test suite phải chạy từ DB trống trước khi viết test mới.

## Đóng lỗ quyền mint Gold (RT-1, chuyển từ Phase 2 về đây)

- Thêm permission `adjust_gold_wallet` (verb_noun, theo convention thực tế `config/permission.php` — RT-1/RT-10) vào group `gold_transactions` trong `config/permission.php`; đăng ký ở seeder (`database/seeders/InitialSetup/RoleAndPermissionSeeder.php` — xác nhận tên file lúc làm) + chạy `SyncPermissions`.
- Gate route `routes/api/admin.php:87` bằng `can:adjust_gold_wallet`; bật `authorize()` thật trong `AdjustWalletBalanceRequest` (dùng `$this->user()->can(...)` scoped campus của student).
- Test route-authorization theo mẫu `tests/Feature/Scholarship/ScholarshipRouteAuthorizationTest.php` (lưu ý note ở test đó: slug typo cũng 403 mọi người vì không có `Gate::before`).

## Code changes

`app/Models/GoldTransaction.php`:
- Constants types mới + allow-list validate ở service/request.
- Fillable/casts cho cột mới; `amount` cast `integer` (bỏ `decimal:2`).
- `getAbsoluteAmountAttribute()` (`:131-134`): bỏ `number_format(...,2)`, trả `int` (RT-11).

`app/Models/StudentWallet.php`:
- Cast `balance => integer` (Laravel int cast OK cho cột unsigned).

`app/Services/GoldService.php`:
- Thêm `lockWalletForUpdate(Student)`: `getOrCreateWallet` rồi re-fetch `where(student_id)->lockForUpdate()->firstOrFail()`; gọi trong `DB::transaction` của mọi method ghi.
- `addGold`/`deductGold`/`adjustBalance`: dùng wallet đã lock; ghi `balance_before`/`balance_after`.
- **`performed_by` là param BẮT BUỘC ở call site đang sửa, không dựa vào default param** (RT-3). "Giữ default param để không vỡ" là sai — default giữ arity nhưng `performed_by` sẽ null cho endpoint staff duy nhất. Cập nhật đủ 7 write-caller đã liệt kê: `StudentWalletController.php:98`; `EventParticipationOperations.php:222,291,434,441,1388`; `GoldService.php:222` (bulkAddGold). `adjustBalance` chuyển actor từ `source_id` sang `performed_by`; thêm migration annotation/backfill cho legacy rows `source_type='manual'` để `source_id` không còn nhập nhằng.
- **`adjustBalance` thêm non-negative guard** (D12 — balance unsigned): nếu amount âm và `abs(amount) > balance` → throw (staff không được đẩy balance âm; unsigned + strict sẽ crash nếu không guard). Path reclaim đã redesign clamp riêng (không dùng đường âm nữa). Guard overspend redemption vẫn ở `deductGold`.
- `getBalance(): int` — bỏ `number_format` (RT-11). `getTransactionStats` `current_balance` dùng int.
- `StudentWalletResource` (`:20`) + `GoldTransactionResource`: bỏ `number_format(...,2)`.
- `GoldTransactionController` filter `in:earn,spend,adjust` (`:30,58`): mở rộng sang allow-list mới, nếu không SV không filter được redemption history (RT-11).
- Method ghi nhận `type` tường minh (redemption/redemption_refund) thay hardcode; chữ ký nhận `int $amount`.

`bulkAddGold` (RT-8): thêm vào caller list. Hiện bọc unbounded loop `addGold` trong 1 `DB::transaction` → với lockForUpdate mỗi vòng, 1 batch 400 SV giữ 400 row lock đến commit → deadlock/lock-timeout với checkout đồng thời. **Chunk thành transaction per-student** (khớp shape `bulkAwardGoldRewards` `EventParticipationOperations:1376-1400`), hoặc document trần batch-size. Notification/side-effect bắn `afterCommit`.

## D8 chưa trọn — nguồn Gold lớn nhất vẫn decimal (RT-11/RT-6)

- `events.gold_reward_amount` vẫn `decimal(10,2)` (`create_events_table:22`); callers cast `(float)` (`EventParticipationOperations:1385` + `:222,291,425,434,441`); model cast `Event.php:41` `decimal:0` đã lệch cột. Thêm vào scope D8: convert cột + cast + Event admin form validate integer, nếu không staff nhập 10.5 → truncate im lặng, ledger lệch event.
- `AdjustWalletBalanceRequest` (`:26` `between:-999999.99,999999.99`, `:63` cast `(float)`): đổi validate `integer` + cast int. Nêu file này theo path (RT-2/RT-3).

## Validation

- Test `GoldServiceTest` (Pest + RefreshDatabase; Student factory cần `intake`, `intake_mode`, `intake_semester_id` + Semester factory — verified session này): add/deduct ghi ledger + balance; deduct quá số dư → throw, state không đổi; chuỗi hỗn hợp balance == sum(ledger).
- **Race test KHÔNG dùng được RefreshDatabase để chứng minh lock** (RT-10): RefreshDatabase bọc mỗi test trong 1 transaction/1 connection → lockForUpdate không contend với ai, test pass y hệt dù có hay không lock. Repo hiện KHÔNG có pattern test concurrency. Chọn một:
  - (a) Test mở PDO connection thứ hai (kiểu `DatabaseTruncation`, không RefreshDatabase), assert lock thứ hai bị block/serialize; HOẶC
  - (b) Bỏ mệnh đề "có test" khỏi acceptance criteria, thay bằng invariant tầng DB + architecture test assert mọi write path đi qua `lockWalletForUpdate`.
  - Không ship criterion "có test" mà test không chứng minh được.
- **Reconciliation invariant (RT-11)**: thêm command/test assert `wallet.balance == SUM(ledger.amount)` per wallet. before/after nullable cho entry cũ → hoặc backfill, hoặc sửa acceptance criteria "mọi thay đổi kèm before/after" cho đúng (chỉ áp dụng entry mới). Không để criterion sai ngày đầu.
- Chạy lại test Engagement (gold) sau khi đổi chữ ký.
- Chạy: recipe docker trong index (sau khi fix D11).

## Risk / rollback

- DDL MariaDB không transactional → tách per-table + idempotent guard (RT-14). down migration đổi ngược (chỉ khi chưa có giá trị mới).
- Đổi chữ ký GoldService: cập nhật đủ 7 caller trong cùng phase, không dựa default param.
