# Enhanced Semester System Implementation

## Overview

The enhanced semester system provides comprehensive campus-wide semester management with academic calendar features, enrollment periods, student tracking, and unit offerings management. This system integrates seamlessly with the existing curriculum and specialization system.

## Key Features

### 1. Academic Calendar Management
- **Semester Types**: Fall, Spring, Summer, Winter, Intersession
- **Academic Year Tracking**: Automatic generation (e.g., "2024-2025")
- **Multiple Date Periods**: Enrollment, Add/Drop, Withdrawal, Final Exams
- **Campus-Specific**: Each campus manages its own academic calendar

### 2. Enrollment Management
- **Student Semester Enrollment**: Track students enrolled in each semester
- **Unit Offerings**: Manage which units are offered each semester
- **Capacity Management**: Track enrollment limits and waitlists
- **Academic Standing**: Monitor student performance and status

### 3. Academic Periods
- **Enrollment Periods**: Define when students can register
- **Add/Drop Period**: Allow course changes within deadlines
- **Withdrawal Period**: Manage late withdrawals with academic impact
- **Final Exam Period**: Schedule and track examination periods

### 4. Credit Load Management
- **Minimum/Maximum Credit Loads**: Configurable per semester
- **Full-time/Part-time Status**: Automatic classification
- **Academic Standing**: Track probation, dean's list, etc.

## Database Schema

### Enhanced Semesters Table
```sql
-- Enhanced semesters table with academic calendar features
ALTER TABLE semesters ADD COLUMN (
    semester_type ENUM('fall', 'spring', 'summer', 'winter', 'intersession') DEFAULT 'fall',
    year VARCHAR(4), -- e.g., "2024"
    enrollment_start_date DATE,
    enrollment_end_date DATE,
    add_drop_deadline DATE,
    withdrawal_deadline DATE,
    final_exam_start DATE,
    final_exam_end DATE,
    is_current BOOLEAN DEFAULT FALSE,
    is_registration_open BOOLEAN DEFAULT FALSE,
    max_credit_load DECIMAL(4,2) DEFAULT 18.00,
    min_credit_load DECIMAL(4,2) DEFAULT 12.00
);
```

### Student Enrollments Table
```sql
CREATE TABLE student_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED NULL,
    enrollment_status ENUM('enrolled', 'active', 'withdrawn', 'completed', 'suspended', 'deferred') DEFAULT 'enrolled',
    enrollment_date DATE NOT NULL,
    total_credit_hours DECIMAL(5,2) DEFAULT 0.00,
    gpa_semester DECIMAL(3,2) NULL,
    gpa_cumulative DECIMAL(3,2) NULL,
    academic_standing ENUM('good_standing', 'probation', 'suspension', 'dismissal', 'dean_list', 'honor_roll') DEFAULT 'good_standing',
    is_full_time BOOLEAN DEFAULT TRUE,
    is_probation BOOLEAN DEFAULT FALSE,
    is_dean_list BOOLEAN DEFAULT FALSE,
    notes TEXT NULL,
    UNIQUE KEY unique_student_semester_enrollment (user_id, semester_id)
);
```

### Semester Unit Offerings Table
```sql
CREATE TABLE semester_unit_offerings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    semester_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NULL,
    section_code VARCHAR(10) NULL,
    max_capacity INT DEFAULT 30,
    current_enrollment INT DEFAULT 0,
    waitlist_capacity INT DEFAULT 10,
    current_waitlist INT DEFAULT 0,
    delivery_mode ENUM('in_person', 'online', 'hybrid', 'blended') DEFAULT 'in_person',
    schedule_days JSON NULL,
    schedule_time_start TIME NULL,
    schedule_time_end TIME NULL,
    location VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    enrollment_status ENUM('open', 'closed', 'waitlist_only', 'cancelled') DEFAULT 'open',
    UNIQUE KEY unique_semester_unit_section (semester_id, unit_id, section_code)
);
```

### Student Unit Enrollments Table
```sql
CREATE TABLE student_unit_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_enrollment_id BIGINT UNSIGNED NOT NULL,
    semester_unit_offering_id BIGINT UNSIGNED NOT NULL,
    enrollment_status ENUM('enrolled', 'active', 'dropped', 'withdrawn', 'completed', 'waitlisted') DEFAULT 'enrolled',
    enrollment_date DATE NOT NULL,
    drop_date DATE NULL,
    withdrawal_date DATE NULL,
    midterm_grade VARCHAR(3) NULL,
    final_grade VARCHAR(3) NULL,
    grade_status ENUM('in_progress', 'midterm', 'final', 'incomplete', 'audit') DEFAULT 'in_progress',
    attendance_percentage DECIMAL(5,2) NULL,
    is_audit BOOLEAN DEFAULT FALSE,
    payment_status ENUM('paid', 'pending', 'overdue', 'waived', 'scholarship') DEFAULT 'pending',
    UNIQUE KEY unique_student_unit_enrollment (student_enrollment_id, semester_unit_offering_id)
);
```

