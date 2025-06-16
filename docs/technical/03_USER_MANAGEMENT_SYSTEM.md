# Giai Đoạn 3: Quản Lý Người Dùng Cơ Bản

## 📋 Tổng Quan

Giai đoạn 3 xây dựng hệ thống quản lý người dùng với kiến trúc tách biệt:
- **Quản Lý User**: Admin và Staff (cán bộ nhân viên) với khả năng giảng dạy
- **Quản Lý Sinh Viên**: Bảng riêng biệt cho student management
- **Quản Lý Giảng Viên**: Bảng riêng biệt cho lecturer management
- **Hệ Thống Phân Quyền**: Role-based access control chi tiết
- **Authentication**: Bảo mật cho tất cả các loại tài khoản

## 🎯 Mục Tiêu Giai Đoạn 3

🔄 **Đang Phát Triển**:
- Kiến trúc tách biệt cho User, Student, Lecturer
- Staff có thể kiêm nhiệm teaching assignments
- Independent authentication cho từng loại user
- Cross-role permission management
- Comprehensive audit logging

## 🏗️ Kiến Trúc Hệ Thống Hiện Tại

### Backend Architecture (Laravel 12)
```
app/
├── Models/
│   ├── User.php                      # Current: All users, migrating to Admin & Staff
│   ├── Student.php                   # New: Independent student model
│   ├── Lecturer.php                  # New: Independent lecturer model
│   ├── StudentEnrollment.php         # Current: Student academic records
│   ├── StudentUnitEnrollment.php     # Current: Course enrollments
│   ├── Role.php                      # Current: System roles with hierarchy
│   ├── Permission.php                # Current: Hierarchical permissions
│   ├── CampusUserRole.php            # Current: Campus-based role assignments
│   ├── Campus.php                    # Current: Multi-campus support
│   └── TeachingAssignment.php        # New: Teaching assignments
├── Http/Controllers/
│   ├── UserController.php            # Admin & Staff management
│   ├── StudentController.php         # Student operations
│   ├── LecturerController.php        # Lecturer management
│   ├── AuthController.php            # Multi-type authentication
│   └── RoleController.php            # Role management
├── Services/
│   ├── UserManagementService.php     # User lifecycle
│   ├── StudentManagementService.php  # Student operations
│   ├── LecturerManagementService.php # Lecturer operations
│   ├── AuthenticationService.php     # Multi-auth logic
│   └── TeachingAssignmentService.php # Teaching role management
└── Http/Middleware/
    ├── CheckUserType.php             # User type verification
    ├── CheckPermission.php           # Permission checking
    └── AuditMiddleware.php           # Activity logging
```

## 📊 Cơ Sở Dữ Liệu Mới

### Users Table (Admin & Staff Only)
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    address TEXT,
    avatar_url VARCHAR(500),
    user_type ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    department_id BIGINT UNSIGNED,
    position VARCHAR(255),
    hire_date DATE,
    employment_status ENUM('active', 'on_leave', 'terminated', 'retired') DEFAULT 'active',
    can_teach BOOLEAN DEFAULT FALSE,
    max_teaching_hours DECIMAL(4,2) DEFAULT 0.00,
    office_location VARCHAR(100),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);
