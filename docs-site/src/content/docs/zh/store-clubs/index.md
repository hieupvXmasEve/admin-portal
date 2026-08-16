---
title: Store & Clubs
description: 学生社团与 Gold 兑换商城。
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

**Store & Clubs** 管理学生社团和 Gold 兑换商城 —— 两个仅限 HQ 使用的界面，从 **Campus** 的教室/活动中拆分而来。

## Clubs — 社团

**用途。** 管理学生社团及其成员。

**谁可以访问。** 拥有查看社团权限的人员。

**操作步骤。** 进入 **Store & Clubs → Clubs**。

## Merchandise — Gold 兑换商城

学生用 **Gold**（奖励积分）兑换商品是在学生门户完成的，不在这个界面。这里的
**Merchandise** 区域是 **staff** 管理商品和处理兑换订单的地方。

### Store — 商品列表

**用途。** 登记可兑换商品：名称、描述、Gold 价格、图片、按校区区分的规格
（颜色/尺码）以及库存。

**谁可以访问。** 拥有商品查看/新增/编辑权限的人员。

**操作步骤**

1. 进入 **Store & Clubs → Merchandise → Store**。
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

1. 进入 **Store & Clubs → Merchandise → Redemption Orders**。
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
| 学生反映兑换订单还没审批 | Redemption Orders → 按订单号查询 |
| 需要给商城新增商品 | Merchandise → Store → 新增商品 + 按校区添加规格 |
