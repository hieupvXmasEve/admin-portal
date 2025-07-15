# Phase 2: Academic Structure Setup
## Detailed Implementation Specifications

### Priority: 🟠 **HIGH** - Core Academic Framework
**Estimated Timeline:** 4-5 weeks  
**Dependencies:** Phase 1 (Foundation) must be complete

---

## 2.1 Curriculum & Courses Management

### 2.1.1 Programs Management
**Route:** `/curriculum/programs`  
**Component:** `pages/programs/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface Program {
  id: number;
  code: string; // 'BSE', 'MIT', 'MBA'
  name: string; // 'Bachelor of Software Engineering'
  degree_type: 'bachelor' | 'master' | 'phd' | 'diploma' | 'certificate';
  duration_years: number; // 3, 4, 2
  total_credit_points: number; // 360, 480, 192
  campus_id: number;
  campus: Campus;
  specializations: Specialization[];
  curriculum_versions: CurriculumVersion[];
  is_active: boolean;
  description?: string;
  entry_requirements?: string;
  created_at: string;
  updated_at: string;
}

interface ProgramStatistics {
  total_students: number;
  active_students: number;
  graduated_students: number;
  current_curriculum_version: CurriculumVersion;
}
```

#### API Endpoints Required
- `GET /api/programs` - List programs ✅
- `POST /api/programs` - Create program ✅
- `GET /api/programs/{id}` - Get program details ✅
- `PUT /api/programs/{id}` - Update program ✅
- `DELETE /api/programs/{id}` - Delete program ✅
- `GET /api/programs/{id}/statistics` - Program statistics
- `GET /api/programs/{id}/curriculum-versions` - Program curriculum history
- `POST /api/programs/{id}/duplicate` - Duplicate program structure

#### Features Required
- ✅ Program list with basic information
- ✅ Create/Edit program forms
- ⏳ Program statistics dashboard
- ⏳ Curriculum version management
- ⏳ Program duplication functionality
- ⏳ Specialization linking interface
- ⏳ Entry requirements management

### 2.1.2 Specializations Management
**Route:** `/curriculum/specializations`  
**Component:** `pages/specializations/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface Specialization {
  id: number;
  code: string; // 'SE-AI', 'SE-WEB', 'MIT-CS'
  name: string; // 'Artificial Intelligence', 'Web Development'
  program_id: number;
  program: Program;
  required_credit_points: number; // 120, 96
  core_units: Unit[]; // Required units for this specialization
  elective_units: Unit[]; // Available elective units
  prerequisites?: string;
  is_active: boolean;
  description?: string;
  career_outcomes?: string;
  created_at: string;
  updated_at: string;
}

interface SpecializationRequirements {
  core_units_count: number;
  elective_units_count: number;
  total_credit_points: number;
  prerequisite_chains: PrerequisiteChain[];
}
```

#### API Endpoints Required
- `GET /api/specializations` - List specializations ✅
- `POST /api/specializations` - Create specialization ✅
- `GET /api/specializations/{id}` - Get specialization details ✅
- `PUT /api/specializations/{id}` - Update specialization ✅
- `DELETE /api/specializations/{id}` - Delete specialization ✅
- `GET /api/specializations/{id}/requirements` - Specialization requirements
- `POST /api/specializations/{id}/units` - Link units to specialization
- `GET /api/specializations/{id}/students` - Students in specialization

#### Features Required
- ✅ Specialization list with program links
- ✅ Create/Edit specialization forms
- ⏳ Unit requirement management
- ⏳ Prerequisite chain visualization
- ⏳ Student enrollment tracking
- ⏳ Career outcome management
- ⏳ Credit point validation

### 2.1.3 Curriculum Versions Management
**Route:** `/curriculum/versions`  
**Component:** `pages/curriculum-versions/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface CurriculumVersion {
  id: number;
  program_id: number;
  program: Program;
  version: string; // 'V1.0', 'V2.1', '2024.1'
  name: string; // 'Software Engineering V2.1'
  effective_from: string; // '2024-01-01'
  effective_until?: string; // '2025-12-31'
  is_active: boolean;
  is_current: boolean; // Only one current version per program
  changelog?: string;
  curriculum_units: CurriculumUnit[]; // Units and their requirements
  total_credit_points: number;
  created_at: string;
  updated_at: string;
}

interface CurriculumUnit {
  id: number;
  curriculum_version_id: number;
  unit_id: number;
  unit: Unit;
  unit_type: 'core' | 'elective' | 'capstone' | 'internship';
  year_level: number; // 1, 2, 3, 4
  semester: number; // 1, 2 (or 0 for either)
  is_required: boolean;
  prerequisite_units: Unit[];
  created_at: string;
  updated_at: string;
}
```

