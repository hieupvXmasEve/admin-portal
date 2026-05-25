# Design — S-001

## Domain Model

### New entity: `FinanceChargeInstallment`

Slice của 1 `FinanceCharge`. Charge giữ `amount` tổng; installment là phân kỳ thu.

Invariants:
- **Split target = NET amount = `charge.amount - charge.discount_amount`** (gross minus credits đã apply vào invoice lines). Nhất quán với `DngPaymentService` đang push `$charges->sum('balance')`. Ví dụ: HP 20M, scholarship -5M → split target 15M; chia 2 đợt = 7.5M/đợt.
- `SUM(installment.amount WHERE finance_charge_id = X) == net_split_target` (decimal exact, không lệch xu).
- Credit charges (amount ≤ 0) **không có installment** (skip trong backfill, không expose UI split).
- Charges có `discount_amount >= amount` (fully credited) **không có installment** (net target ≤ 0).
- `installment_no` liên tục từ 1, không trùng (DB unique `(finance_charge_id, installment_no)`).
- Trong 1 charge, tại 1 thời điểm chỉ 1 installment ở status `awaiting_payment` (đảm bảo qua flow, không cần unique constraint vì DNG invariant đã đảm bảo).
- Phase 1: chỉ split khi `charge.installments.none(status=paid)`.

### Status machine cho `FinanceChargeInstallment`

```
pending → awaiting_payment → paid
       └→ cancelled (khi charge void hoặc plan bị rebuild)
```

`pending` còn lại nếu retry push 3 lần fail (kèm `last_push_error`).

## Application Flow

### Commands (Actions)

| Action | Trigger | Output |
|---|---|---|
| `SplitChargeIntoInstallmentsAction` | Admin gọi qua web UI | Replace installments của 1 charge với N rows mới |
| `PushNextInstallmentAction` | Webhook reconcile (sau commit) + manual retry button | Push 1 installment kế tiếp sang DNG |

### Jobs

| Job | Trigger | Retry |
|---|---|---|
| `PushNextInstallmentJob` | Dispatch post-commit từ `ReconcileDngPaymentsJob` | 3 attempts, exponential 1m → 5m → 15m |

### Events

| Event | Fired when | Listener |
|---|---|---|
| `InstallmentPushed` | Push DNG thành công | Outbox emit → email SV "Đợt N sẵn sàng" |
| `InstallmentPushFailed` | Sau attempt thứ 3 | Outbox emit → noti admin |
| `ChargeFullySettled` | `PushNextInstallmentAction` không còn pending | Invoice gen receipt cuối |

## Interface Contract

### Web routes (Admin, Inertia)

| Method | URI | Action | Authz |
|---|---|---|---|
| POST | `/admin/finance/charges/{charge}/installments/split` | `SplitChargeIntoInstallmentsAction` | `FinanceChargePolicy@splitInstallment` (perm `finance.charge.split-installment`) |
| POST | `/admin/finance/charges/{charge}/installments/{installment}/retry-push` | `PushNextInstallmentAction` (manual) | same |

Request DTO (`SplitChargeIntoInstallmentsRequest`):
```php
'installments' => 'required|array|min:1',
'installments.*.installment_no' => 'required|integer|min:1',
'installments.*.amount' => 'required|numeric|gt:0',
'installments.*.due_date' => 'required|date',
```

Response: `ApiResponse::success($installmentsCollection)`.

### Errors

| HTTP | Code | When |
|---|---|---|
| 422 | `installment_sum_mismatch` | `sum(amount) != net_split_target` (= `charge.amount - charge.discount_amount`) |
| 422 | `charge_is_credit` | `charge.amount <= 0` (credit không split được) |
| 422 | `charge_fully_credited` | `net_split_target <= 0` (đã giảm hết bằng credits) |
| 422 | `installment_no_not_sequential` | `installment_no` không liên tục từ 1 |
| 422 | `due_date_not_ascending` | due_date không tăng dần theo installment_no |
| 409 | `charge_has_paid_installment` | Phase 1 lock: đã có đợt `paid` |
| 403 | (default) | Policy fail |

