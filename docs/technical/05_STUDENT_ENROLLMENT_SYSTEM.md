# Giai Đoạn 5: Hệ Thống Quản Lý Sinh Viên và Đăng Ký Môn Học

## 📋 Tổng Quan

Giai đoạn 5 xây dựng hệ thống REST API hoàn chỉnh cho quản lý sinh viên và đăng ký môn học, phục vụ một web portal riêng dành cho sinh viên:
- **Student Account Management API**: Admin tạo và quản lý tài khoản sinh viên qua giao diện admin
- **Student REST API Portal**: Cung cấp các API endpoints cho web portal riêng của sinh viên (không có giao diện đăng nhập nội bộ)
- **Program & Specialization Assignment**: Admin gán chương trình đào tạo và chuyên ngành cho sinh viên
- **Course Registration API**: API cho phép sinh viên đăng ký môn học theo semester qua portal riêng
- **Academic Holds Management**: Quản lý các ràng buộc học thuật và tài chính ảnh hưởng đến quyền đăng ký
- **Graduation Requirements Tracking**: API theo dõi tiến độ và điều kiện tốt nghiệp

## 🎯 Mục Tiêu Giai Đoạn 5

🔄 **Đang Phát Triển**:
- Student management API endpoints cho admin
- REST API authentication & authorization 
- Course registration API cho portal riêng
- Academic holds management API
- Student enrollment confirmation API
- API documentation & integration guide

## 🏗️ Kiến Trúc Hệ Thống

### Backend Architecture
```
app/
├── Models/
│   ├── Student.php                   # Student model (API driven)
│   ├── StudentCampusRole.php         # Campus-based student roles
│   ├── CourseRegistration.php        # Course enrollment records
│   ├── AcademicHold.php              # Academic/financial holds
│   ├── GraduationRequirement.php     # Program graduation rules
│   └── CourseOffering.php            # Course offerings per semester
├── Services/
│   ├── StudentManagementService.php  # Student creation & management
│   ├── RegistrationService.php       # Course registration logic
│   ├── HoldsManagementService.php    # Academic holds system
│   ├── GraduationTrackingService.php # Graduation progress tracking
│   └── ApiAuthenticationService.php  # API authentication & authorization
├── Http/Controllers/
│   ├── StudentController.php   # Admin student management (web interface)
│   └── Api/V1/                       # REST API for external portal
│       ├── StudentController.php     # Student API endpoints
│       ├── RegistrationController.php # Course registration API
│       ├── CourseController.php      # Course information API
│       └── AuthController.php        # API authentication
```

### Admin Frontend Architecture (Internal)
```
resources/js/
├── pages/Students/         # Admin student management
│   ├── Index.vue                 # Students listing
│   ├── Create.vue                # New student creation
│   ├── Edit.vue                  # Student profile editing
│   └── Show.vue                  # Student details view
└── components/Students/    # Admin-specific student components
    ├── StudentForm.vue           # Student creation/edit form
    ├── ProgramAssignment.vue     # Program assignment interface
    └── EnrollmentHistory.vue     # Student enrollment tracking
```

## 📊 Cơ Sở Dữ Liệu

