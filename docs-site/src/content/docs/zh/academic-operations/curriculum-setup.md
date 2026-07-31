---
title: Curriculum Setup
description: 搭建学务基础框架——学期、培养方案、培养方案版本、课程、模块、教学大纲模板。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Semesters/Index.vue
  - resources/js/pages/Programs/Index.vue
  - resources/js/pages/CurriculumVersions/Index.vue
  - resources/js/pages/Units/Index.vue
  - resources/js/pages/Admin/Modules/Index.vue
  - resources/js/pages/Syllabus/TemplatesIndex.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Curriculum Setup** 用于准备基础数据。**做一次，跨多个学期重复使用**——不是日常工作。

如果这里的数据错误或缺失，开班、选课登记、GPA 计算、学务报表都会受影响。

## Academic Terms — 学期

**用途。** 登记学期：学期代码、开始与结束日期、选课时间段。其他所有活动都挂接在某个学期上。

**谁可以访问。** 拥有查看学期权限的人员——通常是教务处。

**创建新学期的步骤**

1. 进入 **Academic Operations → Curriculum Setup → Academic Terms**。
2. 点击 **Add New Semester**（添加学期）。
3. 填写学期代码、名称、开始日期、结束日期，以及选课开放和关闭时间。
4. 保存。

**登记校区专属日程的步骤**

1. 打开刚创建的学期。
2. 在 **Campus schedules**（按校区排期）部分，点击 **Add campus schedule**。
3. 选择校区并输入该校区的专属时间节点。

**注意事项**

- 学期是全校共用的；各校区日程可能不同，需在 **Campus schedules** 中分别登记。
- 选课开放时间决定学生能否成功选课。这个时间点设错，是“学生无法选课”问题最常见的原因。
- 修改正在进行中的学期日期会连锁影响选课和学费。修改前请三思。

**接下来。** Course Offering List、GPA Management。

## Programs — 培养方案

**用途。** 登记学校正在开设的专业和培养方案。

**谁可以访问。** 拥有查看培养方案权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Curriculum Setup → Programs**。
2. 点击 **Add Program**（添加培养方案）以新建。
3. 填写信息后保存。
4. 对于已有的培养方案，使用行末图标：**View program**（查看）、**Edit program**（编辑）、**Delete program**（删除）。

**注意事项。** 不要删除已有学生就读的培养方案。如果已停止招生，应停用而非删除。

**接下来。** Curriculum Versions、Units。

## Curriculum Versions — 培养方案版本

**用途。** 不同招生批次可能对应不同的培养方案框架。每个这样的框架就是一个版本。

**谁可以访问。** 拥有查看培养方案版本权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Curriculum Setup → Curriculum Versions**。
2. 点击 **Add Curriculum Version** 创建新版本。
3. 如果只是对现有版本做小幅调整，使用 **Duplicate curriculum version**（复制）图标，在副本上修改。

**界面内容。** 顶部三张卡片：版本总数、**生效中**（Active）的版本数、**已停用**（Inactive）的版本数。

**注意事项。** 直接修改生效中的版本会影响正在该版本下就读的学生。安全做法是先复制、在副本上修改，再切换为生效版本。

**接下来。** Units、Course Registration。

## Units — 课程

**用途。** 课程目录：课程代码、名称、学分、先修课程（前置条件）与等效课程。

**谁可以访问。** 拥有查看课程权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Curriculum Setup → Units**。
2. 点击 **Add Unit** 添加课程。
3. 如适用，登记先修课程和等效课程。
4. 使用行末图标查看、编辑或删除单个课程。

**界面内容。** 顶部三张卡片：课程总数、**有先修条件的** 课程数、**有等效课程的** 课程数。

**注意事项**

- 登记先修课程会直接影响学生是否被允许选修该课程。
- 等效课程用于学生转培养方案，或以已被认可的另一门课程重修时。
- 该界面支持 **批量删除**。确认前请仔细检查所选列表——该操作不可撤销。

**接下来。** Syllabus Templates、Course Offering List。

## Modules — 模块

**用途。** 将课程归组为培养方案内的知识模块。

**谁可以访问。** 拥有查看模块权限的人员。

**操作步骤。** 进入 **Academic Operations → Curriculum Setup → Modules** 查看和管理模块列表。

**接下来。** Programs、Units。

## Syllabus Templates — 教学大纲模板

**用途。** 预先制作可重复使用的教学大纲模板，避免每学期从零开始编写。

**谁可以访问。** 拥有查看教学大纲权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Curriculum Setup → Syllabus Templates**。
2. 点击 **New Template**（新建模板）。
3. 编写内容后保存。
4. 如果列表显示异常，点击 **Clear filters**（清除筛选器）。

**接下来。** Course Offering List、Course Statistics。

## 开班前检查

- 学期已存在，且是你要开的那一个。
- 培养方案与培养方案版本与该招生批次相符。
- 课程已具备所需信息：学分、先修课程。
- 要开班的课程已准备好教学大纲模板。
- 如有学生要重修，请在 Finance Office 区域核实重修学费。
