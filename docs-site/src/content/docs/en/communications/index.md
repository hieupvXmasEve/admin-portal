---
title: Communications
description: Email configuration, templates, bulk sending, notifications, and delivery tracking.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/EmailConfiguration/Index.vue
  - resources/js/pages/Admin/EmailTemplate/Index.vue
  - resources/js/pages/Admin/BulkEmail/Index.vue
  - resources/js/pages/Admin/EmailLog/Index.vue
  - resources/js/pages/Admin/Notifications/Send.vue
  - resources/js/pages/Admin/NotificationTemplate/Index.vue
  - resources/js/pages/Admin/Notifications/Ops/Outbox.vue
  - resources/js/pages/Admin/Notifications/Ops/Messages.vue
  - resources/js/pages/Admin/Notifications/Ops/Deliveries.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Finance Office" renamed to "Finance", plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Communications** sends mail and notifications outward, and gives you the tools to check whether they arrived.

Two groups: **Email** and **Notification Management**.

This area sends information **outside the university**. A wrong send cannot be recalled — always test on yourself first.

## Email

### Email Configuration

**What it is for.** Declaring the mail server for each campus. The screen is titled **SMTP Configuration**.

**Who can open it.** Anyone with permission to manage the email system.

**On screen.** Cards for **Total Configurations**, **Active Configurations**, **Tested Configurations**, **Success Rate**, and the **Configuration by Campus** table.

**Notes**

- **Test a configuration before putting it into use.** A wrong one silently drops every message until somebody complains.
- A sudden drop in **Success Rate** needs investigating immediately.

### Email Templates

**What it is for.** Pre-written mail to reuse: admission letters, fee reminders, exam schedules.

**Steps.** Go to **Communications → Email → Email Templates** to create or edit a template.

**Note.** Templates contain fields filled in automatically (student name, amount, due date). Send one to yourself before using it on a group.

### Bulk Email

**What it is for.** Sending one message to many people. The screen is titled **Bulk Email Composer**.

**Steps**

1. Go to **Communications → Email → Bulk Email**.
2. Choose the recipient group.
3. Choose a template or write the message.
4. Review, then send.

**Notes**

- **Send a test to yourself first.** This step is not optional.
- Read the recipient count before sending. An unexpected number means the wrong group.
- Sent mail cannot be recalled.

### Email History

**What it is for.** Looking up sent mail and its status. The screen is titled **Email Logs**.

**Steps**

1. Go to **Communications → Email → Email History**.
2. Filter to find the message.
3. Click **Clear** to reset the filters.

**Note.** When a student says they got nothing, check here first. Mail that sent successfully but was not seen is usually in their spam folder.

## Notification Management

### Send Notification

**What it is for.** Sending a notification to students or staff inside the system.

**Steps**

1. Go to **Communications → Notification Management → Send Notification**.
2. Choose who receives it in the **Recipients** panel.
3. Write the content in the **Notification Details** panel.
4. Send.

### Email Templates (notifications)

**What it is for.** The mail that accompanies a notification. The screen is titled **Notification Email Templates**, with the list under **Templates**.

**Note.** Different from **Email Templates** in the Email group. These belong to system notifications only.

### Ops

Three screens for checking that notifications actually go out.

| Page | What it is for |
| --- | --- |
| Outbox | Notifications waiting to be sent |
| Messages | The notification content that was created |
| Deliveries | The result of delivery to each recipient |

**Who can open them.** Anyone with permission to view notification operations.

**Notes**

- A growing **Outbox** means sending is stuck. Tell the technical team.
- When someone reports a missing notification, check in order: **Messages** (was it created), **Outbox** (did it leave), **Deliveries** (did it arrive).

## Common situations

| Situation | Order of work |
| --- | --- |
| Exam schedule announcement to a whole intake | Email Templates → Bulk Email (test first) |
| A student did not receive an email | Email History → check their spam folder |
| A notification did not arrive | Messages → Outbox → Deliveries |
| University-wide mail suddenly fails | Email Configuration (check Success Rate) |
| Fee due reminders | Bulk Email, or DNG Due Reminders in the Finance area |
