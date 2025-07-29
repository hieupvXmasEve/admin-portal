# Lecturer Portal Backend API - Product Requirements Document

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Authentication & Authorization](#authentication--authorization)
4. [API Endpoints](#api-endpoints)
5. [Data Models](#data-models)
6. [Business Logic](#business-logic)
7. [Error Handling](#error-handling)
8. [Performance Requirements](#performance-requirements)
9. [Security Requirements](#security-requirements)
10. [Assumptions](#assumptions)

## Overview

### Purpose

This document defines the backend API requirements for the Lecturer Portal system, which enables lecturers to manage their courses, track student attendance, view dashboards, and manage their teaching schedules.

### Scope

The API covers four main functional areas:

- **Dashboard Management**: Overview of teaching activities, alerts, and statistics
- **Course Management**: Course offerings, materials, and student enrollment
- **Attendance Management**: Session attendance tracking and analytics
- **Timetable Management**: Schedule management and session planning

### Technology Stack

- **Framework**: RESTful API (Laravel/Node.js recommended)
- **Database**: MySQL (based on provided schema)
- **Authentication**: JWT/Session-based
- **File Storage**: Cloud storage for materials and documents

## System Architecture

### Database Integration

The API integrates with the existing database schema including:

- `lectures` table for lecturer profiles
- `course_offerings` table for course management
- `class_sessions` table for session scheduling
- `attendances` table for attendance tracking
- `students` table for student information
- Related tables for academic records, materials, etc.

### API Response Format

All API responses follow a consistent format:

```json
{
  "success": boolean,
  "message": string,
  "data": object|array|null,
  "meta": {
    "timestamp": "ISO 8601 datetime",
    "request_id": "unique_identifier"
  }
}
```

## Authentication & Authorization

### Authentication

- JWT token-based authentication
- Token includes lecturer ID and permissions
- Token expiration: 24 hours (configurable)

### Authorization

- Role-based access control (RBAC)
- Lecturer can only access their own data
- Admin users can access all lecturer data
- Course-specific permissions for shared courses

### Headers Required

```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

## API Endpoints

### 1. Dashboard APIs

#### GET /api/v1/lecturer/dashboard

**Purpose**: Get comprehensive dashboard data for the authenticated lecturer

**Response**:

```json
{
  "success": true,
  "data": {
    "teaching_summary": {
      "total_courses": 3,
      "total_students": 127,
      "active_sessions_today": [...],
      "upcoming_sessions": [...],
      "completed_sessions_this_week": 8
    },
    "attendance_summary": {
      "sessions_requiring_attendance": 2,
      "sessions_completed_today": 1,
      "average_class_attendance": 87.5,
      "attendance_trend": "improving"
    },
    "student_alerts": {
      "low_attendance_students": [...],
      "students_needing_attention": [...],
      "total_alerts": 5,
      "critical_alerts": 2
    },
    "recent_activities": [...],
    "upcoming_deadlines": {
      "assignment_due_dates": [...],
      "exam_schedules": [...],
      "administrative_tasks": [...]
    }
  }
}
```

**Business Logic**:

- Calculate statistics from current semester data
- Filter sessions by lecturer_id
- Identify at-risk students based on attendance thresholds
- Generate trend analysis from historical data

#### GET /api/v1/lecturer/dashboard/teaching-summary

**Purpose**: Get teaching summary statistics

**Response**:

```json
{
  "success": true,
  "data": {
    "total_courses": 3,
    "total_students": 127,
    "active_sessions_today": [...],
    "upcoming_sessions": [...],
    "completed_sessions_this_week": 8
  }
}
```

#### GET /api/v1/lecturer/dashboard/student-alerts

**Purpose**: Get student alerts requiring attention

**Query Parameters**:

- `severity`: Filter by severity (low, medium, high, critical)
- `course_id`: Filter by specific course
- `limit`: Number of alerts to return (default: 50)

**Response**:

```json
{
  "success": true,
  "data": {
    "low_attendance_students": [...],
    "students_needing_attention": [...],
    "total_alerts": 5,
    "critical_alerts": 2
  }
}
```

#### GET /api/v1/lecturer/dashboard/activities

**Purpose**: Get recent teaching activities

**Query Parameters**:

- `limit`: Number of activities (default: 20)
- `type`: Filter by activity type
- `course_id`: Filter by course

**Response**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "attendance_marked",
      "title": "Attendance marked for CS101",
      "description": "Marked attendance for 45 students",
      "course_code": "CS101",
      "timestamp": "2024-01-15T10:30:00Z",
      "metadata": {...}
    }
  ]
}
```

### 2. Course Management APIs

#### GET /api/v1/lecturer/courses

**Purpose**: Get all course offerings for the authenticated lecturer

**Query Parameters**:

- `semester_id`: Filter by semester
- `delivery_mode`: Filter by delivery mode
- `status`: Filter by course status
- `include`: Include related data (students, materials, sessions)

**Response**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "curriculum_unit": {
        "id": 1,
        "code": "CS101",
        "name": "Introduction to Computer Science",
        "credits": 3,
        "description": "...",
        "prerequisites": ["MATH101"]
      },
      "semester": {
        "id": 1,
        "name": "Semester 1",
        "code": "2024S1",
        "start_date": "2024-01-15",
        "end_date": "2024-05-15",
        "academic_year": "2024",
        "is_current": true
      },
      "lecturer_id": 1,
      "section_code": "A",
      "max_capacity": 50,
      "current_enrollment": 45,
      "delivery_mode": "in_person",
      "schedule_days": ["Monday", "Wednesday", "Friday"],
      "schedule_time_start": "09:00:00",
      "schedule_time_end": "10:30:00",
      "location": "Room A101",
      "syllabus_url": "https://...",
      "course_materials": [...],
      "class_sessions": [...],
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

#### GET /api/v1/lecturer/courses/{courseId}

**Purpose**: Get detailed information for a specific course

**Path Parameters**:

- `courseId`: Course offering ID

**Response**: Same structure as single course in the array above

#### GET /api/v1/lecturer/courses/{courseId}/students

**Purpose**: Get enrolled students for a specific course

**Query Parameters**:

- `include_attendance`: Include attendance statistics
- `include_grades`: Include grade information
- `status`: Filter by enrollment status

**Response**:

```json
{
  "success": true,
  "data": [
    {
      "student_id": 1,
      "student_name": "Alice Johnson",
      "student_email": "alice.johnson@student.edu",
      "student_avatar_url": "https://...",
      "enrollment_status": "active",
      "registration_date": "2024-01-10",
      "attendance_percentage": 95.5,
      "participation_score": 88.0,
      "academic_standing": "good",
      "campus": {...},
      "program": {...},
      "specialization": {...},
      "current_gpa": 3.75,
      "total_credits": 45,
      "notes": "Excellent student"
    }
  ]
}
```

#### GET /api/v1/lecturer/courses/{courseId}/statistics

**Purpose**: Get course statistics and analytics

**Response**:

```json
{
  "success": true,
  "data": {
    "course_offering_id": 1,
    "total_sessions": 24,
    "completed_sessions": 8,
    "average_attendance": 87.5,
    "attendance_trend": "improving",
    "student_performance_summary": {
      "excellent_attendance": 15,
      "good_attendance": 25,
      "poor_attendance": 5
    },
    "engagement_metrics": {
      "average_participation_score": 82.3,
      "active_students": 40,
      "at_risk_students": 5
    }
  }
}
```

#### POST /api/v1/lecturer/courses/{courseId}/materials

**Purpose**: Upload course material

**Request**: Multipart form data

- `file`: Material file
- `title`: Material title
- `description`: Optional description
- `is_required`: Boolean
- `week_number`: Optional week number
- `topic`: Optional topic

**Response**:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "course_offering_id": 1,
    "title": "Lecture 1 Slides",
    "description": "Introduction to programming concepts",
    "file_url": "https://storage.../lecture1.pdf",
    "file_type": "application/pdf",
    "file_size": 2048576,
    "upload_date": "2024-01-15T10:30:00Z",
    "is_required": true,
    "week_number": 1,
    "topic": "Introduction"
  }
}
```

#### PUT /api/v1/lecturer/courses/{courseId}/syllabus

**Purpose**: Update course syllabus

**Request**:

```json
{
  "syllabus_file": "base64_encoded_file_or_url",
  "description": "Updated syllabus for semester 1"
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "syllabus_url": "https://storage.../syllabus.pdf",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

### 3. Attendance Management APIs

#### GET /api/v1/lecturer/attendance/sessions

**Purpose**: Get attendance sessions for the lecturer

**Query Parameters**:

- `course_id`: Filter by course
- `date_from`: Start date filter
- `date_to`: End date filter
- `status`: Filter by marking status (marked, pending)
- `include_records`: Include attendance records

**Response**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "class_session_id": 1,
      "course_code": "CS101",
      "course_name": "Introduction to Computer Science",
      "session_date": "2024-01-15",
      "session_type": "lecture",
      "attendance_records": [...],
      "marked_by": 1,
      "marked_at": "2024-01-15T10:30:00Z",
      "total_students": 45,
      "present_count": 42,
      "absent_count": 2,
      "late_count": 1,
      "attendance_percentage": 93.3
    }
  ]
}
```

#### GET /api/v1/lecturer/attendance/sessions/{sessionId}

**Purpose**: Get detailed attendance information for a specific session

**Response**:

```json
{
  "success": true,
  "data": {
    "class_session_id": 1,
    "course_code": "CS101",
    "course_name": "Introduction to Computer Science",
    "session_title": "Variables and Data Types",
    "session_date": "2024-01-15",
    "start_time": "09:00:00",
    "end_time": "10:30:00",
    "location": "Room A101",
    "expected_students": [
      {
        "student_id": 1,
        "student_name": "Alice Johnson",
        "student_email": "alice.johnson@student.edu",
        "avatar_url": null,
        "attendance_rate": 95.0,
        "recent_pattern": "regular"
      }
    ],
    "current_attendance": [
      {
        "student_id": 1,
        "status": "present",
        "check_in_time": "09:05:00",
        "minutes_late": 5,
        "participation_level": "excellent",
        "participation_score": 95
      }
    ],
    "is_marked": true,
    "marked_at": "2024-01-15T10:30:00Z"
  }
}
```

#### POST /api/v1/lecturer/attendance/sessions/{sessionId}/mark

**Purpose**: Mark attendance for a session

**Request**:

```json
{
  "attendance_records": [
    {
      "student_id": 1,
      "status": "present",
      "check_in_time": "09:05:00",
      "minutes_late": 5,
      "participation_level": "excellent",
      "participation_score": 95,
      "notes": "Active participation"
    }
  ],
  "session_notes": "Good class engagement"
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "session_id": 1,
    "marked_at": "2024-01-15T10:30:00Z",
    "total_records": 45,
    "summary": {
      "present": 42,
      "absent": 2,
      "late": 1,
      "attendance_percentage": 93.3
    }
  }
}
```

#### PUT /api/v1/lecturer/attendance/sessions/{sessionId}/bulk-update

**Purpose**: Bulk update attendance records

**Request**:

```json
{
  "updates": [
    {
      "student_id": 1,
      "status": "present",
      "check_in_time": "09:05:00",
      "minutes_late": 5
    }
  ]
}
```

#### GET /api/v1/lecturer/attendance/analytics/{courseId}

**Purpose**: Get attendance analytics for a course

**Query Parameters**:

- `period`: Time period (week, month, semester)
- `start_date`: Analysis start date
- `end_date`: Analysis end date

**Response**:

```json
{
  "success": true,
  "data": {
    "course_offering_id": 1,
    "period": {
      "start_date": "2024-01-15",
      "end_date": "2024-05-15",
      "total_sessions": 24
    },
    "overall_statistics": {
      "total_students": 45,
      "average_attendance_rate": 87.5,
      "trend": "improving",
      "best_attended_session": {...},
      "worst_attended_session": {...}
    },
    "student_breakdown": [...],
    "session_breakdown": [...],
    "patterns": {
      "day_of_week_analysis": {...},
      "time_of_day_analysis": {...},
      "seasonal_trends": {...}
    }
  }
}
```

#### POST /api/v1/lecturer/attendance/export/{courseId}

**Purpose**: Export attendance report

**Request**:

```json
{
  "format": "pdf",
  "period": {
    "start_date": "2024-01-15",
    "end_date": "2024-05-15"
  },
  "include_analytics": true
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "report_id": "ATT_RPT_20240115_001",
    "file_url": "https://storage.../attendance_report.pdf",
    "expires_at": "2024-01-22T10:30:00Z"
  }
}
```

### 4. Timetable Management APIs

#### GET /api/v1/lecturer/timetable

**Purpose**: Get lecturer's timetable

**Query Parameters**:

- `week_start`: Start of week (YYYY-MM-DD)
- `include_past`: Include past sessions
- `course_id`: Filter by course

**Response**:

```json
{
  "success": true,
  "data": {
    "week_start": "2024-01-15",
    "sessions": [
      {
        "id": 1,
        "course_offering_id": 1,
        "course_code": "CS101",
        "course_name": "Introduction to Computer Science",
        "session_title": "Variables and Data Types",
        "session_type": "lecture",
        "session_date": "2024-01-15",
        "start_time": "09:00:00",
        "end_time": "10:30:00",
        "location": "Room A101",
        "delivery_mode": "in_person",
        "status": "scheduled",
        "expected_attendees": 45,
        "attendance_marked": false,
        "online_meeting_url": null
      }
    ]
  }
}
```

#### POST /api/v1/lecturer/timetable/sessions

**Purpose**: Create a new session

**Request**:

```json
{
  "course_offering_id": 1,
  "session_title": "Advanced Topics",
  "session_description": "Discussion on advanced programming concepts",
  "session_date": "2024-01-20",
  "start_time": "09:00:00",
  "end_time": "10:30:00",
  "session_type": "lecture",
  "delivery_mode": "in_person",
  "location": "Room A101",
  "attendance_required": true,
  "online_meeting_url": null,
  "learning_objectives": ["Understand advanced concepts", "Apply knowledge"],
  "required_materials": ["Textbook Chapter 5"],
  "instructor_notes": "Prepare examples"
}
```

**Response**:

```json
{
  "success": true,
  "data": {
    "id": 25,
    "course_offering_id": 1,
    "session_title": "Advanced Topics",
    "session_date": "2024-01-20",
    "start_time": "09:00:00",
    "end_time": "10:30:00",
    "status": "scheduled",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### PUT /api/v1/lecturer/timetable/sessions/{sessionId}

**Purpose**: Update an existing session

**Request**: Same structure as POST

#### DELETE /api/v1/lecturer/timetable/sessions/{sessionId}

**Purpose**: Cancel/delete a session

**Request**:

```json
{
  "cancellation_reason": "Lecturer unavailable",
  "notify_students": true
}
```

### 5. Student Management APIs

#### GET /api/v1/lecturer/students/alerts

**Purpose**: Get student alerts across all courses

**Query Parameters**:

- `severity`: Filter by severity
- `alert_type`: Filter by alert type
- `course_id`: Filter by course
- `resolved`: Include resolved alerts

**Response**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_id": 123,
      "student_name": "John Doe",
      "course_code": "CS101",
      "course_name": "Introduction to Computer Science",
      "alert_type": "low_attendance",
      "severity": "high",
      "title": "Low Attendance Alert",
      "description": "Student has missed 4 consecutive classes",
      "details": "Last attended: 2024-01-10",
      "action_required": true,
      "attendance_percentage": 65.0,
      "absence_count": 4,
      "created_at": "2024-01-15T10:30:00Z",
      "resolved_at": null,
      "resolved_by": null
    }
  ]
}
```

#### DELETE /api/v1/lecturer/students/alerts/{alertId}

**Purpose**: Dismiss/resolve a student alert

**Response**:

```json
{
  "success": true,
  "data": {
    "alert_id": 1,
    "resolved_at": "2024-01-15T10:30:00Z",
    "resolved_by": 1
  }
}
```

#### POST /api/v1/lecturer/students/{studentId}/notes

**Purpose**: Add a note about a student

**Request**:

```json
{
  "note": "Student showed improvement in recent classes",
  "course_id": 1,
  "is_private": true
}
```

## Data Models

### Core Entities

#### Lecturer

```typescript
interface Lecturer {
  id: number
  employee_id: string
  first_name: string
  last_name: string
  email: string
  title: string
  department: Department
  office_location?: string
  created_at: string
  updated_at: string
}
```

#### CourseOffering

```typescript
interface CourseOffering {
  id: number
  curriculum_unit: CurriculumUnit
  semester: Semester
  lecturer_id: number
  section_code: string
  max_capacity: number
  current_enrollment: number
  delivery_mode: 'in_person' | 'online' | 'hybrid' | 'blended'
  schedule_days: string[]
  schedule_time_start: string
  schedule_time_end: string
  location?: string
  created_at: string
  updated_at: string
}
```

#### ClassSession

```typescript
interface ClassSession {
  id: number
  course_offering_id: number
  instructor_id: number
  session_title: string
  session_date: string
  start_time: string
  end_time: string
  session_type:
    | 'lecture'
    | 'tutorial'
    | 'practical'
    | 'laboratory'
    | 'seminar'
    | 'workshop'
    | 'exam'
  delivery_mode: 'in_person' | 'online' | 'hybrid' | 'blended'
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled' | 'postponed'
  location?: string
  attendance_required: boolean
  attendance_marked: boolean
  expected_attendees: number
  actual_attendees: number
  attendance_percentage: number
  created_at: string
  updated_at: string
}
```

#### AttendanceRecord

```typescript
interface AttendanceRecord {
  id: number
  class_session_id: number
  student_id: number
  status: 'present' | 'absent' | 'late' | 'excused' | 'partial'
  check_in_time?: string
  check_out_time?: string
  minutes_late: number
  participation_level?: 'excellent' | 'good' | 'average' | 'poor' | 'none'
  participation_score?: number
  notes?: string
  recorded_by_lecturer_id: number
  recorded_at: string
  updated_at: string
}
```

## Business Logic

### Dashboard Calculations

1. **Teaching Summary**:

   - Count active courses from `course_offerings` where `lecturer_id` matches
   - Sum enrollment from all active courses
   - Filter today's sessions from `class_sessions`
   - Calculate completed sessions this week

2. **Attendance Summary**:

   - Count sessions requiring attendance marking
   - Calculate average attendance across all courses
   - Determine attendance trend using historical data

3. **Student Alerts**:
   - Identify students with attendance < 75%
   - Flag consecutive absences > 3
   - Calculate participation scores below threshold

### Attendance Processing

1. **Attendance Marking**:

   - Validate session exists and belongs to lecturer
   - Ensure all enrolled students are included
   - Calculate attendance percentages
   - Update session status to "marked"

2. **Analytics Generation**:
   - Aggregate attendance data by time periods
   - Calculate trends using moving averages
   - Identify patterns in attendance behavior

### Course Management

1. **Enrollment Tracking**:

   - Monitor capacity vs. current enrollment
   - Track enrollment changes over time
   - Generate alerts for over/under enrollment

2. **Material Management**:
   - Validate file types and sizes
   - Organize materials by week/topic
   - Track download statistics

## Error Handling

### HTTP Status Codes

- `200`: Success
- `201`: Created
- `400`: Bad Request (validation errors)
- `401`: Unauthorized
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found
- `422`: Unprocessable Entity (business logic errors)
- `500`: Internal Server Error

### Error Response Format

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  },
  "error_code": "VALIDATION_ERROR",
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "req_123456"
  }
}
```

### Common Error Scenarios

1. **Authentication Errors**:

   - Invalid or expired JWT token
   - Missing authorization header

2. **Authorization Errors**:

   - Accessing another lecturer's data
   - Insufficient permissions for operation

3. **Validation Errors**:

   - Invalid date formats
   - Missing required fields
   - Invalid enum values

4. **Business Logic Errors**:
   - Marking attendance for non-existent session
   - Uploading materials to inactive course
   - Scheduling conflicting sessions

## Performance Requirements

### Response Times

- Dashboard APIs: < 500ms
- Course listing: < 300ms
- Attendance marking: < 200ms
- File uploads: < 5s (depending on size)

### Caching Strategy

- Dashboard data: 5 minutes
- Course listings: 15 minutes
- Student data: 10 minutes
- Static reference data: 1 hour

### Database Optimization

- Index on `lecturer_id` for all lecturer-specific queries
- Index on `session_date` for timetable queries
- Index on `course_offering_id` for course-related queries
- Composite indexes for common filter combinations

## Security Requirements

### Data Protection

- Encrypt sensitive data at rest
- Use HTTPS for all API communications
- Sanitize all user inputs
- Implement rate limiting

### Access Control

- Lecturers can only access their own data
- Course-specific permissions for shared courses
- Admin override capabilities with audit logging

### Audit Logging

- Log all data modifications
- Track attendance marking activities
- Monitor file uploads and downloads
- Record alert dismissals

## Assumptions

### Database Assumptions

1. The `lectures` table represents lecturer profiles
2. The `course_offerings` table links lecturers to courses
3. The `class_sessions` table contains scheduled sessions
4. The `attendances` table stores attendance records
5. Foreign key relationships are properly maintained

### Business Logic Assumptions

1. A lecturer can teach multiple courses
2. Attendance marking is session-specific
3. Students are pre-enrolled in courses
4. Semester dates determine "current" courses
5. Attendance thresholds: <75% = poor, >90% = excellent

### Technical Assumptions

1. File storage is handled by cloud service (S3, etc.)
2. Real-time notifications are handled separately
3. Email notifications are handled by external service
4. Database supports JSON fields for flexible data
5. API versioning will be implemented for future changes

### Integration Assumptions

1. Student portal APIs exist for cross-reference
2. Authentication service provides JWT tokens
3. File storage service provides secure URLs
4. Notification service handles email/SMS alerts
5. Analytics service processes attendance patterns

## Implementation Notes

### Priority Levels

1. **High Priority**: Dashboard, Course listing, Attendance marking
2. **Medium Priority**: Analytics, Material upload, Timetable management
3. **Low Priority**: Advanced reporting, Bulk operations, Alert management

### Development Phases

1. **Phase 1**: Core CRUD operations and authentication
2. **Phase 2**: Dashboard and basic analytics
3. **Phase 3**: Advanced features and optimizations
4. **Phase 4**: Reporting and export functionality

### Testing Requirements

- Unit tests for all business logic
- Integration tests for API endpoints
- Performance tests for high-load scenarios
- Security tests for authentication/authorization
