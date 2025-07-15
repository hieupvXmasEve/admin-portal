# Phase 3: Operational Management
## Detailed Implementation Specifications

### Priority: 🟡 **MEDIUM** - Daily Operations
**Estimated Timeline:** 5-6 weeks  
**Dependencies:** Phase 1 & 2 must be complete

---

## 3.1 Student Management

### 3.1.1 Student Directory & Profile Management
**Route:** `/students`  
**Component:** `pages/students/Index.vue` ✅ (Already exists)

#### Data Requirements
```typescript
interface Student {
  id: number;
  student_id: string; // 'S123456789'
  user_id: number;
  user: User; // Linked to users table
  program_id?: number;
  program?: Program;
  specialization_id?: number;
  specialization?: Specialization;
  curriculum_version_id?: number;
  curriculum_version?: CurriculumVersion;
  campus_id: number;
  campus: Campus;
  admission_date: string;
  expected_graduation: string;
  current_year_level: number; // 1, 2, 3, 4
  academic_status: 'active' | 'on_hold' | 'suspended' | 'graduated' | 'withdrawn';
  enrollment_status: 'full_time' | 'part_time' | 'exchange' | 'visiting';
  current_gpa?: number;
  total_credit_points_completed: number;
  total_credit_points_required: number;
  emergency_contact: EmergencyContact;
  academic_records: AcademicRecord[];
  holds: AcademicHold[];
  created_at: string;
  updated_at: string;
}

interface EmergencyContact {
  name: string;
  relationship: string;
  phone: string;
  email?: string;
  address?: string;
}

interface StudentProgressSummary {
  current_semester_enrollments: CourseRegistration[];
  current_credit_load: number;
  completion_percentage: number;
  remaining_core_units: number;
  remaining_elective_units: number;
  academic_standing: 'good_standing' | 'academic_warning' | 'academic_probation' | 'exclusion_risk';
}
```

#### API Endpoints Required
- `GET /api/students` - List students with filters ✅
- `POST /api/students` - Create student ✅
- `GET /api/students/{id}` - Get student details ✅
- `PUT /api/students/{id}` - Update student ✅
- `DELETE /api/students/{id}` - Delete student ✅
- `GET /api/students/{id}/progress` - Academic progress summary
- `GET /api/students/{id}/transcript` - Official transcript
- `POST /api/students/{id}/change-program` - Program change request
- `GET /api/students/search` - Advanced student search

#### Features Required
- ✅ Student directory with advanced search/filter
- ✅ Student profile management
- ⏳ Academic progress dashboard
- ⏳ Program change workflow
- ⏳ Emergency contact management
- ⏳ Academic standing tracking
- ⏳ Student photo management

### 3.1.2 Academic Records Management
**Route:** `/students/academic-records`  
**Component:** `pages/students/AcademicRecords.vue` (New)

#### Data Requirements
```typescript
interface AcademicRecord {
  id: number;
  student_id: number;
  student: Student;
  semester_id: number;
  semester: Semester;
  course_registration_id: number;
  course_registration: CourseRegistration;
  unit_code: string;
  unit_name: string;
  credit_points: number;
  final_grade: string; // 'HD', 'D', 'C', 'P', 'N', 'W'
  final_score?: number; // 0-100
  grade_points: number; // For GPA calculation
  status: 'completed' | 'failed' | 'withdrawn' | 'incomplete' | 'in_progress';
  completion_date?: string;
  is_repeated_unit: boolean;
  original_attempt_id?: number; // If this is a repeat
  created_at: string;
  updated_at: string;
}

interface TranscriptData {
  student: Student;
  academic_records: AcademicRecord[];
  semester_summaries: SemesterSummary[];
  overall_gpa: number;
  cumulative_credit_points: number;
  academic_awards: AcademicAward[];
  generated_date: string;
}

interface SemesterSummary {
  semester: Semester;
  total_units: number;
  credit_points_attempted: number;
  credit_points_completed: number;
  semester_gpa: number;
  academic_standing: string;
}
```

#### API Endpoints Required
- `GET /api/students/{id}/academic-records` - Student's academic records
- `GET /api/academic-records` - All academic records with filters
- `POST /api/academic-records` - Create academic record
- `PUT /api/academic-records/{id}` - Update academic record
- `DELETE /api/academic-records/{id}` - Delete academic record
- `GET /api/students/{id}/transcript` - Generate official transcript
- `POST /api/students/{id}/transcript/send` - Email transcript
- `GET /api/academic-records/statistics` - Academic performance statistics

#### Features Required
- ⏳ Academic record timeline view
- ⏳ Grade entry and editing interface
- ⏳ Transcript generation (PDF)
- ⏳ Academic performance analytics
- ⏳ Grade change audit trail
- ⏳ Semester summary reports
- ⏳ Academic award tracking

---

## 3.2 Lecturer Management

### 3.2.1 Lecturer Directory & Profile Management
**Route:** `/lecturers`  
**Component:** `pages/lecturers/Index.vue` (New)

