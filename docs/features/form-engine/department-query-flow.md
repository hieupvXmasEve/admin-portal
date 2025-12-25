# Requirement – Department Query Workflow (Phase 1) + Assignment Audit

Tài liệu này mô tả các việc cần làm để đáp ứng yêu cầu:

- Query (ticket) được tạo theo **department** (ví dụ: HQ).
- Trong **mỗi department** có **2 quyền nghiệp vụ (Phase 1)**:
    - **Head**: xem tất cả query trong department, trả lời, assign/reassign/unassign.
    - **Staff**: chỉ xem query được assign cho mình, trả lời các query đó.
- Có **audit lịch sử assignment**.

> Lưu ý: hệ thống của bạn đang theo kiến trúc **Template (forms) → Runs (form_targets) → Results (responses/answers)** + Ticketing (queries_tickets/queries_replies). Requirement này **không phá kiến trúc hiện tại**, chỉ bổ sung mảnh ghép “department membership + assignment audit + enforcement rules”.

## 1. Phạm vi (Scope)

### In-scope (Phase 1)

1. Xác định staff thuộc department nào và ai là **Head** (nhiều Head được phép).
2. Quy tắc truy cập:
    - Head: xem tất cả ticket trong department.
    - Staff: chỉ xem ticket được assign.
3. Assignment workflow:
    - Head assign/reassign/unassign ticket.
4. Audit assignment:
    - Ghi log mọi lần assign/reassign/unassign.
5. UI/Admin portal tối thiểu:
    - Trang **Department Members**: cấu hình Head/Staff.
    - Trang **Query Inbox (Department)**:
        - Head: All tickets + assign action.
        - Staff: My assigned tickets.
    - Trang **Ticket Detail**: thread reply + attachments.
6. Student portal (liên quan query):
    - Student tạo query theo form_target department.
    - Student xem và reply ticket của chính mình.

### Out-of-scope (Phase 1)

- SLA, escalation, auto-routing theo topic.
- Nhiều vai trò nâng cao (QA/reviewer/approver).
- Permission theo topic/priority.
- Reporting nâng cao.

## 2. Khái niệm & Mapping bảng hiện tại

### 2.1 Form Target theo Department

- `form_targets.scope_type = 'department'`
- `form_targets.scope_id = departments.id` (HQ)

Mỗi query response sẽ liên kết tới `responses.form_target_id` → suy ra department.

### 2.2 Response & Ticket

- `responses` là record “submitted instance” của student.
- Nếu form là loại `query`, hệ thống tạo:
    - `queries_tickets` (1:1 với response)
    - `queries_replies` (thread message)
- `responses.assigned_to_user_id` giữ **assignee hiện tại** (current assignee).
- `query_assignments` (bảng mới) lưu **audit/history**.

## 3. Vai trò & Quyền nghiệp vụ

### 3.1 Department Role (Phase 1)

- `head`
- `staff`

> Đây là “department role” (nghiệp vụ), **không phải role hệ thống**.  
> Role hệ thống (RBAC) vẫn dùng để “vào trang admin nào”, còn department_role dùng để “thấy ticket nào + làm được gì trên ticket”.

### 3.2 Quyền truy cập dữ liệu (Data Access Rules)

**Given department_id = form_targets.scope_id**

1. **Head** (member.role=head):
    - `can_view_ticket`: YES nếu ticket thuộc department.
    - `can_assign_ticket`: YES
    - `can_reply_ticket`: YES
2. **Staff** (member.role=staff):
    - `can_view_ticket`: YES **chỉ khi** `responses.assigned_to_user_id = current_user.id`
    - `can_assign_ticket`: NO
    - `can_reply_ticket`: YES **chỉ khi** được view ticket
3. User không thuộc department:
    - Không xem/assign/reply ticket của department đó.

## 4. Database Changes

### 4.1 Thêm bảng Department Memberships (bắt buộc)

Bảng này để cấu hình: ai thuộc department, ai là Head.

