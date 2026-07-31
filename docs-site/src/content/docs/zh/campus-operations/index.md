---
title: Campus Operations
description: 教室、预约、审批、活动与社团。
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

**Campus Operations** 管理教学时间之外的空间和活动：教室、活动、社团。

## Room Management — 教室管理

### Rooms — 教室列表

**用途。** 登记教室：教室代码、容纳人数、设备、状态。界面名称为 **Room Management**。

**谁可以访问。** 拥有查看教室权限的人员。

**操作步骤。** 进入 **Campus Operations → Room Management → Rooms**，添加或编辑教室。

**注意事项**

- 设为维护中状态的教室无法被排课或预约。维护结束后记得改回正常状态。
- 容纳人数需登记准确，空闲教室查询才能正确筛选。

### Find Available Rooms — 查找空闲教室

**用途。** 查找所需时段内的空闲教室。**这是预约前应先打开的界面。**

**操作步骤**

1. 进入 **Campus Operations → Room Management → Find Available Rooms**。
2. 在 **Filters** 面板输入日期、时间和所需容纳人数。
3. 从结果中选择合适的教室并进行预约。

### Bookings — 预约记录

**用途。** 查看全部教室预约记录。界面名称为 **Room Bookings**。

**谁可以访问。** 拥有查看预约权限的人员。

### My Bookings — 我的预约

**用途。** 查看并管理你自己创建的预约。

**谁可以访问。** 拥有创建预约请求权限的人员。

### Pending Approvals — 待审批

**用途。** 审批或拒绝他人提交的预约请求。

**谁可以访问。** 拥有审批预约权限的人员。

**操作步骤**

1. 进入 **Campus Operations → Room Management → Pending Approvals**。
2. 逐条审阅请求，批准或拒绝。
3. 点击 **All Bookings** 查看全部记录，而不只是待审批的部分。

**注意事项。** 未获批准的请求 **不会** 锁定该教室。放任待审批堆积是造成重复预约的常见原因。

### Room Usage Calendar — 教室使用日历

**用途。** 以日历形式查看包括上课和活动在内的全部教室使用情况。

**操作步骤。** 进入 **Campus Operations → Room Management → Room Usage Calendar**。点击 **Today** 返回今天。

**注意事项。** 这是最容易发现冲突的地方。确认大型活动或考试时段前应先查看这里。

## Events — 活动

### List

**用途。** 创建和管理校内活动。

**谁可以访问。** 拥有查看活动权限的人员。

**操作步骤。** 进入 **Campus Operations → Events → List**，创建活动或打开已有活动。

**注意事项。** 先在 **Find Available Rooms** 预约教室，再确定活动时间。

### Event Reports

**用途。** 活动统计数据：数量与参与情况。

## Clubs — 社团

**用途。** 管理学生社团及其成员。

**谁可以访问。** 拥有查看社团权限的人员。

**操作步骤。** 进入 **Campus Operations → Clubs**。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 需要一间会议用的教室 | Find Available Rooms → 预约 → 等待审批 |
| 举办活动 | Find Available Rooms → Events → List |
| 安排补考时段 | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| 收到重复预约的报告 | Room Usage Calendar → Pending Approvals |
| 教室损坏、暂停使用 | Rooms（改为维护中状态） |
