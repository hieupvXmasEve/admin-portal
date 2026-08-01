---
title: Student merchandise store API
description: Store catalog, redemption orders (checkout, history, cancel), and dashboard widgets for the Gold merchandise store.
audience:
    - Student portal developers
    - Merchandise store maintainers
status: current
owner: Merchandise Team
last_verified: 2026-08-01
scope: student-merchandise-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Modules/Merchandise/Http/Api/Student/StudentMerchandiseController.php
    - app/Modules/Merchandise/Http/Api/Student/StudentRedemptionOrderController.php
    - app/Modules/Merchandise/Support/RedemptionService.php
---

# Student merchandise store API

Base path: `/api/v1/student/merchandise`

Portal impact: student.

## Actor: student only (D13)

Every route below sits behind the `student.api.auth` middleware ONLY — it
does **not** use the `either:parent.student.access,student.api.auth` group
that the rest of `routes/api/v1/student.php` uses. A parent proxy token gets
`403 Student account required` on every endpoint here, including reads. If a
portal needs a "Gold History" tab inside the Store, call the existing
`/api/v1/student/gold-wallet/*` routes only while the acting user is a
student — those routes remain parent-accessible for other reasons, but the
Store itself must never surface for a parent.

## Store catalog (read-only)

| Method | Path                | Notes                                                    |
| ------ | ------------------- | --------------------------------------------------------- |
| `GET`  | `/store`             | Paginated. Optional `per_page` (max 50, default 15).      |
| `GET`  | `/store/{merchandise}` | Detail: variants scoped to the student's own campus only. |

Availability (`availability` field) is derived live, not stored:
`available`, `out_of_stock`, `coming_soon`, or `not_visible`. A merchandise
item hidden or archived returns `404` from `show`.

`show` variants are filtered to `campus_id = <student's campus>` and
`is_active = true`. A variant that exists at another campus is not returned.

`GET /store` list item (inside the paginated `data[]`):

```json
{
  "id": 12,
  "name": "Campus Hoodie",
  "gold_price": 250,
  "availability": "available",
  "image": "merchandise/abc.jpg"
}
```

- `image` is the primary image path (string) or `null`. It is a storage path,
  not a fully-qualified URL — resolve against the image base URL client-side.
- The list does NOT include `description` or `variants`; fetch `show` for those.

`GET /store/{merchandise}` detail:

```json
{
  "id": 12,
  "name": "Campus Hoodie",
  "description": "Soft fleece hoodie",
  "gold_price": 250,
  "availability": "available",
  "images": [{ "path": "merchandise/abc.jpg", "is_primary": true }],
  "variants": [
    { "id": 34, "color": "Black", "size": "L", "stock_quantity": 8 }
  ]
}
```

- `description`, `color`, `size` may be `null`. There is NO `variant.label`
  field — build a display label client-side from `color` / `size`.
- `images[].path` is a storage path (same resolution as list `image`).
- `variants[].stock_quantity` is the live per-campus stock (integer).

## Dashboard

`GET /dashboard` returns:

```json
{
  "current_gold": 120,
  "total_redeemed": 340,
  "redemption_orders_count": 4
}
```

- `current_gold`: live wallet balance (`GoldService::getBalance()`), not a
  cached/derived value.
- `total_redeemed`: sum of `total_gold` across the student's own orders,
  EXCLUDING `rejected` and `cancelled` (see
  `RedemptionOrder::NON_REVERSED_STATUSES`).
- `redemption_orders_count`: count of all of the student's own orders,
  regardless of status.

## Checkout

`POST /orders`

```json
{
  "lines": [{ "variant_id": 12, "quantity": 1 }],
  "method": "pickup",
  "shipping_address": null
}
```

- `method`: `pickup` or `shipping`. `shipping_address` is required when
  `method` is `shipping`.
- Per-line quantity max is 5; total quantity across lines for the same
  underlying merchandise (summed across variants/colors/sizes) is also
  capped at 5 per order.
- "Enough Gold" is the live wallet balance — there is no hold/reservation
  step (D3/D10). The check happens under a DB row lock at submit time.
- Send an `Idempotency-Key` header to make retries safe: replaying the same
  key for the same student returns the SAME order (no second Gold/stock
  deduction) instead of creating a duplicate.
- Rate limited: `throttle:merchandise-checkout` (10/min per user).

Errors:

