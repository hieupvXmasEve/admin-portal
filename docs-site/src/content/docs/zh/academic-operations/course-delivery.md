---
title: Course Delivery
description: 开班、排课、选课登记、重修、补考、课程统计及 Canvas 关联。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/CourseOfferings/Index.vue
  - resources/js/pages/ClassSchedule/Index.vue
  - resources/js/pages/CourseRegistrations/Index.vue
  - resources/js/pages/Academic/RetakeCourse/Index.vue
  - resources/js/pages/Academic/ExamResit/Index.vue
  - resources/js/pages/Academic/ExamResit/Schedule/Index.vue
  - resources/js/pages/CourseStatistics/Index.vue
  - resources/js/pages/Admin/Canvas/Courses/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Course Statistics moved here from Attendance & Completion; Canvas Integrations moved here from Administration, retitled Canvas Settings, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Course Delivery** 是每学期开始时的核心工作：开班、排课、登记学生选课、处理重修和补考。

## Course Offering List — 开班列表

**用途。** 为某个学期开班：哪门课程、哪个学期、哪种授课方式、由谁授课。

**谁可以访问。** 拥有查看开班权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Course Offering List**。
2. 点击 **Create Course Offering**（创建开班）。
3. 选择课程、学期、授课方式及其余信息后保存。
4. 查找已有开班：在 **Search courses...** 框中输入，或使用 **Filters** 面板——按模块（**All Modules**）、级别（**All Levels**）、类型（**All Types**）、状态（**All Statuses**）、授课方式（**All Modes**）筛选。

**界面内容。** 顶部两张卡片：**Total Offerings**（开班总数）和 **Active Offerings**（运行中的开班数）。

**注意事项**

- 开班必须在向学生开放选课 **之前** 创建完成。
- 列表默认按当前所选校区筛选。如果看不到预期的开班，检查左上角的 `Campus:` 一行。

**接下来。** Course Statistics、Class Schedule、Course Registration、Attendance Summary。

## Course Statistics — 课程统计

**用途。** 查看某学期各课程的情况：选课人数、出勤率、总体成绩。

**谁可以访问。** 拥有查看考勤权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Course Statistics**。
2. 在 **Select semester** 选择学期。
3. 使用 **Unit code or name...**（课程代码或名称）查找课程。

**注意事项。** 必须先选择学期，否则界面不会显示任何数据。

**接下来。** Failed Students（**Attendance** 分组）。

## Class Schedule — 课表

**用途。** 为各开班安排上课时间：日期、时间、教室。

**谁可以访问。** 拥有查看开班权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Class Schedule**。
2. 使用旁边的筛选面板选择学期、开班或时间范围。
3. 以日历或表格形式查看课表。
4. 点击某个课次，在右侧打开编辑面板，编辑完成后保存。

**注意事项**

- 保存前检查教室冲突和教师时间冲突。
- 修改已经上过的课次的时间会使其考勤数据出现偏差。

**接下来。** Attendance Summary。

## Course Registration — 选课登记

**用途。** 记录本学期哪些学生选修了哪个开班。

**谁可以访问。** 拥有查看选课登记权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Course Registration**。
2. 使用 **Filters** 按学期、开班或学生筛选。
3. 点击 **View** 查看详情，**Edit** 修改，**Delete** 取消登记。
4. 当列表为空时，点击 **Register First Student**（登记第一名学生）添加。

**界面内容。** 顶部三张卡片：**Total Registrations**（登记总数）、**Active Registrations**（生效中）、**Pending Registrations**（待处理）。

**注意事项**

- 取消登记会影响学生的学费。金额部分请在 **Finance Office** 区域处理。
- **Pending** 数字代表尚未处理的事项，应在学期开始前清理完毕。

**接下来。** 学生名单、Attendance Summary。

## Retake Registration — 重修登记

**用途。** 登记学生重修其不及格的课程。

**谁可以访问。** 拥有查看重修登记权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Retake Registration**（重修登记）。
2. 筛选找到需要处理的学生或课程。
3. 记录重修登记。
4. 如果列表显示与预期不符，点击 **Xóa bộ lọc**（清除筛选器）。

**注意事项**

- 符合重修条件的学生名单来自 **Failed Students** 界面。
- 重修通常会产生学费。请在 **Finance Office** 区域核实。

**接下来。** Failed Students、Finance Office。

## Thi lại — 补考

**用途。** 建立补考名单并跟踪结果。

**谁可以访问。** 拥有查看补考权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Thi lại**。
2. 按学期、课程或学生筛选。
3. 创建补考批次并选择参加的学生。
4. 考试结束后录入结果以结束该批次。

**接下来。** Lịch thi lại。

## Lịch thi lại — 补考安排

**用途。** 为补考批次安排考试时段、考场，并分配监考人员。

**谁可以访问。** 拥有管理补考安排权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Lịch thi lại**。
2. 点击 **Tạo ca phòng thi**（创建考场时段）打开 **Ca phòng thi mới** 面板。
3. 输入时间和考场后点击 **Tạo ca**（创建时段）。
4. 如需为同一批次添加更多时段，点击 **Thêm ca thi**（添加时段）。
5. 点击 **Phân công coi thi**（分配监考）为各时段安排监考人员。

**注意事项。** 先在 **Campus** 预约考场，以避免与其他活动时间冲突。

## Canvas Courses — Canvas 关联

**用途。** 将 Portal 中的开班与在线学习系统 Canvas 中对应的课程关联起来。

**谁可以访问。** 拥有查看 Canvas 集成权限的人员。

**操作步骤**

1. 进入 **Academic Operations → Course Delivery → Canvas Courses**。
2. 查看 **Integration Status**（集成状态）面板，确认连接正常。
3. 选择要关联的开班，点击 **Map Course**（关联课程）。
4. 使用 **Select All**（全选）和 **Deselect All**（取消全选）一次性关联多个开班。
5. 如果连接出现问题，点击 **Manage Integrations** 或 **Go to Integrations** 打开 **Canvas Settings**。

**注意事项。** 关联错误会导致学生进入错误的在线课堂。确认前请核对开班代码和学期。

**接下来。** Canvas Settings。

## Canvas Settings — Canvas 连接设置

**用途。** 配置与 Canvas 系统的连接（API 密钥、端点）。此前位于 Administration 分组下；现移至 Canvas Courses 旁边，因为两者共用同一权限（`view_canvas_integration`），且都服务于学务团队，而非系统管理团队。

**谁可以访问。** 拥有查看 Canvas 集成权限的人员。

**操作步骤。** 进入 **Academic Operations → Course Delivery → Canvas Settings**。

**接下来。** Canvas Courses。

## 建议流程

```text
Course Offering List
  -> Class Schedule
  -> Course Registration
  -> Canvas Courses
  -> Attendance Summary
```

如果班级中有学生不及格：

```text
Course Statistics
  -> Failed Students
  -> Retake Registration
  -> 如需处理重修学费，进入 Finance Office
```
