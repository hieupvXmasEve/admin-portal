# 👥 Hướng Dẫn Người Dùng

Hướng dẫn người dùng hoàn chỉnh cho Hệ Thống Quản Lý Dự Án Swinburne.

## 📋 Tổng Quan

Hệ Thống Quản Lý Dự Án Swinburne là một nền tảng quản lý giáo dục toàn diện được thiết kế để tối ưu hóa các quy trình hành chính trong môi trường đại học. Hệ thống cung cấp kiểm soát truy cập dựa trên vai trò với hỗ trợ đa cơ sở.

## 🚀 Bắt Đầu

### Truy Cập Hệ Thống
- **URL**: Truy cập thông qua URL được cung cấp bởi tổ chức của bạn
- **Đăng nhập**: Sử dụng email và mật khẩu được chỉ định
- **Hỗ trợ**: Liên hệ quản trị viên hệ thống cho các vấn đề truy cập

### Tài Khoản Admin Mặc Định (Development)
- **Email**: `hieupv2412@gmail.com`
- **Mật khẩu**: `123456`

## 🏢 Cấu Trúc Hệ Thống

### Tổ Chức Cơ Sở
Hệ thống hỗ trợ nhiều địa điểm cơ sở:
- **HN** - Swinburne Hà Nội
- **HCM** - Swinburne Hồ Chí Minh
- **DN** - Swinburne Đà Năng
- **CT** - Swinburne Cần Thơ

### Truy Cập Dựa Trên Vai Trò
Người dùng có thể có các vai trò khác nhau trên các cơ sở khác nhau:
- **Super Admin** - Quyền truy cập toàn hệ thống
- **Giám Đốc Đào Tạo** - Giám đốc đào tạo
- **Trường Phòng Student HQ** - Trưởng phòng Student HQ
- **Trường Phòng Hành Chính** - Trưởng phòng hành chính
- **Trường Ban Tuyển Sinh** - Trưởng ban tuyển sinh
- **Trường Phòng Tuyển Sinh** - Trưởng phòng tuyển sinh
- **Cán Bộ Đào Tạo** - Cán bộ đào tạo
- **Cán Bộ Student HQ** - Cán bộ Student HQ
- **Cán Bộ Hành Chính** - Cán bộ hành chính
- **Cán Bộ Tuyển Sinh** - Cán bộ tuyển sinh
- **Giảng Viên** - Giảng viên

## 👥 Quản Lý Người Dùng

### Xem Người Dùng
1. Điều hướng đến **Users** từ menu chính
2. Xem danh sách người dùng với các tùy chọn lọc và tìm kiếm
3. Sử dụng phân trang để duyệt qua danh sách người dùng lớn
4. Lọc theo cơ sở, vai trò, hoặc tìm kiếm theo tên/email

### Thêm Người Dùng
1. Nhấp nút **Add User**
2. Điền thông tin bắt buộc:
   - Tên (bắt buộc)
   - Email (bắt buộc)
   - Mật khẩu
   - Số điện thoại
   - Địa chỉ
3. Gán các kết hợp cơ sở và vai trò
4. Lưu để tạo người dùng

### Chỉnh Sửa Người Dùng
1. Nhấp nút **Edit** bên cạnh người dùng trong danh sách
2. Sửa đổi thông tin người dùng theo nhu cầu
3. Cập nhật các gán cơ sở-vai trò
4. Lưu thay đổi

### Import/Export Người Dùng

#### Import Người Dùng
1. Điều hướng đến **Users** → **Import Users**
2. Chọn định dạng import:
   - **Simple Format**: Sheet đơn với tất cả thông tin
   - **Detailed Format**: Nhiều sheet cho người dùng và vai trò
   - **Relationship Format**: Một hàng cho mỗi mối quan hệ cơ sở-vai trò
3. Tải template cho định dạng bạn chọn
4. Điền template với dữ liệu người dùng
5. Upload file đã hoàn thành
6. Cấu hình tùy chọn import:
   - Xử lý trùng lặp (Skip/Update/Error)
   - Tùy chọn tạo cơ sở thiếu
7. Xem lại preview và xử lý import

#### Export Người Dùng
1. Đi đến trang danh sách **Users**
2. Nhấp nút **Export Excel**
3. File tự động tải về với timestamp
4. Export bao gồm tất cả thông tin người dùng và gán cơ sở-vai trò

## 📊 Quản Lý Dữ Liệu

### Template Import
Hệ thống cung cấp ba định dạng import:

**Headers Simple Format:**
- Name* (bắt buộc)
- Email* (bắt buộc)
- Password
- Campus Codes (phân cách bằng dấu phẩy)
- Role Codes (phân cách bằng dấu phẩy)
- Phone
- Address

**Detailed Format:**
- Sheet 1: Thông tin cơ bản người dùng
- Sheet 2: Mối quan hệ cơ sở-vai trò

**Relationship Format:**
- Một hàng cho mỗi kết hợp người dùng-cơ sở-vai trò

