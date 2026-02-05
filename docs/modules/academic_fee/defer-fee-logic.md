# Logic bảo lưu học phí

Tài liệu này mô tả nghiệp vụ bảo lưu (defer) và xử lý học phí theo 2 nhóm sinh viên:

- intake_course
- intake_pre_uni_gc (EGC)

## 1) Mục tiêu chung

- Giữ dữ liệu sạch: không tạo credit nếu không cần thiết.
- Bảo lưu phải audit được: biết SV bảo lưu môn nào/level nào.
- Tránh tính phí sai khi SV đăng ký lại sau khi bảo lưu.

## 2) intake_course

### 2.1 Bảo lưu full kỳ

- Sử dụng curriculum_version_id của student (cố định, không thay đổi).
- Defer full kỳ được hiểu là "miễn phí toàn bộ kỳ" khi SV học lại kỳ đó.
- Không tạo charge âm (không tạo credit) nếu chính sách là "đã đóng tiền thì không hoàn".
- Defer full có thể không tạo items theo unit (do có cả elective sau này). Logic miễn phí được áp khi đăng ký lại.

### 2.2 Bảo lưu theo môn

- Tạo defer_case_items theo môn được chọn (theo unit_id hoặc course_registration_id tùy thời điểm).
- Khi SV đăng ký lại, đối chiếu unit_id để bỏ qua charge (skip charge).
- Không cho bảo lưu trùng 1 môn trong cùng kỳ.

## 3) intake_pre_uni_gc (EGC)

### 3.1 Quy tắc chung

- Chỉ cho phép bảo lưu FULL kỳ.
- Khi bảo lưu FULL, bắt buộc chuyển status sinh viên.
- EGC chỉ học môn EGC, không cần tách block phức tạp.

### 3.2 Hiển thị dữ liệu để quyết định bảo lưu

- UI hiển thị danh sách course_registration của kỳ hiện tại (nếu có).
- UI hiển thị các finance_charges có type = egc_level_fee trong kỳ hiện tại.
- User tự chọn số level muốn bảo lưu (0/1/2...)

### 3.3 Tạo credit cho EGC

- Nếu user chọn bảo lưu level nào thì tạo credit tương ứng cho level đó.
- Tạo credit theo mỗi charge egc_level_fee được chọn:
    - charge_type = defer_credit
    - amount = -abs(amount của charge gốc)
    - source_type = FinanceCharge
    - source_id = id charge egc_level_fee
- Nếu không chọn level nào thì không tạo credit.

### 3.4 Điều kiện thanh toán

- Chỉ cho phép bảo lưu EGC nếu charge egc_level_fee đã được thanh toán (is_fully_paid = true).
- Nếu có charge chưa thanh toán, mặc định chính sách học phí = FORFEIT và khóa select.

## 4) Kiểm soát trùng lặp

- Không cho bảo lưu trùng môn/level trong cùng kỳ.
- EGC: nếu đã tạo credit từ charge egc_level_fee, không tạo lại cho charge đó.

## 5) Tóm tắt quyết định

- intake_course: không tạo credit, sử dụng defer items và skip charge khi đăng ký lại.
- intake_pre_uni_gc: có thể tạo credit theo số level đã đóng và được user chọn.
