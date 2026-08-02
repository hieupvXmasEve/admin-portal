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
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Campus Operations** 管理教学时间之外的空间和活动：教室、活动、社团、Gold 兑换商城。

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

## Merchandise — Gold 兑换商城

学生用 **Gold**（奖励积分）兑换商品是在学生门户完成的，不在这个界面。这里的
**Merchandise** 区域是 **staff** 管理商品和处理兑换订单的地方。

### Store — 商品列表

**用途。** 登记可兑换商品：名称、描述、Gold 价格、图片、按校区区分的规格
（颜色/尺码）以及库存。

**谁可以访问。** 拥有商品查看/新增/编辑权限的人员。

**操作步骤**

1. 进入 **Campus Operations → Merchandise → Store**。
2. 新增商品，或打开已有商品进行编辑。
3. 在详情页按校区添加规格并调整库存 — 务必通过调整表单操作，不要直接改
   数字。

**注意事项**

- 隐藏/归档商品后，该商品会从学生商城消失，但已有订单仍保留下单时的
  名称/价格。
- 学生只能看到自己所在校区的商品/规格。

### Redemption Orders — 兑换订单

**用途。** 审批、拒绝并跟踪学生的兑换订单，直至收货/领取完成。

**谁可以访问。** 拥有兑换订单审批权限的人员。只能看到自己被授权校区的
订单。

**操作步骤**

1. 进入 **Campus Operations → Merchandise → Redemption Orders**。
2. 打开需要处理的订单。
3. 根据订单状态点击对应操作：**Approve**、**Reject**（必须填写原因）、
   **Mark ready for collection**、**Mark as shipped**、**Confirm
   collected**、**Mark pickup overdue**、**Extend pickup deadline**，或
   处理取消请求（**Accept**/**Reject cancellation**）。

**注意事项**

- 拒绝或取消订单会把 Gold 和库存退还给学生 —— 无论点击多少次，每个订单只
  退还一次。
- 选择配送（`shipping`）的订单：staff 先通过外部平台发货，再点击 **Mark as
  shipped** —— 系统没有独立的配送模块。
- 学生超时未来领取的订单：staff 手动点击 **Mark pickup overdue**（暂无
  自动化）。

### Reports — Merchandise 报表

**用途。** 查看按状态统计的订单、已使用/已退还的 Gold、最受欢迎商品、按
校区的库存。

**谁可以访问。** 拥有 Merchandise 报表查看权限的人员。

**注意事项。** 导出 Excel：尚未实现，待确认。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 需要一间会议用的教室 | Find Available Rooms → 预约 → 等待审批 |
| 举办活动 | Find Available Rooms → Events → List |
| 安排补考时段 | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| 收到重复预约的报告 | Room Usage Calendar → Pending Approvals |
| 教室损坏、暂停使用 | Rooms（改为维护中状态） |
| 学生反映兑换订单还没审批 | Redemption Orders → 按订单号查询 |
| 需要给商城新增商品 | Merchandise → Store → 新增商品 + 按校区添加规格 |