### Yêu Cầu File
- **Định dạng hỗ trợ**: .xlsx, .xls
- **Kích thước file tối đa**: 2MB (có thể cấu hình)
- **Trường bắt buộc**: Được đánh dấu bằng dấu hoa thị (*)
- **Mã cơ sở**: Phải tồn tại trong hệ thống
- **Mã vai trò**: Phải sử dụng định dạng snake_case

## 🔧 Tính Năng Hệ Thống

### Tìm Kiếm và Lọc
- **Tìm kiếm toàn cục**: Tìm kiếm trên tất cả trường người dùng
- **Lọc cơ sở**: Lọc người dùng theo cơ sở
- **Lọc vai trò**: Lọc người dùng theo vai trò
- **Lọc trạng thái**: Lọc theo trạng thái hoạt động/không hoạt động

### Phân Trang
- Điều hướng qua các tập dữ liệu lớn một cách hiệu quả
- Số mục trên mỗi trang có thể cấu hình
- Nhảy đến trang cụ thể

### Validation Dữ Liệu
- Validation form thời gian thực
- Validation phía server cho tính toàn vẹn dữ liệu
- Thông báo lỗi rõ ràng cho dữ liệu không hợp lệ

### Hệ Thống Quyền
- Kiểm soát truy cập dựa trên vai trò
- Quyền cụ thể theo cơ sở
- Kiểm tra quyền cấp tính năng

## 🛠️ Khắc Phục Sự Cố

### Các Vấn Đề Thường Gặp

**Vấn Đề Đăng Nhập:**
- Xác minh email và mật khẩu
- Kiểm tra xem tài khoản có hoạt động không
- Liên hệ quản trị viên để reset mật khẩu

**Lỗi Import:**
- Kiểm tra định dạng và giới hạn kích thước file
- Xác minh các header bắt buộc có mặt
- Đảm bảo mã cơ sở và vai trò tồn tại
- Kiểm tra email trùng lặp

**Từ Chối Quyền:**
- Xác minh vai trò của bạn có quyền cần thiết
- Kiểm tra xem bạn có được gán đến cơ sở đúng không
- Liên hệ quản trị viên để cập nhật quyền

**Vấn Đề Upload File:**
- Kiểm tra kích thước file (tối đa 2MB)
- Xác minh định dạng file (.xlsx hoặc .xls)
- Đảm bảo kết nối internet ổn định

### Nhận Trợ Giúp
1. Kiểm tra thông báo lỗi để có hướng dẫn cụ thể
2. Xem lại hướng dẫn người dùng này cho các quy trình
3. Liên hệ quản trị viên hệ thống của bạn
4. Báo cáo lỗi cho nhóm phát triển

## 📱 Best Practices

### Nhập Dữ Liệu
- Sử dụng định dạng nhất quán cho tên và địa chỉ
- Xác minh địa chỉ email trước khi nhập
- Sử dụng mật khẩu mạnh cho người dùng mới
- Kiểm tra kỹ các gán cơ sở và vai trò

### Import/Export
- Luôn tải và sử dụng template hiện tại
- Kiểm tra với batch nhỏ trước khi import lớn
- Giữ bản sao backup của file import
- Xem lại kết quả import cẩn thận

### Bảo Mật
- Đăng xuất khi hoàn thành sử dụng hệ thống
- Không chia sẻ thông tin đăng nhập
- Báo cáo hoạt động đáng ngờ
- Giữ thông tin cá nhân được cập nhật

## 🔗 Điều Hướng

### Các Mục Menu Chính
- **Dashboard**: Tổng quan hệ thống và hành động nhanh
- **Users**: Quản lý người dùng và import/export
- **Campuses**: Thông tin cơ sở và cài đặt
- **Roles**: Quản lý vai trò và quyền
- **Reports**: Báo cáo hệ thống và phân tích
- **Settings**: Tùy chọn cấu hình hệ thống

### Hành Động Nhanh
- **Add User**: Tạo tài khoản người dùng mới
- **Import Users**: Import người dùng hàng loạt
- **Export Data**: Tải dữ liệu người dùng
- **Search**: Tìm người dùng hoặc dữ liệu cụ thể

## 📞 Hỗ Trợ

### Thông Tin Liên Hệ
- **Quản Trị Viên Hệ Thống**: Liên hệ phòng IT của tổ chức bạn
- **Hỗ Trợ Kỹ Thuật**: Báo cáo lỗi hoặc vấn đề kỹ thuật
- **Đào Tạo**: Yêu cầu đào tạo bổ sung về tính năng hệ thống

### Tài Nguyên
- **Hướng Dẫn Người Dùng**: Tài liệu này
- **Video Hướng Dẫn**: Có sẵn trong phần trợ giúp
- **FAQ**: Câu hỏi thường gặp và câu trả lời
- **Ghi Chú Phát Hành**: Thông tin về cập nhật hệ thống

---

*Cập nhật lần cuối: {{ date('Y-m-d') }}*
