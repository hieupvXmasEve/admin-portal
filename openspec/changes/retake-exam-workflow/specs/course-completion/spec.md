## MODIFIED Requirements

### Requirement: GV truy cập danh sách nomination sau khi complete course qua query-on-demand

Sau khi `CourseCompletionService::finalizeCourse()` hoàn thành, GV SHALL có thể điều hướng sang màn hình nomination riêng để xem danh sách sinh viên fail đã được eligibility engine filter. Hệ thống KHÔNG thêm bất kỳ bước nào vào `finalizeCourse()` — màn hình nomination tự query từ `FailedStudentsService` khi GV truy cập.

Behavior trước đây: `finalizeCourse()` finalize grades, update registrations, xử lý EGC progression, gửi notification. GV không có nơi để nominate thi lại.

Behavior mới: Completion success screen thêm link/button "Xem danh sách thi lại" dẫn sang `RetakeNomination/Index` — GV chủ động điều hướng khi cần. `finalizeCourse()` KHÔNG thay đổi.

#### Scenario: GV điều hướng sang màn hình nomination sau complete

- **WHEN** `finalizeCourse()` hoàn thành và trả về success response
- **THEN** response/UI có link "Xem danh sách thi lại" dẫn đến màn hình nomination của course offering đó

#### Scenario: Màn hình nomination query-on-demand

- **WHEN** GV truy cập màn hình nomination (bất cứ lúc nào sau khi course completed)
- **THEN** hệ thống query `FailedStudentsService` + `RetakeEligibilityEngine` real-time để hiển thị danh sách cập nhật

#### Scenario: Không có sinh viên fail đủ điều kiện

- **WHEN** GV truy cập màn hình nomination
- **AND** tất cả sinh viên fail đều bị hard-rule loại
- **THEN** màn hình hiển thị "Không có sinh viên đủ điều kiện thi lại" — không cần thao tác thêm