```sql
create table department_memberships
(
    id              bigint unsigned auto_increment primary key,
    department_id   bigint unsigned not null,
    user_id         bigint unsigned not null,
    department_role enum ('head','staff') not null default 'staff',
    is_active       tinyint(1) not null default 1,
    created_at      timestamp null,
    updated_at      timestamp null,

    unique key uq_dept_user (department_id, user_id),
    index idx_dept_role (department_id, department_role),
    index idx_user (user_id)
) collate = utf8mb4_unicode_ci;
```

> Nếu bạn đã có bảng department/staff mapping tương tự, có thể reuse và chỉ cần thêm `department_role`.

### 4.2 Thêm bảng Assignment Audit (query_assignments)

Bảng này log mọi thay đổi assignment.  
Khuyến nghị log theo `queries_tickets` (nghiệp vụ ticket), nhưng vẫn lưu thêm `response_id` để join nhanh.

```sql
create table query_assignments
(
    id                     bigint unsigned auto_increment primary key,

    query_ticket_id         bigint unsigned not null,
    response_id             bigint unsigned null,

    department_id           bigint unsigned null,  -- denormalize để filter nhanh (optional but recommended)
    form_target_id          bigint unsigned null,  -- denormalize để filter nhanh (optional but recommended)

    from_assignee_user_id   bigint unsigned null,
    to_assignee_user_id     bigint unsigned null,  -- null = unassign

    assigned_by_user_id     bigint unsigned not null,
    action                  enum('assign','reassign','unassign') not null,

    note                    varchar(500) null,
    created_at              timestamp null,
    updated_at              timestamp null,

    index idx_ticket_time (query_ticket_id, created_at),
    index idx_to_user (to_assignee_user_id),
    index idx_by_user (assigned_by_user_id),
    index idx_dept (department_id),
    index idx_target (form_target_id),

    constraint fk_query_assignments_ticket
        foreign key (query_ticket_id) references queries_tickets (id)
            on delete cascade,

    constraint fk_query_assignments_response
        foreign key (response_id) references responses (id)
            on delete set null,

    constraint fk_query_assignments_from_user
        foreign key (from_assignee_user_id) references users (id)
            on delete set null,

    constraint fk_query_assignments_to_user
        foreign key (to_assignee_user_id) references users (id)
            on delete set null,

    constraint fk_query_assignments_by_user
        foreign key (assigned_by_user_id) references users (id)
            on delete cascade
) collate = utf8mb4_unicode_ci;
```

#### Audit rules

- **Assign lần đầu**: action=`assign`, from=NULL, to=assignee_id
- **Reassign**: action=`reassign`, from=old_id, to=new_id
- **Unassign**: action=`unassign`, from=old_id, to=NULL
- `department_id`, `form_target_id` nên fill từ:
    - `response.form_target_id`
    - `form_targets.scope_id` (department)

## 5. Backend Logic & Enforcement

### 5.1 Resolver: Department của ticket

Given `response_id`:

- `form_target_id = responses.form_target_id`
- `department_id = form_targets.scope_id` (khi scope_type='department')

### 5.2 Membership check helpers

- `isDepartmentHead(user_id, department_id)`
- `isDepartmentStaff(user_id, department_id)` (member tồn tại & active)
- `canViewTicket(user, response)` theo rule ở mục 3.2

### 5.3 List Inbox (Admin portal)

**Head Inbox**

- Filter: department_id (HQ) + statuses
- Query: tất cả responses thuộc form_targets của department.

**Staff Inbox**

- Same filter theo department_id
-   - `responses.assigned_to_user_id = current_user.id`

### 5.4 Assign action (Admin portal – chỉ Head)

**Input**

- `response_id`
- `to_assignee_user_id` (nullable nếu unassign)
- optional `note`

**Steps**

1. Resolve `department_id` từ response/form_target.
2. Assert current user là **Head** của department.
3. Nếu assign/reassign: assert `to_assignee_user_id` là member active của same department.
4. Update:
    - `responses.assigned_to_user_id`
    - `responses.query_status` (đề xuất: `pending` khi assigned)
5. Insert record vào `query_assignments` theo audit rules.

> Khuyến nghị implement bằng Action class: `AssignQueryAction`.

### 5.5 Reply action (Admin portal)

- Head: reply mọi ticket trong department.
- Staff: chỉ reply ticket assigned (vì rule canViewTicket).

### 5.6 Student reply action

