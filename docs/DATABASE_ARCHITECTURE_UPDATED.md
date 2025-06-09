# Kiến Trúc Database Cập Nhật - Tích Hợp Campus-Based Roles

## 📋 Tổng Quan Thay Đổi

Dựa trên Models hiện tại, kiến trúc database cần được cập nhật để:

- **`users`**: Giữ nguyên cho Admin, Staff và tạm thời cho Students (chuyển dần)
- **`students`**: Bảng mới độc lập cho sinh viên (migration từ users)
- **`lecturers`**: Bảng mới độc lập cho giảng viên
- **Campus-Based Roles**: Sử dụng hệ thống `campus_user_roles` đã có
- **Permission Hierarchy**: Sử dụng parent-child permissions đã implement

## 🏗️ Cấu Trúc Models Hiện Tại

### ✅ Đã Implement:

1. **User Model** với campus-based roles
2. **Role & Permission** system với hierarchy
3. **CampusUserRole** pivot table
4. **StudentEnrollment** system liên kết với User
5. **Campus** management

### 🔄 Cần Migration:

1. **Students Table** - tách riêng từ Users
2. **Lecturers Table** - tạo mới
3. **Teaching Assignments** - liên kết Lecturers và Staff

## 📊 Schema Database Cập Nhật

### 1. Users Table (Hiện Tại - Cần Cập Nhật)

```sql
-- Current Users table cần thêm fields cho staff teaching
ALTER TABLE users ADD COLUMN user_type ENUM('admin', 'staff', 'student', 'lecturer') DEFAULT 'staff';
ALTER TABLE users ADD COLUMN employee_id VARCHAR(20) UNIQUE;
ALTER TABLE users ADD COLUMN can_teach BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN max_teaching_hours DECIMAL(4,2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN department_id BIGINT UNSIGNED;
ALTER TABLE users ADD COLUMN position VARCHAR(255);
ALTER TABLE users ADD COLUMN hire_date DATE;
ALTER TABLE users ADD COLUMN employment_status ENUM('active', 'on_leave', 'terminated', 'retired') DEFAULT 'active';
ALTER TABLE users ADD COLUMN office_location VARCHAR(100);

-- Index cho performance
CREATE INDEX idx_users_type_status ON users(user_type, employment_status);
CREATE INDEX idx_users_employee_id ON users(employee_id);
```

### 2. Students Table (Mới - Migration từ Users)

```sql
CREATE TABLE students (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    
    -- Migration từ existing user
    migrated_from_user_id BIGINT UNSIGNED NULL,
    
    -- Basic Information  
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    address TEXT,
    avatar_url VARCHAR(500),
    
    -- Academic Information
    campus_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED,
    admission_date DATE NOT NULL,
    expected_graduation_date DATE,
    student_type ENUM('full_time', 'part_time', 'exchange', 'visiting') DEFAULT 'full_time',
    enrollment_status ENUM('enrolled', 'active', 'on_leave', 'suspended', 'graduated', 'dropped_out') DEFAULT 'enrolled',
    
    -- Academic Progress
    current_year INTEGER DEFAULT 1,
    current_semester INTEGER DEFAULT 1,
    total_credits_earned DECIMAL(5,2) DEFAULT 0.00,
    cumulative_gpa DECIMAL(3,2),
    academic_standing ENUM('good_standing', 'probation', 'suspension', 'dean_list', 'honor_roll') DEFAULT 'good_standing',
    advisor_lecturer_id BIGINT UNSIGNED,
    
    -- Contact Information
    parent_guardian_name VARCHAR(255),
    parent_guardian_phone VARCHAR(20),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(20),
    
    -- System Fields
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (migrated_from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL,
    
    INDEX idx_students_student_id (student_id),
    INDEX idx_students_email (email),
    INDEX idx_students_campus (campus_id),
    INDEX idx_students_program (program_id),
    INDEX idx_students_status (enrollment_status, status),
    INDEX idx_students_migration (migrated_from_user_id)
);
```

### 3. Lecturers Table (Mới)

