# 🔌 Tài Liệu API

Tài liệu API hoàn chỉnh cho Hệ Thống Quản Lý Dự Án Swinburne.

## 📋 Tổng Quan

Hệ Thống Quản Lý Dự Án Swinburne cung cấp RESTful API để tích hợp với các hệ thống bên ngoài. API tuân theo quy ước Laravel và sử dụng JSON để trao đổi dữ liệu.

## 🔐 Xác Thực

### Xác Thực API Token
Hệ thống sử dụng Laravel Sanctum cho xác thực API:

```bash
# Đăng nhập để lấy token
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

# Response
{
  "token": "your-api-token",
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com"
  }
}
```

### Sử Dụng API Token
Bao gồm token trong Authorization header:

```bash
Authorization: Bearer your-api-token
```

## 📊 Định Dạng Response

### Cấu Trúc Response Chuẩn
```json
{
  "success": true,
  "data": {},
  "message": "Thao tác thành công",
  "errors": null
}
```

### Cấu Trúc Error Response
```json
{
  "success": false,
  "data": null,
  "message": "Thông báo lỗi",
  "errors": {
    "field": ["Thông báo lỗi validation"]
  }
}
```

### Pagination Response
```json
{
  "success": true,
  "data": {
    "data": [],
    "meta": {
      "current_page": 1,
      "total": 100,
      "per_page": 15,
      "last_page": 7
    }
  }
}
```

## 👥 API Quản Lý Người Dùng

### Danh Sách Người Dùng
```bash
GET /api/users
Authorization: Bearer your-api-token

# Query Parameters
?page=1&per_page=15&search=john&campus=HN&role=admin
```

### Chi Tiết Người Dùng
```bash
GET /api/users/{id}
Authorization: Bearer your-api-token
```

### Tạo Người Dùng
```bash
POST /api/users
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "phone": "+1234567890",
  "address": "123 Main St",
  "campus_roles": [
    {
      "campus_code": "HN",
      "role_code": "can_bo_dao_tao"
    }
  ]
}
```

### Cập Nhật Người Dùng
```bash
PUT /api/users/{id}
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "John Doe Updated",
  "phone": "+1234567891",
  "campus_roles": [
    {
      "campus_code": "HN",
      "role_code": "giang_vien"
    }
  ]
}
```

### Xóa Người Dùng
```bash
DELETE /api/users/{id}
Authorization: Bearer your-api-token
```

## 🏢 API Quản Lý Cơ Sở

### Danh Sách Cơ Sở
```bash
GET /api/campuses
Authorization: Bearer your-api-token
```

### Chi Tiết Cơ Sở
```bash
GET /api/campuses/{id}
Authorization: Bearer your-api-token
```

### Tạo Cơ Sở
```bash
POST /api/campuses
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "Swinburne New Campus",
  "code": "NC",
  "address": "456 University Ave",
  "phone": "+1234567890",
  "email": "newcampus@swinburne.edu"
}
```

## 🎭 API Quản Lý Vai Trò

### Danh Sách Vai Trò
```bash
GET /api/roles
Authorization: Bearer your-api-token
```

### Chi Tiết Vai Trò
```bash
GET /api/roles/{id}
Authorization: Bearer your-api-token
```

### Tạo Vai Trò
```bash
POST /api/roles
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "name": "New Role",
  "code": "new_role",
  "description": "Mô tả vai trò mới",
  "permissions": ["permission1", "permission2"]
}
```

## 📤 API Import/Export

### Import Người Dùng
```bash
POST /api/users/import
Authorization: Bearer your-api-token
Content-Type: multipart/form-data

file: [Excel file]
format: "simple" | "detailed" | "relationship"
duplicate_handling: "skip" | "update" | "error"
create_missing_campuses: true | false
```

### Export Người Dùng
```bash
GET /api/users/export
Authorization: Bearer your-api-token

# Query Parameters
?format=excel&campus=HN&role=admin
```

### Trạng Thái Import
```bash
GET /api/imports/{import_id}/status
Authorization: Bearer your-api-token
```

## 📋 Data Models

