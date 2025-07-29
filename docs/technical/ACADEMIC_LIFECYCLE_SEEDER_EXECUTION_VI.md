# Hướng Dẫn Thực Thi Academic Lifecycle Seeder

## Tổng Quan

Tài liệu này hướng dẫn bạn cách thực thi Academic Lifecycle Seeder để tạo dữ liệu học thuật thực tế cho hệ thống. Các task 1, 2, và 3 đã được hoàn thành và sẵn sàng để chạy.

## Các Task Đã Hoàn Thành

- **Task 1**: Hạ tầng cốt lõi và hệ thống cấu hình ✅
- **Task 2**: Tạo Course Offering (Đợt mở môn học) ✅  
- **Task 3**: Đăng ký sinh viên vào các môn học ✅

## Yêu Cầu Tiên Quyết

### 1. Kiểm Tra Database
```bash
# Đảm bảo database đã được migrate
php artisan migrate:status

# Nếu chưa migrate, chạy:
php artisan migrate
```

### 2. Seed Dữ Liệu Cơ Bản
```bash
# Chạy seeder cơ bản trước (nếu chưa có)
php artisan db:seed --class=DatabaseSeeder
```

### 3. Kiểm Tra Cấu Hình
```bash
# Kiểm tra file cấu hình
cat config/academic-lifecycle-seeder.php
```

## Cách Thực Thi

### Phương Pháp 1: Sử Dụng Service Trực Tiếp

#### Bước 1: Mở Laravel Tinker
```bash
php artisan tinker
```

#### Bước 2: Khởi Tạo Seeder
```php
// Khởi tạo Academic Lifecycle Seeder
$seeder = new \App\Services\AcademicLifecycleSeeder\AcademicLifecycleSeeder();

// Kiểm tra cấu hình
$config = $seeder->getConfiguration();
echo "Cấu hình đã sẵn sàng: " . ($config ? "✅" : "❌");
```

#### Bước 3: Chạy Từng Phase

**Phase 1: Course Offering Generation**
```php
// Chạy Phase 1 - Tạo Course Offerings
$result1 = $seeder->runPhase('course_offering_generation');

// Kiểm tra kết quả
echo "Phase 1 kết quả: " . ($result1->isSuccess() ? "✅ Thành công" : "❌ Thất bại");
echo "Thời gian thực thi: " . $result1->getExecutionTime() . " giây";
echo "Course Offerings đã tạo: " . $result1->getRecordsCreated();
```

**Phase 2: Student Registration**
```php
// Chạy Phase 2 - Đăng ký sinh viên
$result2 = $seeder->runPhase('student_registration');

// Kiểm tra kết quả
echo "Phase 2 kết quả: " . ($result2->isSuccess() ? "✅ Thành công" : "❌ Thất bại");
echo "Thời gian thực thi: " . $result2->getExecutionTime() . " giây";
echo "Course Registrations đã tạo: " . $result2->getRecordsCreated();
```

#### Bước 4: Chạy Toàn Bộ Seeder
```php
// Chạy tất cả các phase đã hoàn thành
$fullResult = $seeder->run(['course_offering_generation', 'student_registration']);

// Xem báo cáo tổng hợp
$report = $seeder->getProgressReport();
echo $report->generateSummary();
```

### Phương Pháp 2: Tạo Artisan Command (Khuyến Nghị)

#### Bước 1: Tạo Command File
```bash
php artisan make:command RunAcademicLifecycleSeeder
```

#### Bước 2: Chỉnh Sửa Command
Mở file `app/Console/Commands/RunAcademicLifecycleSeeder.php` và thêm:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AcademicLifecycleSeeder\AcademicLifecycleSeeder;

class RunAcademicLifecycleSeeder extends Command
{
    protected $signature = 'seed:academic-lifecycle {--phase=all : Phase to run (all, course_offering_generation, student_registration)}';
    protected $description = 'Chạy Academic Lifecycle Seeder để tạo dữ liệu học thuật';