### Students Table (Enhanced)
```sql
CREATE TABLE students (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    
    -- OAuth Integration
    oauth_provider ENUM('google', 'microsoft', 'manual') DEFAULT 'google',
    oauth_provider_id VARCHAR(255),
    oauth_avatar_url VARCHAR(500),
    
    -- Basic Information
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    address TEXT,
    avatar_url VARCHAR(500),
    
    -- Academic Assignment
    campus_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED,
    curriculum_version_id BIGINT UNSIGNED NOT NULL,
    admission_date DATE NOT NULL,
    expected_graduation_date DATE,
    
    -- Contact Information
    parent_guardian_name VARCHAR(255),
    parent_guardian_phone VARCHAR(20),
    parent_guardian_email VARCHAR(255),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(20),
    
    -- Admission Details
    high_school_name VARCHAR(255),
    high_school_graduation_year YEAR,
    entrance_exam_score DECIMAL(5,2),
    admission_notes TEXT,
    
    -- System Fields
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    google_id VARCHAR(255),
    remember_token VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    FOREIGN KEY (curriculum_version_id) REFERENCES curriculum_versions(id) ON DELETE RESTRICT,
    FOREIGN KEY (advisor_lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL,
    FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE SET NULL,
    
    INDEX idx_students_student_id (student_id),
    INDEX idx_students_email (email),
    INDEX idx_students_oauth (oauth_provider, oauth_provider_id),
    INDEX idx_students_campus (campus_id),
    INDEX idx_students_program (program_id, specialization_id),
);
```

### Course Registrations Table
```sql
CREATE TABLE course_registrations (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    
    -- Registration Details
    registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn', 'completed') DEFAULT 'confirmed',
    registration_date TIMESTAMP NOT NULL,
    registration_method ENUM('online', 'advisor', 'admin_override') DEFAULT 'online',
    
    -- Academic Record
    credit_hours DECIMAL(4,2) NOT NULL,
    final_grade VARCHAR(3),
    grade_points DECIMAL(3,2),
    attempt_number INTEGER DEFAULT 1,
    is_retake BOOLEAN DEFAULT FALSE,
    
    -- Dates
    drop_date TIMESTAMP NULL,
    withdrawal_date TIMESTAMP NULL,
    completion_date TIMESTAMP NULL,
    
    -- Financial
    tuition_amount DECIMAL(10,2) DEFAULT 0.00,
    fees_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',
    
    -- System
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_student_course_semester (student_id, course_offering_id, semester_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    
    INDEX idx_registrations_student (student_id),
    INDEX idx_registrations_offering (course_offering_id),
    INDEX idx_registrations_semester (semester_id),
    INDEX idx_registrations_status (registration_status)
);
```

### Academic Holds Table
```sql
CREATE TABLE academic_holds (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    hold_type ENUM('financial', 'academic', 'disciplinary', 'administrative', 'health', 'library') NOT NULL,
    hold_category ENUM('registration', 'graduation', 'transcript', 'all') DEFAULT 'registration',
    
    -- Hold Details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NULL, -- For financial holds
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    
    -- Status
    status ENUM('active', 'resolved', 'waived', 'expired') DEFAULT 'active',
    placed_date DATE NOT NULL,
    due_date DATE,
    resolved_date DATE,
    
    -- Management
    placed_by_user_id BIGINT UNSIGNED,
    resolved_by_user_id BIGINT UNSIGNED,
    resolution_notes TEXT,
    
    -- System
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (placed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_holds_student (student_id),
    INDEX idx_holds_type_status (hold_type, status),
    INDEX idx_holds_category (hold_category)
);
```

### Graduation Requirements Table
```sql
CREATE TABLE graduation_requirements (
    id BIGINT UNSIGNED PRIMARY KEY,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED,
    
    -- Credit Requirements
    total_credits_required DECIMAL(5,2) NOT NULL,
    core_credits_required DECIMAL(5,2) DEFAULT 0.00,
    major_credits_required DECIMAL(5,2) DEFAULT 0.00,
    elective_credits_required DECIMAL(5,2) DEFAULT 0.00,
    
    -- GPA Requirements
    minimum_gpa DECIMAL(3,2) DEFAULT 2.00,
    minimum_major_gpa DECIMAL(3,2) DEFAULT 2.00,
    
    -- Other Requirements
    maximum_study_years INTEGER DEFAULT 6,
    required_internship BOOLEAN DEFAULT FALSE,
    required_thesis BOOLEAN DEFAULT FALSE,
    required_english_certification BOOLEAN DEFAULT FALSE,
    
    -- Special Requirements (JSON)
    special_requirements JSON,
    
    -- Validity
    effective_from DATE NOT NULL,
    effective_to DATE,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE,
    
    INDEX idx_grad_req_program (program_id, specialization_id),
    INDEX idx_grad_req_active (is_active, effective_from, effective_to)
);
```