## Model Relationships

### Semester Model
```php
class Semester extends Model
{
    // Relationships
    public function campus(): BelongsTo
    public function curriculumVersions(): HasMany
    public function enrollments(): HasMany // StudentEnrollment
    public function semesterOfferings(): HasMany // SemesterUnitOffering
    
    // Academic Calendar Methods
    public function isEnrollmentOpen(): bool
    public function isAddDropPeriod(): bool
    public function isWithdrawalPeriod(): bool
    public function isFinalExamPeriod(): bool
    public function isActive(): bool
    public function getEnrollmentStatus(): string
    
    // Scopes
    public function scopeCurrent(Builder $query): void
    public function scopeActive(Builder $query): void
    public function scopeByType(Builder $query, string $type): void
    public function scopeEnrollmentOpen(Builder $query): void
}
```

### StudentEnrollment Model
```php
class StudentEnrollment extends Model
{
    // Relationships
    public function student(): BelongsTo // User
    public function semester(): BelongsTo
    public function program(): BelongsTo
    public function specialization(): BelongsTo
    public function unitEnrollments(): HasMany // StudentUnitEnrollment
    
    // Academic Methods
    public function calculateGPA(): float
    public function getAcademicLoad(): string
    public function isActive(): bool
    
    // Scopes
    public function scopeActive(Builder $query): void
    public function scopeFullTime(Builder $query): void
    public function scopeOnProbation(Builder $query): void
    public function scopeDeansList(Builder $query): void
}
```

## Service Layer

### SemesterManagementService
```php
class SemesterManagementService
{
    // Semester Management
    public function createSemester(Campus $campus, array $data): Semester
    public function setCurrentSemester(Semester $semester): bool
    public function openEnrollment(Semester $semester): bool
    public function closeEnrollment(Semester $semester): bool
    
    // Unit Offerings
    public function createUnitOfferings(Semester $semester, array $offerings): Collection
    public function copyOfferingsFromPreviousSemester(Semester $target, Semester $source): Collection
    
    // Student Enrollment
    public function enrollStudent(User $student, Semester $semester, array $data): StudentEnrollment
    
    // Analytics & Reporting
    public function getSemesterStatistics(Semester $semester): array
    public function getAcademicCalendar(Campus $campus, ?string $academicYear = null): Collection
    public function getSemesterEvents(Semester $semester): array
}
```

## Usage Examples

### 1. Creating a New Semester
```php
$semesterService = new SemesterManagementService();
$campus = Campus::find(1);

$semesterData = [
    'name' => 'Fall 2024',
    'semester_type' => 'fall',
    'start_date' => '2024-08-26',
    'end_date' => '2024-12-15',
    'enrollment_start_date' => '2024-07-01',
    'enrollment_end_date' => '2024-08-20',
    'add_drop_deadline' => '2024-09-10',
    'withdrawal_deadline' => '2024-11-15',
    'final_exam_start' => '2024-12-09',
    'final_exam_end' => '2024-12-15',
    'max_credit_load' => 18.00,
    'min_credit_load' => 12.00,
    'is_current' => true,
    'is_registration_open' => true,
];

$semester = $semesterService->createSemester($campus, $semesterData);
```

### 2. Managing Unit Offerings
```php
$offerings = [
    [
        'unit_id' => 1, // CS101
        'instructor_id' => 5,
        'section_code' => 'A',
        'max_capacity' => 30,
        'delivery_mode' => 'in_person',
        'schedule_days' => ['Monday', 'Wednesday', 'Friday'],
        'schedule_time_start' => '09:00',
        'schedule_time_end' => '10:00',
        'location' => 'Room 101',
    ],
    [
        'unit_id' => 2, // CS102
        'instructor_id' => 6,
        'section_code' => 'B',
        'max_capacity' => 25,
        'delivery_mode' => 'hybrid',
        'schedule_days' => ['Tuesday', 'Thursday'],
        'schedule_time_start' => '14:00',
        'schedule_time_end' => '15:30',
        'location' => 'Room 205',
    ],
];

$createdOfferings = $semesterService->createUnitOfferings($semester, $offerings);
```

### 3. Student Enrollment
```php
$student = User::find(10);
$semester = Semester::current()->first();

$enrollmentData = [
    'program_id' => 1, // IT Program
    'specialization_id' => 2, // Software Development
    'is_full_time' => true,
];

$enrollment = $semesterService->enrollStudent($student, $semester, $enrollmentData);
```