#### API Endpoints Required
- `GET /api/curriculum-versions` - List curriculum versions ✅
- `POST /api/curriculum-versions` - Create curriculum version ✅
- `GET /api/curriculum-versions/{id}` - Get curriculum details ✅
- `PUT /api/curriculum-versions/{id}` - Update curriculum version ✅
- `DELETE /api/curriculum-versions/{id}` - Delete curriculum version ✅
- `POST /api/curriculum-versions/{id}/activate` - Set as current version
- `GET /api/curriculum-versions/{id}/structure` - Curriculum structure view
- `POST /api/curriculum-versions/{id}/copy` - Copy from existing version

#### Features Required
- ✅ Curriculum version list with program context
- ✅ Create/Edit curriculum version forms
- ⏳ Curriculum structure builder interface
- ⏳ Unit drag-and-drop assignment
- ⏳ Prerequisite mapping interface
- ⏳ Version comparison tools
- ⏳ Change log management

### 2.1.4 Units (Courses) Management
**Route:** `/curriculum/units`  
**Component:** `pages/units/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface Unit {
  id: number;
  code: string; // 'SWE20001', 'ICT30001'
  name: string; // 'Software Engineering Project'
  credit_points: number; // 12.5, 25
  level: number; // 1, 2, 3, 4 (undergraduate), 5-8 (postgraduate)
  prerequisites: Unit[]; // Required units before enrollment
  corequisites: Unit[]; // Units that must be taken together
  antirequisites: Unit[]; // Units that cannot be taken together
  description: string;
  learning_outcomes: string[];
  assessment_methods: string[];
  unit_type: 'core' | 'elective' | 'capstone' | 'internship';
  delivery_mode: 'face_to_face' | 'online' | 'blended';
  contact_hours: number; // Hours per week
  is_active: boolean;
  campus_restrictions?: number[]; // Available campus IDs
  created_at: string;
  updated_at: string;
}

interface UnitPrerequisite {
  id: number;
  unit_id: number;
  prerequisite_unit_id: number;
  prerequisite_type: 'prerequisite' | 'corequisite' | 'antirequisite';
  is_required: boolean; // For elective prerequisites
}
```

#### API Endpoints Required
- `GET /api/units` - List units ✅
- `POST /api/units` - Create unit ✅
- `GET /api/units/{id}` - Get unit details ✅
- `PUT /api/units/{id}` - Update unit ✅
- `DELETE /api/units/{id}` - Delete unit ✅
- `GET /api/units/{id}/prerequisites` - Unit prerequisite tree
- `POST /api/units/{id}/prerequisites` - Manage prerequisites
- `GET /api/units/search` - Search units for selection

#### Features Required
- ✅ Unit list with search and filter
- ✅ Create/Edit unit forms
- ⏳ Prerequisite management interface
- ⏳ Prerequisite tree visualization
- ⏳ Learning outcome management
- ⏳ Assessment method tracking
- ⏳ Campus availability management

---

## 2.2 Course Offerings & Registration Setup

### 2.2.1 Course Offerings Management
**Route:** `/courses/offerings`  
**Component:** `pages/course-offerings/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface CourseOffering {
  id: number;
  unit_id: number;
  unit: Unit;
  semester_id: number;
  semester: Semester;
  campus_id: number;
  campus: Campus;
  offering_code: string; // 'SWE20001-MEL-FALL2024-1'
  capacity: number; // 120
  enrolled_count: number; // Current enrollments
  waitlist_count: number; // Students on waitlist
  lecturer_id?: number;
  lecturer?: User;
  timetable: ClassSchedule[];
  venue?: string; // 'BLDG-A-101'
  status: 'planned' | 'open' | 'closed' | 'cancelled';
  registration_start?: string;
  registration_end?: string;
  syllabus?: Syllabus;
  created_at: string;
  updated_at: string;
}

interface ClassSchedule {
  id: number;
  course_offering_id: number;
  day_of_week: number; // 1=Monday, 7=Sunday
  start_time: string; // '09:00:00'
  end_time: string; // '11:00:00'
  session_type: 'lecture' | 'tutorial' | 'lab' | 'workshop';
  venue?: string;
  lecturer_id?: number;
  weeks: number[]; // [1,2,3,4,5,6,7,8,9,10,11,12] teaching weeks
}
```

#### API Endpoints Required
- `GET /api/course-offerings` - List course offerings ✅
- `POST /api/course-offerings` - Create course offering ✅
- `GET /api/course-offerings/{id}` - Get offering details ✅
- `PUT /api/course-offerings/{id}` - Update course offering ✅
- `DELETE /api/course-offerings/{id}` - Delete course offering ✅
- `GET /api/course-offerings/{id}/timetable` - Get class schedule
- `POST /api/course-offerings/{id}/timetable` - Update class schedule
- `GET /api/course-offerings/{id}/enrollments` - Get enrolled students

