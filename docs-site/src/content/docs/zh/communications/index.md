---
title: Communications
description: 邮件配置、模板、批量发送、通知与发送状态跟踪。
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

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Communications** 用于向外发送邮件和通知，并提供检查是否送达的工具。

分为两组：**Email**（邮件）和 **Notification Management**（系统内通知）。

这是把信息发送到 **校外** 的区域。发错无法撤回——务必先向自己测试发送。

## Email

### Email Configuration — 发送配置

**用途。** 为各校区登记邮件服务器。界面名称为 **SMTP Configuration**。

**谁可以访问。** 拥有管理邮件系统权限的人员。

**界面内容。** 卡片：**Total Configurations**（配置总数）、**Active Configurations**（生效中）、**Tested Configurations**（已测试通过）、**Success Rate**（发送成功率），以及 **Configuration by Campus**（按校区的配置）表。

**注意事项**

- **投入使用前先测试配置。** 配置出错会导致所有邮件悄无声息地全部失败，直到有人投诉才会被发现。
- **Success Rate** 突然下降是需要立即排查的信号。

### Email Templates — 邮件模板

**用途。** 预先编写可重复使用的邮件：录取通知、学费提醒、考试安排通知。

**操作步骤。** 进入 **Communications → Email → Email Templates**，创建或编辑模板。

**注意事项。** 模板中有自动填充字段（学生姓名、金额、截止日期）。批量使用前先发一封给自己测试。

### Bulk Email — 批量发送

**用途。** 一次性向多人发送同一封邮件。界面名称为 **Bulk Email Composer**。

**操作步骤**

1. 进入 **Communications → Email → Bulk Email**。
2. 选择接收群体。
3. 选择模板或撰写内容。
4. 核对后发送。

**注意事项**

- **先向自己发送测试邮件。** 这一步不可省略。
- 发送前仔细查看接收人数。数字异常意味着选错了群体。
- 已发送的邮件无法撤回。

### Email History — 发送历史

**用途。** 查询已发送邮件及其状态。界面名称为 **Email Logs**。

**操作步骤**

1. 进入 **Communications → Email → Email History**。
2. 筛选查找目标邮件。
3. 点击 **Clear** 清除筛选条件。

**注意事项。** 学生反馈“没收到邮件”时，先在此查询。发送成功但对方没看到的，通常是落入了对方的垃圾邮件箱。

## Notification Management — 通知管理

### Send Notification — 发送通知

**用途。** 在系统内向学生或教职员工发送通知。

**操作步骤**

1. 进入 **Communications → Notification Management → Send Notification**。
2. 在 **Recipients** 面板选择接收人。
3. 在 **Notification Details** 面板填写内容。
4. 发送。

### Email Templates（通知用）

**用途。** 通知附带的邮件模板。界面名称为 **Notification Email Templates**，列表位于 **Templates** 中。

**注意事项。** 与 Email 分组中的 **Email Templates** 不同。此分组专用于系统通知。

### Ops — 发送跟踪

三个用于检查通知是否成功送出的界面。

| 页面 | 用途 |
| --- | --- |
| Outbox | 等待发送的通知 |
| Messages | 已创建的通知内容 |
| Deliveries | 各接收人的发送结果 |

**谁可以访问。** 拥有查看通知运行状态权限的人员。

**注意事项**

- **Outbox** 持续堆积意味着发送出现阻塞。请通知技术部门。
- 有人反馈未收到通知时，按顺序检查：**Messages**（是否已创建） → **Outbox**（是否已发出） → **Deliveries**（是否已送达）。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 向整个批次发送考试安排通知 | Email Templates → Bulk Email（先测试发送） |
| 学生反馈未收到邮件 | Email History → 检查其垃圾邮件箱 |
| 通知未送达 | Messages → Outbox → Deliveries |
| 全校邮件突然发不出去 | Email Configuration（查看 Success Rate） |
| 学费缴纳到期提醒 | Bulk Email，或 Finance Office 区域的 DNG Due Reminders |
