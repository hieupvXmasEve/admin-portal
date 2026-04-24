## ADDED Requirements

### Requirement: GV thấy danh sách nomination scope theo course offering cụ thể

Hệ thống SHALL hiển thị danh sách sinh viên fail của một `course_offering_id` cụ thể (không phải campus-wide). GV chỉ được phép truy cập màn hình nomination cho các offering mà họ là `lecture_id`. Danh sách đã được eligibility engine pre-filter.

#### Scenario: Danh sách hiển thị đúng sau complete course

- **WHEN** GV truy cập màn hình nomination của course offering X (mà họ phụ trách)
- **THEN** hệ thống query sinh viên fail **của course offering X** (không phải campus-wide), gồm: sinh viên đủ điều kiện (có thể nominate) và sinh viên không đủ điều kiện (hiển thị lý do)

#### Scenario: GV không thể truy cập nomination của offering khác

- **WHEN** GV A cố truy cập màn hình nomination của course offering Y (do GV B phụ trách)
- **THEN** hệ thống trả về 403 Forbidden

#### Scenario: Danh sách nomination vẫn truy cập được sau đó

- **WHEN** GV muốn xem lại danh sách nomination sau vài ngày
- **THEN** danh sách vẫn hiển thị với trạng thái nomination hiện tại của từng sinh viên

---

### Requirement: GV nominate từng sinh viên với lý do

Hệ thống SHALL yêu cầu GV ghi lý do (nomination_reason) khi nominate sinh viên. Quyết định nominate/not-nominate phải được audit.

#### Scenario: GV nominate sinh viên

- **WHEN** GV click nominate cho sinh viên A và nhập lý do
- **THEN** hệ thống tạo `course_retake_cases` với status `training_eligibility_pending`, lưu `nominated_by_user_id`, `nominated_at`, `nomination_reason`
- **AND** (nếu chưa có) tự động tạo `retake_batches` ở trạng thái `proposed` cho `unit+campus+semester` tương ứng

#### Scenario: GV không nominate (not-nominate)

- **WHEN** GV chọn not-nominate cho sinh viên B và nhập lý do
- **THEN** hệ thống tạo `course_retake_cases` với status `not_nominated` (terminal), lưu `nominated_by_user_id`, `nominated_at`, `nomination_reason` (lý do không nominate)
- **AND** case này không đi vào workflow approval — chỉ tồn tại để audit

**Lý do**: Không có model `failed_students` trong codebase (chỉ có service/query). Tạo `not_nominated` case dùng lại entity sẵn có, không cần bảng mới, đủ dữ liệu để audit.

#### Scenario: GV không thể nominate sinh viên đã có case đang mở

- **WHEN** GV cố nominate sinh viên đã có retake case đang active cho cùng unit+semester
- **THEN** hệ thống từ chối với thông báo case đã tồn tại

---

### Requirement: Campus scope enforcement cho GV

Hệ thống SHALL chỉ cho phép GV thấy danh sách sinh viên fail thuộc campus mà GV phụ trách course offering đó.

#### Scenario: GV chỉ thấy sinh viên campus của mình

- **WHEN** GV thuộc campus A xem danh sách nomination cho course offering của campus A
- **THEN** chỉ sinh viên thuộc campus A xuất hiện trong danh sách
