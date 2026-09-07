---
title: Student finance API
description: Student settlement, charges, payments, invoices, DNG payment requests, and Gold wallet routes.
audience:
    - Student portal developers
    - Finance maintainers
status: current
owner: Finance Team
last_verified: 2026-09-07
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
`message`, `issues`, and `money_item_status`. If `valid` is false or
`money_item_status.hide_amounts` is true, clients must show
`money_item_status.label_student` (or `message`) and preserve nullable derived
monetary fields. They must not substitute zero or infer a paid state.

`money_item_status` is derived, never stored. Codes: `reviewing`, `adjusted`,
`completed`, `processing`, `overdue`, `awaiting_payment`. Overdue requires a
staff-chosen due date; a missing due date is never overdue. `processing` applies
only to a payable line targeted by an active DNG reservation.

Monetary terms:

- `gross`, `subtotal`, or `amount`: original payable amount;
- `discount`: discount allocations;
- `cash`: completed payment applications;
- `credit`: credit-entitlement applications, separate from cash;
- `remaining`: collectible amount after discount, cash, and credit.

`total_credits` combines discounts and applied credit for compatibility; use
the separate fields when displaying how the balance was settled.

Charge and invoice-line payloads include `learner_label` (Vietnamese student
wording: học phí kỳ, học phí chương trình dự bị, phí học lại môn, phí thi lại,
bảo hiểm y tế, or khoản thu khác plus description). Balance payloads include
`learner_terms` for `unapplied_cash` (Số dư của bạn), `cash` (Bạn đã nộp), and
`credit` (Nhà trường đã giảm). Portal UI rendering is a separate contract.

When a charge is split, charge and DNG-request payloads include
`installment_no`, `installments_total`, and `due_date`. Full-term DNG requests
send `installment_no` / `installments_total` as null and still expose the
staff-chosen `due_date`.

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

The four POST payment-access routes are throttled (`student-payment-access`,
10/minute, keyed by student id). Both student and parent tokens may call them;
the limiter is abuse control, not payer authorization. Each call is an outbound
POST to DNG/Foxpay without an idempotency key.

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
