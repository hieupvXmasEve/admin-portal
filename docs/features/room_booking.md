# Tính năng Room Booking

## I. Đối tượng & Vai trò

### **1. Người có thể tạo booking**

* Student *(nếu hệ thống bật setting allow_student_booking)*
* Lecturer
* User (staff)
* Admin
* Room Manager (vai trò mới)

### **2. Người có quyền phê duyệt**

* **Admin**
* **Room Manager**

---

## **II. Quy tắc tổng quát**

### **BR-01. Một booking chỉ áp dụng 1 ngày**

* `booking_date` là ngày duy nhất
* Muốn đặt nhiều ngày → phải tạo từng booking riêng.

---

### **BR-02. Tất cả booking phải bắt đầu ở trạng thái “pending”**, trừ:

* **Admin tự tạo booking** → **auto-approved**

---

### **BR-03. Student có thể đặt phòng nếu:**

* Hệ thống bật setting `allow_student_booking = true`
* Room có `is_bookable = 1`
* Thời gian đặt nằm trong khung giờ cho phép
* Không vượt giới hạn booking trong ngày

---

### **BR-04. Lecturer và User có thể đặt phòng mà không cần setting đặc biệt**

→ Lý do: họ là nhân viên của trường, không bị hạn chế như student.

---

### **BR-05. Tất cả booking phải được Room Manager hoặc Admin duyệt**

Trừ trường hợp admin tự book.

---

## **III. Quy tắc về thời gian**

### **BR-06. Khung giờ đặt phòng**

* Hệ thống có cấu hình:

  * `system_booking_start_time` (vd: 07:00)
  * `system_booking_end_time` (vd: 20:00)

* Mỗi phòng có:

  * `available_from`
  * `available_until`

➡ Rule kiểm tra:

```
start_time >= max(system_booking_start_time, room.available_from)
end_time <= min(system_booking_end_time, room.available_until)
```

Nếu vi phạm → reject ngay từ bước tạo booking.

---

### **BR-07. Không cho đặt phòng quá khứ**

* `booking_date >= today`
* Nếu là ngày hôm nay → start_time >= current_time

---

### **BR-08. Không cho booking giao nhau**

Không được trùng thời gian với booking đã approved hoặc pending của room:

```
existing.start_time < new.end_time AND
existing.end_time > new.start_time
```

Trạng thái được tính conflict:

* pending
* approved

---

## **IV. Quy tắc review & phê duyệt**

### **BR-09. Room Manager & Admin có quyền:**

* Approve booking
* Reject booking
* Cancel booking
* Update thời gian booking (và bắt đầu lại chu trình duyệt)

---

### **BR-10. Khi Reject booking**

* `status = rejected`
* Phải có `rejection_reason`
* Ghi log vào `room_booking_actions`

---

### **BR-11. Khi Approve booking**

* `status = approved`
* Ghi người phê duyệt vào:

  * `approved_by_type`
  * `approved_by_id`
  * `approved_at`
* Ghi log vào `room_booking_actions`

---

### **BR-12. Khi booking bị cập nhật (đổi thời gian / đổi phòng)**

* Trạng thái quay về **pending**
* Người phê duyệt trước đó bị xóa (`approved_by_* = null`)
* Ghi log “updated”
* Kiểm tra lại conflict

---

## **V. Quy tắc dành riêng cho Student**

### **BR-13. Bật/tắt quyền student book phòng**

* Nếu `allow_student_booking = false` → student không thể tạo booking

---

### **BR-14. Giới hạn số booking/ngày**

Cấu hình:

* `student_booking_limit_per_day` (vd: 2)

Backend tính:

```
count(student bookings where booking_date = X and status != cancelled)
```

Nếu vượt → không cho tạo.

---

### **BR-15. Student không thể tự approve**

* Mọi booking student tạo đều ở trạng thái pending
* Trừ khi admin tạo booking giúp student (qua admin panel)

---

## **VI. Quy tắc dành riêng cho Admin**

### **BR-16. Admin tạo booking → auto-approved**

Không đi qua pending.

---

### **BR-17. Admin có toàn quyền override**

* Có thể tạo booking dù phòng đang occupied
* Có thể bypass time conflict (tùy hệ thống bật “admin_override_mode”)
* Có thể sửa booking đã approved

---

## **VII. Quy tắc dành cho Room Manager**

### **BR-18. Room Manager là người xử lý chính tất cả booking**

* Review
* Approve
* Reject
* Cancel
* Adjust time

---

### **BR-19. Room Manager -> auto-approved**

Room Manager tạo booking auto-approved.

## **VIII. Quy tắc về phòng**

### **BR-20. Phòng phải ở trạng thái “available” và `is_bookable = 1`**

Nếu room status =

* occupied
* maintenance
* out_of_service

→ không thể tạo booking.

---

### **BR-21. Nếu phòng yêu cầu approval bắt buộc**

(`requires_approval = 1`)
→ Mọi booking (kể cả lecturer) phải qua Bước Duyệt.

*(Admin tự đặt vẫn auto-approved)*

---

## **IX. Quy tắc về lịch sử booking**

### **BR-22. Mọi hành động phải được ghi vào log**

Bảng: `room_booking_actions`

Các sự kiện:

* created
* updated
* approved
* rejected (kèm note lý do)
* cancelled

---

### **BR-23. Log phải chứa đầy đủ thông tin**

* Hành động
* Người thực hiện
* Thời điểm
* Note (nếu reject/cancel/update)

---

## Tổng kết Business Rules

* Admin luôn có quyền đặt phòng và booking admin tạo auto-approved.
* Student chỉ được đặt phòng khi hệ thống bật setting, và có giới hạn số booking/ngày.
* Lecturer và User có thể đặt phòng nhưng phải qua phê duyệt.
* Room Manager là người xử lý booking chính (approve/reject).
* Room Manager tạo booking auto-approved.
* Tất cả booking từ non-admin → đều bắt đầu ở trạng thái pending.
* Booking chỉ có hiệu lực trong 1 ngày, nếu đặt nhiều ngày thì tạo nhiều booking.
* Thời gian booking phải nằm trong khung giờ cho phép và theo khung giờ của từng phòng.
* Không cho phép đặt trùng giờ với booking khác (pending + approved).
* Reject booking phải có lý do.
* Mọi thao tác đều được log trong bảng room_booking_actions.