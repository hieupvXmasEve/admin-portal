---
title: Finance Office
description: Generating charges, collecting and reconciling payments, handling exceptions, plus scholarships and discounts.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Finance/Cockpit/Index.vue
  - resources/js/pages/Finance/Reporting/Index.vue
  - resources/js/pages/Finance/Revenue/Index.vue
  - resources/js/pages/Finance/BatchStudio/Hub.vue
  - resources/js/pages/Finance/PricingOperations/Index.vue
  - resources/js/pages/Finance/Payments/Index.vue
  - resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue
  - resources/js/pages/Finance/Invoices/Index.vue
  - resources/js/pages/TuitionPlans/Index.vue
  - resources/js/pages/Scholarships/Index.vue
  - resources/js/pages/StudentScholarships/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Show.vue
  - resources/js/pages/Vouchers/Index.vue
---

Fees span two menu groups: **Finance Office** (the life of a charge) and **Discounts & Funding** (reductions and funding).

A charge moves in this order:

```text
Generate  ->  Collect & reconcile  ->  Exceptions (when it does not match)  ->  Lookup & audit
```

A mistake at generation propagates through every later step. This area touches real money, so **check before running anything in bulk**.

## Hôm nay — Today

**What it is for.** The screen to open each morning: what still needs doing.

**Who can open it.** Anyone with permission to view the finance dashboard.

**On screen**

- **Tổng phải thu** — total receivable.
- **Đã thu** — collected so far.
- **SV chưa sinh phí** — students with no charge generated yet; usually the most urgent item.
- **Cần xử lý** — outstanding work.

**Steps.** Go to **Finance Office → Hôm nay**. Click **Làm mới** (Refresh) for current figures.

**Note.** A non-zero **SV chưa sinh phí** mid-term means students are studying without being charged. Handle it early; recovery gets harder with time.

## Finance Reporting

**What it is for.** Consolidated income and receivable reporting for management.

**Who can open it.** Anyone with permission to view finance reporting.

**Steps.** Go to **Finance Office → Finance Reporting**, choose a term or date range, then read and export.

## Doanh thu — Revenue

**What it is for.** School-wide revenue rolled up across terms, by campus and fee type, computed from the same source data as Collection Progress so the two reports never disagree.

**Who can open it.** Anyone with permission to view the school-wide revenue report (a separate permission from the per-campus Finance Reporting).

**Steps.** Go to **Finance Office → Doanh thu**, read the per-term table and the campus/fee-type breakdown. The **unattributed cash** row is kept separate and never folded into the totals.

## Sinh phí — Generating charges

This group creates the receivables. It carries the most risk in the area.

### Batch Studio

**What it is for.** Generating charges in bulk for a group of students.

**Who can open it.** Anyone with permission to use Batch Studio.

**Steps**

1. Go to **Finance Office → Sinh phí → Batch Studio**.
2. Choose the student group and the charge type.
3. **Preview the result** before running for real.
4. Run, and wait for it to finish.

**Notes**

- Always preview. Wrong charges generated in bulk have to be cancelled one by one.
- Test on a small group before running a whole intake.
- Check whether students already hold a scholarship or voucher, so charges are not generated at the wrong amount.

### Pricing Operations

**What it is for.** Declaring and adjusting the rules that price tuition.

**Steps**

1. Go to **Finance Office → Sinh phí → Pricing Operations**.
2. Read the existing list under **Rule versions**.
3. Click **Create pricing rule version**, fill in the panel, then click **Create version**.

**Note.** Pricing rules are versioned. Create a new version rather than editing the active one, so charges already generated are unaffected.

### EGC · Kết quả & học lại, EGC - Carry Forward

**What it is for.** The fees that arise from academic results: retakes, and the amounts carried into the following term.

**Note.** Only run these once results are finalised. Running early charges students whose final grades do not yet exist.

## Thu & Đối soát — Collection and reconciliation

This group records money in and matches it to receivables.

| Page | What it is for |
| --- | --- |
| DNG Due Reminders | The payment due-date reminder schedule |
| DNG Campus Mapping | Which campus maps to which collection account |
| Lập yêu cầu thanh toán DNG | Creating payment requests sent to the DNG gateway |
| Settlement Worklist | Amounts that need matching by hand |
| Payments | Payments received |

### Payments

