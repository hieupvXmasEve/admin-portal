# I. NGUYÊN TẮC CHUNG CỦA BƯỚC NÀY

- Mỗi **use-case = 1 nhu cầu nghiệp vụ thực tế**
- Không bàn:
    - bảng
    - schema
    - code

- Chỉ trả lời:
    - **Có / Không**
    - hoặc **Có nhưng không cần ở phase 1**

---

# II. CÁC USE-CASE LIÊN QUAN ĐẾN FORM / SURVEY / QUERY

## NHÓM A – TẠO & SỬ DỤNG FORM

### UC-A1. Tạo form làm template dùng lại nhiều lần

**Mô tả**
Admin tạo 1 form (survey / query) để dùng cho nhiều đợt khác nhau, không gắn ngay với môn / kỳ / phòng ban.

👉 Câu hỏi xác nhận:

- Bạn **có cần** form tồn tại độc lập, chưa dùng ngay không?

---

### UC-A2. Một form được dùng cho nhiều đợt / ngữ cảnh khác nhau

**Mô tả**
Cùng 1 form:

- dùng cho course A kỳ này
- lại dùng cho course A kỳ sau
- hoặc dùng cho nhiều course khác nhau

👉 Xác nhận:

- Bạn **có cần** reuse cùng 1 bộ câu hỏi không?

---

## NHÓM B – SURVEY (ĐÁNH GIÁ)

### UC-B1. Survey cho từng môn học (course survey)

**Mô tả**
Sau khi môn học kết thúc:

- sinh viên phải đánh giá môn đó
- mỗi course offering là **một lần đánh giá riêng**

👉 Xác nhận:

- Có cần survey theo **course offering cụ thể** không?

---

### UC-B2. Survey bắt buộc, chưa làm thì không dùng được portal

**Mô tả**
Nếu sinh viên chưa hoàn thành survey:

- không xem được dashboard
- không dùng được chức năng khác

👉 Xác nhận:

- Có cần **khóa portal** không?

---

### UC-B3. Survey theo từng kỳ học (service / tổng hợp)

**Mô tả**
Cuối kỳ:

- sinh viên đánh giá dịch vụ đào tạo / hỗ trợ
- áp dụng cho toàn bộ sinh viên trong kỳ

👉 Xác nhận:

- Có cần survey theo **kỳ học** không?

---

### UC-B4. Lặp lại survey cho các kỳ sau

**Mô tả**
Cùng 1 survey:

- dùng cho Spring 2025
- rồi tiếp tục dùng cho Fall 2025

👉 Xác nhận:

- Có cần **lặp lại cùng survey cho các kỳ sau** không?

---

## NHÓM C – QUERY / REQUEST (HỎI ĐÁP – WORKFLOW)

### UC-C1. Form query để sinh viên gửi thắc mắc

**Mô tả**
Sinh viên gửi:

- câu hỏi
- yêu cầu hỗ trợ
  qua form

👉 Xác nhận:

- Có cần loại form **query / request** không?

---

### UC-C2. Query thuộc về một phòng ban cụ thể

**Mô tả**
Ví dụ:

- HQ xử lý hồ sơ
- IT xử lý kỹ thuật
- Finance xử lý học phí

👉 Xác nhận:

- Có cần **gắn query với phòng ban** không?

---

### UC-C3. Staff chỉ thấy query của phòng ban mình

**Mô tả**
Khi staff đăng nhập:

- chỉ xem được query thuộc department của họ

👉 Xác nhận:

- Có cần filter query theo department không?

---

### UC-C4. Assign query cho nhân viên xử lý

**Mô tả**

- Head / admin assign query cho staff A
- Có thể re-assign sang staff B

👉 Xác nhận:

- Có cần workflow assign / reassign không?

---

## NHÓM D – TỔ HỢP ĐIỀU KIỆN (ĐIỂM NHẠY CẢM)

> Nhóm này **chỉ hỏi nghiệp vụ**, chưa bàn cách làm.

### UC-D1. Query / survey thuộc 1 phòng ban, nhưng chỉ áp dụng cho sinh viên của 1 kỳ học

**Mô tả**
Ví dụ:

- HQ service survey
- chỉ cho sinh viên kỳ Spring 2025

👉 Xác nhận:

- Có cần **department + kỳ học** cùng lúc không?

---

### UC-D2. Một form có thể dùng cho nhiều phòng ban khác nhau

**Mô tả**
Ví dụ:

- Form “Service Feedback”
- HQ dùng
- IT cũng dùng

👉 Xác nhận:

- Có cần reuse form giữa nhiều department không?

---

# III. CÁCH BẠN PHẢN HỒI (ĐỂ ĐI TIẾP)

Bạn **chỉ cần trả lời dạng này**, ví dụ:

```
UC-A1: Yes
UC-A2: Yes
UC-B1: Yes
UC-B2: Yes
UC-B3: No
UC-B4: Yes
UC-C1: Yes
UC-C2: Yes
UC-C3: Yes
UC-C4: No (phase 2)
UC-D1: Yes
UC-D2: Yes
```

👉 Sau khi bạn xác nhận:

- Mình **mới đưa ra solution**
- Solution sẽ:
    - bám 100% use-case bạn chọn
    - **không phát sinh thêm bảng ngoài phạm vi**
    - không “tự nghĩ thêm nghiệp vụ”

---

## IV. CAM KẾT RÕ RÀNG

- Từ bước tiếp theo:
    - ❌ không nói lan man
    - ❌ không áp đặt cách làm
    - ✅ chỉ map **use-case → solution**

👉 Bạn cứ **xác nhận các use-case**, mình chờ phản hồi của bạn.