#### Features Required
- ✅ Course offering list with filters
- ✅ Create/Edit course offering forms
- ⏳ Timetable management interface
- ⏳ Capacity and enrollment tracking
- ⏳ Lecturer assignment interface
- ⏳ Venue booking integration
- ⏳ Bulk offering creation tools

### 2.2.2 Course Registration Management
**Route:** `/courses/registrations`  
**Component:** `pages/course-registrations/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface CourseRegistration {
  id: number;
  student_id: number;
  student: Student;
  course_offering_id: number;
  course_offering: CourseOffering;
  registration_date: string;
  status: 'enrolled' | 'waitlisted' | 'dropped' | 'completed' | 'failed';
  final_grade?: string; // 'HD', 'D', 'C', 'P', 'N'
  final_score?: number; // 0-100
  completion_date?: string;
  registration_type: 'normal' | 'late' | 'special' | 'exemption';
  fees_paid: boolean;
  created_at: string;
  updated_at: string;
}

interface RegistrationEligibility {
  student_id: number;
  course_offering_id: number;
  is_eligible: boolean;
  reasons: string[]; // Reasons for ineligibility
  prerequisites_met: boolean;
  prerequisite_issues: string[];
  credit_limit_ok: boolean;
  current_credit_load: number;
  max_credit_limit: number;
}
```

#### API Endpoints Required
- `GET /api/course-registrations` - List course registrations ✅
- `POST /api/course-registrations` - Create registration ✅
- `GET /api/course-registrations/{id}` - Get registration details ✅
- `PUT /api/course-registrations/{id}` - Update registration ✅
- `DELETE /api/course-registrations/{id}` - Delete registration ✅
- `POST /api/course-registrations/check-eligibility` - Check eligibility
- `GET /api/course-registrations/bulk-enroll` - Bulk enrollment interface
- `POST /api/course-registrations/{id}/drop` - Drop from course

#### Features Required
- ✅ Registration list with student/course context
- ✅ Create/Edit registration forms
- ⏳ Eligibility checking system
- ⏳ Prerequisite validation
- ⏳ Credit load management
- ⏳ Waitlist management
- ⏳ Bulk enrollment tools

---

## Implementation Order

### Week 1: Programs & Specializations
1. **Enhanced Program Management**
   - Add program statistics
   - Implement curriculum version linking
   - Add program duplication functionality

2. **Complete Specialization Management**
   - Unit requirement management
   - Credit point validation
   - Career outcome tracking

### Week 2: Curriculum Versions & Structure
3. **Curriculum Version Builder**
   - Structure builder interface
   - Unit drag-and-drop assignment
   - Prerequisite mapping

4. **Version Management**
   - Version comparison tools
   - Change log management
   - Activation workflow

### Week 3: Units & Prerequisites
5. **Enhanced Unit Management**
   - Prerequisite tree visualization
   - Learning outcome management
   - Assessment method tracking

6. **Prerequisite System**
   - Prerequisite validation logic
   - Visual prerequisite chains
   - Circular dependency detection

### Week 4: Course Offerings Setup
7. **Course Offering Management**
   - Timetable management interface
   - Lecturer assignment
   - Venue booking integration

8. **Offering Creation Tools**
   - Bulk offering creation
   - Template-based creation
   - Capacity management

### Week 5: Registration Framework
9. **Registration Eligibility System**
   - Prerequisite checking
   - Credit load validation
   - Eligibility reporting

10. **Registration Management**
    - Waitlist management
    - Bulk enrollment tools
    - Registration workflow optimization

---

## Success Criteria

### Academic Structure Requirements
- ⏳ Complete program-specialization hierarchy
- ⏳ Working curriculum version system
- ⏳ Comprehensive unit prerequisite system
- ⏳ Accurate credit point tracking

### Course Management Requirements
- ⏳ Efficient course offering creation
- ⏳ Robust registration eligibility checking
- ⏳ Capacity and waitlist management
- ⏳ Timetable conflict detection

### Data Integrity Requirements
- ⏳ Prerequisite loop detection
- ⏳ Credit point validation
- ⏳ Academic calendar compliance
- ⏳ Enrollment limit enforcement

---

## Dependencies for Phase 3

Before starting Phase 3, ensure:
1. ⏳ Program structure fully defined
2. ⏳ Units and prerequisites working
3. ⏳ Course offerings can be created
4. ⏳ Registration eligibility system functional
5. ⏳ Academic calendar integration complete

## Risk Mitigation

### High Risk Areas
- **Prerequisite Complexity** - Start with simple prerequisites, add complexity gradually
- **Curriculum Version Management** - Ensure proper version control and migration paths
- **Registration Eligibility Logic** - Complex business rules need thorough testing

### Mitigation Strategies
- Comprehensive prerequisite testing scenarios
- Version migration testing with real data
- Eligibility rule documentation and testing
- Progressive complexity in prerequisite relationships 
