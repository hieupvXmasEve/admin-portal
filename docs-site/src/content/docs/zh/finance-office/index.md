---
title: Finance
description: 生成收费、收款与对账、处理异常情况，以及奖学金和学费优惠。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Finance/Cockpit/Index.vue
  - resources/js/components/finance/cockpit/ActionPanel.vue
  - app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php
  - resources/js/pages/Finance/Reporting/Index.vue
  - resources/js/pages/Finance/Revenue/Index.vue
  - resources/js/pages/Finance/BatchStudio/Hub.vue
  - resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
  - resources/js/pages/Finance/BatchStudio/DngPush.vue
  - resources/js/pages/Finance/PricingOperations/Index.vue
  - resources/js/pages/Finance/Payments/Index.vue
  - resources/js/pages/Finance/Operations/DueCalendar.vue
  - resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue
  - resources/js/pages/Finance/Invoices/Index.vue
  - resources/js/pages/TuitionPlans/Index.vue
  - resources/js/pages/Scholarships/Index.vue
  - resources/js/pages/StudentScholarships/Index.vue
  - resources/js/pages/Vouchers/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Finance** 涵盖收费项的生命周期，以及其中的 **Discounts & Funding** 子分组（学费套餐、奖学金、优惠券）。

一笔收费项按以下顺序推进：

```text
生成  ->  收款与对账  ->  异常处理（出现不一致时）  ->  查询与审计
```

生成阶段出错，后续所有阶段都会跟着出错。这是涉及真实金钱的区域，**批量操作前务必先检查**。

## Hôm nay — 今日

**用途。** 每天打开的第一个界面：今天还有什么待办事项。

**谁可以访问。** 拥有查看财务看板权限的人员。每种待办只在该人员拥有对应来源
权限时显示——没有 webhook 权限就看不到 DNG webhook 证据。

**界面内容**

- **Tổng phải thu** — 应收总额。
- **Đã thu** — 已收金额。
- **SV chưa sinh phí** — 尚未生成任何收费项的学生；这通常是最紧急要处理的事项。
- **Cần xử lý** — 待办。点开一类先看前几行，再进入已有处理页。
  - **Webhook DNG lỗi** — 失败的 DNG webhook
  - **Settlement cần kiểm tra** — 需核对的结算
  - **Tiền chờ phân bổ** — 待分配款项
  - **DNG đến hạn** — 今日到期或逾期
  - **Ngoại lệ lifecycle chờ review** — 学籍变动例外
  - **Sai sót sinh phí** — 收费生成错误
  - **Installment đẩy thất bại** — 分期推送失败
  - **Huỷ đang chờ** — 待审核取消
  - **Số dư cần quyết** — 待决定余额

**操作步骤。** 进入 **Finance → Hôm nay**。点击 **Làm mới**（刷新）获取最新数据。

**注意事项。** 学期进行到一半时 **SV chưa sinh phí** 不为零，意味着有学生在读却未被收费。应尽早处理，拖得越久越难追收。待决定余额仅针对已离校仍有余款的学生——在读期间不办理退款。

## Finance Reporting — 财务报表

**用途。** 汇总收支情况的报表，用于上报。

**谁可以访问。** 拥有查看财务报表权限的人员。

**操作步骤。** 进入 **Finance → Finance Reporting**，选择学期或时间范围，查看并导出报表。

## Doanh thu — 营收

**用途。** 全校营收报表，跨多个学期汇总，按校区和费用类型划分，与 Collection Progress 使用同一数据源计算，因此两份报表的数字不会出现出入。

**谁可以访问。** 拥有查看全校营收报表权限的人员（与按校区查看的 Finance Reporting 权限不同）。

**操作步骤。** 进入 **Finance → Doanh thu**，查看按学期的表格和按校区/费用类型的明细。**未分配金额** 一行单独列出，不计入总额。

## Sinh phí — 生成收费项

该分组负责创建应收款项，是整个区域中风险最高的环节。

### Sinh phí & lệnh thu

**用途。** 为一批学生批量生成收费项，或批量创建 DNG 付款请求。发送催缴不在这里 — 请使用 **DNG Due Reminders**。

**谁可以访问。** 负责生成收费项或创建收款请求的工作人员。

**操作步骤**

1. 进入 **Finance → Sinh phí → Sinh phí & lệnh thu**。
2. 选择 **1. Sinh phí hàng loạt** 或 **2. Lập yêu cầu thanh toán DNG**。
3. 选择学期和费用类型 — 列表会显示出来。
4. 核对数量；只有点击 **Tạo lệnh thu** 时才填写到期日和说明。
5. 生成 HP/EGC 收费后：**Xem / lập lệnh thu kỳ này** 打开收款请求页（在该页重新选择学期和费用类型）。

**“金额”列的含义。** 对于 HP（学费）类收费，如果学生有奖学金，金额单元格会显示：

- 原始学费（划线）和折扣后实际应收金额（加粗）。
- 🎓 奖学金行——名称、原始比例/金额，以及按原始比例计算的折扣。
- 📉 "奖学金被减免"行（仅当存在针对所选学期生效中的调整决定时才显示）——减免后的比例，以及相比原始比例少扣了多少。

学生明明有奖学金却看不到奖学金行 → 检查第 3 步选择的学期是否与调整决定所针对的学期一致。

**注意事项**

- 运行前务必先看列表。批量生成错误后必须逐笔取消，非常费时费力。
- 先在小范围群体上测试，再对整个批次运行。
- 检查学生是否已有奖学金或优惠券，避免生成金额错误的收费项。到期催缴请到 **Thu & Đối soát → DNG Due Reminders**。

### Pricing Operations — 定价规则

**用途。** 登记和调整学费定价规则。

