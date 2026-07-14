# Student Finance API

Last updated: 2026-07-15
Owner: Finance Team
Status: Active student API contract

Base path: `/api/v1/student/finance`. Student Finance reads are derived from the current Finance Settlement Position; cached invoice status is never authoritative for payment state.

## Settlement position contract

Charge, invoice, and overview responses include `settlement_position`:

```json
{
  "valid": false,
  "mode": "current",
  "state": "invalid",
  "message": "Thông tin thanh toán hiện chưa khả dụng.",
  "issues": [{
    "code": "settlement_position.cash_exceeds_net_due",
    "blocking": true,
    "evidence": {"cash": "1200000.00"}
  }]
}
```

When `valid` is `false`, every derived monetary field is `null`, all derived completion flags are `false`, and invoice `status` is `invalid`. Clients must display the supplied message and must not substitute zero, `paid`, or another settlement state. The `issues` list is review evidence, not a monetary calculation.

## Endpoints

| Endpoint | Filters | Canonical money fields |
| --- | --- | --- |
| `GET /overview` | `semester_id` | gross, discounts, cash, credit, remaining collectible |
| `GET /balance` | `semester_id` | same student summary under `balance` |
| `GET /charges` | `semester_id`, `unpaid` | amount, discount, cash, credit, remaining per charge; `summary` uses the same position |
| `GET /charges/{id}` | — | same charge mapping as the list |
| `GET /invoices` | `semester_id`, `status` | subtotal, discount, cash, credit, remaining per invoice; `summary` uses the same position |
| `GET /invoices/{id}` | — | same invoice mapping and per-line review evidence |

`unpaid` accepts a boolean. Invoice `status` accepts `draft`, `pending`, `paid`, `overdue`, `cancelled`, `open`, `zero_amount`, `issued`, or `invalid`. A cached `draft` or `cancelled` state is shown only after a valid Settlement Position has been established.

## Monetary meanings

- `gross` / `subtotal` / `amount`: original payable amount.
- `discount`: reduction from discount allocations.
- `cash`: completed payment applications.
- `credit`: credit-entitlement applications; it is separate from cash.
- `remaining`: collectible amount after discount, cash, and credit.

`total_credits` is the combined discount and applied-credit amount for compatibility. The individual fields remain available so clients do not present credit as cash payment.