#### Data Requirements
```typescript
interface Lecturer {
  id: number;
  user_id: number;
  user: User;
  employee_id: string; // 'L123456'
  title: string; // 'Dr.', 'Prof.', 'Mr.', 'Ms.'
  department: string;
  office_location?: string;
  phone_extension?: string;
  specializations: string[];
  qualifications: Qualification[];
  employment_type: 'full_time' | 'part_time' | 'casual' | 'visiting';
  employment_start_date: string;
  is_active: boolean;
  teaching_assignments: TeachingAssignment[];
  current_teaching_load: number; // Hours per week
  max_teaching_load: number;
  created_at: string;
  updated_at: string;
}

interface Qualification {
  id: number;
  degree: string; // 'PhD', 'Masters', 'Bachelor'
  field: string; // 'Computer Science', 'Software Engineering'
  institution: string;
  year_completed: number;
  is_verified: boolean;
}

interface TeachingAssignment {
  id: number;
  lecturer_id: number;
  course_offering_id: number;
  course_offering: CourseOffering;
  role: 'primary_lecturer' | 'secondary_lecturer' | 'tutor' | 'guest_lecturer';
  contact_hours: number; // Hours per week for this assignment
  start_date: string;
  end_date: string;
}
```

#### API Endpoints Required
- `GET /api/lecturers` - List lecturers with filters
- `POST /api/lecturers` - Create lecturer profile
- `GET /api/lecturers/{id}` - Get lecturer details
- `PUT /api/lecturers/{id}` - Update lecturer
- `DELETE /api/lecturers/{id}` - Delete lecturer
- `GET /api/lecturers/{id}/assignments` - Teaching assignments
- `POST /api/lecturers/{id}/assignments` - Assign to course
- `GET /api/lecturers/{id}/timetable` - Lecturer's timetable

#### Features Required
- ⏳ Lecturer directory with search/filter
- ⏳ Lecturer profile management
- ⏳ Qualification tracking
- ⏳ Teaching load management
- ⏳ Assignment workflow
- ⏳ Timetable visualization
- ⏳ Performance metrics

### 3.2.2 Teaching Assignments & Timetable
**Route:** `/lecturers/assignments`  
**Component:** `pages/lecturers/Assignments.vue` (New)

#### Data Requirements
```typescript
interface LecturerTimetable {
  lecturer: Lecturer;
  weekly_schedule: WeeklySchedule[];
  total_contact_hours: number;
  conflicts: TimetableConflict[];
  semester: Semester;
}

interface WeeklySchedule {
  day_of_week: number; // 1=Monday
  time_slots: TimeSlot[];
}

interface TimeSlot {
  start_time: string;
  end_time: string;
  course_offering: CourseOffering;
  session_type: 'lecture' | 'tutorial' | 'lab';
  venue: string;
  weeks: number[]; // Teaching weeks
}

interface TimetableConflict {
  type: 'double_booking' | 'travel_time' | 'overload';
  time_slot_1: TimeSlot;
  time_slot_2?: TimeSlot;
  severity: 'warning' | 'error';
  message: string;
}
```

#### API Endpoints Required
- `GET /api/lecturers/{id}/timetable` - Lecturer timetable
- `POST /api/teaching-assignments` - Create assignment
- `PUT /api/teaching-assignments/{id}` - Update assignment
- `DELETE /api/teaching-assignments/{id}` - Remove assignment
- `GET /api/teaching-assignments/conflicts` - Check for conflicts
- `GET /api/lecturers/workload-report` - Teaching load report

#### Features Required
- ⏳ Visual timetable interface
- ⏳ Drag-and-drop assignment
- ⏳ Conflict detection and resolution
- ⏳ Teaching load balancing
- ⏳ Assignment bulk operations
- ⏳ Substitute lecturer management

---

## 3.3 Attendance Management

### 3.3.1 Class Sessions Management
**Route:** `/attendance/sessions`  
**Component:** `pages/attendance/Sessions.vue` (New)

#### Data Requirements
```typescript
interface ClassSession {
  id: number;
  course_offering_id: number;
  course_offering: CourseOffering;
  session_date: string;
  start_time: string;
  end_time: string;
  session_type: 'lecture' | 'tutorial' | 'lab' | 'workshop' | 'exam';
  venue: string;
  lecturer_id?: number;
  lecturer?: Lecturer;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
  attendance_taken: boolean;
  total_enrolled: number;
  total_attended: number;
  attendance_percentage: number;
  session_notes?: string;
  created_at: string;
  updated_at: string;
}

interface AttendanceRecord {
  id: number;
  class_session_id: number;
  student_id: number;
  student: Student;
  status: 'present' | 'absent' | 'late' | 'excused';
  check_in_time?: string;
  check_out_time?: string;
  attendance_method: 'manual' | 'gps' | 'qr_code' | 'biometric';
  gps_coordinates?: GPSCoordinates;
  notes?: string;
  created_at: string;
  updated_at: string;
}

interface GPSCoordinates {
  latitude: number;
  longitude: number;
  accuracy: number; // meters
  timestamp: string;
}
```