### 4. Academic Calendar Management
```php
// Get academic calendar for a campus
$campus = Campus::find(1);
$calendar = $semesterService->getAcademicCalendar($campus, '2024-2025');

foreach ($calendar as $semesterData) {
    $semester = $semesterData['semester'];
    $events = $semesterData['events'];
    $statistics = $semesterData['statistics'];
    
    echo "Semester: {$semester->name}\n";
    echo "Enrollment Status: {$semester->getEnrollmentStatus()}\n";
    echo "Total Students: {$statistics['enrollments']['total']}\n";
    
    foreach ($events as $event) {
        echo "- {$event['title']}: {$event['date']->format('Y-m-d')}\n";
    }
}
```

### 5. Semester Statistics
```php
$semester = Semester::find(1);
$stats = $semesterService->getSemesterStatistics($semester);

echo "Semester Statistics:\n";
echo "Total Enrollments: {$stats['enrollments']['total']}\n";
echo "Active Enrollments: {$stats['enrollments']['active']}\n";
echo "Full-time Students: {$stats['enrollments']['full_time']}\n";
echo "Part-time Students: {$stats['enrollments']['part_time']}\n";
echo "Unit Offerings: {$stats['offerings']['total']}\n";
echo "Capacity Utilization: {$stats['offerings']['capacity_utilization']}%\n";
```

### 6. Integration with Curriculum System
```php
// Get available units for a student in current semester
$student = User::find(10);
$enrollment = $student->currentSemesterEnrollment();
$specialization = $enrollment->specialization;
$currentSemester = $enrollment->semester;

// Get curriculum units for current year/semester
$availableUnits = $specialization->curriculumVersions()
    ->first()
    ->curriculumUnits()
    ->where('year_level', $student->current_year_level)
    ->where('semester_number', $currentSemester->semester_number)
    ->with('unit')
    ->get();

// Check which units are offered this semester
$offeredUnits = $currentSemester->semesterOfferings()
    ->whereIn('unit_id', $availableUnits->pluck('unit_id'))
    ->available()
    ->with('unit')
    ->get();

foreach ($offeredUnits as $offering) {
    $curriculumUnit = $availableUnits->firstWhere('unit_id', $offering->unit_id);
    
    echo "Unit: {$offering->unit->code} - {$offering->unit->name}\n";
    echo "Role: {$curriculumUnit->group_type}\n";
    echo "Required: " . ($curriculumUnit->is_required ? 'Yes' : 'No') . "\n";
    echo "Available Spots: {$offering->getAvailableSpots()}\n";
    echo "Schedule: {$offering->getScheduleDisplay()}\n";
    echo "---\n";
}
```

## API Endpoints

### Semester Management
- `GET /semesters` - List semesters with filtering and pagination
- `POST /semesters` - Create new semester
- `GET /semesters/{id}` - Show semester details
- `PUT /semesters/{id}` - Update semester
- `DELETE /semesters/{id}` - Delete semester
- `POST /semesters/{id}/set-current` - Set as current semester
- `POST /semesters/{id}/open-enrollment` - Open enrollment
- `POST /semesters/{id}/close-enrollment` - Close enrollment

### Academic Calendar
- `GET /semesters/calendar` - Get academic calendar
- `POST /semesters/{id}/copy-offerings` - Copy offerings from previous semester

### Student Enrollment
- `POST /semesters/{id}/enroll` - Enroll student in semester
- `GET /semesters/{id}/enrollments` - List semester enrollments
- `GET /semesters/{id}/statistics` - Get semester statistics

## Frontend Components

### Enhanced Semester List
- Advanced filtering by semester type, academic year, status
- Real-time enrollment statistics
- Quick actions for enrollment management
- Academic calendar integration

### Semester Creation Form
- Comprehensive form with all academic calendar features
- Date validation and dependency checking
- Credit load configuration
- Academic settings management

### Academic Calendar View
- Visual calendar with important dates
- Semester timeline view
- Event management
- Statistics dashboard

## Integration Points

### 1. Curriculum System Integration
- Curriculum versions are linked to effective semesters
- Unit offerings respect curriculum requirements
- Prerequisites are enforced across semesters

### 2. User Management Integration
- Students are enrolled in semesters with academic tracking
- Instructors are assigned to unit offerings
- Campus-based access control

### 3. Academic Progress Tracking
- GPA calculation across semesters
- Academic standing monitoring
- Credit accumulation tracking
- Graduation requirement checking

## Benefits

1. **Comprehensive Academic Management**: Full lifecycle semester management
2. **Flexible Academic Calendar**: Support for various semester types and schedules
3. **Student Progress Tracking**: Detailed academic performance monitoring
4. **Capacity Management**: Enrollment limits and waitlist management
5. **Integration Ready**: Seamless integration with existing curriculum system
6. **Campus Scalability**: Multi-campus support with independent calendars
7. **Reporting & Analytics**: Rich statistics and reporting capabilities

This enhanced semester system provides a robust foundation for campus-wide academic management while maintaining flexibility for different institutional needs and academic structures. 