```sql
CREATE TABLE lecturers (
    id BIGINT UNSIGNED PRIMARY KEY,
    lecturer_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    
    -- Basic Information
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    address TEXT,
    avatar_url VARCHAR(500),
    
    -- Employment Information
    campus_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED,
    hire_date DATE NOT NULL,
    employment_type ENUM('full_time', 'part_time', 'adjunct', 'visiting', 'emeritus') DEFAULT 'full_time',
    employment_status ENUM('active', 'on_leave', 'sabbatical', 'retired', 'terminated') DEFAULT 'active',
    
    -- Academic Information
    academic_rank ENUM('instructor', 'assistant_professor', 'associate_professor', 'professor', 'distinguished_professor') DEFAULT 'instructor',
    highest_degree VARCHAR(100),
    highest_degree_institution VARCHAR(255),
    highest_degree_year YEAR,
    areas_of_expertise JSON,
    research_interests TEXT,
    
    -- Office & Contact
    office_location VARCHAR(100),
    office_hours TEXT,
    cv_file_path VARCHAR(500),
    
    -- Teaching Load Management
    teaching_load_hours DECIMAL(4,2) DEFAULT 0.00,
    max_teaching_load_hours DECIMAL(4,2) DEFAULT 40.00,
    is_thesis_supervisor BOOLEAN DEFAULT FALSE,
    max_thesis_students INTEGER DEFAULT 5,
    current_thesis_students INTEGER DEFAULT 0,
    
    -- System Fields
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    
    INDEX idx_lecturers_lecturer_id (lecturer_id),
    INDEX idx_lecturers_email (email),
    INDEX idx_lecturers_campus (campus_id),
    INDEX idx_lecturers_department (department_id),
    INDEX idx_lecturers_status (employment_status, status)
);
```

### 4. Campus-Based Role System (Đã Có - Mở Rộng)

```sql
-- Campus User Roles (Hiện tại)
-- Cần mở rộng để support Lecturers
CREATE TABLE campus_lecturer_roles (
    id BIGINT UNSIGNED PRIMARY KEY,
    lecturer_id BIGINT UNSIGNED NOT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_date DATE DEFAULT (CURRENT_DATE),
    assigned_by BIGINT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_lecturer_campus_role (lecturer_id, campus_id, role_id),
    INDEX idx_lecturer_roles_campus (campus_id),
    INDEX idx_lecturer_roles_lecturer (lecturer_id)
);

-- Student Campus Roles (Mới - cho student leadership roles)
CREATE TABLE campus_student_roles (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_date DATE DEFAULT (CURRENT_DATE),
    assigned_by BIGINT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_student_campus_role (student_id, campus_id, role_id),
    INDEX idx_student_roles_campus (campus_id),
    INDEX idx_student_roles_student (student_id)
);
```

### 5. Teaching Assignments (Mới)

```sql
CREATE TABLE teaching_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    semester_unit_offering_id BIGINT UNSIGNED NOT NULL,
    
    -- Either lecturer OR staff can be assigned
    lecturer_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,      -- For staff teaching
    
    assignment_type ENUM('primary', 'co_instructor', 'teaching_assistant', 'grader') DEFAULT 'primary',
    teaching_percentage DECIMAL(5,2) DEFAULT 100.00,
    credit_hours DECIMAL(3,1) NOT NULL,
    hourly_rate DECIMAL(8,2),
    total_compensation DECIMAL(10,2),
    
    assigned_date DATE NOT NULL,
    assignment_status ENUM('pending', 'accepted', 'declined', 'active', 'completed') DEFAULT 'pending',
    response_deadline DATE,
    accepted_date DATE,
    completion_date DATE,
    performance_rating DECIMAL(3,2),
    
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (semester_unit_offering_id) REFERENCES semester_unit_offerings(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Ensure either lecturer_id OR user_id is set, not both
    CHECK ((lecturer_id IS NOT NULL AND user_id IS NULL) OR (lecturer_id IS NULL AND user_id IS NOT NULL)),
    
    INDEX idx_teaching_assignments_offering (semester_unit_offering_id),
    INDEX idx_teaching_assignments_lecturer (lecturer_id),
    INDEX idx_teaching_assignments_staff (user_id),
    INDEX idx_teaching_assignments_status (assignment_status)
);
```

## 🔧 Updated Model Relationships

### User Model (Cập Nhật)

