---
title: Store & Clubs
description: Student clubs and the Gold redemption store.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Clubs/Index.vue
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Campus Operations split into "Campus" (Rooms + Events) and "Store & Clubs" (Clubs + Merchandise); page moved from campus-operations/index.md, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Store & Clubs** manages student clubs and the Gold redemption store — two HQ-only screens, split out from the rooms/events in **Campus**.

## Clubs

**What it is for.** Managing student clubs and their members.

**Who can open it.** Anyone with permission to view clubs.

**Steps.** Go to **Store & Clubs → Clubs**.

## Merchandise — Gold redemption store

Students spend **Gold** (their reward points) on the student portal, not this
screen. The **Merchandise** area here is where **staff** manage products and
process redemption orders.

### Store

**What it is for.** Declaring redeemable products: name, description, Gold
price, images, variants (color/size) per campus, and stock.

**Who can open it.** Anyone with permission to view/create/edit products.

**Steps**

1. Go to **Store & Clubs → Merchandise → Store**.
2. Add a new product or open an existing one to edit.
3. On the detail screen, add variants per campus and adjust stock — always
   through the adjust form, never by editing figures directly.

**Notes**

- Hiding/archiving a product removes it from the student store, but existing
  orders keep the name/price captured at order time.
- Students only see products/variants that belong to their own campus.

### Redemption Orders

**What it is for.** Approving, rejecting, and tracking student redemption
orders through to delivery/pickup.

**Who can open it.** Anyone with permission to approve redemption orders.
Only orders in a campus you're granted show up.

**Steps**

1. Go to **Store & Clubs → Merchandise → Redemption Orders**.
2. Open the order to handle.
3. Depending on the order's status, click the matching action: **Approve**,
   **Reject** (a reason is required), **Mark ready for collection**, **Mark
   as shipped**, **Confirm collected**, **Mark pickup overdue**, **Extend
   pickup deadline**, or handle a cancellation request (**Accept**/**Reject
   cancellation**).

**Notes**

- Rejecting or cancelling an order refunds Gold and stock to the student —
  it refunds exactly once per order no matter how many times you click.
- For a shipping order, staff ship it through an outside platform first,
  then click **Mark as shipped** — there is no dedicated shipping module.
- For a student who never showed up to collect, staff mark it **Mark pickup
  overdue** by hand (there's no automatic job yet).

### Reports

**What it is for.** Orders by status, Gold used/refunded, most-redeemed
products, stock by campus.

**Who can open it.** Anyone with permission to view Merchandise reports.

**Note.** Excel export: not built yet, pending sign-off.

## Common situations

| Situation | Order of work |
| --- | --- |
| A student says their redemption order isn't approved yet | Redemption Orders → look up by order code |
| Need to add a new product to the store | Merchandise → Store → add product + per-campus variants |
