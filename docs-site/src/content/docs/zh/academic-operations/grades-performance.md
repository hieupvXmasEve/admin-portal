---
title: Grades & Performance
description: 确定 GPA、查询 GPA 历史，以及跟踪处于学业警示中的学生。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Academic/Gpa/Index.vue
  - resources/js/pages/Admin/Academic/Gpa/History.vue
  - resources/js/pages/Academic/Warnings/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Grades & Performance** 是结算结果的阶段：计算 GPA、查询历史成绩、识别有学业风险的学生。

全校范围的报表——Performance Dashboard、Academic Report、Course Ranking——位于独立的 **Reports & Audits** 菜单分组，不在此处。

## GPA Management — GPA 管理

**用途。** 确定学生某学期的平均绩点。

**谁可以访问。** 拥有查看成绩权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Grades & Performance → GPA Management**。
2. 在 **Semester Finalization**（学期结算）面板中选择要结算的学期。
3. 核对数据后确认结算。

**注意事项**

- 确定 GPA 是关键节点：奖学金评审、学业警示、毕业审核均依据此结果。
- 在成绩尚未全部录入完成前就结算，会得出错误的 GPA。确认结算前请与录入成绩的部门核实是否已完成。

**接下来。** GPA History、Warning Center。

## GPA History — GPA 历史

**用途。** 查询以往学期已确定的 GPA。

**谁可以访问。** 拥有查看成绩权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Grades & Performance → GPA History**。
2. 在 **Search student...** 中查找学生。
3. 进一步按 **Semester**（学期）、**Program**（培养方案）或 **Academic Standing**（学业状态）筛选。

**注意事项。** 在回复成绩异议或核实以往学期成绩时使用此界面。

**接下来。** Warning Center、学生档案。

## Warning Center — 警示中心

**用途。** 查看当前处于警示中的学生，分为两类：

- **Academic Standing Warnings** — 因学业成绩触发的警示。
- **Attendance Warnings** — 因缺勤触发的警示。

**谁可以访问。** 同时拥有考勤查看权限和成绩查看权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Grades & Performance → Warning Center**。
2. 查看两个面板，核对需要处理的学生名单。
3. 转到学生档案记录处理情况。

**注意事项**

- 警示随成绩和考勤数据更新，每次确定 GPA 后都应重新查看。
- 应定期检查，不要等到学期末才查看。

**接下来。** Attendance Summary、Student Services。

## 确定 GPA 后的核查流程

```text
GPA Management
  -> GPA History
  -> Warning Center
```

如发现数据异常：

```text
GPA History
  -> 该学生的学务档案
  -> Course Statistics
```
