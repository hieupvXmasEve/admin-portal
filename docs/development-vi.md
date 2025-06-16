# 🛠️ Hướng Dẫn Phát Triển

Hướng dẫn phát triển toàn diện cho Hệ Thống Quản Lý Dự Án Swinburne được xây dựng với Laravel 12 + Vue.js 3 + Inertia.js.

## 🏗️ Tổng Quan Kiến Trúc

### Tech Stack
- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue.js 3, TypeScript, TailwindCSS
- **Web Server**: FrankenPHP (máy chủ ứng dụng PHP hiện đại)
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Development**: Docker Compose, Vite HMR

### Cấu Trúc Dự Án
```
swinx/
├── app/
│   ├── Http/Controllers/     # Controllers được tổ chức theo tính năng
│   ├── Models/              # Eloquent models
│   ├── Services/            # Business logic services
│   └── Policies/            # Authorization policies
├── resources/
│   ├── js/
│   │   ├── pages/           # Vue pages theo tính năng
│   │   ├── components/      # Vue components tái sử dụng
│   │   ├── types/           # TypeScript interfaces
│   │   └── composables/     # Logic tái sử dụng
├── routes/                  # Định nghĩa routes theo tính năng
├── database/
│   ├── migrations/          # Database migrations
│   └── seeders/            # Database seeders
└── docs/                   # Tài liệu dự án
```

## 📋 Tiêu Chuẩn Phát Triển

### Cấu Trúc Code
- **Controllers**: Nhóm các controllers liên quan trong thư mục (ví dụ: `Users/`, `Settings/`)
- **Models**: Lưu trữ trong `app/Models/` với relationships phù hợp
- **Routes**: Tổ chức trong các file riêng biệt theo tính năng
- **Frontend**: Sử dụng Composition API với TypeScript

### Quy Ước Đặt Tên
- **Controllers**: PascalCase với hậu tố `Controller`
- **Models**: PascalCase số ít (ví dụ: `User.php`)
- **Vue Components**: PascalCase (ví dụ: `DataTable.vue`)
- **Routes**: kebab-case (ví dụ: `/curriculum-versions`)
- **Database**: snake_case cho bảng và cột

### Validation Form (BẮT BUỘC)
- **Frontend**: Luôn sử dụng shadcn-vue forms với vee-validate và Zod schemas
- **Backend**: Định nghĩa validation rules trong Model sử dụng static methods
- **Tính nhất quán**: Đồng bộ validation giữa frontend và backend

### Tiêu Chuẩn Component
- **DebouncedInput**: Luôn sử dụng cho search và filter inputs
- **DataTable**: Sử dụng DataTable.vue và DataPagination.vue cho dữ liệu dạng bảng
- **Select Components**: Không bao giờ sử dụng empty string values trong SelectItem

## 🎯 Quản Lý Type

### Tổ Chức Type Tập Trung (BẮT BUỘC)
Lưu trữ TẤT CẢ interfaces trong thư mục `resources/js/types/`:

```typescript
// resources/js/types/models.ts
export interface User {
  id: number
  name: string
  email: string
  created_at: string
  updated_at: string
}

// resources/js/types/api.ts
export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    total: number
    per_page: number
  }
}
```

### Tiêu Chuẩn Interface
- Khớp chính xác với cấu trúc model backend
- Bao gồm relationships như optional properties
- Sử dụng kiểu date phù hợp (string cho ISO dates)
- Định nghĩa union types cho enums và status fields

## 🧪 Tiêu Chuẩn Testing

### Các Loại Test Bắt Buộc
- **Feature Tests**: Quy trình người dùng hoàn chỉnh
- **Unit Tests**: Các hàm và phương thức riêng lẻ
- **Component Tests**: Hành vi Vue component
- **API Tests**: API endpoints và responses

### Cấu Trúc Test (Sử Dụng Pest)
```php
describe('UserController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('index', function () {
        it('displays users index page', function () {
            $response = $this->actingAs($this->user)->get('/users');

            $response->assertOk();
            $response->assertInertia(
                fn($page) => $page->component('users/Index')
            );
        });
    });
});
```

## ⚠️ Xử Lý Lỗi

### Xử Lý Lỗi Frontend
- Sử dụng try-catch blocks cho async operations
- Hiển thị thông báo lỗi thân thiện với người dùng bằng toast notifications
- Xử lý lỗi validation form với field highlighting phù hợp
- Triển khai loading states trong API calls

