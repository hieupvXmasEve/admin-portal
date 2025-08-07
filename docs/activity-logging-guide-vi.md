# Tài liệu Hệ thống Ghi nhật ký Hoạt động

## Mục lục

1.  [Tổng quan](#tổng-quan)
2.  [Kiến trúc](#kiến-trúc)
3.  [Hướng dẫn Bắt đầu Nhanh](#hướng-dẫn-bắt-đầu-nhanh)
4.  [Ghi nhật ký theo Cơ sở](#ghi-nhật-ký-theo-cơ-sở)
5.  [Triển khai trên Model](#triển-khai-trên-model)
6.  [Xem Nhật ký Hoạt động](#xem-nhật-ký-hoạt-động)
7.  [Cấu hình Nâng cao](#cấu-hình-nâng-cao)
8.  [Xử lý sự cố](#xử-lý-sự-cố)
9.  [Thực hành Tốt nhất](#thực-hành-tốt-nhất)

## Tổng quan

Hệ thống Ghi nhật ký Hoạt động cung cấp các dấu vết kiểm toán toàn diện cho tất cả các thay đổi trong cơ sở dữ liệu của hệ thống quản lý học thuật. Được xây dựng trên nền tảng của [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog/), hệ thống này cung cấp:

-   **Ghi nhật ký theo cơ sở**: Mọi hoạt động được tự động phân loại theo từng cơ sở (campus).
-   **Ba cấp độ ghi nhật ký**: Tối thiểu (Minimal), Tiêu chuẩn (Standard), và Toàn diện (Comprehensive).
-   **Bối cảnh nâng cao**: Siêu dữ liệu phong phú bao gồm thông tin người dùng, địa chỉ IP, và bối cảnh học thuật.
-   **Giao diện thân thiện**: Trình xem nhật ký hoạt động dựa trên Vue.js với chức năng lọc và tìm kiếm.

### Các tính năng chính

-   ✅ **Tự động gắn thẻ cơ sở** để phân tách dữ liệu giữa các đơn vị.
-   ✅ **Theo dõi thay đổi toàn diện** với so sánh giá trị cũ/mới.
-   ✅ **Kiểm soát truy cập dựa trên vai trò** để xem nhật ký hoạt động.
-   ✅ **Giám sát hoạt động thời gian thực** với bộ lọc nâng cao.
-   ✅ **Ghi nhật ký tập trung vào bảo mật** cho các hoạt động nhạy cảm.
-   ✅ **Tối ưu hóa hiệu năng** với các truy vấn cơ sở dữ liệu hiệu quả.

---

## Kiến trúc

### Các thành phần cốt lõi

```
├── app/Support/CampusLogContext.php          # Helper quản lý bối cảnh cơ sở
├── app/Models/AuditableModel.php             # Model cơ sở có thể kiểm toán
├── app/Models/UserAuditableModel.php         # Model cơ sở cho người dùng
├── app/Models/StudentAuditableModel.php      # Model cơ sở cho sinh viên
├── app/Http/Controllers/Web/ActivityLogController.php  # Controller cho trình xem nhật ký
└── resources/js/pages/systems/ActivityLogs.vue        # Giao diện người dùng
```

### Lược đồ Cơ sở dữ liệu

Hệ thống sử dụng ba bảng chính:
-   `activity_log`: Lưu trữ chính cho các hoạt động (cung cấp bởi spatie/laravel-activitylog).
-   `campuses`: Thông tin các cơ sở để phân loại.
-   Các bảng model tiêu chuẩn có hỗ trợ soft deletes.

---

## Hướng dẫn Bắt đầu Nhanh

### Dành cho Thành viên mới

#### 1. Hiểu về các Cấp độ Ghi nhật ký

**Ghi nhật ký Tối thiểu** (`LOG_LEVEL_MINIMAL`)
-   Chỉ ghi lại các thay đổi trạng thái quan trọng.
-   Phù hợp nhất cho: Các model có tần suất thay đổi cao nhưng yêu cầu kiểm toán tối thiểu.
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_MINIMAL;
}
```

**Ghi nhật ký Tiêu chuẩn** (`LOG_LEVEL_STANDARD`) - Mặc định
-   Ghi lại các trường nghiệp vụ chính và thay đổi trạng thái.
-   Phù hợp nhất cho: Hầu hết các model nghiệp vụ.
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_STANDARD;
}
```

**Ghi nhật ký Toàn diện** (`LOG_LEVEL_COMPREHENSIVE`)
-   Ghi lại tất cả các trường có thể điền (trừ dữ liệu nhạy cảm).
-   Phù hợp nhất cho: Các model quan trọng về bảo mật, hồ sơ sinh viên, dữ liệu tài chính.
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_COMPREHENSIVE;
}
```

#### 2. Cách Hoạt động được tạo ra

Hoạt động được **tạo tự động** khi:
-   Model được tạo (`created` event).
-   Model được cập nhật (`updated` event).
-   Model được xóa (`deleted` event).
-   Model được khôi phục (`restored` event).

**Không cần can thiệp thủ công** - chỉ cần sử dụng model của bạn như bình thường:

```php
// Thao tác này tự động tạo một nhật ký hoạt động
$user = User::find(1);
$user->update(['name' => 'Tên Mới']);

// Thao tác này cũng tạo một nhật ký hoạt động
$program = Program::create(['name' => 'Chương trình Mới', 'code' => 'NP001']);
```

#### 3. Xem Nhật ký Hoạt động

**Đối với Người dùng thông thường:**
-   Điều hướng đến: **System → Activity Logs** (Hệ thống → Nhật ký Hoạt động).
-   Chỉ thấy các hoạt động từ các cơ sở bạn có quyền truy cập.
-   Sử dụng tìm kiếm và bộ lọc để tìm các hoạt động cụ thể.

**Đối với Quản trị viên Hệ thống:**
-   Điều hướng đến: **System → Activity Logs**.
-   Thấy tất cả hoạt động trên mọi cơ sở.
-   Sử dụng bộ lọc cơ sở để tập trung vào các cơ sở cụ thể.
-   Truy cập vào chi tiết hoạt động nâng cao.

---

## Ghi nhật ký theo Cơ sở

### Tự động phát hiện Cơ sở

Hệ thống tự động xác định bối cảnh cơ sở từ:

1.  **ID Cơ sở trong Session** (phổ biến nhất)
    ```php
    session('current_campus_id') // → 1
    ```

2.  **Trường campus_id của Model**
    ```php
    $student->campus_id // → 2
    ```

3.  **Bối cảnh Cơ sở của Người dùng**
    ```php
    CampusLogContext::getCurrentCampusId() // → 3
    ```

### Quy ước đặt tên Nhật ký

Tất cả các hoạt động đều sử dụng quy ước đặt tên theo cơ sở:

-   ✅ `user_campus_1` - Hoạt động người dùng tại Cơ sở ID 1.
-   ✅ `student_campus_2` - Hoạt động sinh viên tại Cơ sở ID 2.
-   ✅ `program_system` - Hoạt động chương trình trên toàn hệ thống.
-   ❌ `default` - Tên cũ (không nên xuất hiện trong nhật ký mới).

### Thuộc tính Bối cảnh Cơ sở

Mỗi hoạt động bao gồm thông tin cơ sở phong phú:

```json
{
  "campus_id": 1,
  "campus_code": "HN",
  "campus_name": "Asia Hà Nội",
  "campus_address": "Số 1 Đường Trần Đặng Ninh...",
  "acting_user_campus": 1,
  "academic_year": "2025-2026",
  "local_timestamp": "2025-08-07T15:21:13.390698Z",
  "session_id": "abc123...",
  "ip_address": "127.0.0.1",
  "user_agent": "Mozilla/5.0...",
  "user_id": 5,
  "user_email": "admin@example.com",
  "user_name": "John Admin"
}
```

---

## Triển khai trên Model

### Các Model đã được triển khai

#### Model Ưu tiên Cao (Ghi nhật ký Toàn diện)
-   ✅ **Student** - Theo dõi toàn bộ hồ sơ học tập.
-   ✅ **CourseRegistration** - Thay đổi đăng ký môn học và điểm số.
-   ✅ **CourseOffering** - Thay đổi lịch trình khóa học.
-   ✅ **Semester** - Thay đổi lịch học.
-   ✅ **StudentApplication** - Theo dõi quy trình tuyển sinh.

#### Model Bảo mật (Ghi nhật ký Toàn diện)
-   ✅ **User** - Thay đổi tài khoản.
-   ✅ **Role** - Quản lý vai trò.
-   ✅ **Permission** - Thay đổi quyền.
-   ✅ **CampusUserRole** - Gán vai trò (quan trọng về bảo mật).
-   ✅ **RolePermission** - Cấp/thu hồi quyền.

#### Model Nghiệp vụ (Ghi nhật ký Tiêu chuẩn)
-   ✅ **Program** - Thay đổi chương trình học.
-   ✅ **Campus** - Thay đổi về cơ sở hạ tầng.

### Thêm Ghi nhật ký vào Model mới

#### Đối với Model thông thường

1.  **Thay đổi lớp cha từ `Model` thành `AuditableModel`:**

    ```php
    // Trước đây
    use Illuminate\Database\Eloquent\Model;
    class MyModel extends Model { }

    // Sau này
    use App\Models\AuditableModel;
    class MyModel extends AuditableModel { }
    ```

2.  **Tùy chỉnh ghi nhật ký (tùy chọn):**

    ```php
    class MyModel extends AuditableModel
    {
        // Đặt cấp độ ghi nhật ký
        protected function getLoggingLevel(): string
        {
            return static::LOG_LEVEL_COMPREHENSIVE;
        }

        // Tùy chỉnh các trường được ghi lại
        protected function getStandardLogFields(): array
        {
            return ['name', 'code', 'status', 'important_field'];
        }

        // Loại trừ các trường nhạy cảm
        protected function getExcludedLogFields(): array
        {
            return ['password', 'secret_token', 'internal_notes'];
        }

        // Mô tả hoạt động tùy chỉnh
        public function getDescriptionForEvent(string $eventName): string
        {
            $identifier = $this->name ?? "ID {$this->id}";
            
            return match ($eventName) {
                'created' => "Đã tạo bản ghi mới: {$identifier}",
                'updated' => "Đã cập nhật bản ghi: {$identifier}",
                'deleted' => "Đã xóa bản ghi: {$identifier}",
                default => "{$eventName} bản ghi: {$identifier}",
            };
        }

        // Thêm thuộc tính tùy chỉnh vào nhật ký
        protected function getCustomLogProperties(): array
        {
            return [
                'category' => $this->category,
                'priority' => $this->priority,
                'related_count' => $this->relatedItems()->count(),
            ];
        }
    }
    ```

#### Đối với Model Người dùng/Xác thực

Sử dụng `UserAuditableModel` thay thế:

```php
use App\Models\UserAuditableModel;

class MyAuthModel extends UserAuditableModel
{
    // Triển khai model của bạn
}
```

#### Đối với Model Sinh viên

Sử dụng `StudentAuditableModel` cho các model xác thực liên quan đến sinh viên:

```php
use App\Models\StudentAuditableModel;

class MyStudentModel extends StudentAuditableModel
{
    // Triển khai model của bạn
}
```

### Ví dụ: Thêm Ghi nhật ký vào Model Tòa nhà

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Building extends AuditableModel
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'campus_id'];

    // Sử dụng ghi nhật ký toàn diện cho cơ sở hạ tầng
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    // Định danh tùy chỉnh
    protected function getIdentifierForLog(): string
    {
        return $this->name ?? $this->code ?? "Building ID {$this->id}";
    }

    // Mô tả hoạt động tùy chỉnh
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();
        
        return match ($eventName) {
            'created' => "Đã thêm tòa nhà mới: {$identifier}",
            'updated' => "Đã sửa đổi tòa nhà: {$identifier}",
            'deleted' => "Đã xóa tòa nhà: {$identifier}",
            default => "{$eventName} tòa nhà: {$identifier}",
        };
    }

    // Thêm bối cảnh dành riêng cho tòa nhà
    protected function getCustomLogProperties(): array
    {
        return [
            'building_code' => $this->code,
            'campus_id' => $this->campus_id,
            'room_count' => $this->rooms()->count(),
        ];
    }
}
```

---

## Xem Nhật ký Hoạt động

### Truy cập Giao diện Nhật ký Hoạt động

**URL:** `/systems/activity-logs`
**Route:** `system.activity-logs.index`
**Menu:** System → Activity Logs

### Các tính năng Giao diện Người dùng

#### Đối với Người dùng Thông thường
-   **Bối cảnh Cơ sở**: Hiển thị cơ sở hiện tại ở tiêu đề.
-   **Chế độ xem đã lọc**: Chỉ thấy các hoạt động từ các cơ sở được cấp quyền.
-   **Bộ lọc cơ bản**: Tìm kiếm, loại đối tượng, loại sự kiện.

#### Đối với Quản trị viên Hệ thống
-   **Chọn Cơ sở**: Dropdown để lọc theo cơ sở cụ thể.
-   **Chế độ xem liên cơ sở**: Có thể xem tất cả hoạt động hoặc lọc theo cơ sở.
-   **Bối cảnh Nâng cao**: Thông tin bổ sung về cơ sở và người dùng.

### Các cột trong Bảng Hoạt động

| Cột           | Mô tả                                   | Ví dụ                               |
|---------------|-----------------------------------------|-------------------------------------|
| **ID**        | Mã định danh duy nhất của hoạt động      | `#12345`                            |
| **Mô tả**     | Mô tả hoạt động dễ đọc                  | `Cập nhật sinh viên: John Doe (ST001)` |
| **Sự kiện**   | Loại thao tác cơ sở dữ liệu             | `created`, `updated`, `deleted`     |
| **Đối tượng** | Model đã bị thay đổi                    | `App\Models\Student`             |
| **Chủ thể**   | Bản ghi cụ thể đã thay đổi              | `John Doe`                          |
| **Người tạo** | Người dùng đã thực hiện thay đổi        | `Admin User`                        |
| **Cơ sở**     | Cơ sở nơi hoạt động xảy ra              | `Asia Hà Nội (HN)`                  |
| **Thời gian** | Thời điểm hoạt động xảy ra              | `2025-08-07 15:30:45`               |

### Modal Chi tiết Hoạt động

Nhấp vào bất kỳ hàng hoạt động nào để xem chi tiết toàn diện:

#### Thông tin cơ bản
-   ID hoạt động, mô tả, loại sự kiện.
-   Thông tin đối tượng và người tạo.
-   Dấu thời gian.

#### Bối cảnh Cơ sở
-   Tên, mã, địa chỉ cơ sở.
-   Năm học và dấu thời gian cục bộ.
-   Cơ sở của người dùng thực hiện (nếu khác).

#### Thông tin Thay đổi
-   Danh sách các trường đã thay đổi dưới dạng huy hiệu.
-   So sánh song song giá trị cũ và mới.
-   Số lượng thay đổi và loại thao tác.

#### Toàn bộ Thuộc tính
-   JSON hoàn chỉnh của tất cả các thuộc tính được ghi lại.
-   Chi tiết kỹ thuật để gỡ lỗi.

### Tìm kiếm và Lọc

#### Chức năng Tìm kiếm
Hộp tìm kiếm hỗ trợ:
-   Mô tả hoạt động
-   Tên nhật ký
-   Tên cơ sở
-   Tên người dùng
-   Tên người tạo

**Ví dụ tìm kiếm:**
-   `"John Doe"` - Tìm các hoạt động liên quan đến John Doe.
-   `"sinh viên"` - Tìm tất cả các hoạt động liên quan đến sinh viên.
-   `"cập nhật"` - Tìm tất cả các thao tác cập nhật.
-   `"cơ sở 1"` - Tìm các hoạt động tại Cơ sở ID 1.

#### Các bộ lọc có sẵn

1.  **Bộ lọc Loại Đối tượng**
    -   Lọc theo loại model (ví dụ: `App\Models\User`).
    -   Chỉ hiển thị các loại có sẵn dựa trên quyền truy cập của bạn.

2.  **Bộ lọc Sự kiện**
    -   `created` - Tạo bản ghi.
    -   `updated` - Cập nhật bản ghi.
    -   `deleted` - Xóa bản ghi.
    -   `restored` - Khôi phục bản ghi.

3.  **Bộ lọc Cơ sở** (Chỉ dành cho Quản trị viên Hệ thống)
    -   `Tất cả Cơ sở` - Xem hoạt động từ tất cả các cơ sở.
    -   Lựa chọn cơ sở cụ thể.
    -   Hiển thị tên và mã cơ sở.

#### Xóa Bộ lọc
-   **Nút Xóa**: Xuất hiện khi có bất kỳ bộ lọc nào đang hoạt động.
-   **Đặt lại Tất cả**: Xóa tìm kiếm, bộ lọc và phân trang.

---

## Cấu hình Nâng cao

### Helper Bối cảnh Cơ sở

Helper `CampusLogContext` cung cấp quản lý thông tin cơ sở tập trung:

```php
use App\Support\CampusLogContext;

// Lấy bối cảnh cơ sở toàn diện
$context = CampusLogContext::getCampusContext();

// Lấy ID cơ sở hiện tại
$campusId = CampusLogContext::getCurrentCampusId();

// Lấy tên nhật ký theo cơ sở
$logName = CampusLogContext::getLogName('User', $campusId);

// Kiểm tra xem người dùng có phải là quản trị viên hệ thống không
$isAdmin = CampusLogContext::isSystemAdmin();

// Lấy các ID cơ sở mà người dùng hiện tại có thể truy cập
$campusIds = CampusLogContext::getAccessibleCampusIds();

// Bổ sung thuộc tính hoạt động với bối cảnh cơ sở
$enhanced = CampusLogContext::enhanceLogProperties($existingProperties);
```

### Ghi nhật ký Hoạt động Tùy chỉnh

Đối với các kịch bản phức tạp, bạn có thể ghi nhật ký hoạt động thủ công:

```php
use Spatie\Activitylog\Models\Activity;

// Ghi nhật ký hoạt động thủ công
activity()
    ->performedOn($model)
    ->causedBy($user)
    ->withProperties([
        'custom_field' => 'custom_value',
        'additional_context' => 'important_info'
    ])
    ->useLogName(CampusLogContext::getLogName('CustomModel'))
    ->log('Mô tả hoạt động tùy chỉnh');

// Ghi nhật ký không có model chủ thể
activity()
    ->causedBy(auth()->user())
    ->withProperties(CampusLogContext::getCampusContext())
    ->useLogName('system_operation')
    ->log('Hoàn tất bảo trì hệ thống');
```

### Tối ưu hóa Hiệu năng

#### Chỉ mục Cơ sở dữ liệu

Hệ thống tự động tối ưu hóa các truy vấn bằng cách sử dụng:
-   Chỉ mục trên `log_name` để lọc theo cơ sở.
-   Chỉ mục trên `created_at` cho các truy vấn theo thời gian.
-   Chỉ mục phức hợp cho các kết hợp bộ lọc phổ biến.

#### Bộ nhớ đệm (Caching)

Thông tin cơ sở được lưu vào bộ nhớ đệm để tăng hiệu năng:

```php
// Xóa bộ nhớ đệm của cơ sở sau khi có thay đổi về cơ sở
CampusLogContext::clearCache($campusId);

// Xóa toàn bộ bộ nhớ đệm của các cơ sở
CampusLogContext::clearCache();
```

### Mở rộng các Model Cơ sở

Bạn có thể mở rộng các model cơ sở có thể kiểm toán cho các nhu cầu cụ thể:

```php
// Model kiểm toán tùy chỉnh với các tính năng bổ sung
abstract class CustomAuditableModel extends AuditableModel
{
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getCustomLogProperties(): array
    {
        return array_merge(parent::getCustomLogProperties(), [
            'custom_field' => $this->calculateCustomField(),
            'environment' => app()->environment(),
        ]);
    }
    
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getCustomIdentifier();
        
        return match ($eventName) {
            'created' => "Đã tạo {$this->getModelType()}: {$identifier}",
            'updated' => "Đã cập nhật {$this->getModelType()}: {$identifier}",
            'deleted' => "Đã xóa {$this->getModelType()}: {$identifier}",
            default => parent::getDescriptionForEvent($eventName),
        };
    }

    abstract protected function getModelType(): string;
    abstract protected function getCustomIdentifier(): string;
}
```

---

## Xử lý sự cố

### Các vấn đề thường gặp

#### 1. Hoạt động không được ghi lại

**Triệu chứng:** Không có hoạt động nào xuất hiện trong nhật ký sau khi thay đổi model.

**Nguyên nhân & Giải pháp:**

-   **Model không kế thừa `AuditableModel`**
    ```php
    // Sai ❌
    class MyModel extends Model { }
    
    // Đúng ✅
    class MyModel extends AuditableModel { }
    ```

-   **Ghi nhật ký hoạt động bị vô hiệu hóa**
    ```php
    // Kiểm tra xem shouldSkipLogging có trả về true không
    protected function shouldSkipLogging(): bool
    {
        return false; // Đảm bảo hàm này trả về false
    }
    ```

-   **Không có thay đổi thực tế nào được thực hiện**
    ```php
    // Thao tác này sẽ không ghi lại gì nếu tên đã là 'John'
    $user->update(['name' => 'John']);
    
    // Chỉ các thuộc tính "dirty" mới được ghi lại
    $user->name = 'Jane'; // Thao tác này sẽ được ghi lại
    $user->save();
    ```

#### 2. Thiếu Bối cảnh Cơ sở

**Triệu chứng:** Hoạt động có `log_name` là "default" thay vì tên theo cơ sở.

**Nguyên nhân & Giải pháp:**

-   **Không có session cơ sở được thiết lập**
    ```php
    // Đảm bảo cơ sở đã được chọn
    session(['current_campus_id' => 1]);
    ```

-   **Model không có `campus_id` và session trống**
    ```php
    // Hoặc đặt campus_id của model hoặc session cơ sở
    $model->campus_id = 1; // HOẶC
    session(['current_campus_id' => 1]);
    ```

-   **Sử dụng lớp `Model` cũ thay vì `AuditableModel`**
    ```php
    // Cập nhật để sử dụng AuditableModel
    class MyModel extends AuditableModel { }
    ```

#### 3. Vấn đề về Quyền

**Triệu chứng:** Không thể truy cập nhật ký hoạt động hoặc chỉ thấy các hoạt động bị giới hạn.

**Nguyên nhân & Giải pháp:**

-   **Thiếu quyền**
    ```php
    // Người dùng cần quyền 'view_system_log'
    $user->hasPermission('view_system_log'); // Phải trả về true
    ```

-   **Hạn chế truy cập theo cơ sở**
    ```php
    // Kiểm tra các cơ sở có thể truy cập
    $campusIds = CampusLogContext::getAccessibleCampusIds();
    // Phải bao gồm các cơ sở bạn mong đợi được thấy
    ```

#### 4. Vấn đề về Hiệu năng

**Triệu chứng:** Trang nhật ký hoạt động tải chậm.

**Giải pháp:**

-   **Thêm chỉ mục cơ sở dữ liệu**
    ```sql
    -- Thêm chỉ mục trên log_name để lọc theo cơ sở
    ALTER TABLE activity_log ADD INDEX idx_log_name (log_name);
    
    -- Thêm chỉ mục phức hợp cho các bộ lọc phổ biến
    ALTER TABLE activity_log ADD INDEX idx_subject_event (subject_type, event);
    ```

-   **Giới hạn kết quả truy vấn**
    ```php
    // Sử dụng phân trang và bộ lọc để giới hạn kết quả
    // Phân trang mặc định là 15 mục mỗi trang
    ```

-   **Dọn dẹp nhật ký cũ định kỳ**
    ```php
    // Dọn dẹp các nhật ký hoạt động cũ (chạy như một tác vụ theo lịch)
    Activity::where('created_at', '<', now()->subDays(365))->delete();
    ```

### Công cụ Gỡ lỗi

#### Kiểm tra Nội dung Nhật ký Hoạt động

```php
// Lấy các hoạt động gần đây
$activities = \Spatie\Activitylog\Models\Activity::latest()->take(10)->get();

foreach ($activities as $activity) {
    echo "ID: {$activity->id}\n";
    echo "Log Name: {$activity->log_name}\n";
    echo "Description: {$activity->description}\n";
    echo "Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n";
    echo "---\n";
}
```

#### Kiểm tra Bối cảnh Cơ sở

```php
// Kiểm tra helper bối cảnh cơ sở
use App\Support\CampusLogContext;

session(['current_campus_id' => 1]);

$context = CampusLogContext::getCampusContext();
print_r($context);

$logName = CampusLogContext::getLogName('TestModel', 1);
echo "Log Name: {$logName}\n"; // Phải là: testmodel_campus_1
```

#### Xác minh Triển khai Model

```php
// Kiểm tra ghi nhật ký của model
$model = new MyModel(['name' => 'Test']);
$model->save(); // Phải tạo hoạt động

// Kiểm tra xem hoạt động đã được tạo chưa
$activity = \Spatie\Activitylog\Models\Activity::latest()->first();
echo "Hoạt động mới nhất: " . $activity->description . "\n";
```

---

## Thực hành Tốt nhất

### Cân nhắc về Bảo mật

#### 1. Xử lý Dữ liệu Nhạy cảm

Luôn loại trừ các trường nhạy cảm khỏi việc ghi nhật ký:

```php
protected function getExcludedLogFields(): array
{
    return [
        'password',           // Không bao giờ ghi lại mật khẩu
        'remember_token',     // Không bao giờ ghi lại token
        'api_token',         // Không bao giờ ghi lại khóa API
        'oauth_provider_id', // Không ghi lại ID OAuth
        'ssn',               // Không bao giờ ghi lại SSN
        'credit_card',       // Không bao giờ ghi lại dữ liệu tài chính
        'internal_notes',    // Không ghi lại ghi chú nội bộ
    ];
}
```

#### 2. Kiểm soát Truy cập

Triển khai các biện pháp kiểm soát truy cập phù hợp:

```php
// Trong model User của bạn, triển khai các phương thức kiểm soát truy cập
public function hasSystemRole(string $roleCode): bool
{
    return $this->campusRoles()->where('code', $roleCode)->exists();
}

public function getAccessibleCampuses()
{
    return $this->campuses()->select('id', 'name', 'code');
}
```

#### 3. Tính toàn vẹn của Dấu vết Kiểm toán

-   **Không bao giờ xóa nhật ký hoạt động** trong môi trường production.
-   **Lưu trữ nhật ký cũ** thay vì xóa chúng.
-   **Giám sát các mẫu hoạt động đáng ngờ**.
-   **Sao lưu nhật ký hoạt động thường xuyên**.

### Hướng dẫn về Hiệu năng

#### 1. Lựa chọn Cấp độ Ghi nhật ký

Chọn cấp độ ghi nhật ký phù hợp dựa trên tầm quan trọng của model:

```php
// Model có tần suất thay đổi cao, tầm quan trọng thấp
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_MINIMAL; // Chỉ các thay đổi quan trọng
}

// Model nghiệp vụ tiêu chuẩn
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_STANDARD; // Các trường chính + trạng thái
}

// Model quan trọng/nhạy cảm
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_COMPREHENSIVE; // Tất cả các trường liên quan
}
```

#### 2. Bảo trì Cơ sở dữ liệu

Bảo trì bảng nhật ký hoạt động thường xuyên:

```php
// Tạo tác vụ theo lịch để dọn dẹp nhật ký
// Trong app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Lưu trữ nhật ký cũ hơn 2 năm
    $schedule->command('activitylog:clean --days=730')->monthly();
}
```

#### 3. Truy vấn Hiệu quả

Sử dụng các chỉ mục và tối ưu hóa truy vấn phù hợp:

```sql
-- Các chỉ mục được đề xuất cho bảng activity_log
CREATE INDEX idx_activity_log_name ON activity_log (log_name);
CREATE INDEX idx_activity_created_at ON activity_log (created_at);
CREATE INDEX idx_activity_subject ON activity_log (subject_type, subject_id);
CREATE INDEX idx_activity_causer ON activity_log (causer_type, causer_id);
```

### Quy trình Phát triển

#### 1. Thêm Model mới

Khi thêm một model mới cần ghi nhật ký:

1.  **Kế thừa từ lớp cơ sở phù hợp**.
2.  **Xác định cấp độ ghi nhật ký** dựa trên tầm quan trọng của model.
3.  **Tùy chỉnh các trường được ghi lại** nếu cần.
4.  **Kiểm tra việc ghi nhật ký** với các thao tác tạo/cập nhật/xóa.
5.  **Xác minh bối cảnh cơ sở** được áp dụng đúng cách.
6.  **Cập nhật tài liệu** nếu cần.

#### 2. Kiểm tra Nhật ký Hoạt động

Luôn kiểm tra việc triển khai ghi nhật ký của bạn:

```php
// Trong các feature test của bạn
public function test_model_creates_activity_log()
{
    $model = MyModel::create(['name' => 'Test']);
    
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => MyModel::class,
        'subject_id' => $model->id,
        'event' => 'created',
    ]);
    
    $activity = Activity::latest()->first();
    $this->assertStringContains('campus_', $activity->log_name);
    $this->assertArrayHasKey('campus_id', $activity->properties);
}
```

#### 3. Danh sách kiểm tra khi Review Code

Khi review code có liên quan đến ghi nhật ký hoạt động:

-   ✅ Model kế thừa từ lớp cơ sở kiểm toán phù hợp.
-   ✅ Các trường nhạy cảm được loại trừ khỏi nhật ký.
-   ✅ Cấp độ ghi nhật ký phù hợp với tầm quan trọng của model.
-   ✅ Mô tả tùy chỉnh có ý nghĩa và nhất quán.
-   ✅ Bối cảnh cơ sở được duy trì đúng cách.
-   ✅ Tác động về hiệu năng được xem xét.
-   ✅ Các bài test bao gồm xác minh nhật ký hoạt động.

### Bảo trì và Giám sát

#### 1. Giám sát thường xuyên

Giám sát nhật ký hoạt động của bạn để phát hiện:

-   **Các mẫu hoạt động bất thường** (xóa hàng loạt, cập nhật số lượng lớn).
-   **Suy giảm hiệu năng** (truy vấn chậm, tập kết quả lớn).
-   **Tăng trưởng lưu trữ** (sử dụng không gian đĩa).
-   **Các vấn đề bảo mật** (cố gắng truy cập trái phép).

#### 2. Chiến lược Sao lưu

-   **Bao gồm nhật ký hoạt động** trong các bản sao lưu cơ sở dữ liệu thường xuyên.
-   **Kiểm tra quy trình khôi phục** bao gồm dữ liệu nhật ký hoạt động.
-   **Cân nhắc lưu trữ riêng** cho các nhật ký rất cũ.
-   **Ghi lại chính sách lưu giữ** để tuân thủ quy định.

#### 3. Cập nhật Tài liệu

Giữ tài liệu luôn cập nhật:

-   **Cập nhật hướng dẫn này** khi thêm các tính năng mới.
-   **Ghi lại các triển khai tùy chỉnh** cho các model phức tạp.
-   **Duy trì phần xử lý sự cố** với các vấn đề thường gặp.
-   **Chia sẻ kiến thức** với các thành viên trong nhóm.

---

## Kết luận

Hệ thống Ghi nhật ký Hoạt động cung cấp khả năng kiểm toán toàn diện cho hệ thống quản lý học thuật. Bằng cách tuân theo hướng dẫn này, các thành viên trong nhóm có thể:

-   ✅ **Hiểu** cách hoạt động của hệ thống ghi nhật ký.
-   ✅ **Triển khai** ghi nhật ký cho các model mới một cách chính xác.
-   ✅ **Sử dụng** giao diện nhật ký hoạt động hiệu quả.
-   ✅ **Tự xử lý** các sự cố thường gặp.
-   ✅ **Bảo trì** hệ thống để có hiệu năng tối ưu.

Đối với các câu hỏi hoặc vấn đề không được đề cập trong hướng dẫn này, vui lòng tham khảo ý kiến của nhóm kỹ thuật hoặc xem [tài liệu của spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog/).

---

*Cập nhật lần cuối: 7 tháng 8, 2025*
*Phiên bản: 1.0*

