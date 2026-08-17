---
title: Administration
description: 用户、权限、校区、部门、外部集成与系统配置。
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Users/Index.vue
  - resources/js/pages/Roles/Index.vue
  - resources/js/pages/Campuses/Index.vue
  - resources/js/pages/Admin/Departments/Index.vue
  - resources/js/pages/SystemConfig/Index.vue
  - resources/js/pages/Systems/ActivityLogs.vue
  - resources/js/pages/Admin/EmailMonitoring/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Administration** 面向管理员。这里的修改会影响 **所有用户**，不只是你自己。

一般原则：每次只改一项，记录修改原因，改完后用真实账号验证。

## Identity & Access — 账号与权限

### Users — 用户

**用途。** 添加、编辑、停用教职员工账号。

**谁可以访问。** 拥有查看用户权限的人员。

**操作步骤**

1. 进入 **Administration → Identity & Access → Users**。
2. 添加新用户，或打开已有账号进行编辑。
3. 分配角色以及可访问的校区。

**注意事项**

- 用户通过 Google 登录。此处登记的邮箱必须与其 Google 账号邮箱 **完全一致**，否则无法登录。
- 员工离职时应 **停用账号**，而不是删除。删除会丢失该账号过往操作的记录。
- 分配的校区决定该用户能看到哪个校区的数据。

### Roles & Permissions — 角色与权限

**用途。** 登记角色及其附带的权限。界面名称为 **Roles**。

**谁可以访问。** 拥有查看角色权限的人员。

**系统自带角色**

| 角色 | 常见范围 |
| --- | --- |
| Super Admin | 系统全部权限 |
| Giám Đốc Đào Tạo（教务主任） | 全校学务管理 |
| Trưởng Phòng（部门主管） | 部门范围内管理 |
| Cán Bộ（职员） | 日常业务操作 |
| Phụ huynh（家长） | 查看子女信息 |

**注意事项**

- 权限决定用户能看到哪些菜单。同事反馈“菜单不见了”，先在此检查其角色。
- 修改某个角色会影响 **所有** 拥有该角色的人。如果只有一个人需要不同的权限，应创建新角色，而不是修改共用角色。
- 只授予工作所需的最小权限。权限过多是风险，尤其是在财务区域。

## Organization — 组织架构

### Campuses — 校区

**用途。** 登记学校的各个校区。

**操作步骤。** 进入 **Administration → Organization → Campuses**。点击 **Clear** 清除筛选条件。

**注意事项。** 校区是整个系统的数据边界。新增或修改校区是罕见且影响面广的操作——务必确认无误后再执行。

### Departments — 部门

**用途。** 登记部门。内容显示在 **Department List** 面板中。

**谁可以访问。** 拥有管理部门权限的人员。

## Integrations — 外部集成

| 页面 | 用途 |
| --- | --- |
| Staff Copilot | 监控教职员工可用的 AI 助手 |
| AI Provider Settings | 配置 AI 服务提供商 |

**注意事项。** AI 相关页面需要独立权限。此处的更改会影响全系统的 AI 功能。

Canvas 连接设置（**Canvas Settings**）已迁移到 **Academic Operations → Course Delivery**，位于它所服务的 **Canvas Courses** 界面旁边——两者共用同一权限，且都由学务团队使用。

## System Operations — 系统运维

### System Configuration — 系统配置

**用途。** 修改系统信息和品牌展示元素。界面名称为 **System configuration**。

**谁可以访问。** 拥有查看系统配置权限的人员。

**界面内容。** **Application details**（系统名称与一般信息）面板和 **Branding assets**（徽标与图像）面板。

**操作步骤。** 进入 **Administration → System Operations → System Configuration**，编辑后保存。点击 **Reset** 可恢复到之前的数值。

**注意事项。** 系统名称和徽标会出现在每个界面和所有发出的邮件中。一旦更改，所有人立即可见。

### Activity Logs — 活动日志

**用途。** 查看整个系统的操作历史。

**谁可以访问。** 拥有查看系统日志权限的人员。

**注意事项。** 与 **Student Actions Audit** 不同——那里只记录针对学生档案的操作，这里覆盖整个系统。

### Email Monitoring — 邮件监控

**用途。** 监控邮件系统的运行状况。

**谁可以访问。** 拥有查看邮件系统权限的人员。

**注意事项。** 收到邮件未送达的报告时，应先在此查看，再去修改具体配置。

## 常见情况

| 情况 | 处理顺序 |
| --- | --- |
| 新员工入职 | Users（添加账号、分配角色和校区） |
| “我看不到 X 菜单” | Users（检查角色） → Roles & Permissions |
| 员工离职 | Users（停用，不要删除） |
| Canvas 报告连接错误 | Canvas Settings（Academic Operations → Course Delivery） |
| 更改徽标或显示名称 | System Configuration |
| 调查一次异常变更 | Activity Logs |
| 全校邮件故障 | Email Monitoring → Email Configuration |
