# brainstorm: validate attendance import from excel

## Goal

Xac minh file Excel diem danh co du du lieu va map dung voi database de tao attendance cho buoi dau tien, truoc khi thuc hien import thuc te.

## What I already know

* File nguon: `/Users/hunt2412/Downloads/EGC_SPR M2_Attendance (copy from EGC file).xlsx`
* Workbook chi co 1 sheet `Sheet1`, ben trong chua nhieu block lop.
* Moi block bat dau bang ten lop o cot A, dong header co `Student ID` o cot C, `Full name` o cot D.
* Cot E la ngay dau tien cua block, hien dang la `2026-03-02`.
* Gia tri du lieu diem danh trong file dang la boolean/blank (`True`, `False`, `None`), chua co mapping nghiep vu xac nhan sang trang thai attendance.
* Trong codebase, `attendances.student_id` la FK toi `students.id`, khong phai `students.student_id`.
* `course_registrations.student_id` cung la FK toi `students.id`; can map tu Excel `students.student_id` sang `students.id` truoc.
* `attendances.class_session_id` tro toi `class_sessions.id`, moi session thuoc `course_offerings.id`.
* Logic san co `ClassSessionService::generateAttendanceForSession()` tao attendance mac dinh `absent` cho toan bo sinh vien da dang ky cua 1 course offering.

## Assumptions (temporary)

* Ten lop trong cot A co the doi chieu duoc voi `course_offerings.section_code` hoac 1 quy uoc co the suy ra section.
* Cot E la trang thai diem danh cua buoi dau tien cho tung lop.
* Can tao/chinh sua attendance cho session dau tien cua dung section ma sinh vien dang hoc, khong phai cho tat ca registration cung mon.

## Open Questions

* Ten lop Excel (`P2-EGC-2.1`, `P2-EGC-3.1`, ...) map chinh xac sang truong nao trong DB: `section_code`, `course_code + section_code`, hay truong khac?
* `False` trong file nghia la `absent`, hay chi la "chua diem danh/khong tham gia buoi nay/chua hoc lop do"?
* `None` trong file co nghia la de trong co chu dich hay loi du lieu?
* Neu 1 student co nhieu `course_registrations` active phu hop, quy tac chon registration nao?

## Requirements (evolving)

* Phan tich file Excel theo tung block lop trong `Sheet1`.
* Doi chieu `Student ID` trong file voi `students.student_id`.
* Doi chieu lop/section trong file voi registration dang hoc cua sinh vien trong `course_registrations`.
* Xac dinh session dau tien cua registration/lop dung voi cot E.
* Liet ke cac diem mo ho, du lieu sai, hoac du lieu khong du can cu so voi database truoc khi import.

## Acceptance Criteria (evolving)

* [ ] Liet ke duoc cac block lop va ngay o cot E can import.
* [ ] Xac dinh duoc quy tac map `Student ID` Excel -> `students.id`.
* [ ] Xac dinh duoc cac truong DB can dung de map lop -> session dau tien.
* [ ] Bao cao ro cac du lieu sai/khong ro truoc khi tao attendance.

## Definition of Done (team quality bar)

* Khong doan field/relationship; moi ket luan dua tren workbook + code/schema + DB thuc te
* Co danh sach blocker ro rang truoc khi import
* Neu du dieu kien moi chuyen sang buoc tao attendance

## Out of Scope (explicit)

* Chua tao/chinh sua attendance record
* Chua sua code import
* Chua cap nhat docs repo

## Technical Notes

* Task dir: `.trellis/tasks/04-23-attendance-import-validation`
* Code da inspect:
* `app/Models/Attendance.php`
* `app/Models/ClassSession.php`
* `app/Models/CourseRegistration.php`
* `app/Models/CourseOffering.php`
* `app/Models/Student.php`
* `app/Services/ClassSessionService.php`
* `database/migrations/2025_05_28_120000_create_students_table.php`
* `database/migrations/2025_05_28_120002_create_course_offerings_table.php`
* `database/migrations/2025_05_28_120005_create_course_registrations_table.php`
* `database/migrations/2025_06_15_102000_create_class_sessions_table.php`
* `database/migrations/2025_06_15_103000_create_attendances_table.php`
