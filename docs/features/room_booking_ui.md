# Tính năng Room Booking UI

## I. Danh sách UI màn hình (screens)

Tôi chia thành 3 nhóm người dùng: **Student/Lecturer → Room Manager → Admin**.

## **1. Room List (Danh sách phòng)**

**Mục đích:** Để người dùng xem danh sách phòng đang có và xem phòng nào được book.

### Chức năng:

* Xem danh sách phòng theo campus
* Filter theo:

  * building
  * room type
  * capacity
  * is_bookable
* Xem trạng thái phòng:

  * available / maintenance / out_of_service
* Xem chi tiết phòng (room detail)

### Tại sao cần?

→ Đây là entry point để user chọn phòng trước khi tạo booking.

---

## **2. Room Detail (Chi tiết phòng)**

Hiển thị:

* room name, type, capacity
* available_from / available_until
* is_bookable
* requires_approval
* “today timetable” hoặc “weekly calendar” của phòng
* nút **Book this room**

---

## **3. Booking Form (Trang tạo booking)**

Form bao gồm:

* Booking Date
* Start time / End time
* Title
* Description
* Contact person (auto-fill from user)
* required_equipment (optional)
* special_requirements
* File upload (optional)

Nếu user là admin → hiển thị checkbox:

* `[x] Approve immediately` (auto-approve)

Kiểm tra realtime:

* time conflict
* out of allowed time range

Sau khi submit:

* Student → booking vào pending
* Lecturer → pending
* Room Manager → pending (trừ bạn muốn họ auto approve – hiện tại không)
* Admin → auto-approved

---

## **4. My Bookings (Danh sách booking tôi đã tạo)**

Áp dụng cho TẤT CẢ user (student, lecturer, admin).

Hiển thị:

* danh sách booking
* trạng thái: pending / approved / rejected / cancelled
* filter theo ngày
* nút: edit, cancel
* xem lý do reject nếu bị từ chối

---

## **5. Booking Detail (Chi tiết booking)**

Hiển thị:

* thông tin phòng
* thời gian
* người đặt
* trạng thái
* lịch sử phê duyệt (từ bảng room_booking_actions)
* nút:

  * **Cancel booking**
  * **Edit booking** (nếu chưa approved)

---

# 🟩 **II. MÀN HÌNH DÀNH RIÊNG CHO ROOM MANAGER**

## **6. Approval Dashboard (Trang duyệt booking)**

Đây là trang QUAN TRỌNG NHẤT cho Room Manager.

Gồm 3 tabs:

### **6.1. Pending Bookings (cần duyệt)**

* Hiển thị tất cả booking pending
* Filter:

  * theo campus/building/room
  * theo đối tượng đặt (student/lecturer/user)
  * theo ngày
* Nút:

  * **Approve**
  * **Reject (kèm lý do)**
  * **View detail**

### **6.2. Approved Bookings**

* Xem những booking đã duyệt
* Có thể cancel hoặc edit (tùy permission)

### **6.3. Rejected Bookings**

* Xem những booking bị từ chối
* Có lý do reject

---

## **7. Booking Calendar (Lịch phòng tổng hợp)**

Một màn hình quan trọng để Room Manager theo dõi tổng quan.

View theo:

* Day / Week / Month
* Xem tất cả booking đã approved + pending
* Màu sắc theo trạng thái

Tính năng:

* Click vào 1 slot → tạo booking nhanh
* Click vào booking → mở Booking Detail

---

## **8. Booking Log (Lịch sử thao tác)**

Từ bảng `room_booking_actions`.

Hiển thị:

* booking_id
* action type
* action_by
* time
* note

Dùng cho audit hoặc xử lý khiếu nại.

---

# 🟥 **III. MÀN HÌNH RIÊNG DÀNH CHO ADMIN**

Admin có nhiều quyền hơn Room Manager.

## **9. Booking Settings (Cấu hình Booking)**

Trang config cho toàn hệ thống.

Thông số:

### **9.1. Student Booking Settings**

* Allow student booking? (on/off)
* Student booking limit per day (number)

### **9.2. System time range**

* System booking start time
* System booking end time

### **9.3. Room Manager role assignment**

* Assign user(s) làm Room Manager

### **9.4. Admin override**

* Allow admin bypass conflict? (on/off)

---

## **10. Room Management**

Admin quản lý thông tin phòng từ bảng `rooms`:

* is_bookable
* requires_approval
* available_from / available_until
* capacity
* room status

---

## **11. Full Booking List**

Admin xem tất cả booking (không chỉ pending):

* filter theo trạng thái
* filter theo người đặt
* filter theo phòng
* filter theo campus
* xuất file (CSV/XLS)

---

# 🟪 **IV. MÀN HÌNH CHO MOBILE (Student Portal / Lecturer App)**

Nếu bạn có mobile app, cần các UI đơn giản:

### **12. Mobile – Quick Book**

* chọn phòng nhanh
* chọn giờ nhanh
* Giao diện tối giản

### **13. Mobile – My Bookings**

* danh sách booking của tôi
* xem detail
* cancel

---

# 🎯 **TÓM TẮT NGẮN GỌN**

Hệ thống Room Booking gồm các màn hình sau:

### **Cho tất cả user**

1. Room List
2. Room Detail
3. Create Booking Form
4. My Bookings
5. Booking Detail

### **Cho Room Manager**

6. Approval Dashboard
7. Booking Calendar
8. Booking History Log

### **Cho Admin**

9. Booking Settings
10. Room Management
11. All Bookings Overview

### **Cho Mobile (optional)**

12. Quick Book
13. My Bookings