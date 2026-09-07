---
id: ADR-0053
title: "Collection operations: no billing-cycle entity, no payer-identity gate"
status: accepted
owner: Finance Team
last_verified: 2026-09-07
scope: architecture-decision
portal_impact: student
---

# Collection operations without cycles or payer gating

**Context.** Owner sessions 2026-09-04 and 2026-09-06 locked collection
operations for staff and students/parents. Later audits kept reopening three
intentional absences: a billing-cycle entity, payment-initiator authorization,
and automated dunning. This ADR records those absences as policy.

**Decisions.**

1. **There is no billing-cycle entity.** Staff choose a single due date at DNG
   commit (`CommitBatchDngRequest`); that date is written onto the invoice,
   including a reused invoice. Overdue is derived from that date. A missing due
   date is never overdue.

   `billing_cycles` has no writer; six live readers must keep running, including
   `InvoiceGenerationService` which uses `billing_cycle_id` to decide which
   charges belong on an invoice (a money-composition read inside
   `SettlementMutationGuard`). Also:
   `BillingInvoiceController`, `StudentInvoiceResource` (student payload),
   `PayInvoiceRequest`, `FinanceAuditSearchRequest`,
   `GetFinanceAuditGraphQuery`. The column is frozen, not dead. Do not drop
   the table.

2. **Portal QR and Foxpay are open to every portal actor (student or parent).**
   The four write routes mint a provider link for a request already
   `pushed_to_dng`; they do not mutate the local ledger
   (`CreateStudentDngPaymentAccessAction`). `access_level` is written and
   audited, and is intentionally **not** used as a payment-authorization gate;
   the field stays because Identity maintains it. It is a method on the public
   cross-module contract `GuardianAccessGrantWriter`, with its own action
   (`ChangeGuardianAccessLevelAction`, any non-empty string, no allow-list),
   writer, DTO, audit, DB index, and frontend type. It is not a dead column.

   Known consequence of not tracing who paid: `ParentStudentAccess` already
   stores `accessing_parent` on the request (zero consumers), and `ApiLogging`
   runs before the user swap, so one log line records the parent and a later
   line records the student, with no request id joining them. That is two
   contradictory actor lines, not "no data".

   Opening QR/Foxpay for every portal actor is an authorization decision.
   Abuse control is separate: the four POSTs are throttled
   (`student-payment-access`, keyed by student id) because each call is an
   outbound POST to DNG/Foxpay without an idempotency key.

3. **Manual reminders are a decision, not a gap.** There is no dunning ladder
   and no cron send. Unified money-item status is derived from Settlement
   Position, never stored. Internal installment slots appear at DNG one slot
   at a time (ADR-0028).

**Consequences.** Do not revive `billing_cycles` writes, payer-identity
evidence, or `access_level` payment gates without a new ADR. Throttle is not
a substitute for decision 2.

**Related.** ADR-0028, ADR-0029, ADR-0022, ADR-0030;
`docs/api/student/finance.md`; plan `260904-1114-finance-flow-redesign`.
