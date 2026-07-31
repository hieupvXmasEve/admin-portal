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
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Campus Operations** covers space and activity outside teaching hours: rooms, events, clubs.

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

## Common situations

| Situation | Order of work |
| --- | --- |
| You need a room for a meeting | Find Available Rooms → book → wait for approval |
| Running an event | Find Available Rooms → Events → List |
| Scheduling resit exams | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| A double-booking is reported | Room Usage Calendar → Pending Approvals |
| A room is out of service | Rooms (set it to maintenance) |