```

### Students Table (Independent)
```sql
CREATE TABLE students (
    id BIGINT UNSIGNED PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    passport_number VARCHAR(20),
    address TEXT,
    avatar_url VARCHAR(500),
    campus_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED,
    class_group_id BIGINT UNSIGNED,
    admission_date DATE NOT NULL,
    expected_graduation_date DATE,
    student_type ENUM('full_time', 'part_time', 'exchange', 'visiting') DEFAULT 'full_time',
    enrollment_status ENUM('enrolled', 'active', 'on_leave', 'suspended', 'graduated', 'dropped_out') DEFAULT 'enrolled',
    current_year INTEGER DEFAULT 1,
    current_semester INTEGER DEFAULT 1,
    total_credits_earned DECIMAL(5,2) DEFAULT 0.00,
    cumulative_gpa DECIMAL(3,2),
    academic_standing ENUM('good_standing', 'probation', 'suspension', 'dean_list', 'honor_roll') DEFAULT 'good_standing',
    financial_status ENUM('current', 'overdue', 'hold', 'scholarship') DEFAULT 'current',
    advisor_lecturer_id BIGINT UNSIGNED,
    parent_guardian_name VARCHAR(255),
    parent_guardian_phone VARCHAR(20),
    parent_guardian_email VARCHAR(255),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(20),
    high_school_name VARCHAR(255),
    high_school_graduation_year YEAR,
    entrance_exam_score DECIMAL(5,2),
    scholarship_info JSON,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL
);
```

### Lecturers Table (Independent)
```sql
CREATE TABLE lecturers (
    id BIGINT UNSIGNED PRIMARY KEY,
    lecturer_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(100) DEFAULT 'Vietnamese',
    national_id VARCHAR(20) UNIQUE,
    address TEXT,
    avatar_url VARCHAR(500),
    campus_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    hire_date DATE NOT NULL,
    employment_type ENUM('full_time', 'part_time', 'adjunct', 'visiting', 'emeritus') DEFAULT 'full_time',
    employment_status ENUM('active', 'on_leave', 'sabbatical', 'retired', 'terminated') DEFAULT 'active',
    academic_rank ENUM('instructor', 'assistant_professor', 'associate_professor', 'professor', 'distinguished_professor') DEFAULT 'instructor',
    highest_degree VARCHAR(100),
    highest_degree_institution VARCHAR(255),
    highest_degree_year YEAR,
    areas_of_expertise JSON,
    research_interests TEXT,
    office_location VARCHAR(100),
    office_hours TEXT,
    cv_file_path VARCHAR(500),
    research_gate_profile VARCHAR(255),
    google_scholar_profile VARCHAR(255),
    orcid_id VARCHAR(50),
    teaching_load_hours DECIMAL(4,2) DEFAULT 0.00,
    max_teaching_load_hours DECIMAL(4,2) DEFAULT 40.00,
    is_thesis_supervisor BOOLEAN DEFAULT FALSE,
    max_thesis_students INTEGER DEFAULT 5,
    current_thesis_students INTEGER DEFAULT 0,
    salary_grade VARCHAR(10),
    contract_end_date DATE,
    performance_rating DECIMAL(3,2),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);
```

### Staff Teaching Assignments Table
```sql
CREATE TABLE staff_teaching_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    assignment_type ENUM('primary', 'co_instructor', 'teaching_assistant') DEFAULT 'primary',
    teaching_percentage DECIMAL(5,2) DEFAULT 100.00,
    hourly_rate DECIMAL(8,2),
    total_compensation DECIMAL(10,2),
    assigned_date DATE NOT NULL,
    assignment_status ENUM('pending', 'accepted', 'declined', 'active', 'completed') DEFAULT 'pending',
    response_deadline DATE,
    accepted_date DATE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE
);
```

## 🔧 Model Relationships Mới

### User Model (Hiện Tại - Campus-Based Roles)
```php
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'address',
        // New fields for staff teaching (to be added)
        'user_type', 'employee_id', 'can_teach', 'max_teaching_hours',
        'department_id', 'position', 'hire_date', 'employment_status',
        'office_location'
    ];
    
    // Current campus-based role system
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
    
    // Current student enrollment relationship (migration period)
    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
    
    // New methods for staff teaching (to be added)
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
        return $this->can_teach ?? false;
    }
}
```

### Student Model (Independent)
```php
class Student extends Authenticatable
{
    use SoftDeletes;
    
    protected $guard = 'student';
    
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
    
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }
    
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'advisor_lecturer_id');
    }
    
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
    
    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }
    
    // Academic methods
    public function calculateGPA(): float
    {
        // GPA calculation logic
    }
    
    public function getTotalCreditsEarned(): float
    {
        return $this->total_credits_earned;
    }
    
    public function isEligibleForGraduation(): bool
    {
        // Graduation eligibility logic
    }
}
```

### Lecturer Model (Independent)
```php
class Lecturer extends Authenticatable
{
    use SoftDeletes;
    
    protected $guard = 'lecturer';
    
