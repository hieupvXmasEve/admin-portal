# Backend API Requirements for Student Portal

## Overview

This document outlines the complete backend API requirements, database models, and technical specifications needed to support the Student Portal frontend implementation. The backend should provide RESTful APIs with proper authentication, validation, and error handling.

## Required API Endpoints

### 1. Dashboard APIs

```http
GET /api/student/dashboard
# Response: Current semester, GPA calculations, credit progress, academic holds, upcoming assessments
# Includes: Semester, GPACalculation, CurriculumUnit progress, AcademicHold[], AssessmentComponentDetail[]

GET /api/student/dashboard/semester/current
# Response: Current semester details with enrollment status
# Includes: Semester with Enrollment data

GET /api/student/dashboard/credit-progress
# Response: Earned vs required credits based on CurriculumVersion and AcademicRecord
# Includes: CurriculumUnit[] remaining requirements, completion percentage

GET /api/student/dashboard/gpa
# Response: GPACalculation with semester and cumulative GPA, trend analysis
# Includes: GPACalculation with Program and Semester relationships

GET /api/student/dashboard/holds
# Response: Active AcademicHold entities with User (placed_by) information
# Includes: AcademicHold[] with resolution details
```

### 2. Course Registration APIs

```http
GET /api/student/course-offerings/available
# Query params: curriculum_version_id, search, page, limit
# Response: Available CourseOffering entities with CurriculumUnit, Lecturer, and ClassSession data
# Includes: CourseOffering[] with enrollment capacity and conflict information

GET /api/student/course-registrations
# Query params: semester_id, status
# Response: Student's CourseRegistration entities with CourseOffering details
# Includes: CourseRegistration[] with CourseOffering, Semester relationships

POST /api/student/course-registrations
# Body: { course_offering_id: number, semester_id: number }
# Response: CourseRegistration confirmation with conflict detection
# Validates: ClassSession time conflicts, prerequisite requirements

DELETE /api/student/course-registrations/{course_offering_id}
# Response: CourseRegistration removal confirmation

GET /api/student/course-offerings/{course_offering_id}/details
# Response: CourseOffering with CurriculumUnit, Unit, ClassSession, and Lecturer details
# Includes: Complete course offering information with schedule

GET /api/student/course-offerings/conflicts
# Body: { course_offering_ids: [number] }
# Response: ClassSession time conflicts and CurriculumUnit prerequisite violations
# Includes: Detailed conflict analysis with affected sessions

GET /api/student/curriculum-units/{curriculum_unit_id}/prerequisites
# Response: Unit prerequisite tree with completion status from AcademicRecord
# Includes: Unit relationships and student completion status
```

### 3. Timetable APIs

```http
GET /api/student/timetable
# Query params: semester_id, week_start_date
# Response: ClassSession entities for student's registered CourseOffering
# Includes: ClassSession[] with CourseOffering, Room, and Lecturer information

GET /api/student/class-sessions/{class_session_id}
# Response: ClassSession details with Room, CourseOffering, and online meeting information
# Includes: Complete session details with location and instructor data

GET /api/student/timetable/filters
# Response: Available filter options based on student's CourseOffering entities
# Includes: CourseOffering types, Lecturer names, Room locations
```

### 4. Grades and Academic Progress APIs

```http
GET /api/student/grades
# Query params: semester_id (optional)
# Response: AcademicRecord entities grouped by Semester with Unit and CourseOffering details
# Includes: AcademicRecord[], AssessmentScore[], GPACalculation[], semester summaries

GET /api/student/grades/gpa-trend
# Response: GPACalculation history across Semester entities with trend analysis
# Includes: GPACalculation[] with Semester and Program relationships

GET /api/student/grades/course-offering/{course_offering_id}
# Response: AcademicRecord and AssessmentScore entities for specific CourseOffering
# Includes: Detailed assessment breakdown with Syllabus and AssessmentComponent data

GET /api/student/assessments
# Query params: course_offering_id, semester_id, status
# Response: AssessmentComponentDetail entities with submission status
# Includes: AssessmentComponentDetail[] with AssessmentScore relationships

GET /api/student/assessments/{assessment_component_detail_id}
# Response: AssessmentComponentDetail with AssessmentComponent and Syllabus information
# Includes: Complete assessment details with scoring rubric

GET /api/student/assessment-scores
# Query params: course_offering_id, assessment_component_detail_id
# Response: AssessmentScore entities for student with CourseOffering context
# Includes: AssessmentScore[] with grading details and feedback
```

### 5. Attendance APIs

```http
GET /api/student/attendance
# Query params: semester_id, course_offering_id
# Response: Attendance entities with ClassSession and CourseOffering relationships
# Includes: Attendance[] with session details and percentage calculations

GET /api/student/attendance/summary
# Response: Attendance summary per CourseOffering with ClassSession totals
# Includes: CourseOffering attendance percentages, total/attended session counts

GET /api/student/attendance/alerts
# Response: CourseOffering entities where attendance falls below thresholds
# Includes: CourseOffering[] with attendance percentage and risk status
```

### 6. Profile Management APIs

