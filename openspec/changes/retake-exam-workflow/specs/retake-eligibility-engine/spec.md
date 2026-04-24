## ADDED Requirements

### Requirement: Hard-rule pre-filter trước khi GV nominate

Hệ thống SHALL tự động lọc danh sách sinh viên fail bằng hard-rules trước khi GV thấy danh sách để nominate. Sinh viên bị hard-rule loại sẽ không xuất hiện trong danh sách nomination của GV, hoặc xuất hiện với nhãn "Không đủ điều kiện" và lý do rõ ràng.

#### Scenario: Sinh viên bị loại do thiếu điểm danh

- **WHEN** GV mở danh sách nomination cho course offering đã complete
- **AND** sinh viên A có `attendance_percentage` < `config('retake.min_attendance_percentage')` (default 70%)
- **THEN** sinh viên A xuất hiện với status "Không đủ điều kiện - Điểm danh không đủ" và không thể được nominate

#### Scenario: Sinh viên bị loại do vượt max attempts

- **WHEN** GV mở danh sách nomination
- **AND** sinh viên B đã có số lần retake thực tế (có `final_pass/final_fail/no_show`) >= `config('retake.max_attempts')` (default 2) cho cùng unit
- **THEN** sinh viên B xuất hiện với status "Không đủ điều kiện - Đã vượt số lần thi lại tối đa" và không thể được nominate

#### Scenario: Sinh viên đủ điều kiện xuất hiện trong danh sách

- **WHEN** GV mở danh sách nomination
- **AND** sinh viên C thỏa tất cả hard-rules
- **THEN** sinh viên C xuất hiện trong danh sách với nút nominate/not-nominate

---

### Requirement: Chỉ tính attempt retake thực tế vào quota

Hệ thống SHALL chỉ tính một lần retake là "đã sử dụng" khi attempt đó có kết quả `final_pass`, `final_fail`, hoặc `no_show`. Retake case đang mở (chưa có kết quả) KHÔNG được tính vào quota.

#### Scenario: Case đang mở không ảnh hưởng quota

- **WHEN** sinh viên D có 1 retake case đang ở trạng thái `scheduled` (chưa có kết quả)
- **AND** `config('retake.max_attempts')` = 2
- **THEN** hệ thống không block sinh viên D vì quota chưa đạt

#### Scenario: Case đã có kết quả được tính vào quota

- **WHEN** sinh viên E đã có 2 retake case với kết quả `final_fail`
- **AND** `config('retake.max_attempts')` = 2
- **THEN** hệ thống block sinh viên E vì đã đạt max quota

---

### Requirement: Hard-rule configurable qua config/retake.php

Hệ thống SHALL đọc giá trị hard-rules từ `config('retake.max_attempts')` và `config('retake.min_attendance_percentage')`. Các giá trị này có thể được override qua environment variables `RETAKE_MAX_ATTEMPTS` và `RETAKE_MIN_ATTENDANCE`.

#### Scenario: Config được đọc đúng

- **WHEN** `RETAKE_MAX_ATTEMPTS=3` được set trong environment
- **THEN** eligibility engine sử dụng max_attempts = 3 thay vì default 2

#### Scenario: Default values khi không có env override

- **WHEN** không có env variable nào được set
- **THEN** hệ thống dùng `max_attempts = 2`, `min_attendance_percentage = 70.0`
