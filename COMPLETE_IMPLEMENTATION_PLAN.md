# Complete Implementation Plan for Swinx Educational Management System

## Overview
This plan outlines the implementation of all functional modules based on the fixed admin menu structure. The system uses Laravel 12 for backend APIs and Vue 3 + Inertia.js for the frontend, with a fully seeded database containing comprehensive academic management entities.

## System Architecture Summary
- **Backend**: Laravel 12, PHP 8.4, MySQL 8.0, Redis
- **Frontend**: Vue.js 3, TypeScript, Inertia.js, TailwindCSS 4.x, reka-ui components
- **Authentication**: Laravel Sanctum with multi-guard support
- **Authorization**: Spatie Laravel Permission with campus-specific roles
- **Database**: Multi-campus architecture with proper relationships and constraints

---

## Module Breakdown and Implementation Plan

### 1. Dashboard Module
**Status**: ✅ **IMPLEMENTED** (Foundation exists)
**Description**: Central overview with key metrics and quick actions

**Data Sources**:
- Student enrollment statistics
- Course offering summaries
- Recent academic activities
- Campus-specific metrics

**Implementation**: Basic dashboard exists, enhance with:
- Real-time metrics widgets
- Quick action shortcuts
- Campus-specific data filtering

---

### 2. System Management Module

#### 2.1 Users Management
**Status**: ✅ **PARTIALLY IMPLEMENTED**
**Controller**: `Web/UserController.php` (needs creation)
**Routes**: `/users/*`
**Database**: `users` table

**Features**:
- CRUD operations for system users
- Campus-specific user assignment
- Role management integration
- Bulk user import/export

**Implementation Priority**: **HIGH** (Foundation module)