- `422` (`BUSINESS_LOGIC_ERROR`) — insufficient Gold, insufficient stock,
  inactive variant, wrong-campus variant, or invalid line/method.
- `422` (`VALIDATION_ERROR`) — request shape/quantity limits.

On success (`201`), the response is the created order in the same shape as
`GET /orders/{id}` (see below). A checkout is atomic: if any line fails
validation under lock, or the Gold deduction fails, nothing is created — no
partial order, no partial Gold spend, no partial stock decrement.

## Order history

| Method | Path                       | Notes                                          |
| ------ | -------------------------- | ----------------------------------------------- |
| `GET`  | `/orders`                  | Optional `status` filter, `per_page` (max 50). |
| `GET`  | `/orders/{redemptionOrder}` | Includes `timeline`.                            |
| `POST` | `/orders/{redemptionOrder}/cancel` | See below.                              |

Both read routes return `404` (not `403`) for another student's order id, so
the endpoint never confirms whether an order id belongs to someone else.

Order shape:

```json
{
  "id": 42,
  "code": "MRD-202608-000123",
  "status": "ready_for_collection",
  "previous_status": "approved",
  "method": "pickup",
  "total_gold": 80,
  "items": [
    {
      "merchandise_name": "Campus Hoodie",
      "variant_label": "Black / L",
      "gold_price_each": 80,
      "line_total": 80,
      "quantity": 1
    }
  ],
  "collection": {
    "location": "Front desk, Building A",
    "ready_at": "2026-08-01T03:00:00+00:00",
    "deadline": "2026-08-15T03:00:00+00:00",
    "collected_at": null
  },
  "shipping": null,
  "reject_reason": null,
  "cancellation": null,
  "created_at": "2026-07-30T09:00:00+00:00",
  "timeline": [{ "event": "order_placed", "at": "2026-07-30T09:00:00+00:00" }]
}
```

`items`, `merchandise_name`, `variant_label`, and `gold_price_each` are a
SNAPSHOT taken at order time — renaming, repricing, or archiving the
merchandise afterwards never rewrites an existing order's line items.

`collection` is present (possibly with null fields) only when
`method = pickup`; `shipping` only when `method = shipping`.

`timeline` is derived from the order's own stage timestamps
(`created_at`, `ready_at`, `collected_at`, `shipped_at`,
`cancellation_requested_at`, `cancellation_handled_at`, `cancelled_at`) —
there is no separate append-only event log table.

## State machine

```
pending_review --approve--> approved
pending_review --reject(reason)--> rejected            (Gold + stock refunded)
pending_review --cancel--> cancelled                    (Gold + stock refunded)

approved --set ready--> ready_for_collection
approved --mark shipped--> shipped                      (terminal)
approved --request cancel--> cancellation_requested

ready_for_collection --confirm collected--> collected    (terminal)
ready_for_collection --mark overdue--> pickup_overdue
ready_for_collection --request cancel--> cancellation_requested

pickup_overdue --set ready (extend)--> ready_for_collection
pickup_overdue --confirm collected--> collected           (terminal)
pickup_overdue --staff cancel--> cancelled                (Gold + stock refunded)
pickup_overdue --request cancel--> cancellation_requested

cancellation_requested --staff accept--> cancelled        (Gold + stock refunded)
cancellation_requested --staff reject--> <previous_status>
```

`collected`, `shipped`, `rejected`, and `cancelled` are terminal — no route
from this API can transition an order out of them.

## Cancel

`POST /orders/{redemptionOrder}/cancel`

```json
{ "reason": "Changed my mind" }
```

`reason` is optional. Behavior depends on the order's CURRENT status:

- `pending_review`: cancels immediately (`cancelled`), Gold and stock are
  refunded synchronously in the same request.
- `approved`, `ready_for_collection`, `pickup_overdue`: moves to
  `cancellation_requested` — refund only happens once staff accepts the
  request (asynchronous, staff-side action).
- Any other status (`collected`, `shipped`, `rejected`, `cancelled`,
  `cancellation_requested` already): `409 Conflict`
  (`This order can no longer be cancelled`).

Rate limited: `throttle:merchandise-checkout` (shared with checkout).

## Refund guarantee

A refund (Gold + stock, always together) fires at most once per order,
enforced at two layers: a locked state-machine guard in
`RedemptionService`, and a DB-level partial-unique index on
`gold_transactions` that rejects a second `redemption_refund` row for the
same order outright.
