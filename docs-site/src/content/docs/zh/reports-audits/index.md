---
title: Reports & Audits
description: 全校范围的报表与学务数据核对。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Reports/StudentActionsAudit.vue
  - resources/js/pages/Admin/Reports/StudentDecisions/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDocuments.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue
  - resources/js/pages/Admin/Academic/Performance/Dashboard.vue
  - resources/js/pages/Academic/CourseRanking/Index.vue
  - resources/js/pages/Academic/Report/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue
  - resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/StudentStatusTable.vue
  - resources/js/pages/Academic/Report/StudentUnits/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Reports & Audits** 面向管理层：查看全校数据，以及发现数据缺失之处。

与逐一处理学生的 **Students** 不同。这里的每一行报表都 **单向** 链接到对应的学生档案；从档案无法反向回到报表。

分为两组：**Lifecycle & Decisions**（学籍与处分决定）和 **Academic Performance**（学业表现）。

## Lifecycle & Decisions

### Student Actions Audit — 学生操作审计日志

**用途。** 审查所有针对学生档案执行过的操作：谁做的、做了什么、什么时候做的。

**谁可以访问。** 拥有查看学生操作审计权限的人员。

**操作步骤**

1. 进入 **Reports & Audits → Lifecycle & Decisions → Student Actions Audit**。
2. 查看 **Action Logs** 表。
3. 按日期、操作类型或操作人筛选。
4. 点击 **Reset all** 清除全部筛选条件。

**注意事项。** 该界面用于回答“谁改了这个”。用于处理争议或内部核查。

### Lifecycle Yearly Analysis — 按年度的学籍分析

**用途。** 逐年跟踪每个招生批次：多少人继续在读、休学、退学、毕业。点击表中任意数字可查看该数字背后的学生名单。

**谁可以访问。** 拥有查看学生操作审计权限的人员。

**界面内容**

- **按批次和学期分列的学生数** — 每个招生批次一个矩阵表：行是状态（在读、休学、退学、毕业……），列是学期。任何大于零的单元格都可点击；被选中的单元格会高亮显示。
- **按学期的变动情况** — 各学期新入学人数以及状态变动人数。
- **各批次当前状态** — 每个批次的人数、NE（未确定）人数，以及截至当前学期的休学 / 退学 / 毕业比率。
- **按学期的学生名单** — 详细表格，可按学期和状态筛选；与矩阵表中刚点击的单元格保持同步。

**操作步骤**

1. 进入 **Reports & Audits → Lifecycle & Decisions → Lifecycle Yearly Analysis**。
2. 点击矩阵表中的某个数字单元格（例如：批次 X 在学期 Y 的休学人数）。**Danh sách sinh viên**（学生名单）对话框会打开对应的名单。
3. 如果不需要按批次筛选，也可以在下方 **Danh sách sinh viên theo kỳ**（按学期的学生名单）面板中手动选择 **学期** 和 **状态**。
4. 点击 **Bỏ lọc khoá**（清除批次筛选）可查看该学期全部学生，不受所选批次限制。
5. 点击 **Xuất Excel**（导出 Excel）下载当前查看的名单（已应用学期 / 状态 / 批次筛选）。

**注意事项。** 矩阵中某批次的列只从该批次实际入学的学期开始计数；更早的学期显示 `-`，而不是 0。

### Academic Progression — 学业进度核对

**用途。** 检查学生是否被正确归入相应的学习阶段。

**谁可以访问。** 拥有查看学生操作审计权限的人员。

**界面内容。** 汇总卡片：**Pre-Uni GC Placements**（预科分班）、**Intake Course Placements**（入学分班）、**Stage Changes**（阶段变更）、**IELTS Recorded**（已记录 IELTS 成绩）。

**注意事项。** 这里数据与实际不符，通常意味着档案尚未更新，而不是学生被分错阶段。下结论前先检查下面两个界面。

### Missing Documents — 材料缺失

**用途。** 筛选出档案中仍缺少 IELTS 证书的学生。界面全名为 **Missing IELTS Documents**。

**操作步骤**

1. 进入 **Reports & Audits → Lifecycle & Decisions → Missing Documents**。
2. 查看 **Students with Missing Documents** 表。
3. 联系学生补交，或者如果材料已提交但未录入，则补录。

**注意事项。** 该名单应在每次进度评审前清零。积压未处理会卡住阶段转换流程。

### Missing Decisions — 处分决定缺失

**用途。** 筛选出尚未附带处分决定的阶段转换记录。

**操作步骤**

1. 进入 **Reports & Audits → Lifecycle & Decisions → Missing Decisions**。
2. 查看 **Transitions Missing a Decision** 表。
3. 为每条记录补充相应的处分决定。

**注意事项。** 处分决定缺失是正式档案上的漏洞，而不只是数据缺失。应优先处理。

### Student Decisions — 学生处分决定

**用途。** 查询已对学生下达的全部处分决定。

**操作步骤**

1. 进入 **Reports & Audits → Lifecycle & Decisions → Student Decisions**。
2. 使用 **Filter** 面板缩小范围。
3. 查看 **Decision List** 表，打开某条决定查看详情。

## Academic Performance

### Performance Dashboard — 表现看板

**用途。** 全校学业成绩总览。界面名称为 **Academic Performance**。

**谁可以访问。** 拥有查看成绩权限的人员。

**界面内容。** 四张主要卡片：**Avg Semester GPA**（学期平均 GPA）、**Avg Cumulative GPA**（累计平均 GPA）、**At-Risk Students**（有风险学生）、**Completion Rate**（完成率）。

**注意事项。** 只有在 GPA 已确定后数据才准确。提前查看会得到未完成状态的结果。

### Course Ranking — 课程排名

**用途。** 比较各课程之间的成绩差异，找出成绩异常高或异常低的课程。

**谁可以访问。** 拥有查看成绩权限的人员。

**注意事项。** 在学期末排查教学质量时，可与 Failed Students 界面的 **Top 10 Failed Units** 一起查看。

### Academic Report — 学务报告

**用途。** 供内部资料使用的表格和图表形式报告。

**界面内容。** **Grade Distribution**（成绩分布）与 **Grade Statistics**（成绩统计）。

**操作步骤**

1. 进入 **Reports & Audits → Academic Performance → Academic Report**。
2. 按学期、培养方案或课程筛选。
3. 需要重新查看全部数据时点击 **Clear Filters**。

### Student Completed Units — 已完成课程

**用途。** 查看所有已通过课程的学生，按 **GC**（通识课程）和 **Major**（专业课程）分组展示，并附上课程门数与已累积学分。

**谁可以访问。** 拥有查看学务报告权限的人员。

**操作步骤**

1. 进入 **Reports & Audits → Academic Performance → Student Completed Units**。
2. 按 **Student ID / Name** 搜索，或按 **Program**（培养方案）筛选。
3. 查看 **GC Units** 和 **Major Units** 列——每门课程以课程代码标签展示；`—` 表示该分组暂无课程。
4. 点击 **Export Excel** 下载当前查看的名单（已应用当前筛选条件）。

**注意事项。** 该名单只统计 **已通过**（passed）的课程；正在修读或不及格的课程不会出现在此处。

## 常见情况

| 情况 | 应使用的页面 |
| --- | --- |
| “谁修改了这名学生的档案？” | Student Actions Audit |
| 准备学期总结会议 | Performance Dashboard → Academic Report → Course Ranking |
| 进度评审前清理档案 | Missing Documents → Missing Decisions → Academic Progression |
| 按批次统计流失率 | Lifecycle Yearly Analysis |
| 查看学生已通过的课程属于 GC 还是 Major | Student Completed Units |