#### API Endpoints Required
- `GET /api/class-sessions` - List class sessions
- `POST /api/class-sessions` - Create class session
- `GET /api/class-sessions/{id}` - Get session details
- `PUT /api/class-sessions/{id}` - Update session
- `DELETE /api/class-sessions/{id}` - Delete session
- `GET /api/class-sessions/{id}/attendance` - Session attendance
- `POST /api/class-sessions/{id}/take-attendance` - Take attendance
- `PUT /api/attendance-records/{id}` - Update attendance record

#### Features Required
- ⏳ Class session calendar view
- ⏳ Session scheduling interface
- ⏳ Attendance taking interface
- ⏳ GPS location verification
- ⏳ QR code attendance system
- ⏳ Attendance reporting
- ⏳ Make-up session management

### 3.3.2 Attendance Tracking & Reporting
**Route:** `/attendance/reports`  
**Component:** `pages/attendance/Reports.vue` (New)

#### Data Requirements
```typescript
interface AttendanceReport {
  student: Student;
  course_offering: CourseOffering;
  total_sessions: number;
  attended_sessions: number;
  attendance_percentage: number;
  attendance_trend: AttendanceTrend[];
  risk_level: 'low' | 'medium' | 'high';
  last_attendance_date?: string;
  consecutive_absences: number;
}

interface AttendanceTrend {
  week: number;
  sessions_scheduled: number;
  sessions_attended: number;
  weekly_percentage: number;
}

interface AttendanceAnalytics {
  overall_attendance_rate: number;
  attendance_by_unit: UnitAttendance[];
  attendance_by_day: DayAttendance[];
  attendance_by_time: TimeAttendance[];
  at_risk_students: Student[];
}
```

#### API Endpoints Required
- `GET /api/attendance/reports/student/{id}` - Student attendance report
- `GET /api/attendance/reports/course/{id}` - Course attendance report
- `GET /api/attendance/reports/analytics` - Attendance analytics
- `GET /api/attendance/at-risk-students` - Students at risk
- `POST /api/attendance/alerts` - Send attendance alerts
- `GET /api/attendance/export` - Export attendance data

#### Features Required
- ⏳ Individual student attendance tracking
- ⏳ Course-level attendance analytics
- ⏳ Attendance trend visualization
- ⏳ At-risk student identification
- ⏳ Automated attendance alerts
- ⏳ Attendance data export
- ⏳ Compliance reporting

---

## Implementation Order

### Week 1: Student Directory & Profiles
1. **Enhanced Student Management**
   - Advanced search and filtering
   - Student progress dashboard
   - Emergency contact management

2. **Academic Status Tracking**
   - Academic standing calculation
   - Program change workflow
   - Hold management integration

### Week 2: Academic Records System
3. **Academic Records Management**
   - Grade entry interface
   - Record timeline view
   - Grade change audit trail

4. **Transcript Generation**
   - Official transcript PDF
   - Email transcript functionality
   - Academic award tracking

### Week 3: Lecturer Management Setup
5. **Lecturer Directory**
   - Lecturer profile management
   - Qualification tracking
   - Employment type handling

6. **Teaching Load Management**
   - Assignment workflow
   - Load balancing algorithms
   - Conflict detection

### Week 4: Teaching Assignments & Timetable
7. **Assignment Management**
   - Teaching assignment interface
   - Timetable visualization
   - Substitute management

8. **Timetable Optimization**
   - Conflict resolution tools
   - Automated scheduling assistance
   - Room booking integration

### Week 5: Attendance Infrastructure
9. **Class Session Management**
   - Session scheduling
   - Session status tracking
   - Venue management

10. **Attendance Taking System**
    - Manual attendance interface
    - GPS verification system
    - QR code integration

### Week 6: Attendance Analytics & Reporting
11. **Attendance Reporting**
    - Individual student reports
    - Course-level analytics
    - Trend visualization

12. **Risk Management**
    - At-risk student identification
    - Automated alert system
    - Compliance reporting

---

## Success Criteria

### Student Management Requirements
- ⏳ Complete student lifecycle management
- ⏳ Accurate academic progress tracking
- ⏳ Efficient program change workflow
- ⏳ Comprehensive academic records

### Lecturer Management Requirements
- ⏳ Effective teaching load management
- ⏳ Conflict-free timetabling
- ⏳ Qualification verification system
- ⏳ Assignment workflow automation

### Attendance Management Requirements
- ⏳ Reliable attendance taking system
- ⏳ GPS and QR code integration
- ⏳ Comprehensive attendance analytics
- ⏳ Proactive risk identification

---

## Dependencies for Phase 4

Before starting Phase 4, ensure:
1. ⏳ Student management system fully functional
2. ⏳ Academic records accurately maintained
3. ⏳ Teaching assignments working properly
4. ⏳ Attendance system integrated and tested
5. ⏳ Reporting capabilities operational

## Risk Mitigation

### High Risk Areas
- **Attendance GPS Accuracy** - Implement fallback methods and validation
- **Academic Record Integrity** - Strict validation and audit trails
- **Timetable Conflicts** - Robust conflict detection and resolution

### Mitigation Strategies
- Multi-method attendance verification
- Real-time data validation
- Comprehensive testing scenarios
- User training and documentation
- Gradual rollout with pilot groups 