    public function handle()
    {
        $this->info('🚀 Bắt đầu Academic Lifecycle Seeder...');
        
        $seeder = new AcademicLifecycleSeeder();
        $phase = $this->option('phase');
        
        try {
            if ($phase === 'all') {
                $result = $seeder->run(['course_offering_generation', 'student_registration']);
            } else {
                $result = $seeder->runPhase($phase);
            }
            
            $this->info('✅ Seeder hoàn thành thành công!');
            $this->table(['Metric', 'Value'], [
                ['Thời gian thực thi', $result->getExecutionTime() . ' giây'],
                ['Records đã tạo', $result->getRecordsCreated()],
                ['Trạng thái', $result->isSuccess() ? 'Thành công' : 'Thất bại']
            ]);
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
```

#### Bước 3: Chạy Command
```bash
# Chạy tất cả các phase
php artisan seed:academic-lifecycle

# Chỉ chạy Course Offering Generation
php artisan seed:academic-lifecycle --phase=course_offering_generation

# Chỉ chạy Student Registration
php artisan seed:academic-lifecycle --phase=student_registration
```

## Kiểm Tra Kết Quả

### 1. Kiểm Tra Course Offerings
```sql
-- Kiểm tra số lượng course offerings đã tạo
SELECT COUNT(*) as total_course_offerings FROM course_offerings;

-- Xem chi tiết course offerings theo semester
SELECT s.name as semester, COUNT(co.id) as course_count
FROM course_offerings co
JOIN semesters s ON co.semester_id = s.id
GROUP BY s.id, s.name;
```

### 2. Kiểm Tra Course Registrations
```sql
-- Kiểm tra số lượng đăng ký
SELECT COUNT(*) as total_registrations FROM course_registrations;

-- Xem thống kê đăng ký theo sinh viên
SELECT s.student_id, COUNT(cr.id) as registered_courses
FROM course_registrations cr
JOIN students s ON cr.student_id = s.id
GROUP BY s.student_id
LIMIT 10;
```

### 3. Kiểm Tra Academic Records
```sql
-- Kiểm tra academic records đã tạo
SELECT COUNT(*) as total_academic_records FROM academic_records;

-- Xem trạng thái academic records
SELECT completion_status, COUNT(*) as count
FROM academic_records
GROUP BY completion_status;
```

### 4. Kiểm Tra Syllabus và Assessment Components
```sql
-- Kiểm tra syllabus
SELECT COUNT(*) as total_syllabus FROM syllabus;

-- Kiểm tra assessment components
SELECT COUNT(*) as total_assessment_components FROM assessment_components;

-- Kiểm tra tổng weight của assessment components
SELECT syllabus_id, SUM(weight) as total_weight
FROM assessment_components
GROUP BY syllabus_id
HAVING SUM(weight) != 100;
```

## Xử Lý Lỗi Thường Gặp

### 1. Lỗi Foreign Key Constraint
```bash
# Kiểm tra dữ liệu tiên quyết
php artisan db:seed --class=InitialSeederRunner
```

### 2. Lỗi Memory Limit
```bash
# Tăng memory limit
php -d memory_limit=512M artisan seed:academic-lifecycle
```

### 3. Lỗi Timeout
```bash
# Tăng max execution time
php -d max_execution_time=300 artisan seed:academic-lifecycle
```

### 4. Rollback Nếu Có Lỗi
```sql
-- Rollback course registrations
DELETE FROM course_registrations WHERE created_at >= 'YYYY-MM-DD';

-- Rollback academic records
DELETE FROM academic_records WHERE created_at >= 'YYYY-MM-DD';

-- Rollback course offerings (cẩn thận với điều này)
-- DELETE FROM course_offerings WHERE created_at >= 'YYYY-MM-DD';
```

## Monitoring và Log

### 1. Xem Log Files
```bash
# Xem Laravel logs
tail -f storage/logs/laravel.log

# Grep cho seeder logs cụ thể
grep "AcademicLifecycleSeeder" storage/logs/laravel.log
```

### 2. Kiểm Tra Performance
```bash
# Chạy với verbose output
php artisan seed:academic-lifecycle -v

# Chạy với debug mode
APP_DEBUG=true php artisan seed:academic-lifecycle
```

## Cấu Hình Tùy Chỉnh

### 1. Chỉnh Sửa Cấu Hình
```php
// config/academic-lifecycle-seeder.php
return [
    'batch_size' => 100, // Điều chỉnh batch size
    'max_students_per_course' => 60, // Số sinh viên tối đa mỗi môn
    'group_split_threshold' => 55, // Ngưỡng chia nhóm
    // ... các cấu hình khác
];
```

### 2. Environment Variables
```env
# .env
ACADEMIC_SEEDER_BATCH_SIZE=100
ACADEMIC_SEEDER_DEBUG=true
```

## Best Practices

1. **Backup Database** trước khi chạy seeder
2. **Chạy trên staging environment** trước
3. **Monitor memory usage** với datasets lớn
4. **Chạy từng phase riêng biệt** để debug dễ hơn
5. **Kiểm tra kết quả** sau mỗi phase

## Liên Hệ Hỗ Trợ

Nếu gặp vấn đề, vui lòng:
1. Kiểm tra logs chi tiết
2. Chạy với debug mode
3. Kiểm tra database constraints
4. Liên hệ team development để hỗ trợ

---

*Tài liệu này được cập nhật cho phiên bản Academic Lifecycle Seeder v1.0* 
