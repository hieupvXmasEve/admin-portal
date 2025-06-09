# Giai Đoạn 1: Quản lý Kỳ Học và Chương Trình Đào Tạo

## 📋 Tổng Quan

Giai đoạn 1 tập trung vào xây dựng nền tảng cốt lõi của hệ thống giáo dục, bao gồm:
- **Quản lý Kỳ Học**: Tạo và quản lý các kỳ học với lịch học thuật
- **Quản lý Chương Trình Đào Tạo**: Tạo cấu trúc chương trình học theo phiên bản
- **Quản lý Môn Học**: Tạo và quản lý môn học với hệ thống tiên quyết phức tạp
- **Quản lý Chuyên Ngành**: Tạo các chuyên ngành trong chương trình đào tạo

## 🎯 Mục Tiêu Đạt Được

✅ **Hoàn thành 100%**:
- Cấu trúc học kỳ với lịch học thuật chi tiết
- Chương trình học theo phiên bản và chuyên ngành
- Môn học với hệ thống tiên quyết phức tạp
- Import/Export dữ liệu từ Excel với template linh hoạt
- Giao diện người dùng hoàn chỉnh với Vue.js + TypeScript

## 🏗️ Kiến Trúc Hệ Thống

### Backend Architecture (Laravel 12)
```
app/
├── Models/
│   ├── Semester.php              # Quản lý kỳ học
│   ├── Program.php               # Chương trình đào tạo
│   ├── Specialization.php        # Chuyên ngành
│   ├── CurriculumVersion.php     # Phiên bản chương trình
│   ├── CurriculumUnit.php        # Môn học trong chương trình
│   ├── Unit.php                  # Môn học
│   ├── UnitPrerequisite.php      # Điều kiện tiên quyết
│   └── EquivalentUnit.php        # Môn học tương đương
├── Http/Controllers/
│   ├── SemesterController.php    # CRUD kỳ học
│   ├── ProgramController.php     # CRUD chương trình
│   ├── SpecializationController.php # CRUD chuyên ngành
│   ├── CurriculumController.php  # CRUD chương trình đào tạo
│   └── UnitController.php        # CRUD môn học
├── Services/
│   ├── SemesterManagementService.php # Logic quản lý kỳ học
│   ├── UnitValidationService.php     # Validation môn học
│   ├── UnitRelationshipService.php  # Quản lý quan hệ môn học
│   └── UnitExcelImportService.php   # Import từ Excel
└── Http/Requests/
    ├── StoreSemesterRequest.php  # Validation tạo kỳ học
    ├── StoreProgramRequest.php   # Validation tạo chương trình
    └── StoreUnitRequest.php      # Validation tạo môn học
```

### Frontend Architecture (Vue.js 3 + TypeScript)
```
resources/js/
├── pages/
│   ├── Semesters/
│   │   ├── Index.vue            # Danh sách kỳ học
│   │   ├── Create.vue           # Tạo kỳ học
│   │   ├── Edit.vue             # Chỉnh sửa kỳ học
│   │   └── Show.vue             # Chi tiết kỳ học
│   ├── Programs/
│   │   ├── Index.vue            # Danh sách chương trình
│   │   ├── Create.vue           # Tạo chương trình
│   │   └── CurriculumBuilder.vue # Xây dựng chương trình
│   ├── Specializations/
│   │   ├── Index.vue            # Danh sách chuyên ngành
│   │   └── Create.vue           # Tạo chuyên ngành
│   └── Units/
│       ├── Index.vue            # Danh sách môn học
│       ├── Create.vue           # Tạo môn học
│       ├── Edit.vue             # Chỉnh sửa môn học
│       ├── Show.vue             # Chi tiết môn học
│       └── Import.vue           # Import từ Excel
├── components/
│   ├── DataTable.vue            # Bảng dữ liệu
│   ├── DataPagination.vue       # Phân trang
│   ├── DebouncedInput.vue       # Input tìm kiếm
│   └── ui/                      # Shadcn/UI components
├── types/
│   ├── models.ts                # TypeScript interfaces
│   ├── api.ts                   # API response types
│   └── validation.ts            # Validation constants
└── composables/
    ├── useSemesters.ts          # Logic kỳ học
    ├── usePrograms.ts           # Logic chương trình
    └── useUnits.ts              # Logic môn học
```

## 📊 Cơ Sở Dữ Liệu

