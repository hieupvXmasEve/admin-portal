---
title: Academic Operations
description: 学务运营区域概览——页面分组、使用对象、从哪里开始。
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Academic Operations** 是开展学务工作的区域：搭建培养方案框架、开班、管理选课登记、跟踪考勤、确定 GPA、识别有学业风险的学生。

四个页面分组按学期推进顺序排列：

1. **Curriculum Setup** — 框架：学期、培养方案、课程。
2. **Course Delivery** — 开班、排课、选课登记、重修、补考。
3. **Attendance & Completion** — 考勤跟踪与不及格学生筛查。
4. **Grades & Performance** — 成绩确定、GPA、学业警示。

顺序错乱会卡住流程：没有学期就无法开班，没有开班就无法登记选课学生。

## 使用对象

| 角色 | 用途 |
| --- | --- |
| 教务处 | 学期、培养方案、课程、教学大纲模板 |
| 学务人员 | 开班、跟踪选课、考勤、不及格学生、重修 |
| 系主任、教务主任 | 确定 GPA、审阅警示、核对学期结果 |
| 支持部门 | 学生咨询时快速查询开班或成绩状态 |

## 从哪里开始

| 分组 | 在以下情况从此开始 |
| --- | --- |
| [Curriculum Setup](/zh/academic-operations/curriculum-setup/) | 新学期前准备基础数据，或更新培养方案 |
| [Course Delivery](/zh/academic-operations/course-delivery/) | 开班、排课、登记选课学生、处理重修和补考 |
| [Attendance & Completion](/zh/academic-operations/attendance-completion/) | 检查考勤、不及格学生、班级统计 |
| [Grades & Performance](/zh/academic-operations/grades-performance/) | 确定 GPA、查询历史成绩、审阅警示 |

不熟悉系统？先阅读 [页面流程图](/zh/academic-operations/flow-map/) 了解各页面如何衔接。

## 本区域全部页面

| 分组 | 页面 | 用途 |
| --- | --- | --- |
| Curriculum Setup | Academic Terms | 登记学期及其选课时间段 |
| Curriculum Setup | Programs | 登记培养方案与专业 |
| Curriculum Setup | Curriculum Versions | 按招生批次应用的培养方案版本 |
| Curriculum Setup | Units | 课程目录、先修课程、等效课程 |
| Curriculum Setup | Modules | 将课程归组为知识模块 |
| Curriculum Setup | Syllabus Templates | 可跨学期重复使用的教学大纲模板 |
| Course Delivery | Course Offering List | 为某个学期开班 |
| Course Delivery | Class Schedule | 为每次上课安排日期、时间、教室 |
| Course Delivery | Course Registration | 记录学生所选的开班 |
| Course Delivery | Retake Registration | 为不及格课程重新登记重修 |
| Course Delivery | Thi lại（补考） | 建立并跟踪补考名单 |
| Course Delivery | Lịch thi lại（补考安排） | 考试时段、考场、监考安排 |
| Course Delivery | Canvas Courses | 将开班与其在线课程关联 |
| Attendance & Completion | Course Statistics | 本学期各课程的进展情况 |
| Attendance & Completion | Attendance Summary | 按学生、按课次查询考勤记录 |
| Attendance & Completion | Failed Students | 不及格学生及其原因 |
| Grades & Performance | GPA Management | 确定某学期的 GPA |
| Grades & Performance | GPA History | 查询以往学期已确定的 GPA |
| Grades & Performance | Warning Center | 处于学业或考勤警示中的学生 |

全校范围的报表——Performance Dashboard、Academic Report、Course Ranking——位于独立的 **Reports & Audits** 菜单分组中。

## 常见流程

| 流程 | 页面顺序 |
| --- | --- |
| 准备新学期 | Academic Terms → Programs → Curriculum Versions → Units → Syllabus Templates |
| 开班并运行课程 | Course Offering List → Class Schedule → Course Registration → Canvas Courses |
| 跟踪学业风险 | Attendance Summary → Failed Students → Warning Center → Retake Registration |
| 组织补考 | Thi lại → Lịch thi lại |
| 结算学期 | Course Statistics → GPA Management → GPA History → Warning Center |
