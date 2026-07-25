---
title: Student finance API
description: Student settlement, charges, payments, invoices, DNG payment requests, and Gold wallet routes.
audience:
    - Student portal developers
    - Finance maintainers
status: current
owner: Finance Team
last_verified: 2026-07-25
scope: student-finance-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Modules/Finance/Http/Api/Student/StudentFinanceController.php
    - app/Modules/Finance/Queries/GetStudentFinancePresentationQuery.php
    - app/Modules/Finance/Dng/Http/Controllers/DngWebhookController.php
---

# Student finance API

Student finance base path: `/api/v1/student/finance`

Protected student finance reads and payment-access operations derive the
selected student from the authenticated student or authorized parent proxy.

## Settlement and ledger endpoints

| Method | Path                    | Input                                     |
| ------ | ----------------------- | ----------------------------------------- |
| `GET`  | `/overview`             | Optional `semester_id`.                   |
| `GET`  | `/balance`              | Optional `semester_id`.                   |
| `GET`  | `/charges`              | Optional `semester_id`, `unpaid` boolean. |
| `GET`  | `/charges/{chargeId}`   | None.                                     |
| `GET`  | `/payments`             | Optional `from`, `to`, and `method`.      |
| `GET`  | `/payments/{paymentId}` | None.                                     |
| `GET`  | `/invoices`             | Optional `semester_id`, `status`.         |
| `GET`  | `/invoices/{invoiceId}` | None.                                     |

Invoice `status` accepts `draft`, `pending`, `paid`, `overdue`, `cancelled`,
`open`, `zero_amount`, `issued`, or `invalid`.

Payment `method` accepts `cash`, `bank_transfer`, `gateway`, `wallet`,
`import`, or `other`.

The compatibility reads `GET /finance` and `GET /finance/{semester}` remain
registered through `LegacyFinanceController`.

## Settlement position

Charge, invoice, balance, and overview values are derived from the current
Finance Settlement Position. Cached invoice status is not authoritative for
payment state.

Responses can include `settlement_position` with `valid`, `mode`, `state`,
`message`, and `issues`. If `valid` is false, clients must show the supplied
message and preserve nullable derived monetary fields. They must not substitute
zero or infer a paid state.

Monetary terms:

- `gross`, `subtotal`, or `amount`: original payable amount;
- `discount`: discount allocations;
- `cash`: completed payment applications;
- `credit`: credit-entitlement applications, separate from cash;
- `remaining`: collectible amount after discount, cash, and credit.

`total_credits` combines discounts and applied credit for compatibility; use
the separate fields when displaying how the balance was settled.

## DNG payment requests

| Method | Path                                       |
| ------ | ------------------------------------------ |
| `GET`  | `/dng-requests`                            |
| `GET`  | `/dng-requests/all`                        |
| `GET`  | `/dng-requests/{dngRequestId}`             |
| `POST` | `/dng-requests/{dngRequestId}/qr`          |
| `POST` | `/dng-requests/{dngRequestId}/installment` |
| `POST` | `/dng/qr`                                  |
| `POST` | `/dng/installment`                         |

Request fields and DNG response mapping are owned by
`StudentFinanceController` and the actions it invokes. A portal must treat
cancelled DNG requests as terminal.

`GET /dng-requests` accepts an optional `status`: `pending`,
`pushed_to_dng`, `paid_uninvoiced`, `paid_invoiced`, `reconciled`, `failed`,
`cancelled`, `unknown_outcome`, or `needs_review`.

The provider callback is `POST /api/webhooks/dng/payment`. It is not a student
route: the controller persists the raw event, deduplicates by payload hash,
queues processing, and returns the DNG compatibility acknowledgement. Checksum
and business validation happen in the queued DNG pipeline.

## Gold wallet

| Method | Path                                                     |
| ------ | -------------------------------------------------------- |
| `GET`  | `/api/v1/student/gold-wallet`                            |
| `GET`  | `/api/v1/student/gold-wallet/summary`                    |
| `GET`  | `/api/v1/student/gold-wallet/transactions`               |
| `GET`  | `/api/v1/student/gold-wallet/transactions/recent`        |
| `GET`  | `/api/v1/student/gold-wallet/transactions/stats`         |
| `GET`  | `/api/v1/student/gold-wallet/transactions/{transaction}` |

These routes are student reads. Administrative wallet mutation routes are
outside this contract.