```php
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'address',
        'user_type', 'employee_id', 'can_teach', 'max_teaching_hours',
        'department_id', 'position', 'hire_date', 'employment_status',
        'office_location'
    ];
    
    protected $casts = [
        'can_teach' => 'boolean',
        'max_teaching_hours' => 'decimal:2',
        'hire_date' => 'date',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Existing campus roles relationship
    public function campusRoles(): HasMany
    {
        return $this->hasMany(CampusUserRole::class);
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class, 'campus_user_roles')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    // New teaching relationships
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
    
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Student enrollment relationship (for migration period)
    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    // Existing permission methods remain unchanged
    public function hasPermission($permission_code, $campusId)
    {
        return $this->getAllPermissions($campusId)->contains($permission_code);
    }

    public function getAllPermissions($campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');

        $roleIds = $this->campusRoles()
            ->where('campus_id', $campusId)
            ->pluck('role_id');
            
        if ($roleIds->isEmpty()) return collect();

        $roles = Role::with('permissions.children')->whereIn('id', $roleIds)->get();

        $permissions = collect();
        foreach ($roles as $role) {
            foreach ($role->permissions as $perm) {
                $permissions->push($perm);
                foreach ($perm->children as $child) {
                    $permissions->push($child);
                }
            }
        }

        return $permissions->unique('code')->pluck('code')->values();
    }
    
    // New methods for staff teaching
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }
    
    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }
    
    public function canTeach(): bool
    {
        return $this->can_teach;
    }
    
    public function getCurrentTeachingLoad(): float
    {
        return $this->teachingAssignments()
            ->whereHas('semesterOffering.semester', fn($q) => $q->where('is_active', true))
            ->sum(DB::raw('teaching_percentage * credit_hours')) / 100;
    }
    
    public function getAvailableTeachingHours(): float
    {
        return $this->max_teaching_hours - $this->getCurrentTeachingLoad();
    }
}
```

### Student Model (Mới)

```php
class Student extends Authenticatable
{
    use SoftDeletes;
    
    protected $guard = 'student';
    
    protected $fillable = [
        'student_id', 'full_name', 'email',
        'phone', 'password', 'migrated_from_user_id', 'campus_id', 'program_id',
        'specialization_id', 'current_year', 'current_semester', 'enrollment_status',
        'student_type', 'advisor_lecturer_id'
    ];
    
    protected $casts = [
        'total_credits_earned' => 'decimal:2',
        'cumulative_gpa' => 'decimal:2',
        'admission_date' => 'date',
        'expected_graduation_date' => 'date',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
    
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
    
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }
    
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'advisor_lecturer_id');
    }
    
    // Migration relationship
    public function migratedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'migrated_from_user_id');
    }
    
    // Academic relationships
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }
    
    // Campus roles for student leadership
    public function campusRoles(): HasMany
    {
        return $this->hasMany(CampusStudentRole::class);
    }
    
    public function hasPermission($permission_code, $campusId): bool
    {
        $roleIds = $this->campusRoles()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->pluck('role_id');
            
        if ($roleIds->isEmpty()) return false;
        
        return Role::whereIn('id', $roleIds)
            ->whereHas('permissions', fn($q) => $q->where('code', $permission_code))
            ->exists();
    }
}
```

### Lecturer Model (Mới)

