# Finance Office Cutover Inventory (M6)

Milestone 6 classifies every Finance Office entrypoint after M1–M5 implementation.
Decisions: `keep` | `hide` | `redirect` | `deep-link` | `retire`.

Portal impact: **none**.

2026-06-17 update: HP/Tuition and EGC Generate Charges now enter Batch Studio as
the primary workflow. Old standalone GET URLs redirect with safe prefill; old
standalone POST URLs return 410 so they cannot bypass the Batch Studio preview
token.

## Task-first navigation (primary path)

| Work group | Sidebar target | Route | Permission | Decision |
| --- | --- | --- | --- | --- |
| Hôm nay | Cockpit | `finance.cockpit.index` | `view_finance_cockpit` | **keep** — primary daily landing (replaces Billing Dashboard as Hôm nay) |
| Sinh phí | Batch Studio hub | `finance.batch-studio.hub` | `view_finance_batch_studio` | **keep** — bulk wizard entry for HP/Tuition, EGC, non-academic generation, DNG, and reminders |
| Sinh phí | Sinh HP/Tuition | `finance.batch-studio.charges?fee_category=major` | `create_finance_charges` | **keep** — primary HP/Tuition generation entry |
| Sinh phí | Sinh phí EGC | `finance.batch-studio.charges?fee_category=egc` | `generate_egc_finance_charges` | **keep** — primary EGC charge generation entry; EGC support pages stay separate |
| Sinh phí | EGC · Block Results | `finance.egc.block-results.index` | `view_egc_block_results` | **keep** |
| Sinh phí | EGC · Retake Adjustments | `finance.egc.retake-adjustments.index` | `view_egc_retake_adjustments` | **keep** |
| Sinh phí | EGC · Carry Forward | `finance.egc.carry-forward.index` | `view_egc_retake_adjustments` | **keep** |
| Thu & Đối soát | Lập yêu cầu thanh toán DNG | `finance.batch-studio.dng` | `view_finance_batch_studio` + `create_finance_payments` | **keep** — Batch Studio DNG wizard is the primary creation path |
| Thu & Đối soát | DNG Payment Requests | `finance.dng.payment-requests.index` | `view_finance_dng_payment_requests` | **keep** |
| Thu & Đối soát | DNG Webhook Events | `finance.dng.webhook-events.index` | `view_finance_dng_webhook_events` | **keep** |
| Thu & Đối soát | Settlement Worklist | `finance.operations.settlement.index` | `allocate_finance_payment` | **keep** |
| Thu & Đối soát | Payments | `finance.payments.index` | `view_finance_payments` | **keep** — M5 lookup standard + reconciliation worklist |
| Thu & Đối soát | DNG Due Reminders | `finance.operations.due-calendar` | `view_finance_operations_due_calendar` | **keep** |
| Ngoại lệ | Exceptions Queue | `finance.operations.exceptions` | `view_finance_operations_exceptions` | **keep** |
| Ngoại lệ | Lifecycle Exceptions | `finance.operations.lifecycle-exceptions` | `view_finance_operations_due_calendar` | **keep** |
| Ngoại lệ | Lifecycle History | `finance.operations.lifecycle-exception-history` | `view_finance_operations_due_calendar` | **keep** |
| Tra cứu & Audit | Audit Workspace | `finance.audit.index` | `view_finance_audit_workspace` | **keep** |
| Tra cứu & Audit | Charge Ledger (Global) | `finance.charges.index` | `view_finance_charges` | **keep** — M5 lookup |
| Tra cứu & Audit | Invoices | `finance.invoices.index` | `view_finance_invoices` | **keep** — M5 lookup |

## Legacy / secondary entrypoints