## 🔧 Quy Trình Chính

### Bước 1: Tạo và Quản Lý Tài Khoản Sinh Viên

#### 1.1 Admin Tạo Tài Khoản Sinh Viên
**Mục đích**: Admin tạo tài khoản cho sinh viên mới nhập học

**Các việc cần làm**:
1. **Tạo giao diện admin** cho việc nhập thông tin sinh viên:
   - Form nhập thông tin cá nhân (họ tên, email, số điện thoại)
   - Chọn campus, chương trình học và chuyên ngành
   - Nhập thông tin liên hệ khẩn cấp và phụ huynh

2. **Tự động sinh mã sinh viên**:
   - Theo format: `[MÃ_CAMPUS][NĂM][SỐ_THỨ_TỰ]`
   - Ví dụ: HN2504001 (Hà Nội, năm 2025, sinh viên thứ 1)

3. **Gửi email chào mừng** chứa:
   - Thông tin tài khoản và mã sinh viên
   - Hướng dẫn truy cập portal sinh viên
   - Link đến portal với thông tin đăng nhập tạm thời

4. **Ghi log hoạt động** để theo dõi việc tạo tài khoản

#### 1.2 Cấu Hình API Authentication
**Mục đích**: Thiết lập xác thực API cho portal sinh viên

**Các việc cần làm**:
1. **Thiết lập API authentication**:
   - Sử dụng Laravel Sanctum hoặc Passport
   - Tạo token xác thực cho từng sinh viên
   - Cấu hình rate limiting và security headers

2. **Cấu hình domain và CORS**:
   - Cho phép truy cập từ domain của portal sinh viên
   - Thiết lập các endpoint API cần thiết
   - Bảo mật API với proper validation

### Bước 2: Gán Chương Trình và Chuyên Ngành

#### 2.1 Quy Trình Gán Chương Trình Học
**Mục đích**: Admin gán chương trình đào tạo cụ thể cho sinh viên

**Các việc cần làm**:
1. **Tạo giao diện assignment**:
   - Dropdown chọn chương trình (Program)
   - Dropdown chọn chuyên ngành (Specialization) - tùy chọn
   - Tự động tìm curriculum version phù hợp

2. **Validate assignment logic**:
   - Kiểm tra chương trình có đang active không
   - Đảm bảo chuyên ngành thuộc chương trình được chọn
   - Xác định curriculum version hiện tại đang áp dụng

3. **Thiết lập graduation requirements**:
   - Tự động gán yêu cầu tốt nghiệp theo chương trình
   - Tính toán ngày dự kiến tốt nghiệp
   - Lưu snapshot requirements tại thời điểm gán

4. **Cập nhật trạng thái sinh viên** từ 'admitted' sang 'enrolled'

### Bước 3: Thiết Lập Course Offerings

#### 3.1 Cấu Hình Môn Học Cho Học Kỳ
**Mục đích**: Admin thiết lập các môn học available cho từng semester

**Các việc cần làm**:
1. **Tạo giao diện quản lý offerings**:
   - Chọn semester để cấu hình
   - Hiển thị danh sách units từ curriculum versions
   - Bulk create offerings cho toàn bộ curriculum

2. **Tự động generate course offerings**:
   - Duyệt qua tất cả curriculum versions active
   - Tạo offering cho từng unit trong curriculum
   - Sinh course code và section code tự động

3. **Cấu hình capacity và delivery mode**:
   - Set default max enrollment (có thể edit sau)
   - Chọn delivery mode (in_person, online, hybrid)
   - Gán instructor và schedule nếu có