- Student chỉ reply ticket do mình tạo (own submission).

## 6. UI / Pages (Phase 1)

### 6.1 Admin – Department Members

**Route**: `/admin/departments/{id}/members`

- List user thuộc department
- Fields:
    - user
    - department_role: head/staff
    - is_active
- Actions:
    - Add member
    - Promote/demote head
    - Disable member

> Permission route-level: `can:manage_department_members`

### 6.2 Admin – Query Inbox (Department)

**Route**: `/admin/queries?department_id=HQ`

- Nếu Head:
    - Tab/Filter: All tickets
    - Assign button
- Nếu Staff:
    - Chỉ hiển thị “My assigned”
- Columns gợi ý:
    - Ticket code / created_at
    - Student
    - Topic (nếu có)
    - Status (query_status)
    - Assignee
    - Last reply time

> Permission route-level: `can:view_department_queries`

### 6.3 Admin – Ticket Detail

**Route**: `/admin/queries/{ticket_id}`

- Thread replies (queries_replies)
- Attachments (upload_records linked reply_id)
- Assign panel (chỉ Head)
- Status actions: open/pending/answered/closed (theo query_status bạn có)

### 6.4 Student – My Queries

- List queries của student (own responses)
- Ticket detail + reply + attachments

## 7. Permission Strategy (Route vs Data)

### 7.1 Route-level (middleware)

- `view_department_queries`: cho phép vào module query inbox
- `manage_department_members`: cho phép cấu hình members

### 7.2 Data-level (policy/service)

- Ticket nào thấy được → **department_memberships + assigned_to_user_id**
- Tuyệt đối không chỉ dựa vào route permission để lọc dữ liệu.

### 7.3 form_result_visibility (giữ nguyên)

- Có thể dùng để quyết định “depth” (own/aggregate/full_detail) theo role.
- Nhưng với Query workflow, phần quan trọng vẫn là **assignment/membership**.

## 8. Migration & Data Backfill

### 8.1 Backfill membership

- Tạo memberships cho staff HQ hiện tại.
- Set 1+ Head.

### 8.2 Backfill department_id trong audit log (nếu denormalize)

- Khi insert audit record: fill `department_id` và `form_target_id` từ response/form_target.

## 9. Acceptance Criteria (Phase 1)

1. **Head sees all**:
    - User A là Head của HQ → vào Inbox HQ thấy tất cả ticket HQ.
2. **Staff sees assigned only**:
    - User B là staff HQ → chỉ thấy ticket có `assigned_to_user_id = B`.
3. **Assign enforce**:
    - Staff không thể assign.
    - Head assign được và chỉ assign cho member HQ.
4. **Audit created**:
    - Mỗi lần assign/reassign/unassign tạo đúng 1 record trong `query_assignments`.
    - Record có đúng from/to/action/by/timestamp.
5. **Reply enforce**:
    - Staff chỉ reply được ticket assigned.
    - Head reply được mọi ticket HQ.
6. **Student isolation**:
    - Student chỉ xem/reply ticket do mình tạo.

## 10. Deliverables / Task List

### DB

- [ ] Migration: `department_memberships`
- [ ] Migration: `query_assignments`
- [ ] Add indexes cần thiết (đã nêu)

### Backend

- [ ] Model + repository: DepartmentMembership
- [ ] Policy/service: `QueryAccessPolicy` (view/reply/assign)
- [ ] Action: `AssignQueryAction` (update response + insert audit)
- [ ] Controller endpoints:
    - [ ] Inbox list
    - [ ] Ticket detail
    - [ ] Assign endpoint
    - [ ] Reply endpoint
- [ ] Tests (Feature):
    - [ ] Head vs Staff visibility
    - [ ] Assign permission + audit row inserted
    - [ ] Reply permission

### Admin UI

- [ ] Department Members page
- [ ] Query Inbox page
- [ ] Ticket Detail page (assign panel only for Head)

### Student UI

- [ ] My Queries list
- [ ] Ticket detail + reply + attachments

## 11. Phase 2+ (Future)

- Thêm roles: supervisor/qa/reviewer
- Auto-assign theo `query_topics`
- SLA + escalation
- Assignment rules theo workload
- Reporting/analytics