| Current entrypoint | Permission | New destination | Decision | Reason |
| --- | --- | --- | --- | --- |
| `/finance/operations/dashboard` | `view_finance_operations_dashboard` | Cockpit when operator has `view_finance_cockpit`; else stay on dashboard | **deep-link** | Demoted from Hôm nay (M3); kept under Tra cứu & Audit for semester KPI table |
| `/finance/operations/generate-charges` | `view_finance_operations_generate_charges` | Batch Studio charges wizard | **hide** | Bulk non-academic generation absorbed by Batch Studio; route kept for exports/template + repair |
| `/finance/major/charges` | `create_finance_charges` | Batch Studio charges wizard with `fee_category=major` | **redirect** | Compatibility GET only; POST returns 410 |
| `/finance/egc/charges` | `generate_egc_finance_charges` | Batch Studio charges wizard with `fee_category=egc` | **redirect** | Compatibility GET only; POST returns 410; EGC block results, retake adjustments, and carry-forward remain separate |
| `/finance/charges/create` | `create_finance_charges` | Student 360 action drawer / charge detail | **deep-link** | Manual charge creation from student context |
| `/finance/charges/{charge}` | `view_finance_charges` | Lookup row / Student 360 / Audit | **deep-link** | Detail + void/installment actions |
| `/finance/invoices/{invoice}` | `view_finance_invoices` | Lookup row / Audit | **deep-link** | Invoice detail + line void |
| `/finance/payments/{payment}` | `view_finance_payment_details` | Lookup row / Student 360 | **deep-link** | Payment detail + allocation |
| `/finance/payments/auto-allocate` | `allocate_finance_payment` | Cockpit unallocated queue / Settlement | **deep-link** | Batch allocation tool |
| `/finance/dng/payment-requests/{id}` | `view_finance_dng_payment_requests` | Worklist / Student 360 / Cockpit | **deep-link** | DNG detail + cancel |
| `/finance/dng/webhook-events/{id}` | `view_finance_dng_webhook_events` | Cockpit webhook queue | **deep-link** | Webhook retry |
| `/finance/students/{student}` | `view_finance_student_overview` | Global search / Lookup row→360 | **deep-link** | Not in sidebar by design (M1) |
| `/finance/students/{student}/charges` | `view_finance_charges` | Student 360 ledger lens | **deep-link** | Per-student charge list |
| `/finance/operations/dng-worklist` | `view_finance_batch_studio` | Batch Studio DNG wizard | **redirect** | Legacy URL only; direct POST write path is gone |
| `/finance/operations/batch-dng` | — | Batch Studio DNG wizard | **retire** | Route removed; Batch Studio DNG is canonical |
| `/finance/payments/create` | — | Batch Studio DNG wizard | **retire** | Route removed; unified DNG creation |

## Internal link cutover (M6)

| Source page | Old link | New link |
| --- | --- | --- |
| Settlement Worklist back button | `finance.operations.dashboard` | `finance.cockpit.index` when permitted, else dashboard |
| Auto-allocate completion CTA | `finance.operations.dashboard` | `finance.cockpit.index` ("Hôm nay") |

## Permission matrix (visible Finance Office entrypoints)

| Role pattern | Hôm nay | Sinh phí | Thu & Đối soát | Ngoại lệ | Tra cứu & Audit |
| --- | --- | --- | --- | --- | --- |
| Finance staff (`can_bo`) | Cockpit if `view_finance_cockpit` | Per-item `requiredPermissions` | Per-item | Per-item | Per-item |
| Finance lead (`truong_phong`) | Same + oversight paths | Same | Same | Same | Audit + lookup |
| Restricted finance user | Hidden items omitted | Hidden items omitted | Hidden items omitted | Hidden items omitted | Hidden items omitted |
| All-campus (`view_finance_all_campus`) | Cockpit `scope_badge: all_campus` | Campus-scoped pages unchanged | Same | Same | Audit scope badge |

## Unresolved / backlog

- Billing Dashboard full retirement pending product sign-off (cockpit M3 note).
- Browser UAT smoke for role matrix remains manual (no Playwright harness).
- `S-011-student-360-installment-plan-guards` explicitly out of M6 scope.