#### 2.2 Roles & Permissions
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/RoleController.php`
**Routes**: `/roles/*`
**Database**: `roles`, `permissions`, `role_permissions`, `campus_user_roles`

**Features**:
- ✅ Role CRUD with bitwise permissions
- ✅ Campus-specific role assignments
- Permission matrix management

#### 2.3 Academic Terms (Semesters)
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/SemesterController.php`
**Routes**: `/semesters/*`
**Database**: `semesters`

**Features**:
- ✅ Semester CRUD operations
- Academic calendar management
- Term activation/deactivation

#### 2.4 Campuses & Departments
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/CampusController.php`, `Web/BuildingController.php`
**Routes**: `/campuses/*`, `/buildings/*`
**Database**: `campuses`, `buildings`, `rooms`

**Features**:
- ✅ Campus and building management
- Room management and booking system
- Department structure (extend buildings)

---

### 3. Student Management Module

#### 3.1 Student List
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/StudentController.php`
**Routes**: `/students/*`
**Database**: `students`, `users`

**Features**:
- ✅ Student CRUD operations
- Advanced search and filtering
- Bulk operations and import

#### 3.2 Academic Records
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AcademicRecordController.php` (create)
**Routes**: `/students/{id}/academic-records`
**Database**: `academic_records`, `gpa_calculations`

**Features**:
- Complete academic history view
- GPA calculations and trends
- Transcript generation
- Grade appeals management

**Vue Components**:
```
resources/js/pages/students/academic-records/
├── Index.vue (academic record list)
├── Show.vue (detailed academic record)
├── GpaHistory.vue (GPA trends)
└── TranscriptView.vue (transcript display)
```

#### 3.3 Program/Specialization Change
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/ProgramChangeController.php` (create)
**Routes**: `/students/{id}/program-changes`
**Database**: New `program_change_requests` table needed

**Features**:
- Program transfer requests
- Credit evaluation and mapping
- Approval workflow
- Historical change tracking

#### 3.4 Repeat/Retake Courses
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/CourseRetakeController.php` (create)
**Routes**: `/students/{id}/retakes`
**Database**: Extend `course_registrations` with retake fields

**Features**:
- Retake eligibility checking
- Grade replacement policies
- Retake history tracking
- Impact on GPA calculations

#### 3.5 Academic Standing
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AcademicStandingController.php` (create)
**Routes**: `/students/{id}/standing`
**Database**: New `academic_standings` table needed

**Features**:
- Academic probation tracking
- Honor roll calculations
- Standing history and appeals
- Automated status updates

#### 3.6 Enrollments & Holds
**Status**: ✅ **PARTIALLY IMPLEMENTED**
**Controller**: `Web/SemesterEnrollmentController.php`
**Routes**: `/semesters/{id}/enrollments`
**Database**: `enrollments`, `academic_holds`

**Features**:
- Semester enrollment management
- Academic hold tracking and resolution
- Enrollment restrictions and prerequisites

#### 3.7 Student Status Tracking
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/StudentStatusController.php` (create)
**Routes**: `/students/status-tracking`
**Database**: Extend `students` with status fields

**Features**:
- Active/Inactive/Graduated status
- Leave of absence tracking
- Withdrawal management
- Re-enrollment procedures

---

### 4. Lecturer Management Module

#### 4.1 Lecturer List
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/LecturerController.php` (create)
**Routes**: `/lecturers/*`
**Database**: Extend `users` with lecturer role

**Features**:
- Lecturer profile management
- Teaching credentials tracking
- Contact information and availability

#### 4.2 Teaching Assignments
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/TeachingAssignmentController.php` (create)
**Routes**: `/lecturers/{id}/assignments`
**Database**: Extend `course_offerings` with lecturer assignments

**Features**:
- Course assignment management
- Workload balancing
- Semester planning tools
- Conflict detection

#### 4.3 Lecturer Timetable
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/LecturerTimetableController.php` (create)
**Routes**: `/lecturers/{id}/timetable`
**Database**: `class_sessions`, `course_offerings`

**Features**:
- Personal timetable view
- Room and time conflict detection
- Schedule export capabilities
- Substitute lecturer management

---

### 5. Curriculum & Courses Module

#### 5.1 Programs
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/ProgramController.php`
**Routes**: `/programs/*`
**Database**: `programs`

**Features**:
- ✅ Program CRUD operations
- Degree level management
- Credit requirement tracking

#### 5.2 Specializations
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/SpecializationController.php`
**Routes**: `/specializations/*`
**Database**: `specializations`

**Features**:
- ✅ Specialization CRUD operations
- Program association management
- Specialization requirements

#### 5.3 Curriculum Versions
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/CurriculumVersionController.php`
**Routes**: `/curriculum-versions/*`
**Database**: `curriculum_versions`, `curriculum_units`

**Features**:
- ✅ Version control for curricula
- Unit mapping and requirements
- Historical curriculum tracking

#### 5.4 Units (Courses)
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/UnitController.php`
**Routes**: `/units/*`
**Database**: `units`, `unit_prerequisite_groups`, `unit_prerequisite_conditions`

**Features**:
- ✅ Unit CRUD operations
- Prerequisite management
- Credit point allocation

#### 5.5 Equivalent/Substitute Courses
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/EquivalentUnitController.php` (create)
**Routes**: `/units/equivalent`
**Database**: `equivalent_units`

**Features**:
- Course equivalency mapping
- Transfer credit evaluation
- Substitution approval workflow
- Cross-institution mapping

#### 5.6 Curriculum Structure
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/CurriculumStructureController.php` (create)
**Routes**: `/curriculum/structure`
**Database**: `curriculum_versions`, `curriculum_units`, `graduation_requirements`

**Features**:
- Visual curriculum mapping
- Prerequisite flow charts
- Graduation requirement tracking
- Program pathway visualization

---

### 6. Course Offerings & Registration Module

#### 6.1 Course Offering List
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/CourseOfferingController.php`
**Routes**: `/course-offerings/*`
**Database**: `course_offerings`

**Features**:
- ✅ Course offering CRUD operations
- Semester-based organization
- Capacity and enrollment tracking

#### 6.2 Room & Instructor Assignment
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/RoomAssignmentController.php` (create)
**Routes**: `/course-offerings/{id}/assignments`
**Database**: `course_offerings`, `rooms`, `users` (lecturers)

**Features**:
- Room booking and assignment
- Instructor assignment and conflicts
- Resource allocation optimization
- Automated scheduling assistance

#### 6.3 Class Schedule
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/ClassScheduleController.php` (create)
**Routes**: `/schedules/*`
**Database**: `class_sessions`

**Features**:
- Master schedule generation
- Conflict detection and resolution
- Room utilization reports
- Student/lecturer schedule views

#### 6.4 Course Registration
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/CourseRegistrationController.php`
**Routes**: `/course-registrations/*`
**Database**: `course_registrations`

**Features**:
- ✅ Student course registration
- Prerequisite validation
- Enrollment capacity management

#### 6.5 Enrollment Summary
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/EnrollmentSummaryController.php` (create)
**Routes**: `/enrollments/summary`
**Database**: `course_registrations`, `course_offerings`

**Features**:
- Real-time enrollment statistics
- Capacity utilization reports
- Registration trend analysis
- Waitlist management

---

### 7. Attendance Management Module

#### 7.1 Class Sessions
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/ClassSessionController.php` (create)
**Routes**: `/class-sessions/*`
**Database**: `class_sessions`

**Features**:
- Session scheduling and management
- Session type classification
- Makeup session handling
- Session cancellation procedures

#### 7.2 Take Attendance
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AttendanceController.php` (create)
**Routes**: `/attendance/*`
**Database**: `attendances`

**Features**:
- Quick attendance marking
- Bulk attendance operations
- Late arrival tracking
- Excuse management

#### 7.3 Attendance Reports
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AttendanceReportController.php` (create)
**Routes**: `/attendance/reports`
**Database**: `attendances`, `class_sessions`

**Features**:
- Individual student reports
- Class attendance summaries
- Trend analysis and alerts
- Export capabilities

#### 7.4 GPS & Method Tracking
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AttendanceTrackingController.php` (create)
**Routes**: `/attendance/tracking`
**Database**: Extend `attendances` with location fields

**Features**:
- Location-based attendance verification
- Multiple tracking methods (QR, GPS, Manual)
- Fraud detection and prevention
- Audit trail maintenance

---

### 8. Assessments & Grading Module

#### 8.1 Assessment Components
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AssessmentComponentController.php` (create)
**Routes**: `/assessments/components`
**Database**: `assessment_components`, `assessment_component_details`

**Features**:
- Assessment structure definition
- Weightage and rubric management
- Component type classification
- Outcome alignment

#### 8.2 Enter Grades
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/GradeEntryController.php` (create)
**Routes**: `/grades/entry`
**Database**: `assessment_component_detail_scores`

**Features**:
- Bulk grade entry interface
- Grade validation and verification
- Comment and feedback attachment
- Grade submission workflow

#### 8.3 Academic Results
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AcademicResultController.php` (create)
**Routes**: `/results/*`
**Database**: `academic_records`, `assessment_component_detail_scores`

**Features**:
- Comprehensive result display
- Grade distribution analysis
- Historical performance tracking
- Result publication management

#### 8.4 Re-assessments
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/ReassessmentController.php` (create)
**Routes**: `/reassessments/*`
**Database**: Extend assessments with retake fields

**Features**:
- Retake eligibility verification
- Supplementary exam management
- Grade replacement policies
- Appeal process integration

#### 8.5 Semester Grade Reports
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/GradeReportController.php` (create)
**Routes**: `/grades/reports`
**Database**: `academic_records`, `gpa_calculations`

**Features**:
- Automated grade report generation
- Multiple report formats
- Batch processing capabilities
- Distribution management

#### 8.6 GPA History
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/GpaHistoryController.php` (create)
**Routes**: `/gpa/history`
**Database**: `gpa_calculations`

**Features**:
- Comprehensive GPA tracking
- Trend visualization
- Comparative analysis
- Projection modeling

---

### 9. Academic Summary Module

#### 9.1 GPA Calculations
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/GpaCalculationController.php` (create)
**Routes**: `/gpa/calculations`
**Database**: `gpa_calculations`

**Features**:
- Automated GPA computation
- Multiple GPA scales support
- Recalculation triggers
- Historical accuracy verification

#### 9.2 Degree Classification
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/DegreeClassificationController.php` (create)
**Routes**: `/degrees/classification`
**Database**: `academic_records`, `graduation_requirements`

**Features**:
- Honor classification algorithms
- Graduation requirement checking
- Degree audit processes
- Classification appeals

#### 9.3 Transcript History
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/TranscriptController.php` (create)
**Routes**: `/transcripts/*`
**Database**: `academic_records`

**Features**:
- Official transcript generation
- Digital signature integration
- Version control and auditing
- External verification support

#### 9.4 Academic Performance Report
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/PerformanceReportController.php` (create)
**Routes**: `/performance/reports`
**Database**: Multiple academic tables

**Features**:
- Comprehensive performance analytics
- Predictive modeling
- Intervention recommendations
- Comparative benchmarking

---

### 10. Program Transfers & Course Retakes Module

#### 10.1 Program Change Requests
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/ProgramChangeRequestController.php` (create)
**Routes**: `/program-changes/*`
**Database**: New `program_change_requests` table

**Features**:
- Change request workflow
- Academic advisor approval
- Credit transfer evaluation
- Impact assessment

#### 10.2 Repeated Courses
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/RepeatedCourseController.php` (create)
**Routes**: `/repeated-courses/*`
**Database**: Extend `course_registrations`

**Features**:
- Repeat course tracking
- Policy enforcement
- GPA impact calculation
- Progression monitoring

#### 10.3 Substitute Course Mapping
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/SubstituteCourseController.php` (create)
**Routes**: `/substitute-courses/*`
**Database**: `equivalent_units`

**Features**:
- Course substitution management
- Equivalency verification
- Approval workflows
- Historical mapping

#### 10.4 Credit Transfer Evaluation
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/CreditTransferController.php` (create)
**Routes**: `/credit-transfers/*`
**Database**: New `credit_transfers` table

**Features**:
- External credit evaluation
- Institution verification
- Grade conversion systems
- Transfer credit limits

---

### 11. Reports & Analytics Module

#### 11.1 Student Statistics
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/StudentStatisticsController.php` (create)
**Routes**: `/reports/student-statistics`
**Database**: Multiple student-related tables

**Features**:
- Enrollment demographics
- Retention rate analysis
- Success rate metrics
- Comparative statistics

#### 11.2 Academic Performance Summary
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/PerformanceSummaryController.php` (create)
**Routes**: `/reports/performance-summary`
**Database**: `academic_records`, `gpa_calculations`

**Features**:
- Institutional performance metrics
- Department comparisons
- Trend analysis
- Benchmark reporting

#### 11.3 Attendance Summary
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/AttendanceSummaryController.php` (create)
**Routes**: `/reports/attendance-summary`
**Database**: `attendances`, `class_sessions`

**Features**:
- Attendance rate analytics
- Pattern identification
- Risk factor analysis
- Intervention triggers

#### 11.4 GPA Distribution Charts
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/GpaDistributionController.php` (create)
**Routes**: `/reports/gpa-distribution`
**Database**: `gpa_calculations`

**Features**:
- Visual GPA distributions
- Statistical analysis
- Comparative charts
- Export capabilities

---

### 12. Course Syllabus & Content Module

#### 12.1 Syllabi Management
**Status**: ✅ **IMPLEMENTED**
**Controller**: `Web/SyllabusController.php`
**Routes**: `/syllabus/*`
**Database**: `syllabus`

**Features**:
- ✅ Syllabus CRUD operations
- Unit association management
- Content versioning

#### 12.2 Learning Materials
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/LearningMaterialController.php` (create)
**Routes**: `/learning-materials/*`
**Database**: New `learning_materials` table

**Features**:
- Material upload and organization
- Version control and updates
- Access permission management
- Student resource portal

#### 12.3 Course Learning Outcomes
**Status**: ❌ **NOT IMPLEMENTED**
**Controller**: `Web/LearningOutcomeController.php` (create)
**Routes**: `/learning-outcomes/*`
**Database**: New `learning_outcomes` table

**Features**:
- Outcome definition and mapping
- Assessment alignment
- Program outcome integration
- Achievement tracking
---

## Implementation Priority and Dependencies

### Phase 1: Foundation (Weeks 1-2)
**Priority**: CRITICAL
1. **Users Management** - Core system requirement
2. **Enhanced Dashboard** - Central user interface
3. **Class Sessions Management** - Foundation for attendance
4. **Assessment Components** - Foundation for grading

### Phase 2: Core Academic Operations (Weeks 3-6)
**Priority**: HIGH
1. **Academic Records Management**
2. **Attendance Management** (all modules)
3. **Grade Entry and Management**
4. **Course Schedule Management**
5. **Room & Instructor Assignment**

### Phase 3: Student Services (Weeks 7-10)
**Priority**: HIGH
1. **Program Change Requests**
2. **Academic Standing Tracking**
3. **Credit Transfer Evaluation**
4. **Student Status Management**
5. **Enrollment Summary**

### Phase 4: Faculty and Content (Weeks 11-14)
**Priority**: MEDIUM
1. **Lecturer Management** (all modules)
2. **Learning Materials Management**
3. **Learning Outcomes Tracking**
4. **Assessment Rubrics**
5. **Curriculum Structure Visualization**

### Phase 5: Analytics and Reporting (Weeks 15-18)
**Priority**: MEDIUM
1. **All Reports & Analytics modules**
2. **Academic Performance Reports**
3. **GPA History and Calculations**
4. **Transcript Generation**
5. **Degree Classification**

### Phase 6: Advanced Features (Weeks 19-22)
**Priority**: LOW
1. **GPS & Method Tracking**
2. **Re-assessments Management**
3. **Substitute Course Mapping**
4. **Advanced Analytics**

---

## Frontend Component Structure

### Recommended Directory Organization
```
resources/js/pages/
├── dashboard/
│   └── Index.vue
├── system/
│   ├── users/
│   ├── roles/
│   ├── semesters/
│   └── campuses/
├── students/
│   ├── Index.vue, Create.vue, Edit.vue, Show.vue
│   ├── academic-records/
│   ├── program-changes/
│   ├── retakes/
│   ├── standing/
│   └── status/
├── lecturers/
│   ├── Index.vue, Create.vue, Edit.vue, Show.vue
│   ├── assignments/
│   └── timetable/
├── curriculum/
│   ├── programs/
│   ├── specializations/
│   ├── versions/
│   ├── units/
│   └── structure/
├── courses/
│   ├── offerings/
│   ├── registrations/
│   ├── schedules/
│   └── assignments/
├── attendance/
│   ├── sessions/
│   ├── taking/
│   ├── reports/
│   └── tracking/
├── assessments/
│   ├── components/
│   ├── grades/
│   ├── results/
│   └── rubrics/
├── reports/
│   ├── students/
│   ├── performance/
│   ├── attendance/
│   └── gpa/
└── syllabus/
    ├── management/
    ├── materials/
    └── outcomes/
```

### Shared Components
```
resources/js/components/
├── ui/ (reka-ui components)
├── forms/
│   ├── StudentForm.vue
│   ├── CourseForm.vue
│   └── GradeEntryForm.vue
├── data-tables/
│   ├── DataTable.vue (existing)
│   ├── DataPagination.vue (existing)
│   └── DebouncedInput.vue (existing)
├── charts/
│   ├── GpaChart.vue
│   ├── AttendanceChart.vue
│   └── EnrollmentChart.vue
└── modals/
    ├── ConfirmationModal.vue
    └── FormModal.vue
```

---

## Database Extensions Required

### New Tables Needed
```sql
-- Program change tracking
CREATE TABLE program_change_requests (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    from_program_id BIGINT UNSIGNED NOT NULL,
    to_program_id BIGINT UNSIGNED NOT NULL,
    from_specialization_id BIGINT UNSIGNED NULL,
    to_specialization_id BIGINT UNSIGNED NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Academic standing tracking
CREATE TABLE academic_standings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    standing ENUM('good', 'probation', 'suspension', 'honors') NOT NULL,
    gpa DECIMAL(4,2) NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Learning materials
CREATE TABLE learning_materials (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    unit_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(500) NULL,
    material_type ENUM('document', 'video', 'audio', 'link') NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Learning outcomes
CREATE TABLE learning_outcomes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    unit_id BIGINT UNSIGNED NOT NULL,
    outcome_code VARCHAR(20) NOT NULL,
    description TEXT NOT NULL,
    bloom_level ENUM('remember', 'understand', 'apply', 'analyze', 'evaluate', 'create') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Credit transfers
CREATE TABLE credit_transfers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    external_institution VARCHAR(255) NOT NULL,
    external_course_code VARCHAR(50) NOT NULL,
    external_course_name VARCHAR(255) NOT NULL,
    external_grade VARCHAR(10) NOT NULL,
    external_credits INT NOT NULL,
    equivalent_unit_id BIGINT UNSIGNED NULL,
    converted_grade VARCHAR(10) NULL,
    converted_credits INT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    evaluated_by BIGINT UNSIGNED NULL,
    evaluated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Extensions to Existing Tables
```sql
-- Add retake tracking to course_registrations
ALTER TABLE course_registrations 
ADD COLUMN is_retake BOOLEAN DEFAULT FALSE,
ADD COLUMN original_registration_id BIGINT UNSIGNED NULL,
ADD COLUMN retake_reason TEXT NULL;

-- Add status tracking to students
ALTER TABLE students 
ADD COLUMN academic_status ENUM('active', 'inactive', 'graduated', 'suspended', 'withdrawn') DEFAULT 'active',
ADD COLUMN status_change_date DATE NULL,
ADD COLUMN status_reason TEXT NULL;

-- Add location tracking to attendances
ALTER TABLE attendances 
ADD COLUMN location_lat DECIMAL(10, 8) NULL,
ADD COLUMN location_lng DECIMAL(11, 8) NULL,
ADD COLUMN attendance_method ENUM('manual', 'qr_code', 'gps', 'biometric') DEFAULT 'manual',
ADD COLUMN device_info TEXT NULL;

-- Add lecturer assignment to course_offerings
ALTER TABLE course_offerings 
ADD COLUMN primary_lecturer_id BIGINT UNSIGNED NULL,
ADD COLUMN secondary_lecturer_ids JSON NULL,
ADD COLUMN room_id BIGINT UNSIGNED NULL;
```

---

## API Route Structure

### RESTful Route Patterns
Each module follows Laravel's resource routing conventions:

```php
// Example for Student Academic Records
Route::prefix('students/{student}')->group(function () {
    Route::get('academic-records', [AcademicRecordController::class, 'index']);
    Route::get('academic-records/{record}', [AcademicRecordController::class, 'show']);
    Route::post('academic-records', [AcademicRecordController::class, 'store']);
    Route::put('academic-records/{record}', [AcademicRecordController::class, 'update']);
    Route::delete('academic-records/{record}', [AcademicRecordController::class, 'destroy']);
});

// Bulk operations
Route::post('academic-records/bulk', [AcademicRecordController::class, 'bulkStore']);
Route::put('academic-records/bulk', [AcademicRecordController::class, 'bulkUpdate']);

// Export operations
Route::get('academic-records/export', [AcademicRecordController::class, 'export']);
```

---

## Development Standards and Patterns

### Controller Pattern
```php
class AcademicRecordController extends Controller
{
    public function __construct(
        protected AcademicRecordService $service
    ) {}
    
    public function index(Student $student, Request $request): Response
    {
        $query = $student->academicRecords()
            ->with(['unit', 'semester'])
            ->orderBy('semester_id', 'desc');
            
        if ($request->filled('semester')) {
            $query->where('semester_id', $request->semester);
        }
        
        return Inertia::render('students/academic-records/Index', [
            'student' => $student,
            'records' => $query->paginate(15),
            'semesters' => Semester::active()->get(),
            'filters' => $request->only(['semester']),
        ]);
    }
}
```

### Service Layer Pattern
```php
class AcademicRecordService
{
    public function calculateGPA(Student $student, Semester $semester): float
    {
        return DB::transaction(function () use ($student, $semester) {
            $records = $student->academicRecords()
                ->where('semester_id', $semester->id)
                ->get();
                
            $totalPoints = 0;
            $totalCredits = 0;
            
            foreach ($records as $record) {
                $gradePoints = $this->convertGradeToPoints($record->final_grade);
                $totalPoints += $gradePoints * $record->unit->credit_points;
                $totalCredits += $record->unit->credit_points;
            }
            
            return $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
        });
    }
}
```

### Vue Component Pattern
```vue
<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

interface Props {
    student: Student
    semesters: Semester[]
}

const props = defineProps<Props>()

const formSchema = toTypedSchema(z.object({
    semester_id: z.string().min(1, 'Semester is required'),
    unit_id: z.string().min(1, 'Unit is required'),
    final_grade: z.string().min(1, 'Grade is required'),
}))

const { handleSubmit, isSubmitting } = useForm({
    validationSchema: formSchema,
    initialValues: {
        semester_id: '',
        unit_id: '',
        final_grade: '',
    }
})

const onSubmit = handleSubmit((values) => {
    router.post(`/students/${props.student.id}/academic-records`, values, {
        onSuccess: () => {
            // Handle success
        },
        onError: (errors) => {
            // Handle errors
        }
    })
})
</script>
```

---

## Testing Strategy

### Feature Tests Structure
```php
class AcademicRecordTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_lecturer_can_view_student_academic_records(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = Student::factory()->create();
        $records = AcademicRecord::factory()->count(3)->create([
            'student_id' => $student->id
        ]);
        
        $this->actingAs($lecturer)
            ->get("/students/{$student->id}/academic-records")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => 
                $page->component('Students/AcademicRecords/Index')
                    ->has('records.data', 3)
            );
    }
}
```

---

## Deployment Checklist

### Pre-Implementation Setup
- [ ] Database migrations for new tables
- [ ] Service class creation and registration
- [ ] Permission definitions and seeding
- [ ] Route definition and testing
- [ ] Controller implementation with proper authorization

### Post-Implementation Verification
- [ ] Unit and feature test coverage
- [ ] Frontend component integration
- [ ] Permission and role verification
- [ ] Performance optimization
- [ ] Documentation updates

---

## Estimated Timeline

**Total Estimated Time**: 22 weeks (5.5 months)

**Team Requirements**:
- 2-3 Full-stack developers
- 1 Database specialist
- 1 QA/Testing specialist
- 1 UI/UX designer (for complex interfaces)

**Weekly Breakdown**:
- **Analysis & Setup**: 1 week
- **Phase 1 (Foundation)**: 2 weeks
- **Phase 2 (Core Academic)**: 4 weeks  
- **Phase 3 (Student Services)**: 4 weeks
- **Phase 4 (Faculty & Content)**: 4 weeks
- **Phase 5 (Analytics & Reporting)**: 4 weeks
- **Phase 6 (Advanced Features)**: 4 weeks
- **Testing & Polish**: 2 weeks
- **Documentation & Deployment**: 1 week

---

## Conclusion

This implementation plan provides a comprehensive roadmap for converting the Swinx admin menu structure into fully functional modules. The phased approach ensures that critical foundation components are built first, followed by core academic operations, and then advanced features and analytics.

Each module is designed to integrate seamlessly with the existing Laravel + Vue.js architecture while maintaining the established patterns for authentication, authorization, and data management. The campus-specific multi-tenancy architecture is preserved throughout all modules.

The plan prioritizes modules based on their importance to daily academic operations and their dependencies on other system components, ensuring a logical development flow that minimizes blocking issues and maximizes early value delivery.
