---
title: Administration
description: Users, permissions, campuses, departments, integrations, and system configuration.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Users/Index.vue
  - resources/js/pages/Roles/Index.vue
  - resources/js/pages/Campuses/Index.vue
  - resources/js/pages/Admin/Departments/Index.vue
  - resources/js/pages/SystemConfig/Index.vue
  - resources/js/pages/Systems/ActivityLogs.vue
  - resources/js/pages/Admin/EmailMonitoring/Index.vue
---

**Administration** is for administrators. Changes here affect **every user**, not just you.

General rule: change one thing at a time, record why, and verify with a real account afterwards.

## Identity & Access

### Users

**What it is for.** Adding, editing, and disabling staff accounts.

**Who can open it.** Anyone with permission to view users.

**Steps**

1. Go to **Administration → Identity & Access → Users**.
2. Add a user, or open an existing account to edit.
3. Assign the role and the campuses they may access.

**Notes**

- Users sign in through Google. The email recorded here must **match** their Google account exactly, or they cannot sign in.
- When staff leave, **disable the account** rather than deleting it. Deleting destroys the record of what they did.
- The assigned campuses decide whose data that person can see.

### Roles & Permissions

**What it is for.** Declaring roles and the permissions attached to them. The screen is titled **Roles**.

**Who can open it.** Anyone with permission to view roles.

**The roles that ship with the system**

| Role | Typical scope |
| --- | --- |
| Super Admin | Full system access |
| Giám Đốc Đào Tạo (Academic Director) | Academic management, university-wide |
| Trưởng Phòng (Department Head) | Management within a department |
| Cán Bộ (Staff) | Day-to-day operational work |
| Phụ huynh (Parent) | Viewing their child's information |

**Notes**

- Permissions decide which menu items a user sees. When a colleague reports a "missing menu", check their role here.
- Editing a role affects **everyone** holding it. If one person needs different access, create a new role rather than editing a shared one.
- Grant only what the work requires. Excess permission is a risk, especially in the finance area.

## Organization

### Campuses

**What it is for.** Declaring the university's campuses.

**Steps.** Go to **Administration → Organization → Campuses**. Click **Clear** to reset filters.

**Note.** The campus is the data boundary of the whole system. Adding or changing one is rare and far-reaching — do it only when certain.

### Departments

**What it is for.** Declaring departments. The list appears under **Department List**.

**Who can open it.** Anyone with permission to manage departments.

## Integrations

| Page | What it is for |
| --- | --- |
| Canvas Integrations | Configuring the connection to Canvas, the online learning system |
| Staff Copilot | Monitoring the AI assistant available to staff |
| AI Provider Settings | Configuring the AI service provider |

**Notes**

- **Canvas Integrations** is where you fix things when the **Canvas Courses** screen reports a broken connection.
- The AI pages require their own permissions. Changes here affect AI features across the whole system.

## System Operations

### System Configuration

**What it is for.** Changing the system's details and branding. The screen is titled **System configuration**.

**Who can open it.** Anyone with permission to view system configuration.

**On screen.** The **Application details** panel (system name and general information) and **Branding assets** (logo and imagery).

**Steps.** Go to **Administration → System Operations → System Configuration**, edit, and save. Click **Reset** to revert to the previous values.

**Note.** The system name and logo appear on every screen and in outgoing mail. A change is visible to everyone immediately.

### Activity Logs

**What it is for.** The history of actions across the whole system.

**Who can open it.** Anyone with permission to view system logs.

**Note.** Different from **Student Actions Audit**, which covers only student records. This one covers everything.

### Email Monitoring

**What it is for.** Watching the health of the mail system.

**Who can open it.** Anyone with permission to view the email system.

**Note.** Check here when mail is reported missing, before editing individual configurations.

## Common situations

| Situation | Order of work |
| --- | --- |
| A new staff member joins | Users (add account, assign role and campuses) |
| "I cannot see menu X" | Users (check their role) → Roles & Permissions |
| A staff member leaves | Users (disable, do not delete) |
| Canvas reports a connection error | Canvas Integrations |
| Changing the logo or display name | System Configuration |
| Investigating an unexpected change | Activity Logs |
| University-wide mail failure | Email Monitoring → Email Configuration |