4. **Validation và conflict checking**:
   - Tránh tạo duplicate offerings
   - Kiểm tra conflicts về schedule và resources

### Bước 4: Quản Lý Academic Holds

#### 4.1 Hệ Thống Holds và Restrictions
**Mục đích**: Kiểm soát quyền đăng ký môn học của sinh viên

**Các việc cần làm**:
1. **Tạo giao diện quản lý holds**:
   - Danh sách holds theo loại (financial, academic, administrative)
   - Form tạo hold mới với lý do cụ thể
   - Tracking và resolution workflow

2. **Thiết lập automated holds**:
   - Financial holds khi có nợ học phí
   - Academic holds khi GPA thấp
   - Administrative holds cho các vi phạm

3. **Hold checking mechanism**:
   - API endpoint kiểm tra holds trước khi đăng ký
   - Phân loại holds theo mức độ nghiêm trọng
   - Resolution tracking và notification

4. **Integration với registration process**:
   - Block registration nếu có active holds
   - Clear explanation về lý do block
   - Contact information để resolve holds

### Bước 5: API Đăng Ký Môn Học

#### 5.1 Course Registration API
**Mục đích**: Cung cấp API cho portal sinh viên đăng ký môn học

**Các việc cần làm**:
1. **Tạo API endpoints**:
   - `GET /api/student/courses/available` - Danh sách môn có thể đăng ký
   - `POST /api/student/registration` - Đăng ký môn học
   - `GET /api/student/registrations` - Xem đăng ký hiện tại
   - `DELETE /api/student/registration/{id}` - Hủy đăng ký

2. **Validation logic phức tạp**:
   - **Eligibility checking**: Trạng thái sinh viên, holds, academic standing
   - **Prerequisite validation**: Kiểm tra môn tiên quyết đã hoàn thành
   - **Schedule conflict detection**: Tránh xung đột thời khóa biểu
   - **Capacity checking**: Đảm bảo lớp chưa đầy

3. **Real-time updates**:
   - Cập nhật enrollment count ngay lập tức
   - Transaction-based để tránh race conditions
   - Rollback mechanism nếu có lỗi

4. **Confirmation và notification**:
   - Gửi email xác nhận đăng ký thành công
   - Update student academic records
   - Log activities cho audit trail

#### 5.2 Prerequisite và Schedule Validation
**Mục đích**: Đảm bảo sinh viên đủ điều kiện đăng ký môn học

**Các việc cần làm**:
1. **Prerequisite checking service**:
   - Lấy danh sách completed courses của sinh viên
   - Cross-reference với prerequisite requirements
   - Support complex prerequisite logic (AND/OR conditions)

2. **Schedule conflict detection**:
   - So sánh time slots của courses
   - Kiểm tra conflicts trong cùng semester
   - Xử lý multiple sections và time formats

3. **Credit load validation**:
   - Kiểm tra tổng số tín chỉ không vượt quá giới hạn
   - Warning nếu đăng ký quá ít credits
   - Academic advisor approval cho overload

4. **Error handling và user feedback**:
   - Clear error messages cho từng loại validation failure
   - Suggestions để resolve issues
   - Alternative course recommendations

### Bước 6: Student Portal API Integration

#### 6.1 API Documentation và Testing
**Mục đích**: Hỗ trợ team phát triển portal sinh viên

**Các việc cần làm**:
1. **API Documentation**:
   - Swagger/OpenAPI specification
   - Request/response examples
   - Error codes và handling
   - Authentication requirements

2. **Testing suite**:
   - Unit tests cho business logic
   - Integration tests cho API endpoints
   - Load testing cho concurrent registrations
   - Mock data cho development

3. **Monitoring và logging**:
   - API usage analytics
   - Performance monitoring
   - Error tracking và alerting
   - Audit logs cho compliance

4. **Deployment và versioning**:
   - API versioning strategy
   - Backward compatibility
   - Rate limiting configuration
   - Security hardening