## ADDED Requirements

### Requirement: RecipientResolver supports department target type
`RecipientResolver::resolve()` SHALL xử lý target type `"department"` bằng cách lookup tất cả active `DepartmentMembership` của department đó và trả về `user_id` list.

Resolution SHALL:
- Query `DepartmentMembership::where('department_id', $id)->where('is_active', true)->pluck('user_id')`
- Không filter theo campus (department members nhận notification toàn hệ thống)
- Deduplicate với các resolved user_ids khác trong cùng batch

#### Scenario: Department with active members resolved
- **WHEN** target `{"type": "department", "id": 5}` và department 5 có 3 active members
- **THEN** `resolved_user_ids` bao gồm 3 user_ids tương ứng

#### Scenario: Department with no active members
- **WHEN** target `{"type": "department", "id": 5}` và department 5 không có active member nào
- **THEN** không có user_id nào được thêm vào `resolved_user_ids`, không có unresolved entry

#### Scenario: Department not found
- **WHEN** target `{"type": "department", "id": 9999}` và không có department với id đó
- **THEN** entry được thêm vào `unresolved` với reason `"department_not_found"`

#### Scenario: Unsupported target type unchanged
- **WHEN** target type không phải `"user"`, `"student"`, `"lecture"`, hoặc `"department"`
- **THEN** entry vẫn được xử lý như cũ với reason `"unsupported_target_type"`