### User Model
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "email_verified_at": "2023-01-01T00:00:00Z",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z",
  "campus_roles": [
    {
      "campus": {
        "id": 1,
        "name": "Swinburne Hà Nội",
        "code": "HN"
      },
      "role": {
        "id": 1,
        "name": "Cán Bộ Đào Tạo",
        "code": "can_bo_dao_tao"
      },
      "assigned_at": "2023-01-01T00:00:00Z"
    }
  ]
}
```

### Campus Model
```json
{
  "id": 1,
  "name": "Swinburne Hà Nội",
  "code": "HN",
  "address": "123 University St",
  "phone": "+84123456789",
  "email": "hanoi@swinburne.edu",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z"
}
```

### Role Model
```json
{
  "id": 1,
  "name": "Cán Bộ Đào Tạo",
  "code": "can_bo_dao_tao",
  "description": "Vai trò cán bộ đào tạo",
  "created_at": "2023-01-01T00:00:00Z",
  "updated_at": "2023-01-01T00:00:00Z",
  "permissions": [
    {
      "id": 1,
      "name": "view_users",
      "display_name": "Xem Người Dùng"
    }
  ]
}
```

## ⚠️ Mã Lỗi

### HTTP Status Codes
- **200**: Thành công
- **201**: Đã tạo
- **400**: Yêu cầu không hợp lệ
- **401**: Chưa xác thực
- **403**: Bị cấm
- **404**: Không tìm thấy
- **422**: Lỗi validation
- **500**: Lỗi server nội bộ

### Mã Lỗi Tùy Chỉnh
- **USER_NOT_FOUND**: Không tìm thấy người dùng với ID được chỉ định
- **CAMPUS_NOT_FOUND**: Không tìm thấy cơ sở với mã được chỉ định
- **ROLE_NOT_FOUND**: Không tìm thấy vai trò với mã được chỉ định
- **DUPLICATE_EMAIL**: Địa chỉ email đã tồn tại
- **INVALID_FILE_FORMAT**: Định dạng file upload không được hỗ trợ
- **IMPORT_VALIDATION_FAILED**: Validation dữ liệu import thất bại

## 🔒 Giới Hạn Tốc Độ

Các API endpoints bị giới hạn tốc độ để ngăn chặn lạm dụng:
- **Authentication endpoints**: 5 requests mỗi phút
- **General API endpoints**: 60 requests mỗi phút
- **Import/Export endpoints**: 10 requests mỗi phút

## 📝 Ví Dụ

### Ví Dụ Tạo Người Dùng Hoàn Chỉnh
```bash
curl -X POST https://your-domain.com/api/users \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "securepassword123",
    "phone": "+84987654321",
    "address": "456 Academic Road",
    "campus_roles": [
      {
        "campus_code": "HCM",
        "role_code": "giang_vien"
      },
      {
        "campus_code": "HN",
        "role_code": "can_bo_dao_tao"
      }
    ]
  }'
```

### Ví Dụ Import Người Dùng Hàng Loạt
```bash
curl -X POST https://your-domain.com/api/users/import \
  -H "Authorization: Bearer your-api-token" \
  -F "file=@users_import.xlsx" \
  -F "format=simple" \
  -F "duplicate_handling=update" \
  -F "create_missing_campuses=false"
```

## 🧪 Testing

### Công Cụ API Testing
- **Postman**: Import collection từ `/api/documentation/postman`
- **Insomnia**: Import workspace từ `/api/documentation/insomnia`
- **cURL**: Sử dụng ví dụ được cung cấp trong tài liệu này

### Môi Trường Test
- **Base URL**: `https://your-domain.com/api`
- **Test Token**: Sử dụng login endpoint để lấy token hợp lệ
- **Test Data**: Sử dụng dữ liệu seeded để testing

## 📞 Hỗ Trợ

Để hỗ trợ tích hợp API:
- **Tài liệu**: Hướng dẫn này và docs API inline
- **Postman Collection**: Có sẵn tại `/api/documentation/postman`
- **Hỗ trợ kỹ thuật**: Liên hệ nhóm phát triển
- **Vấn đề Rate Limit**: Liên hệ quản trị viên để tăng giới hạn

---

*Cập nhật lần cuối: {{ date('Y-m-d') }}*