    protected $casts = [
        'areas_of_expertise' => 'array',
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
    
    // Teaching load management
    public function getCurrentTeachingLoad(): float
    {
        return $this->teachingAssignments()
            ->whereHas('courseOffering', fn($q) => $q->where('status', 'active'))
            ->sum(DB::raw('teaching_percentage * credit_hours')) / 100;
    }
    
    public function canTakeAdditionalLoad(float $hours): bool
    {
        return ($this->getCurrentTeachingLoad() + $hours) <= $this->max_teaching_load_hours;
    }
    
    public function canSuperviseStudent(): bool
    {
        return $this->is_thesis_supervisor && 
               $this->current_thesis_students < $this->max_thesis_students;
    }
}
```

## 🔐 Multi-Authentication System

### Authentication Guards
```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'user' => [
        'driver' => 'session', 
        'provider' => 'users',
    ],
    'student' => [
        'driver' => 'session',
        'provider' => 'students',
    ],
    'lecturer' => [
        'driver' => 'session',
        'provider' => 'lecturers',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'students' => [
        'driver' => 'eloquent',
        'model' => App\Models\Student::class,
    ],
    'lecturers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Lecturer::class,
    ],
],
```

### Multi-Auth Service
```php
class AuthenticationService
{
    public function attemptLogin(string $email, string $password, string $userType): array
    {
        $guard = $this->getGuardForUserType($userType);
        
        if (Auth::guard($guard)->attempt(['email' => $email, 'password' => $password])) {
            $user = Auth::guard($guard)->user();
            
            return [
                'success' => true,
                'user' => $user,
                'user_type' => $userType,
                'guard' => $guard,
                'redirect' => $this->getRedirectPath($userType),
            ];
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    private function getGuardForUserType(string $userType): string
    {
        return match($userType) {
            'admin', 'staff' => 'user',
            'student' => 'student', 
            'lecturer' => 'lecturer',
            default => 'web'
        };
    }
    
    private function getRedirectPath(string $userType): string
    {
        return match($userType) {
            'admin' => '/admin/dashboard',
            'staff' => '/staff/dashboard',
            'student' => '/student/dashboard',
            'lecturer' => '/lecturer/dashboard',
            default => '/dashboard'
        };
    }
}
```

## 🎨 Frontend Architecture Updates

### Login Interface
```vue
<template>
  <div class="multi-auth-login">
    <!-- User Type Selection -->
    <div class="user-type-tabs mb-6">
      <button 
        v-for="type in userTypes" 
        :key="type.value"
        @click="selectedUserType = type.value"
        :class="{ 'active': selectedUserType === type.value }"
        class="tab-button"
      >
        <Icon :name="type.icon" />
        {{ type.label }}
      </button>
    </div>
    
    <!-- Login Form -->
    <form @submit="handleLogin" class="space-y-6">
      <FormField name="email">
        <FormLabel>Email Address</FormLabel>
        <FormControl>
          <Input
            v-model="form.email"
            type="email"
            :placeholder="getEmailPlaceholder(selectedUserType)"
            required
          />
        </FormControl>
        <FormMessage />
      </FormField>
      
      <FormField name="password">
        <FormLabel>Password</FormLabel>
        <FormControl>
          <Input
            v-model="form.password"
            type="password"
            required
          />
        </FormControl>
        <FormMessage />
      </FormField>
      
      <Button 
        type="submit" 
        :loading="isLoading"
        class="w-full"
      >
        Login as {{ getUserTypeLabel(selectedUserType) }}
      </Button>
    </form>
  </div>
</template>

<script setup lang="ts">
const userTypes = [
  { value: 'admin', label: 'Administrator', icon: 'shield' },
  { value: 'staff', label: 'Staff', icon: 'users' },
  { value: 'lecturer', label: 'Lecturer', icon: 'graduation-cap' },
  { value: 'student', label: 'Student', icon: 'book-open' },
]

const selectedUserType = ref('student')
const form = reactive({
  email: '',
  password: ''
})

const handleLogin = async () => {
  const response = await router.post('/auth/login', {
    ...form,
    user_type: selectedUserType.value
  })
  
  if (response.success) {
    router.visit(response.redirect)
  }
}
</script>
```

## 📊 Permission System Updates

### Role Definitions
```php
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Admin Roles
        $superAdmin = Role::create([
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'guard_name' => 'user',
        ]);
        
        $admin = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator', 
            'guard_name' => 'user',
        ]);
        
        // Staff Roles
        $academicStaff = Role::create([
            'name' => 'academic_staff',
            'display_name' => 'Academic Staff',
            'guard_name' => 'user',
        ]);
        
        $teachingStaff = Role::create([
            'name' => 'teaching_staff',
            'display_name' => 'Teaching Staff',
            'guard_name' => 'user',
        ]);
        
        // Lecturer Roles
        $lecturer = Role::create([
            'name' => 'lecturer',
            'display_name' => 'Lecturer',
            'guard_name' => 'lecturer',
        ]);
        
        $seniorLecturer = Role::create([
            'name' => 'senior_lecturer',
            'display_name' => 'Senior Lecturer',
            'guard_name' => 'lecturer',
        ]);
        
        // Student Role
        $student = Role::create([
            'name' => 'student',
            'display_name' => 'Student',
            'guard_name' => 'student',
        ]);
    }
}
```

## 🔧 Service Layer Updates

### User Management Service
```php
class UserManagementService
{
    public function createStaff(array $data): User
    {
        DB::transaction(function () use ($data) {
            $user = User::create([
                'employee_id' => $this->generateEmployeeId(),
                'user_type' => 'staff',
                'can_teach' => $data['can_teach'] ?? false,
                'max_teaching_hours' => $data['max_teaching_hours'] ?? 0,
                ...$data
            ]);
            
            $user->assignRole('academic_staff');
            
            if ($user->can_teach) {
                $user->assignRole('teaching_staff');
            }
            
            event(new StaffCreated($user));
            
            return $user;
        });
    }
    