## Data Model

### Migration: `2026_05_25_create_finance_charge_installments_table.php`

```php
Schema::create('finance_charge_installments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('finance_charge_id')->constrained('finance_charges')->cascadeOnDelete();
    $t->unsignedSmallInteger('installment_no');
    $t->decimal('amount', 15, 2);
    $t->date('due_date');
    $t->string('status', 20)->default('pending');
    $t->foreignId('dng_payment_request_id')->nullable()->constrained('dng_payment_requests')->nullOnDelete();
    $t->timestamp('paid_at')->nullable();
    $t->unsignedSmallInteger('push_attempt_count')->default(0);
    $t->text('last_push_error')->nullable();
    $t->timestamp('last_push_attempted_at')->nullable();
    $t->timestamps();

    $t->unique(['finance_charge_id', 'installment_no']);
    $t->index(['status', 'due_date']);
});
```

### Backfill migration: `2026_05_25_backfill_finance_charge_installments.php`

Mỗi `FinanceCharge` ACTIVE chưa có installment → insert 1 row:
- `installment_no = 1`
- `amount = charge.amount`
- `due_date = charge.due_date ?? today + 30d`
- `status` map từ trạng thái thu hiện tại (xem spec §5)
- `dng_payment_request_id` = DNG hiện tại nếu có

Rollback guard: nếu `finance_charge_installments` đã có row → fail migration với message "Run pre-rollback cleanup".

### Indexes / Retention

- Không cần partition (volume thấp, mỗi charge ≤ vài chục installments là max thực tế).
- `last_push_error` text — không lock log retention; dọn riêng qua `last_push_attempted_at < now() - 90d` nếu cần.

## UI / Platform Impact

### Admin (Vue + Inertia v3)

- Page `FinanceCharges/Show.vue` thêm tab "Đợt thu":
  - Bảng đợt: `#`, `Amount`, `Due date`, `Status (badge)`, `DNG ID`, `Paid at`, `Actions`.
  - Action: "Tách đợt" (modal `SplitInstallmentsModal.vue` — vee-validate + Zod vì là form trong modal không navigate).
  - Action: "Thử push lại" (chỉ hiện khi `status=pending && last_push_error != null`).
- Modal `SplitInstallmentsModal`: default 2 rows × 50%, allow add/remove, auto-balance đợt cuối.
- Lock toàn bộ form khi đã có đợt `paid` (readonly, hiển thị badge "Khóa — đợt 1 đã thanh toán").

### Student portal

- Page `Student/Finance/Charges/Index.vue` thêm bảng đợt cho mỗi charge (nếu có > 1 installment).
- Đợt `awaiting_payment` → button "Thanh toán đợt này" (redirect DNG URL).

## Observability

- Log JSON 1-line cho mỗi push attempt: `request_id`, `finance_charge_id`, `installment_id`, `attempt_count`, `outcome`, `duration_ms`, `error_class` (nếu fail).
- Audit log (existing audit infrastructure) cho `SplitChargeIntoInstallmentsAction` — ghi old plan vs new plan để truy vết.
- Metric (nếu Prometheus có sẵn): counter `finance_installment_push_total{outcome=success|fail}`.

## Alternatives Considered

1. **Lưu `installment_total` trên charge** thay vì derive `COUNT(*)`. Reject: tăng denormalization risk (split-brain khi soft delete installment). `COUNT(*)` rẻ vì luôn query theo `finance_charge_id` (index sẵn).
2. **Push tất cả đợt sang DNG cùng lúc** với metadata `installment_no`. Reject: phá invariant DNG hiện tại + DNG phải hiểu khái niệm installment (vượt khỏi spec ràng buộc bất biến).
3. **Tách thành 2 charge con thay vì installment**. Reject: phá invariant "1 ACTIVE per charge_type" + cần tách `Invoice` con → blast radius lớn. Installment giữ Invoice/Charge nguyên trạng.