**操作步骤**

1. 进入 **Finance → Sinh phí → Pricing Operations**。
2. 在 **Rule versions**（规则版本）面板查看现有列表。
3. 点击 **Create pricing rule version** 打开创建面板，填写信息后点击 **Create version**。

**注意事项。** 定价规则按版本管理。应创建新版本而非修改生效中的版本，以免影响已生成的收费项。

### EGC · Kết quả & học lại、EGC - Carry Forward

**用途。** 处理因学业结果产生的学费：重修费用，以及结转到下一学期的金额。

**注意事项。** 只应在成绩确定后运行。提前运行会为尚无最终成绩的学生错误计费。

## Thu & Đối soát — 收款与对账

该分组负责记录收到的款项并与应收款项匹配。

| 页面 | 用途 |
| --- | --- |
| DNG Due Reminders | 缴费到期提醒。含 **Phát thông báo học phí**，向学生和家长发送学费通知 |
| DNG Campus Mapping | 登记各校区对应的收款账户 |
| Lập yêu cầu thanh toán DNG | 创建发送至 DNG 网关的付款请求 |
| Settlement Worklist | 需要人工核对匹配的款项列表 |
| Payments | 已收款项列表 |

### Payments — 收款记录

**界面内容。** 三张卡片：**Tổng đã đóng**（已缴总额）、**Đã thanh toán**（已结清）、**Còn dư**（余额）。

**注意事项。** **Còn dư** 列不为零，意味着学生多缴，或该款项尚未完全分配。请在 **Settlement Worklist** 中处理。

### Settlement Worklist — 结算工作台

**用途。** 将已收到但系统无法自动匹配的款项，人工分配到正确的应收款项上。

**谁可以访问。** 拥有分配款项权限的人员。

**注意事项。** 这是直接涉及学生金钱的操作。分配前请核对凭证，如有疑虑请勿分配。

## Ngoại lệ — 异常

用于集中存放无法自动处理的不一致情况。

| 页面 | 用途 |
| --- | --- |
| Exceptions Queue | 待处理的异常队列 |
| Lifecycle Exceptions | 因学生状态变化产生的异常 |
| Lifecycle History | 已处理异常的历史记录 |

**注意事项。** 异常长期积压会导致学期末报表出现错误。应按周清理，不要积压。

## Tra cứu & Audit — 查询与审计

只读界面，用于查找和核对。

| 页面 | 用途 |
| --- | --- |
| Audit Workspace | 调查单个案例时使用的综合查询区域 |
| Charge Ledger (Global) | 全部已生成收费项的总账 |
| Invoices | 发票列表，显示于 **Invoice List** |
| DNG Payment Requests | 已发送至 DNG 网关的付款请求 |
| DNG · Cần kiểm tra | 显示异常迹象的网关回执 |
| DNG Webhook Events | 从网关收到的信号日志 |

### DNG Payment Requests

**界面内容。** 状态卡片：**Total**、**Pending**（待处理）、**Pushed to DNG**（已推送）、**Paid Uninvoiced**（已付款，未开票）、**Paid Invoiced**（已付款，已开票）、**Failed / Bridged**（失败或需桥接处理）。有按 **学期** 的筛选器，**Ref (Webhook)** 列显示 DNG 返回的参考号，以及 **Export Excel** 按钮，可导出当前筛选后的列表。

**注意事项。** **Failed / Bridged** 和 **DNG · Cần kiểm tra** 是两个需要每天查看的地方。那是钱已到账但尚未正确记录的所在。

### Invoices

**界面内容。** 有按 **学期** 的筛选器（默认是操作人员当前所选的学期）。Excel 导出功能已迁移到 **DNG Payment Requests** 界面，此处不再提供。

## Discounts & Funding — 减免与资助

**Finance** 内部的子分组，决定学生实际需要支付的金额。**Scholarship Adjustments**（因不及格产生的奖学金调整）不在此处——它属于 Progression Action，请在 **Academic Operations → Grades & Performance** 查看。

### Tuition Plans — 学费套餐

**操作步骤**

1. 进入 **Finance → Discounts & Funding → Tuition Plans**。
2. 点击 **Create Tuition Plan** 创建新套餐。
3. 使用 **Filters** 面板查找已有套餐。

### Scholarships — 奖学金

**操作步骤。** 进入 **Finance → Discounts & Funding → Scholarships**，使用 **Filter Scholarships** 面板查找、创建或编辑奖学金类型。

### Student Scholarships — 学生奖学金分配

**用途。** 为具体学生分配奖学金。界面名称为 **Student Scholarship Assignments**。

**谁可以访问。** 拥有分配奖学金权限的人员。

**操作步骤。** 进入 **Finance → Discounts & Funding → Student Scholarships**，使用 **Filter Assignments** 面板查找，然后分配或取消。

**注意事项。** 应在生成收费项 **之前** 分配奖学金。事后分配的话，已生成的收费项不会自动减少，必须手动调整。

### Vouchers — 优惠券

**操作步骤。** 进入 **Finance → Discounts & Funding → Vouchers**，使用 **Filter Vouchers** 面板管理优惠码。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 学期初，为整个批次收费 | Student Scholarships → Pricing Operations → Sinh phí & lệnh thu → Hôm nay |
| 学生反馈已缴费但系统未记录 | DNG · Cần kiểm tra → DNG Webhook Events → Settlement Worklist |
| 学生多缴 | Payments（Còn dư 列） → Settlement Worklist |
| 每周清理 | Exceptions Queue → Lifecycle Exceptions |
| 学期末结算数字 | 异常（全部清理） → Charge Ledger → Finance Reporting |
| 学生重修产生学费 | Retake Registration → EGC · Kết quả & học lại |