    public function enableTeaching(User $user, float $maxHours): void
    {
        $user->update([
            'can_teach' => true,
            'max_teaching_hours' => $maxHours,
        ]);
        
        $user->assignRole('teaching_staff');
        
        event(new StaffTeachingEnabled($user));
    }
}

class StudentManagementService  
{
    public function createStudent(array $data): Student
    {
        DB::transaction(function () use ($data) {
            $student = Student::create([
                'student_id' => $this->generateStudentId($data['campus_id']),
                'password' => Hash::make($data['password']),
                ...$data
            ]);
            
            event(new StudentCreated($student));
            
            return $student;
        });
    }
}

class LecturerManagementService
{
    public function createLecturer(array $data): Lecturer
    {
        DB::transaction(function () use ($data) {
            $lecturer = Lecturer::create([
                'lecturer_id' => $this->generateLecturerId($data['campus_id']),
                'password' => Hash::make($data['password']),
                ...$data
            ]);
            
            event(new LecturerCreated($lecturer));
            
            return $lecturer;
        });
    }
}
```

## 🎯 Key Benefits của Kiến Trúc Mới

### ✅ Separation of Concerns
- **Users**: Chỉ cho admin và staff
- **Students**: Quản lý riêng biệt với academic features
- **Lecturers**: Independent với teaching-specific features
- **Staff Teaching**: Flexible assignment cho staff

### ✅ Scalability
- Mỗi entity có thể scale độc lập
- Database sharding dễ dàng hơn
- Performance optimization cho từng loại user

### ✅ Security
- Separated authentication guards
- Role-based permissions cho từng user type  
- Independent password policies

### ✅ Flexibility
- Staff có thể teaching assignments
- Lecturers có thể administrative roles
- Students có thể part-time teaching (future)

## 📊 Updated API Structure

### Authentication Endpoints
```http
POST /api/auth/login                    # Multi-type login
POST /api/auth/logout                   # Multi-guard logout
POST /api/auth/refresh                  # Token refresh

# User Management (Admin/Staff)
GET  /api/users                         # List users
POST /api/users                         # Create user
GET  /api/users/{id}                    # Show user
PUT  /api/users/{id}                    # Update user
POST /api/users/{id}/enable-teaching    # Enable teaching

# Student Management  
GET  /api/students                      # List students
POST /api/students                      # Create student
GET  /api/students/{id}                 # Show student
PUT  /api/students/{id}                 # Update student

# Lecturer Management
GET  /api/lecturers                     # List lecturers  
POST /api/lecturers                     # Create lecturer
GET  /api/lecturers/{id}                # Show lecturer
PUT  /api/lecturers/{id}                # Update lecturer

# Teaching Assignments
GET  /api/teaching-assignments          # List assignments
POST /api/staff/{id}/teaching-assignments # Assign staff to teach
```

Kiến trúc mới này sẽ cung cấp sự linh hoạt và khả năng mở rộng tốt hơn cho hệ thống của bạn!
