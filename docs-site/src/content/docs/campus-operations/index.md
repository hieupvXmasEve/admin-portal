---
title: Campus Operations
description: Phòng học, đặt phòng, duyệt yêu cầu, sự kiện và câu lạc bộ.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Rooms/Index.vue
  - resources/js/pages/RoomBookings/Index.vue
  - resources/js/pages/RoomBookings/Availability.vue
  - resources/js/pages/RoomBookings/MyBookings.vue
  - resources/js/pages/RoomBookings/Pending.vue
  - resources/js/pages/RoomBookings/Calendar.vue
  - resources/js/pages/Events/Index.vue
  - resources/js/pages/Clubs/Index.vue
---

**Campus Operations** quản lý không gian và hoạt động ngoài giờ học: phòng ốc, sự kiện, câu lạc bộ.

## Room Management — Quản lý phòng

### Rooms — Danh sách phòng

**Dùng để làm gì.** Khai báo phòng: mã phòng, sức chứa, trang thiết bị, tình trạng. Màn hình tên **Room Management**.

**Ai vào được.** Người có quyền xem phòng.

**Các bước.** Vào **Campus Operations → Room Management → Rooms**, thêm hoặc sửa phòng.

**Lưu ý**

- Phòng đặt ở trạng thái bảo trì sẽ không xếp lịch hay đặt được. Nhớ trả về trạng thái bình thường khi bảo trì xong.
- Sức chứa khai đúng thì mới lọc được phòng phù hợp ở màn hình tìm phòng trống.

### Find Available Rooms — Tìm phòng trống

**Dùng để làm gì.** Tìm phòng còn trống trong khung giờ cần dùng. **Đây là màn hình nên mở trước khi đặt phòng.**

**Các bước**

1. Vào **Campus Operations → Room Management → Find Available Rooms**.
2. Ở khung **Filters**, nhập ngày, giờ và yêu cầu về sức chứa.
3. Chọn phòng phù hợp trong kết quả rồi tiến hành đặt.

### Bookings — Đặt phòng

**Dùng để làm gì.** Xem toàn bộ lượt đặt phòng. Màn hình tên **Room Bookings**.

**Ai vào được.** Người có quyền xem đặt phòng.

### My Bookings — Lượt đặt của tôi

**Dùng để làm gì.** Xem và quản lý các lượt đặt do chính bạn tạo.

**Ai vào được.** Người có quyền tạo yêu cầu đặt phòng.

### Pending Approvals — Chờ duyệt

**Dùng để làm gì.** Duyệt hoặc từ chối yêu cầu đặt phòng của người khác.

**Ai vào được.** Người có quyền duyệt đặt phòng.

**Các bước**

1. Vào **Campus Operations → Room Management → Pending Approvals**.
2. Xem từng yêu cầu, duyệt hoặc từ chối.
3. Bấm **All Bookings** để xem toàn bộ, không chỉ phần đang chờ.

**Lưu ý.** Yêu cầu chưa duyệt thì phòng **chưa được giữ**. Để tồn đọng là nguyên nhân phổ biến của trùng phòng.

### Room Usage Calendar — Lịch sử dụng phòng

**Dùng để làm gì.** Nhìn toàn bộ lịch dùng phòng dưới dạng lịch, gồm cả lớp học và sự kiện.

**Các bước.** Vào **Campus Operations → Room Management → Room Usage Calendar**. Bấm **Today** để quay về hôm nay.

**Lưu ý.** Đây là chỗ dễ phát hiện trùng lịch nhất. Xem trước khi xác nhận sự kiện lớn hoặc ca thi.

## Events — Sự kiện

### List

**Dùng để làm gì.** Tạo và quản lý sự kiện của trường.

**Ai vào được.** Người có quyền xem sự kiện.

**Các bước.** Vào **Campus Operations → Events → List**, tạo sự kiện hoặc mở sự kiện có sẵn.

**Lưu ý.** Đặt phòng trước ở **Find Available Rooms**, rồi mới chốt thời gian sự kiện.

### Event Reports

**Dùng để làm gì.** Thống kê sự kiện: số lượng, mức tham dự.

## Clubs — Câu lạc bộ

**Dùng để làm gì.** Quản lý câu lạc bộ sinh viên và thành viên.

**Ai vào được.** Người có quyền xem câu lạc bộ.

**Các bước.** Vào **Campus Operations → Clubs**.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Cần phòng cho một buổi họp | Find Available Rooms → đặt phòng → chờ duyệt |
| Tổ chức sự kiện | Find Available Rooms → Events → List |
| Xếp ca thi lại | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| Bị báo trùng phòng | Room Usage Calendar → Pending Approvals |
| Phòng hỏng, tạm ngừng dùng | Rooms (chuyển sang trạng thái bảo trì) |