```http
PUT /api/student/profile/personal
# Body: Student entity fields (full_name, email, etc.)
# Response: Updated Student entity confirmation

GET /api/student/profile/study-plan
# Response: CurriculumVersion with CurriculumUnit layout and AcademicRecord completion status
# Includes: CurriculumVersion, CurriculumUnit[] with Unit details and completion tracking

PUT /api/student/profile/avatar
# Body: FormData with image file
# Response: Updated Student entity with new avatar URL

GET /api/student/profile/academic-history
# Response: Complete AcademicRecord history with Enrollment and AcademicStanding milestones
# Includes: AcademicRecord[], Enrollment[], AcademicStanding[] across all semesters
```

### 7. Curriculum and Program APIs

```http
GET /api/student/curriculum
# Query params: curriculum_version_id, program_id, specialization_id
# Response: CurriculumVersion with CurriculumUnit entities and Unit relationships
# Includes: Complete curriculum roadmap with Unit prerequisites and semester placement

GET /api/student/curriculum/prerequisites/{unit_id}
# Response: Unit prerequisite tree with AcademicRecord completion status
# Includes: Unit relationships and student completion tracking

GET /api/student/programs/{program_id}/requirements
# Response: Program graduation requirements with CurriculumVersion progress tracking
# Includes: Program, Specialization, CurriculumUnit requirements and completion status
```

### 8. Notification and Communication APIs

```http
GET /api/student/notifications
# Query params: type, category, read, page, limit
# Response: System-generated notifications for academic events
# Includes: Academic deadline alerts, grade notifications, hold notifications

PUT /api/student/notifications/{notification_id}/read
# Response: Mark notification as read confirmation

POST /api/student/notifications/push/subscribe
# Body: { subscription: PushSubscription }
# Response: Push notification subscription confirmation

DELETE /api/student/notifications/push/unsubscribe
# Response: Push notification unsubscription confirmation

GET /api/student/notifications/preferences
# Response: Student notification preferences and settings

PUT /api/student/notifications/preferences
# Body: { email_enabled, push_enabled, categories: [] }
# Response: Updated notification preferences
```

### 9. Academic Calendar and System APIs

```http
GET /api/student/semesters
# Query params: year, is_current
# Response: Semester entities with academic calendar information
# Includes: Semester[] with registration periods and important dates

GET /api/student/semesters/{semester_id}/deadlines
# Query params: date_range, type
# Response: Semester-specific deadlines and AssessmentComponentDetail due dates
# Includes: Academic deadlines, assessment due dates, registration periods

GET /api/student/academic-calendar
# Response: Academic events, holidays, and Semester schedules
# Includes: System-wide academic calendar with important dates
```

## API Response Standards

### Success Response Format

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data aligned with database entities
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    },
    "filters": {
      "applied": ["semester_id:number", "program_id:number"],
      "available": ["semester_id", "program_id", "specialization_id"]
    }
  }
}
```

### Dashboard Response Example

```json
{
  "success": true,
  "data": {
    "current_semester": {
      "id": "number",
      "name": "Fall 2024",
      "code": "2024-3",
      "start_date": "2024-09-01",
      "end_date": "2024-12-15"
    },
    "credit_progress": {
      "earned_credits": 45,
      "required_credits": 120,
      "remaining_requirements": [
        {
          "id": "number",
          "curriculum_version_id": "number",
          "unit": {
            "id": "number",
            "code": "CS301",
            "name": "Data Structures",
            "credit_points": 3
          },
          "semester": {
            "id": "number",
            "name": "Spring 2025"
          }
        }
      ],
      "completion_percentage": 37.5
    },
    "current_gpa": {
      "id": "number",
      "student_id": "number",
      "semester_id": "number",
      "program_id": "number",
      "semester_gpa": 3.45,
      "cumulative_gpa": 3.32,
      "trend": "up"
    },
    "academic_holds": [
      {
        "id": "number",
        "student_id": "number",
        "placed_by_user_id": "number",
        "placed_by": {
          "id": "number",
          "name": "Academic Office",
          "email": "academic@university.edu"
        }
      }
    ],
    "upcoming_assessments": [
      {
        "id": "number",
        "component_id": "number",
        "assessment_component": {
          "id": "number",
          "syllabus": {
            "curriculum_unit": {
              "unit": {
                "code": "CS101",
                "name": "Introduction to Programming"
              }
            }
          }
        }
      }
    ],
    "enrollment_status": {
      "id": "number",
      "student_id": "number",
      "semester_id": "number",
      "curriculum_version_id": "number"
    },
    "academic_standing": {
      "id": "number",
      "student_id": "number",
      "semester_id": "number"
    }
  }
}
```

### Error Response Format

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email is required", "Email must be valid"],
    "course_id": ["Course not found"]
  },
  "error_code": "VALIDATION_ERROR",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Authentication & Authorization

### JWT Token Structure

```json
{
  "sub": "student_number",
  "email": "student@example.com",
  "student_id": "STU123456",
  "role": "student",
  "campus_id": "campus_number",
  "program_id": "program_number",
  "permissions": ["view_grades", "register_courses", "view_transcript"],
  "iat": 1642234567,
  "exp": 1642320967
}
```

### Required Middleware

- Authentication verification
- Rate limiting (per endpoint)
- Request validation
- CORS handling
- Error logging and monitoring

## Database Indexes and Performance

### Critical Indexes

```sql
-- Student lookups
CREATE INDEX idx_students_email ON students(email);
CREATE INDEX idx_students_student_id ON students(student_id);
CREATE INDEX idx_students_program_campus ON students(program_id, campus_id);
CREATE INDEX idx_students_curriculum_version ON students(curriculum_version_id);

