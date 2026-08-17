---
title: 页面流程图
description: 学务相关页面按实际操作顺序如何衔接。
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

本页说明工作人员在各界面之间的移动路径。当你知道要做什么但不知道从哪里开始时使用。

## 整体流程

```text
Curriculum Setup
  -> Course Delivery
  -> Attendance
  -> Grades & Performance
  -> 需要处理学籍或学费时进入 Students / Finance
```

## 准备学务数据

| 步骤 | 页面 | 预期结果 |
| --- | --- | --- |
| 1 | Academic Terms | 已有学期，可用于开班和确定 GPA |
| 2 | Programs | 已有培养方案，可挂接培养方案版本 |
| 3 | Curriculum Versions | 已有生效中的培养方案版本 |
| 4 | Units | 课程已有学分和先修课程信息 |
| 5 | Syllabus Templates | 已准备好后续开班要用的教学大纲 |

完成以上步骤后，进入 **Course Offering List** 开班。

## 运行课程

| 步骤 | 页面 | 预期结果 |
| --- | --- | --- |
| 1 | Course Offering List | 该学期的开班已建立 |
| 2 | Class Schedule | 各课次已安排日期、时间、教室 |
| 3 | Course Registration | 学生已登记到该开班 |
| 4 | Canvas Courses | 如需要，开班已与其在线课程关联 |
| 5 | Attendance Summary | 课程进行期间可跟踪考勤 |

如有学生不及格，进入 **Failed Students**，然后进入 **Retake Registration**。

## 风险管理

| 信号 | 检查页面 | 处理页面 |
| --- | --- | --- |
| 缺勤频繁 | Attendance Summary | Warning Center |
| 班级成绩不佳 | Course Statistics | Failed Students |
| GPA 低于阈值 | GPA History | Warning Center |
| 需要重修 | Failed Students | Retake Registration |
| 需要补考 | Thi lại | Lịch thi lại |

## 结算学期

| 步骤 | 页面 | 预期结果 |
| --- | --- | --- |
| 1 | Course Statistics | 已检查班级数据和成绩 |
| 2 | GPA Management | 该学期 GPA 已确定 |
| 3 | GPA History | 可以查询已确定的结果 |
| 4 | Warning Center | 已识别出需要处理的学生 |

全校范围的报表位于 **Reports & Audits** 菜单分组。

## 何时离开学务区域

| 从 | 前往 | 原因 |
| --- | --- | --- |
| Course Registration | Students | 登记前先核实学生档案 |
| Failed Students | Students | 查看某学生的成绩、考勤、GPA 详情 |
| Retake Registration | Finance | 处理重修学费 |
| Lịch thi lại | Campus | 预约考场并避免时间冲突 |
| Warning Center | Students | 记录对学生的处理决定 |

Canvas Courses 与 Faculty 现在都位于 **Academic Operations** 内（Faculty 是从旧的 Faculty & Teaching 分组并入的）——核对该班级的授课教师不再需要离开学务区域。

## 按问题选择起点

| 你的问题 | 打开 |
| --- | --- |
| “我需要为新学期开班” | Course Offering List |
| “这名学生登记选课了吗？” | Course Registration |
| “谁的缺勤过多？” | Attendance Summary |
| “哪些学生不及格？” | Failed Students |
| “我需要确定本学期 GPA” | GPA Management |
| “谁处于学业警示中？” | Warning Center |
| “我需要安排补考时段” | Lịch thi lại |
