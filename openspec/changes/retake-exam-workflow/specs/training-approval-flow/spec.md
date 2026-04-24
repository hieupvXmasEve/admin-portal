## ADDED Requirements

### Requirement: Đào tạo có màn hình danh sách + filter để review các case pending

Hệ thống SHALL cung cấp màn hình danh sách cho Đào tạo với filter theo status, unit, campus, semester để xử lý các case đang chờ approval.

#### Scenario: Đào tạo xem danh sách pending eligibility

- **WHEN** Đào tạo truy cập màn hình quản lý thi lại
- **THEN** có thể filter để thấy tất cả case ở status `training_eligibility_pending`, hiển thị thông tin: sinh viên, unit, điểm fail, điểm danh, lý do nomination của GV

---

### Requirement: Approve eligibility và Reject là hai hành động riêng biệt

Hệ thống SHALL xử lý approve eligibility và reject nomination là hai actions riêng, cả hai đều audit trail.

#### Scenario: Đào tạo approve eligibility

- **WHEN** Đào tạo approve một case `training_eligibility_pending`
- **THEN** case chuyển sang `training_eligibility_approved`, ghi lại `eligibility_approved_at` và `eligibility_approved_by`

#### Scenario: Đào tạo reject nomination

- **WHEN** Đào tạo reject một case và nhập `rejection_reason_code` + `rejection_comment`
- **THEN** case chuyển sang `training_rejected` (terminal state), không thể resubmit trong workflow thường

#### Scenario: Reject yêu cầu nhập reason

- **WHEN** Đào tạo cố reject case mà không nhập reason
- **THEN** hệ thống từ chối và yêu cầu nhập `rejection_reason_code` và `rejection_comment`

---

### Requirement: Mở registration window là bước riêng sau approve eligibility

Hệ thống SHALL yêu cầu Đào tạo thực hiện hành động riêng để mở registration window sau khi đã approve eligibility. Hai bước này là hai state transitions tách biệt.

#### Scenario: Đào tạo mở registration sau approve

- **WHEN** Đào tạo mở registration window cho một batch có case đã `training_eligibility_approved`
- **THEN** batch chuyển sang `open`, nhập `registration_window_start/end`, `batch_close_date`
- **AND** tất cả case `training_eligibility_approved` trong batch chuyển sang `registration_opened`

#### Scenario: Không thể skip bước approve eligibility

- **WHEN** Đào tạo cố mở registration window cho batch mà chưa có case nào được approve eligibility
- **THEN** hệ thống từ chối

---

### Requirement: Đào tạo xác nhận roster cuối trước khi tạo CourseOffering thi lại

Hệ thống SHALL yêu cầu Đào tạo xác nhận danh sách sinh viên dự thi (`roster_confirmed`) trước khi tạo CourseOffering thi lại. Hệ thống đề xuất danh sách từ các case có status `waiting_listed`.

#### Scenario: Đào tạo xác nhận roster

- **WHEN** Đào tạo xem danh sách đề xuất từ các case `waiting_listed` và confirm
- **THEN** tất cả case trong danh sách chuyển sang `roster_confirmed`

#### Scenario: Đào tạo chỉnh tay trước khi confirm

- **WHEN** Đào tạo muốn loại một sinh viên khỏi roster (ví dụ vi phạm kỷ luật sau khi paid)
- **THEN** có thể withdraw case với `reason_code` và `comment` bắt buộc, case chuyển sang `repeat_course` (paid → staff xử lý hoàn tiền thủ công)
