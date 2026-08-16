---
title: Attendance
description: 考勤跟踪与不及格学生名单。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Attendance/Index.vue
  - resources/js/pages/FailedStudents/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Attendance & Completion" renamed to "Attendance"; Course Statistics moved to Course Delivery, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Attendance** 用于检查学生出勤情况，以及需要跟进处理的情况。各课程统计（**Course Statistics**）已移至 **Course Delivery** 分组。

## Attendance Summary — 考勤汇总

**用途。** 按学生、按课次查询考勤记录。

**谁可以访问。** 拥有查看考勤权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Attendance → Attendance Summary**。
2. 在 **Search students or sessions...** 输入以查找学生或课次。
3. 进一步按状态（**All Statuses**）或考勤方式（**All Methods**）筛选。

**注意事项**

- 该界面用于查询和核对。日常考勤由教师在各自的门户中记录。
- 学生对缺勤记录有异议时，先在此核实再做决定。

**接下来。** Warning Center、Failed Students。

## Failed Students — 不及格学生

**用途。** 筛选出未通过课程的学生，并了解不及格原因。

**谁可以访问。** 拥有查看考勤权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Attendance → Failed Students**。
2. 使用 **Filters** 按学期、培养方案或课程筛选。
3. 核对名单后转到 **Retake Registration** 开启重修。
4. 点击 **Clear Filters** 恢复查看完整名单。

**界面内容**

- 顶部四张卡片：**Total Failed Students**（不及格学生数）、**Total Failed Courses**（不及格课程门次数）、**Avg Attendance**（平均出勤率）、**Retake Eligible**（符合重修条件人数）。
- **Fail Reason Distribution** — 按不及格原因的占比分布。
- **Top 10 Failed Units** — 不及格人数最多的十门课程。

**注意事项**

- 只有属于 **Retake Eligible** 的学生才应开启重修登记。其余情况须按规定单独处理。
- **Top 10 Failed Units** 是排查教学质量的线索，建议在每学期末查看。

**接下来。** Retake Registration、Finance Office。

## 常见问题

| 问题 | 应使用的页面 |
| --- | --- |
| 这个班有多少学生完成课程？ | Course Statistics（**Course Delivery** 分组） |
| 谁的缺勤过多？ | Attendance Summary |
| 哪些学生需要重修？ | Failed Students |
| 是否需要发出警示？ | Warning Center |

## 操作提示

- **Attendance Summary** 适合在课程进行期间及早跟踪。
- **Failed Students** 适合在学期末成绩已出的阶段使用。
- 查看某一门具体课程情况时，请使用 **Course Delivery** 分组下的 **Course Statistics**。