-- Course registration and offerings
CREATE INDEX idx_course_registrations_student_offering ON course_registrations(student_id, course_offering_id);
CREATE INDEX idx_course_registrations_semester ON course_registrations(semester_id);
CREATE INDEX idx_course_offerings_semester ON course_offerings(semester_id);
CREATE INDEX idx_course_offerings_curriculum_unit ON course_offerings(curriculum_unit_id);

-- Academic records and assessments
CREATE INDEX idx_academic_records_student_semester ON academic_records(student_id, semester_id);
CREATE INDEX idx_academic_records_course_offering ON academic_records(course_offering_id);
CREATE INDEX idx_assessment_scores_student ON assessment_component_detail_scores(student_id);
CREATE INDEX idx_assessment_scores_course_offering ON assessment_component_detail_scores(course_offering_id);

-- Attendance tracking
CREATE INDEX idx_attendances_student ON attendances(student_id);
CREATE INDEX idx_attendances_class_session ON attendances(class_session_id);
CREATE INDEX idx_class_sessions_course_offering ON class_sessions(course_offering_id);

-- GPA and academic standing
CREATE INDEX idx_gpa_calculations_student_semester ON gpa_calculations(student_id, semester_id);
CREATE INDEX idx_academic_standings_student_semester ON academic_standings(student_id, semester_id);

-- Academic holds and support
CREATE INDEX idx_academic_holds_student ON academic_holds(student_id);
CREATE INDEX idx_academic_holds_placed_by ON academic_holds(placed_by_user_id);

-- Curriculum structure
CREATE INDEX idx_curriculum_units_version ON curriculum_units(curriculum_version_id);
CREATE INDEX idx_curriculum_units_unit ON curriculum_units(unit_id);
CREATE INDEX idx_curriculum_units_semester ON curriculum_units(semester_id);
CREATE INDEX idx_curriculum_versions_program ON curriculum_versions(program_id);
CREATE INDEX idx_curriculum_versions_specialization ON curriculum_versions(specialization_id);

-- Enrollment tracking
CREATE INDEX idx_enrollments_student_semester ON enrollments(student_id, semester_id);
CREATE INDEX idx_enrollments_curriculum_version ON enrollments(curriculum_version_id);
```

## Data Validation Rules

### Student Registration Validation

- Maximum credits per semester (default: 18)
- Prerequisite completion verification
- Time conflict detection
- Hold status check
- Registration period validation

### Grade Calculation Rules

- GPA calculation: (Sum of grade points × credits) / Total credits
- Academic standing thresholds:
  - Good Standing: GPA ≥ 2.0
  - Academic Probation: GPA < 2.0
  - Academic Suspension: GPA < 1.5 for 2 consecutive semesters

### Attendance Requirements

- Minimum attendance: 75% for exam eligibility
- Warning threshold: 80%
- Automatic notifications when below thresholds

## Security Requirements

### Data Protection

- Encrypt sensitive personal information
- Hash and salt passwords (if local auth implemented)
- Secure file upload validation
- SQL injection prevention
- XSS protection

### API Security

- Rate limiting: 100 requests/minute per user
- Input validation and sanitization
- HTTPS enforcement
- CORS policy configuration
- Request logging for audit trails

## Testing Requirements

### Unit Tests Required

- All API endpoints with success/error scenarios
- Database model validations
- Business logic functions (GPA calculation, conflict detection)
- Authentication and authorization flows

### Integration Tests Required

- Complete user workflows (registration, grade viewing)
- Database transaction integrity
- File upload and processing
- Push notification delivery

### Performance Requirements

- API response time: < 500ms for 95% of requests
- Database query optimization
- Caching strategy for frequently accessed data
- File upload handling up to 10MB per file

## Deployment Considerations

### Environment Variables

```env
# Database
DATABASE_URL=mysql://user:pass@host:port/dbname
REDIS_URL=redis://host:port/db

# Authentication
JWT_SECRET=your-secret-key
JWT_EXPIRY=24h
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

# File Storage
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_BUCKET=your-bucket-name

# Push Notifications
VAPID_PUBLIC_KEY=your-vapid-public-key
VAPID_PRIVATE_KEY=your-vapid-private-key

# Email (for notifications)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
```

### Infrastructure Requirements

- MySQL 8.0 
- Redis for caching and session storage
- File storage (AWS S3 or compatible)
- Background job processing (for notifications)
- SSL certificate for HTTPS
- CDN for static assets (optional)

This comprehensive backend specification should provide everything needed to implement the APIs that support your student portal frontend. Would you like me to elaborate on any specific section or add additional requirements?
