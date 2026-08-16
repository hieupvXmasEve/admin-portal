---
title: Students
description: 学生档案、入学登记、学籍冻结与入学申请。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Students/Index.vue
  - resources/js/pages/Students/enrollments/Index.vue
  - resources/js/pages/StudentApplications/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Student Services" renamed to "Students", plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Students** 是处理 **每一名具体学生** 的地方：档案、入学登记状态，以及入学申请。

与 **Reports & Audits** 区分开来：那边是全校范围的汇总数据，这边是具体的个人。

## Students — 学生名单

**用途。** 查询、添加、编辑学生档案。这是进入学生详细档案的入口。

**谁可以访问。** 拥有查看学生权限的人员。

**操作步骤**

1. 进入 **Students → Students**。界面名称为 **Students Management**。
2. 使用搜索框或筛选器查找学生。
3. 点击某行打开详细档案。
4. 点击 **Đặt lại**（重置）清除全部筛选条件。

**导出名单**

1. 在 **Select format** 选择格式。
2. 在 **Select scope** 选择范围——仅当前筛选的行，或全部。
3. 确认后下载文件。

**注意事项**

- 学生档案是查询一切信息的交叉入口：成绩、考勤、GPA、学费、处分决定。
- 名单按当前所选校区筛选。找不到学生时检查 `Campus:` 一行。
- 导出范围默认跟随当前筛选条件。导出人数偏少通常是因为忘记清除筛选器。

**接下来。** Course Registration、Warning Center、Finance Office。

## Enrollments & Holds — 入学登记与学籍冻结

**用途。** 按学期管理学生的登记状态，以及学籍被冻结的情况。

**谁可以访问。** 拥有查看学生权限的人员。

**操作步骤**

1. 进入 **Students → Enrollments & Holds**。界面名称为 **Student Enrollments & Holds Management**。
2. 查看 **Student Enrollments** 表了解谁在哪个学期登记。
3. 筛选出需要处理的群体。
4. 打开某行查看或修改状态。

**注意事项**

- 学籍被冻结的学生通常无法选课。收到“无法选课”的反馈时，先检查此界面，再检查选课开放时间。
- 冻结常见原因是学费欠费。解除前请与 **Finance Office** 区域核对。
- 冻结提醒也会显示在总览界面（Dashboard）上。

**接下来。** Finance Office、Course Registration。

## Student Applications — 入学申请

**用途。** 处理入学申请：查看材料、批准或拒绝。

**谁可以访问。** 拥有查看入学申请权限的人员。

**操作步骤**

1. 进入 **Students → Student Applications**。
2. 使用 **Search name, email, code…** 查找申请，或按状态（**Status**）和招生批次（**Intake**）筛选。
3. 点击申请中的文件名打开附带材料。
4. 批准或拒绝申请。

**拒绝时**

1. 在 **Explain the reason for rejection** 中填写理由。
2. 点击 **Confirm rejection** 确认。

**注意事项**

- 拒绝理由会被保存，且可能发送给申请人。写清楚，让对方明白需要补充什么。
- 批准前请仔细核对附带材料。一旦批准，申请就进入后续入学流程。
- 按 **Intake** 筛选，避免不同批次的申请混在一起。

**接下来。** Students。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 学生反馈无法选课 | Enrollments & Holds → Finance Office → Academic Terms |
| 需要查看某学生的完整情况 | Students → 打开详细档案 |
| 审批新一批招生申请 | Student Applications（按 Intake 筛选） |
| 导出学生名单用于报表 | Students → 选择格式和范围 |
