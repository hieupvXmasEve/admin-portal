---
title: Forms & Surveys
description: 表单库、发放批次、问卷结果与工作人员咨询收件箱。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Forms/Admin/Index.vue
  - resources/js/pages/Forms/Runs/Index.vue
  - resources/js/pages/Forms/Admin/results/Index.vue
  - resources/js/pages/Forms/Queries/Inbox.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Forms & Surveys** 用于从学生处收集信息：教学质量问卷、报名表、各类申请。

需要区分三个概念：

| 概念 | 含义 |
| --- | --- |
| **Form**（表单） | 题目设计。创建一次，可多次使用。 |
| **Run**（发放批次） | 将该表单发放给某个群体、在某段时间内进行的一次投放。 |
| **Result**（结果） | 某次发放批次收集到的答复。 |

不要每学期都新建表单，而是基于已有表单创建 **新的发放批次**。

## Forms Library — 表单库

**用途。** 创建和管理表单。界面名称为 **Forms Management**。

**谁可以访问。** 拥有查看表单权限的人员。

**操作步骤**

1. 进入 **Forms & Surveys → Forms Library**。
2. 创建新表单，或打开已有表单进行编辑。
3. 点击 **Clear** 清除筛选条件。

**注意事项**

- 在发放批次进行中修改表单会导致答复不一致。如需更改题目，应创建新表单。
- 表单命名要足够清晰，让其他人一看就知道用途。

## Runs — 发放批次

### Runs List — 批次列表

**用途。** 查看正在进行和已结束的发放批次。界面名称为 **Form Runs**。

**操作步骤**

1. 进入 **Forms & Surveys → Runs → Runs List**。
2. 使用 **Filters** 面板筛选。
3. 点击 **Clear** 恢复查看全部。
4. 打开某个批次查看答复进度。

### Create Run — 创建批次

**用途。** 开启一个新的发放批次。

**操作步骤**

1. 进入 **Forms & Surveys → Runs → Create Run**。
2. 选择要发放的表单。
3. 选择接收群体和开放时间段。
4. 保存以启动该批次。

**注意事项**

- 保存前仔细核对接收群体。发错群体的通知无法撤回。
- 开放时间过短会导致答复率偏低。学期末问卷应在学生考完试之前就开放。

## Surveys — 问卷结果

### Survey Results

**用途。** 查看各发放批次收集到的答复。

**谁可以访问。** 拥有查看问卷汇总结果权限的人员。

**操作步骤。** 进入 **Forms & Surveys → Surveys → Survey Results**，选择要查看的批次。

**注意事项。** 教学问卷结果属于敏感数据，仅在被允许的范围内分享。

### Program Stats

**用途。** 按培养方案汇总的数据，而非按单个发放批次统计。

**接下来。** Lecturer GPA、Course Ranking。

## Staff Inbox — 咨询收件箱

**用途。** 接收并处理学生通过表单提交的请求和问题。

**谁可以访问。** 拥有审核表单权限的人员。

**操作步骤**

1. 进入 **Forms & Surveys → Staff Inbox**。
2. 打开每条请求，阅读内容。
3. 回复，或转交给负责部门。

**注意事项。** 这是共用收件箱，不是个人收件箱。处理完毕后应做标记，避免同事重复处理。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 学期末教学质量问卷 | Forms Library（选择表单） → Create Run → Survey Results |
| 查看进行中批次的答复率 | Runs List → 打开该批次 |
| 按专业汇总反馈 | Program Stats |
| 处理学生提交的咨询 | Staff Inbox |