**On screen.** Three cards: **Tổng đã đóng** (total paid), **Đã thanh toán** (settled), **Còn dư** (remaining balance).

**Note.** A non-zero **Còn dư** means the student overpaid, or the money has not been fully allocated. Resolve it in **Settlement Worklist**.

### Settlement Worklist

**What it is for.** Matching received money to the right receivable, for cases the system could not match automatically.

**Who can open it.** Anyone with permission to allocate payments.

**Note.** This directly touches students' money. Check the supporting document before allocating, and do not allocate while in doubt.

## Ngoại lệ — Exceptions

Where mismatched cases collect.

| Page | What it is for |
| --- | --- |
| Exceptions Queue | The queue of exceptions to work through |
| Lifecycle Exceptions | Exceptions caused by a change in student status |
| Lifecycle History | The history of exceptions already handled |

**Note.** Exceptions left standing distort end-of-term reporting. Clear the queue weekly rather than letting it build.

## Tra cứu & Audit — Lookup and audit

Read-only screens for finding and reconciling.

| Page | What it is for |
| --- | --- |
| Audit Workspace | The general lookup area when investigating one case |
| Charge Ledger (Global) | The ledger of every charge generated |
| Invoices | The invoice list, shown under **Invoice List** |
| DNG Payment Requests | Payment requests sent to the DNG gateway |
| DNG · Cần kiểm tra | Gateway receipts that look wrong |
| DNG Webhook Events | The log of signals received from the gateway |

### DNG Payment Requests

**On screen.** Status cards: **Total**, **Pending**, **Pushed to DNG**, **Paid Uninvoiced**, **Paid Invoiced**, **Failed / Bridged**. There is a filter by **term**, a **Ref (Webhook)** column showing the DNG-returned reference, and an **Export Excel** button that exports the currently filtered list.

**Note.** **Failed / Bridged** and **DNG · Cần kiểm tra** deserve a daily look. That is where money has arrived but has not been recorded correctly.

### Invoices

**On screen.** Filter by **term** (defaults to the operator's currently selected term). Excel export has moved to the **DNG Payment Requests** screen and is no longer here.

## Discounts & Funding

A separate menu group that decides how much a student actually pays.

### Tuition Plans

**Steps**

1. Go to **Discounts & Funding → Tuition Plans**.
2. Click **Create Tuition Plan** for a new one.
3. Use the **Filters** panel to find existing plans.

### Scholarships

**Steps.** Go to **Discounts & Funding → Scholarships** and use the **Filter Scholarships** panel to find, create, or edit a scholarship type.

### Student Scholarships

**What it is for.** Assigning a specific scholarship to a student. The screen is titled **Student Scholarship Assignments**.

**Who can open it.** Anyone with permission to assign scholarships.

**Steps.** Go to **Discounts & Funding → Student Scholarships**, use the **Filter Assignments** panel, then assign or remove.

**Note.** Assign scholarships **before** generating charges. Assigned afterwards, existing charges do not reduce themselves and must be adjusted by hand.

### Scholarship Adjustments

**What it's for.** Review students who failed a course, log interview sessions, and decide whether their scholarship gets reduced for the next term. The original scholarship is never edited or deleted — the system creates a separate adjustment for the affected term, and the next term it auto-proposes restoring the original rate if the student stops failing.

**Who can access.** Users with permission to view/process scholarship adjustment dossiers (`view_scholarship_adjustment`, `approve_scholarship_adjustment`).

**Step-by-step guide:** [Scholarship Adjustments](/en/finance-office/scholarship-adjustments/) (find candidates, schedule the interview, get student confirmation, decide, apply, and restore next term).

### Vouchers

**Steps.** Go to **Discounts & Funding → Vouchers** and use the **Filter Vouchers** panel to manage discount codes.

## Common situations

| Situation | Order of work |
| --- | --- |
| Start of term, charging a whole intake | Student Scholarships → Pricing Operations → Batch Studio (preview) → Hôm nay |
| Student paid but the system has not recorded it | DNG · Cần kiểm tra → DNG Webhook Events → Settlement Worklist |
| Student overpaid | Payments (Còn dư column) → Settlement Worklist |
| Weekly clean-up | Exceptions Queue → Lifecycle Exceptions |
| Closing the term's numbers | Exceptions (clear them) → Charge Ledger → Finance Reporting |
| Retake fees | Retake Registration → EGC · Kết quả & học lại |
