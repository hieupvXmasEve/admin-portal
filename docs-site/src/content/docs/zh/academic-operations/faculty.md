---
title: Faculty
description: 教师档案、授课工时，以及所授班级的成绩表现。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Lectures/Index.vue
  - resources/js/pages/Lectures/TeachingHours.vue
  - resources/js/pages/Lectures/LecturerGpa.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Academic Operations** 下的 **Faculty** 管理教学队伍：谁在授课、授课多少小时、所负责班级的成绩表现如何。

## Lecturer List — 教师名单

**用途。** 查询和管理教师档案。

**谁可以访问。** 拥有查看教师权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Faculty → Lecturer List**。界面名称为 **Lecturers**。
2. 按学期（**All Semesters**）、培养方案（**All Programs**）、状态（**All Statuses**）或聘用类型（**All Types**）筛选。
3. 点击某行打开教师档案。

**注意事项**

- 聘用类型（专职、兼职）会影响下一个界面中授课工时的计算方式。
- 教师必须先出现在此名单中，才能被分配到某个开班。

**接下来。** Lecturer Hours、Course Offering List。

## Lecturer Hours — 授课工时

**用途。** 统计每位教师在某学期或某时间段的授课工时。界面名称为 **Lecturer Teaching Hours Report**。

**谁可以访问。** 拥有查看教师权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Faculty → Lecturer Hours**。
2. 在 **Select semester** 选择学期，或在 **From date** 和 **To date** 输入时间范围。
3. 查看结果表，打开某位教师可查看按班级分列的明细。

**注意事项**

- 工时按已排定的课次计算。未出现在 **Class Schedule** 中的课次不计入统计。
- 该数据通常用于计算酬金。确认前请与实际授课记录核对。
- 工时确认后再更改课次时间，会使该学期的数据出现偏差。

**接下来。** Class Schedule。

## Lecturer GPA — 教师授课班级成绩

**用途。** 查看每位教师所授各班级的平均成绩。

**谁可以访问。** 同时拥有查看教师权限 **和** 查看问卷汇总结果权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Faculty → Lecturer GPA**。
2. 在 **Select semester** 选择学期。
3. 按教师查看结果。

**注意事项**

- 该数据只有在该学期 GPA 已确定后才有意义。
- 班级成绩偏低并不必然说明教学质量差：课程难度、生源水平、班级规模都会产生影响。下结论前应结合 **Course Ranking** 和 **Course Statistics** 一同查看。
- 这是涉及个人的敏感数据。仅在被允许的范围内使用。

**接下来。** Course Ranking、Survey Results。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 学期末计算授课酬金 | Class Schedule → Lecturer Hours |
| 准备新学期授课分配 | Lecturer List → Course Offering List |
| 评估教学质量 | Lecturer GPA → Course Ranking → Survey Results |