```php
class Lecturer extends Authenticatable
{
    use SoftDeletes;
    
    protected $guard = 'lecturer';
    
    protected $fillable = [
        'lecturer_id', 'full_name', 'email',
        'phone', 'password', 'campus_id', 'department_id', 'employment_type',
        'employment_status', 'academic_rank', 'areas_of_expertise', 'research_interests',
        'office_location', 'office_hours', 'max_teaching_load_hours', 'is_thesis_supervisor'
    ];
    
    protected $casts = [
        'areas_of_expertise' => 'array',
        'is_thesis_supervisor' => 'boolean',
        'teaching_load_hours' => 'decimal:2',
        'max_teaching_load_hours' => 'decimal:2',
        'hire_date' => 'date',
        'highest_degree_year' => 'integer',
        'max_thesis_students' => 'integer',
        'current_thesis_students' => 'integer',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
    
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
    
    public function advisorStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'advisor_lecturer_id');
    }
    
    // Campus roles for lecturer administrative roles  
    public function campusRoles(): HasMany
    {
        return $this->hasMany(CampusLecturerRole::class);
    }
    
    public function getCurrentTeachingLoad(): float
    {
        return $this->teachingAssignments()
            ->whereHas('semesterOffering.semester', fn($q) => $q->where('is_active', true))
            ->sum(DB::raw('teaching_percentage * credit_hours')) / 100;
    }
    
    public function canTakeAdditionalLoad(float $hours): bool
    {
        return ($this->getCurrentTeachingLoad() + $hours) <= $this->max_teaching_load_hours;
    }
    
    public function hasPermission($permission_code, $campusId): bool
    {
        $roleIds = $this->campusRoles()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->pluck('role_id');
            
        if ($roleIds->isEmpty()) return false;
        
        return Role::whereIn('id', $roleIds)
            ->whereHas('permissions', fn($q) => $q->where('code', $permission_code))
            ->exists();
    }
}
```

## 📈 Migration Strategy

### Bước 1: Chuẩn Bị Migration

```php
// Migration: Add fields to users table
class AddTeachingFieldsToUsersTable extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['admin', 'staff', 'student', 'lecturer'])
                  ->default('staff')->after('email');
            $table->string('employee_id', 20)->unique()->nullable()->after('user_type');
            $table->boolean('can_teach')->default(false)->after('employee_id');
            $table->decimal('max_teaching_hours', 4, 2)->default(0)->after('can_teach');
            $table->unsignedBigInteger('department_id')->nullable()->after('max_teaching_hours');
            $table->string('position')->nullable()->after('department_id');
            $table->date('hire_date')->nullable()->after('position');
            $table->enum('employment_status', ['active', 'on_leave', 'terminated', 'retired'])
                  ->default('active')->after('hire_date');
            $table->string('office_location', 100)->nullable()->after('employment_status');
            
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->index(['user_type', 'employment_status']);
            $table->index('employee_id');
        });
    }
}
```

### Bước 2: Tạo Bảng Mới

```php
// Migration: Create students table
class CreateStudentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->unique();
            $table->string('full_name', 100);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            
            // Migration tracking
            $table->unsignedBigInteger('migrated_from_user_id')->nullable();
            
            // Academic fields...
            // [Đầy đủ các fields như đã định nghĩa]
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('migrated_from_user_id')->references('id')->on('users')->onDelete('set null');
            // [Các foreign keys khác]
        });
    }
}
```

### Bước 3: Data Migration

```php
class MigrateStudentDataFromUsers extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Migrate students từ users table
            $studentUsers = User::where('user_type', 'student')
                ->with('studentEnrollments')
                ->get();
                
            foreach ($studentUsers as $user) {
                $enrollment = $user->studentEnrollments->first();
                
                $student = Student::create([
                    'migrated_from_user_id' => $user->id,
                    'student_id' => $this->generateStudentId(),
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'password' => $user->password,
                    'campus_id' => $enrollment?->program?->campus_id ?? 1,
                    'program_id' => $enrollment?->program_id ?? 1,
                    'specialization_id' => $enrollment?->specialization_id,
                    // Map other fields...
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);
                
                // Update StudentEnrollment to reference new student
                StudentEnrollment::where('user_id', $user->id)
                    ->update(['student_id' => $student->id]);
            }
        });
    }
}
```

## 🎯 Benefits của Kiến Trúc Cập Nhật

### ✅ Tận Dụng Infrastructure Có Sẵn
- Sử dụng campus-based role system đã có
- Giữ lại permission hierarchy đã implement
- Tương thích với StudentEnrollment system hiện tại

### ✅ Migration Seamless
- Gradual migration từ Users sang Students
- Maintain data integrity với foreign key references
- Backward compatibility trong quá trình transition

### ✅ Scalable Architecture
- Independent authentication cho từng user type
- Campus-specific permissions cho tất cả user types
- Flexible teaching assignment system

### ✅ Future-Ready
- Dễ dàng extend thêm role types
- Support cho cross-campus assignments
- Microservice architecture readiness

Kiến trúc này tận dụng tối đa những gì đã được implement và cung cấp path migration an toàn cho hệ thống của bạn! 