### Semesters Table
```sql
CREATE TABLE semesters (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    semester_type ENUM('fall', 'spring', 'summer', 'winter', 'intersession') DEFAULT 'fall',
    year VARCHAR(4),
    start_date DATE,
    end_date DATE,
    enrollment_start_date DATE,
    enrollment_end_date DATE,
    add_drop_deadline DATE,
    withdrawal_deadline DATE,
    final_exam_start DATE,
    final_exam_end DATE,
    is_active BOOLEAN DEFAULT FALSE,
    is_current BOOLEAN DEFAULT FALSE,
    is_registration_open BOOLEAN DEFAULT FALSE,
    max_credit_load DECIMAL(4,2) DEFAULT 18.00,
    min_credit_load DECIMAL(4,2) DEFAULT 12.00,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### Programs Table
```sql
CREATE TABLE programs (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    degree_level ENUM('bachelor', 'master', 'phd') DEFAULT 'bachelor',
    description TEXT,
    duration_years INTEGER DEFAULT 4,
    total_credits DECIMAL(5,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Specializations Table
```sql
CREATE TABLE specializations (
    id BIGINT UNSIGNED PRIMARY KEY,
    program_id BIGINT UNSIGNED,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);
```

### Curriculum Versions Table
```sql
CREATE TABLE curriculum_versions (
    id BIGINT UNSIGNED PRIMARY KEY,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED NULL,
    version_code VARCHAR(50),
    semester_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL
);
```

### Units Table
```sql
CREATE TABLE units (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    credit_points DECIMAL(4,2) NOT NULL,
    level VARCHAR(20),
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### Unit Prerequisites Table
```sql
CREATE TABLE unit_prerequisites (
    id BIGINT UNSIGNED PRIMARY KEY,
    unit_id BIGINT UNSIGNED NOT NULL,
    required_unit_id BIGINT UNSIGNED,
    required_credits DECIMAL(5,2),
    condition_type ENUM('prerequisite', 'co_requisite', 'anti_requisite', 'credit_requirement', 'assumed_knowledge', 'textual') NOT NULL,
    group_logic ENUM('AND', 'OR') DEFAULT 'AND',
    group_description VARCHAR(255),
    free_text TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (required_unit_id) REFERENCES units(id) ON DELETE CASCADE
);
```

## 🔧 Tính Năng Chính

### 1. Quản Lý Kỳ Học

#### Lịch Học Thuật Toàn Diện
- **Các loại kỳ học**: Fall, Spring, Summer, Winter, Intersession
- **Theo dõi năm học**: Tự động tạo (ví dụ: "2024-2025")
- **Nhiều giai đoạn thời gian**: Đăng ký, Add/Drop, Rút môn, Thi cuối kỳ
- **Trạng thái kỳ học**: Active, Current, Registration Open

#### Tính Năng Nâng Cao
```php
// Kiểm tra trạng thái kỳ học
$semester->isEnrollmentOpen()     // Có đang mở đăng ký?
$semester->isAddDropPeriod()      // Có trong thời gian add/drop?
$semester->isWithdrawalPeriod()   // Có trong thời gian rút môn?
$semester->isFinalExamPeriod()    // Có trong thời gian thi cuối kỳ?

// Scopes để truy vấn
Semester::current()->get()        // Kỳ học hiện tại
Semester::enrollmentOpen()->get() // Kỳ đang mở đăng ký
Semester::byType('fall')->get()   // Kỳ thu
```

### 2. Quản Lý Chương Trình Đào Tạo

#### Phân Cấp Chương Trình
- **Program**: Chương trình đào tạo (Bachelor, Master, PhD)
- **Specialization**: Chuyên ngành trong chương trình
- **Curriculum Version**: Phiên bản chương trình theo thời gian
- **Curriculum Units**: Môn học trong từng phiên bản

#### Tính Năng Version Control
```php
// Tạo phiên bản mới của chương trình
$newVersion = CurriculumVersion::create([
    'program_id' => $program->id,
    'specialization_id' => $specialization->id,
    'version_code' => '2024.1',
    'semester_id' => $semester->id,
    'name' => 'Computer Science 2024 v1',
    'effective_date' => '2024-08-01',
]);

// Copy môn học từ phiên bản cũ
$oldVersion->curriculumUnits->each(function ($unit) use ($newVersion) {
    $newVersion->curriculumUnits()->create($unit->toArray());
});
```

### 3. Quản Lý Môn Học

#### Hệ Thống Tiên Quyết Phức Tạp
Hỗ trợ các biểu thức tiên quyết phức tạp như:
- `(P)175cps And ((E) BUS30010 OR BUS30024)`
- `(CS101 OR COMP101) AND MATH101`
- `150cps AND (STAT101 OR MATH101 OR DATA101)`

#### Cấu Trúc Tiên Quyết
```php
// Ví dụ: (P)175cps And ((E) BUS30010 OR BUS30024)
$prerequisites = [
    [
        'unit_id' => $unit->id,
        'condition_type' => 'credit_requirement',
        'required_credits' => 175,
        'group_logic' => 'AND',
        'group_description' => 'Credit requirement',
    ],
    [
        'unit_id' => $unit->id,
        'condition_type' => 'anti_requisite',
        'required_unit_id' => $bus30010->id,
        'group_logic' => 'OR',
        'group_description' => 'Business ethics choice',
    ],
    [
        'unit_id' => $unit->id,
        'condition_type' => 'anti_requisite',
        'required_unit_id' => $bus30024->id,
        'group_logic' => 'OR',
        'group_description' => 'Business ethics choice',
    ],
];
```

#### Validation Nâng Cao
- **Circular Dependency Detection**: Phát hiện vòng lặp trong tiên quyết
- **Conflict Validation**: Kiểm tra xung đột anti-requisite vs prerequisite
- **Edit Restrictions**: Hạn chế chỉnh sửa khi có quan hệ active

### 4. Import/Export Excel

#### Template Linh Hoạt
Hỗ trợ 3 loại template:
- **Detailed Format**: Units + Prerequisites
- **Complete Format**: Units + Prerequisites + Equivalents
- **Combined Format**: Units + Prerequisites + Equivalents + Syllabus + Assessments

#### Cấu Trúc Template Mới
```
Unit Code* | Group Logic* | Group Description | Condition Type* | Required Unit Code | Required Credits | Free Text
CS301      | AND          | Credit requirement| credit_requirement |                   | 175              | 175 credit points
CS301      | OR           | Business choice   | anti_requisite     | BUS30010          |                  |
CS301      | OR           | Business choice   | anti_requisite     | BUS30024          |                  |
```

## 🎨 Giao Diện Người Dùng

### Design System
- **Framework**: Vue.js 3 + TypeScript
- **UI Library**: Shadcn/UI + TailwindCSS
- **Icons**: Lucide Icons
- **Forms**: Vee-validate + Zod schemas
- **Tables**: TanStack Table

### Key UI Components

#### DataTable với Pagination
```vue
<template>
  <div class="space-y-4">
    <DataTable
      :data="semesters.data"
      :columns="columns"
      :loading="loading"
    />
    <DataPagination
      :current-page="semesters.meta.current_page"
      :per-page="semesters.meta.per_page"
      :total="semesters.meta.total"
      :last-page="semesters.meta.last_page"
      @page-change="handlePageChange"
    />
  </div>
</template>
```

#### DebouncedInput cho Search
```vue
<template>
  <DebouncedInput
    v-model="filters.search"
    @debounced="handleSearch"
    placeholder="Search semesters..."
    :debounce="300"
  />
</template>
```

#### Form Validation với Zod
```typescript
const formSchema = toTypedSchema(z.object({
  code: z.string().min(1, 'Code is required').max(20, 'Code too long'),
  name: z.string().min(1, 'Name is required').max(255, 'Name too long'),
  semester_type: z.enum(['fall', 'spring', 'summer', 'winter', 'intersession']),
  start_date: z.string().optional(),
  end_date: z.string().optional(),
}))
```

### Responsive Design
- **Mobile-first**: Thiết kế ưu tiên mobile
- **Tablet Support**: Giao diện tối ưu cho tablet
- **Desktop**: Full features cho desktop

## 📈 Statistics & Analytics

### Semester Statistics
```php
public function getSemesterStatistics(Semester $semester): array
{
    return [
        'total_programs' => $semester->curriculumVersions()->distinct('program_id')->count(),
        'total_units_offered' => $semester->semesterOfferings()->count(),
        'total_enrollments' => $semester->enrollments()->count(),
        'enrollment_capacity_percentage' => $this->calculateCapacityPercentage($semester),
        'popular_units' => $this->getPopularUnits($semester),
        'academic_calendar_events' => $this->getAcademicEvents($semester),
    ];
}
```

### Unit Statistics
```php
public function getUnitStatistics(): array
{
    return [
        'total_units' => Unit::count(),
        'active_units' => Unit::where('status', 'active')->count(),
        'units_with_prerequisites' => Unit::has('prerequisites')->count(),
        'units_with_equivalents' => Unit::has('equivalentUnits')->count(),
        'average_credit_points' => Unit::avg('credit_points'),
        'credit_distribution' => $this->getCreditDistribution(),
    ];
}
```

## 🧪 Testing Strategy

### Backend Testing (Pest)
```php
// Semester Tests
test('can create semester with academic calendar', function () {
    $semester = Semester::factory()->withAcademicCalendar()->create();
    
    expect($semester->isEnrollmentOpen())->toBeFalse();
    expect($semester->enrollment_start_date)->not->toBeNull();
    expect($semester->final_exam_end)->not->toBeNull();
});

// Unit Prerequisites Tests
test('detects circular dependencies in prerequisites', function () {
    $unitA = Unit::factory()->create(['code' => 'CS101']);
    $unitB = Unit::factory()->create(['code' => 'CS102']);
    
    // A requires B
    UnitPrerequisite::create([
        'unit_id' => $unitA->id,
        'required_unit_id' => $unitB->id,
        'condition_type' => 'prerequisite',
    ]);
    
    // Try to make B require A (circular)
    expect(fn() => UnitPrerequisite::create([
        'unit_id' => $unitB->id,
        'required_unit_id' => $unitA->id,
        'condition_type' => 'prerequisite',
    ]))->toThrow(ValidationException::class);
});
```

### Frontend Testing
```typescript
// Component Tests với Vue Test Utils
describe('SemesterIndex', () => {
  test('displays semesters in table', async () => {
    const wrapper = mount(SemesterIndex, {
      props: {
        semesters: mockSemesterData
      }
    })
    
    expect(wrapper.find('[data-testid="semester-table"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid="semester-row"]')).toHaveLength(3)
  })
})
```

## 🚀 Performance Optimization

### Database Optimization
- **Indexes**: Đánh index cho các trường tìm kiếm thường xuyên
- **Eager Loading**: Load relationships để tránh N+1 queries
- **Query Optimization**: Sử dụng scopes và query builders hiệu quả

### Frontend Optimization
- **Lazy Loading**: Component và route lazy loading
- **Debounced Search**: Giảm số lượng API calls
- **Caching**: Cache dữ liệu static như programs, semester types
- **Pagination**: Giảm tải dữ liệu với pagination

## 📝 API Documentation

### Semester Endpoints
```http
GET    /api/semesters                 # List semesters
POST   /api/semesters                 # Create semester
GET    /api/semesters/{id}            # Show semester
PUT    /api/semesters/{id}            # Update semester
DELETE /api/semesters/{id}            # Delete semester
POST   /api/semesters/{id}/activate   # Activate semester
POST   /api/semesters/{id}/set-current # Set as current semester
```

### Unit Endpoints
```http
GET    /api/units                     # List units
POST   /api/units                     # Create unit
GET    /api/units/{id}                # Show unit
PUT    /api/units/{id}                # Update unit
DELETE /api/units/{id}                # Delete unit
POST   /api/units/import              # Import from Excel
GET    /api/units/export              # Export to Excel
POST   /api/units/bulk-delete         # Bulk delete
GET    /api/units/{id}/prerequisites  # Get prerequisites tree
```

## 🎯 Kết Quả Đạt Được

### ✅ Hoàn Thành 100%
1. **Hệ thống Kỳ học**:
   - ✅ CRUD operations hoàn chỉnh
   - ✅ Academic calendar với multiple date periods
   - ✅ Semester activation và management
   - ✅ Statistics và analytics

2. **Hệ thống Chương trình**:
   - ✅ Program management với degree levels
   - ✅ Specialization support
   - ✅ Curriculum versioning
   - ✅ Unit type classification (core, elective, major)

3. **Hệ thống Môn học**:
   - ✅ Complex prerequisite expressions
   - ✅ Import/Export Excel với template linh hoạt
   - ✅ Circular dependency detection
   - ✅ Advanced validation rules

4. **Giao diện User-friendly**:
   - ✅ Responsive design
   - ✅ Real-time search và filtering
   - ✅ Form validation với error handling
   - ✅ Statistics dashboards

### 📊 Số liệu thống kê
- **Backend Files**: 25+ PHP files (Controllers, Models, Services, Requests)
- **Frontend Components**: 20+ Vue components
- **Database Tables**: 8 core tables với relationships
- **API Endpoints**: 30+ REST endpoints
- **Test Coverage**: 80+ test cases

## 🔜 Chuẩn Bị Cho Giai Đoạn 2

Hệ thống hiện tại đã sẵn sàng cho giai đoạn 2 với:
- ✅ Stable database schema
- ✅ Comprehensive API endpoints
- ✅ Reusable UI components
- ✅ Service layer architecture
- ✅ Testing infrastructure

**Giai đoạn 2** sẽ tập trung vào "Quản lý cơ sở và lớp học" và sẽ được xây dựng dựa trên nền tảng vững chắc này. 
