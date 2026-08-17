---
title: Hướng dẫn sử dụng Portal
description: Hướng dẫn sử dụng Portal cho đội vận hành học vụ, viết theo menu thật của hệ thống.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

Tài liệu dành cho **cán bộ, nhân viên nhà trường** dùng hệ thống Portal trên trình duyệt. Không cần biết kỹ thuật.

Sinh viên và giảng viên dùng cổng riêng, không nằm trong tài liệu này.

## Bắt đầu từ đâu

Người mới đọc [Bắt đầu](/bat-dau/) trước: đăng nhập, chọn cơ sở, cách đọc màn hình. Đọc một lần là đủ dùng cho mọi chương sau.

Sau đó vào khu vực công việc của bạn — mười khu vực tương ứng chín nhóm menu, liệt kê bên dưới.

## Phạm vi

| Khu vực | Trạng thái |
| --- | --- |
| [Bắt đầu](/bat-dau/) | Đã viết |
| [Academic Operations — Học vụ (gồm cả Giảng viên)](/academic-operations/) | Đã viết |
| [Students — Sinh viên](/student-services/) | Đã viết |
| [Reports & Audits — Báo cáo, đối chiếu](/reports-audits/) | Đã viết |
| [Finance — Học phí](/finance-office/) | Đã viết |
| [Store & Clubs — Cửa hàng, câu lạc bộ](/store-clubs/) | Đã viết |
| [Campus — Phòng học, sự kiện](/campus/) | Đã viết |
| [Forms & Surveys — Biểu mẫu, khảo sát](/forms-quality/) | Đã viết |
| [Communications — Email, thông báo](/communications/) | Đã viết |
| [Administration — Người dùng, phân quyền](/administration/) | Đã viết |

## Quy ước trong tài liệu

- Đường dẫn menu viết dạng **Nhóm menu → Mục → Mục con**.
- Tên trên màn hình hiện là tiếng Anh, nên tài liệu giữ nguyên tên đó và ghi nghĩa tiếng Việt bên cạnh. Ví dụ: **Programs (Chương trình đào tạo)**.
- Mỗi màn hình viết theo bốn phần: *Dùng để làm gì · Ai vào được · Các bước · Lưu ý*.
- Nếu một mục trong tài liệu không có trên menu của bạn, nghĩa là tài khoản chưa được cấp quyền vào mục đó. Liên hệ quản trị viên.

## Dành cho người bảo trì tài liệu

Mỗi trang khai `source` ở đầu tệp — danh sách tệp mã nguồn mà trang đó mô tả. Khi tệp nguồn thay đổi, `scripts/check-docs-freshness.sh` nhắc cập nhật trang tương ứng ngay trong pull request đó.
