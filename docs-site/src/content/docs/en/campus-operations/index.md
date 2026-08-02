---
title: Campus Operations
description: Rooms, bookings, approvals, events, and clubs.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Rooms/Index.vue
  - resources/js/pages/RoomBookings/Index.vue
  - resources/js/pages/RoomBookings/Availability.vue
  - resources/js/pages/RoomBookings/MyBookings.vue
  - resources/js/pages/RoomBookings/Pending.vue
  - resources/js/pages/RoomBookings/Calendar.vue
  - resources/js/pages/Events/Index.vue
  - resources/js/pages/Clubs/Index.vue
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Campus Operations** covers space and activity outside teaching hours: rooms, events, clubs, the Gold redemption store.

## Room Management

### Rooms

**What it is for.** Declaring rooms: code, capacity, equipment, condition. The screen is titled **Room Management**.

**Who can open it.** Anyone with permission to view rooms.

**Steps.** Go to **Campus Operations → Room Management → Rooms** to add or edit a room.

**Notes**

- A room set to maintenance cannot be scheduled or booked. Set it back when maintenance ends.
- Capacity must be accurate for the availability search to filter usefully.

### Find Available Rooms

**What it is for.** Finding rooms free in the slot you need. **Open this before booking.**

**Steps**

1. Go to **Campus Operations → Room Management → Find Available Rooms**.
2. In the **Filters** panel, enter the date, time, and capacity you need.
3. Choose a room from the results and book it.

### Bookings

**What it is for.** Seeing every room booking. The screen is titled **Room Bookings**.

**Who can open it.** Anyone with permission to view bookings.

### My Bookings

**What it is for.** Viewing and managing the bookings you created.

**Who can open it.** Anyone with permission to create bookings.

### Pending Approvals

**What it is for.** Approving or rejecting other people's booking requests.

**Who can open it.** Anyone with permission to approve bookings.

**Steps**

1. Go to **Campus Operations → Room Management → Pending Approvals**.
2. Review each request and approve or reject it.
3. Click **All Bookings** to see everything, not just what is pending.

**Note.** An unapproved request does **not** hold the room. Letting them pile up is the usual cause of double-booking.

### Room Usage Calendar

**What it is for.** Seeing all room usage as a calendar, including classes and events.

**Steps.** Go to **Campus Operations → Room Management → Room Usage Calendar**. Click **Today** to return to today.

**Note.** This is the easiest place to spot a clash. Check it before confirming a large event or an exam slot.

## Events

### List

**What it is for.** Creating and managing university events.

**Who can open it.** Anyone with permission to view events.

**Steps.** Go to **Campus Operations → Events → List** to create an event or open an existing one.

**Note.** Book the room in **Find Available Rooms** first, then fix the event time.

### Event Reports

**What it is for.** Event figures: how many, and how well attended.

## Clubs

**What it is for.** Managing student clubs and their members.

**Who can open it.** Anyone with permission to view clubs.

**Steps.** Go to **Campus Operations → Clubs**.

## Merchandise — Gold redemption store

Students spend **Gold** (their reward points) on the student portal, not this
screen. The **Merchandise** area here is where **staff** manage products and
process redemption orders.

### Store

**What it is for.** Declaring redeemable products: name, description, Gold
price, images, variants (color/size) per campus, and stock.

**Who can open it.** Anyone with permission to view/create/edit products.

**Steps**

1. Go to **Campus Operations → Merchandise → Store**.
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

1. Go to **Campus Operations → Merchandise → Redemption Orders**.
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
| You need a room for a meeting | Find Available Rooms → book → wait for approval |
| Running an event | Find Available Rooms → Events → List |
| Scheduling resit exams | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| A double-booking is reported | Room Usage Calendar → Pending Approvals |
| A room is out of service | Rooms (set it to maintenance) |
| A student says their redemption order isn't approved yet | Redemption Orders → look up by order code |
| Need to add a new product to the store | Merchandise → Store → add product + per-campus variants |