### Xử Lý Lỗi Backend
- Sử dụng Form Requests cho validation với custom error messages
- Trả về error responses nhất quán với HTTP status codes phù hợp
- Log lỗi sử dụng hệ thống logging của Laravel
- Xử lý database exceptions một cách graceful

## 🚀 Hướng Dẫn Performance

### Tối Ưu Database
- Sử dụng eager loading để ngăn N+1 queries
- Triển khai indexing phù hợp trên các cột được query thường xuyên
- Sử dụng pagination cho datasets lớn
- Sử dụng database transactions cho các operations nhiều bước

### Performance Frontend
- Triển khai lazy loading cho các components lớn
- Sử dụng computed properties thay vì methods cho reactive data
- Debounce user inputs cho search và filters
- Sử dụng preserveState và preserveScroll cho UX tốt hơn

## 🔒 Yêu Cầu Bảo Mật

### Authentication & Authorization
- Xác minh user authentication trên tất cả protected routes
- Triển khai permission checks sử dụng middleware
- Validate user permissions trong controllers
- Sử dụng CSRF protection cho tất cả forms

### Validation & Sanitization Dữ Liệu
- Validate tất cả user inputs trên cả frontend và backend
- Sanitize dữ liệu trước khi lưu database
- Validate file uploads với type và size checks phù hợp
- Escape output để ngăn XSS attacks

## 🛠️ Quy Trình Phát Triển

### Phát Triển Hàng Ngày
```bash
# Khởi động môi trường phát triển
./dev.sh start

# Hoặc phát triển truyền thống
composer dev

# Frontend development với hot reload
npm run dev
```

### Quy Trình Testing
```bash
# Chạy tất cả tests
php artisan test

# Chạy test file cụ thể
php artisan test tests/Feature/UserControllerTest.php

# Frontend type checking
npm run type-check

# Linting
npm run lint
```

### Checklist Pre-commit
- [ ] Chạy tất cả tests và đảm bảo pass
- [ ] Kiểm tra code formatting với Prettier và ESLint
- [ ] Xác minh TypeScript compilation không có lỗi
- [ ] Test chức năng trong browser thủ công
- [ ] Kiểm tra console errors và warnings

## 📋 Checklist Triển Khai

### Triển Khai Page Mới
1. [ ] Tạo controller với validation phù hợp
2. [ ] Định nghĩa routes với middleware
3. [ ] Tạo TypeScript interfaces
4. [ ] Xây dựng Vue page component
5. [ ] Triển khai form validation (frontend + backend)
6. [ ] Thêm table với DataTable component
7. [ ] Viết tests toàn diện
8. [ ] Cập nhật navigation/breadcrumbs
9. [ ] Test permissions và authorization
10. [ ] Review và tối ưu performance

### Triển Khai Tính Năng Mới
1. [ ] Lập kế hoạch thay đổi database schema
2. [ ] Tạo/cập nhật models với relationships
3. [ ] Viết migrations và seeders
4. [ ] Triển khai backend logic
5. [ ] Tạo frontend components
6. [ ] Thêm validation rules
7. [ ] Viết tests cho tất cả scenarios
8. [ ] Cập nhật documentation
9. [ ] Test integration points
10. [ ] Deploy và monitor

## 🔗 Tài Liệu Liên Quan

- **[Hướng Dẫn Cài Đặt](installation-vi.md)** - Hướng dẫn setup và cài đặt
- **[Hướng Dẫn Triển Khai](deployment-vi.md)** - Quy trình triển khai production
- **[Tài Liệu API](api-documentation-vi.md)** - API endpoints và tích hợp
- **[Hướng Dẫn Người Dùng](user-guide-vi.md)** - Chức năng và cách sử dụng hệ thống

## 📖 Tài Nguyên Bổ Sung

- **Laravel Documentation**: https://laravel.com/docs
- **Vue.js Documentation**: https://vuejs.org/guide/
- **Inertia.js Documentation**: https://inertiajs.com/
- **Tailwind CSS Documentation**: https://tailwindcss.com/docs
- **shadcn/ui Vue Documentation**: https://www.shadcn-vue.com/

---

*Cập nhật lần cuối: {{ date('Y-m-d') }}*
