# Lecturer API Documentation

## Base URL
```
/api/v1/lecturer
```

## Authentication
All protected endpoints require Bearer token authentication:
```typescript
headers: {
  'Authorization': 'Bearer {token}',
  'Accept': 'application/json',
  'Content-Type': 'application/json'
}
```

## Response Format
All responses follow a consistent envelope format:
```typescript
interface ApiResponse<T> {
  success: boolean;
  data?: T;
  meta?: {
    page?: number;
    per_page?: number;
    total?: number;
    total_pages?: number;
  };
  message?: string;
  timestamp: string;
}

interface ApiErrorResponse {
  success: false;
  message: string;
  errors: Array<{
    code: string;
    field?: string;
    detail?: string;
  }>;
  timestamp: string;
}
```

## Endpoints

### Authentication

#### Login
```typescript
POST /api/v1/lecturer/auth/login
{
  email: string;
  password: string;
  remember?: boolean;
}
```

#### Google Login
```typescript
POST /api/v1/lecturer/auth/login/google
{
  token: string;
}
```

#### Refresh Token
```typescript
POST /api/v1/lecturer/auth/refresh
```

#### Logout
```typescript
POST /api/v1/lecturer/auth/logout
```

#### Get User Info
```typescript
GET /api/v1/lecturer/auth/me

interface LecturerResponse {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  full_name: string;
  phone?: string;
  is_active: boolean;
}
```

---

### Dashboard

#### Main Dashboard
```typescript
GET /api/v1/lecturer/dashboard
```

#### Teaching Summary
```typescript
GET /api/v1/lecturer/dashboard/teaching-summary
```

#### Attendance Overview
```typescript
GET /api/v1/lecturer/dashboard/attendance-overview
```

#### Student Alerts
```typescript
GET /api/v1/lecturer/dashboard/student-alerts
```

#### Upcoming Sessions
```typescript
GET /api/v1/lecturer/dashboard/upcoming-sessions
```

#### Recent Activities
```typescript
GET /api/v1/lecturer/dashboard/recent-activities
```

---

### Course Management

#### List Courses
```typescript
GET /api/v1/lecturer/courses

// Query params
interface CourseFilters {
  semester_id?: number;
  delivery_mode?: 'online' | 'in_person' | 'hybrid';
  status?: 'active' | 'inactive';
  search?: string;
  per_page?: number; // 5-50, default 15
}

interface CourseOffering {
  id: number;
  section_code: string;
  delivery_mode: string;
  location?: string;
  max_capacity: number;
  current_enrollment: number; // Visible roster count (confirmed + completed + defer)
  active_roster_students: number;
  visible_roster_students: number;
  enrollment_status: string;
  schedule_days: string[];
  schedule_time_start?: string; // "HH:mm"
  schedule_time_end?: string;   // "HH:mm"
  
  curriculum_unit: {
    id: number;
    code: string;
    name: string;
    credit_hours: number;
    description?: string;
  };
  
  semester: {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
  };
  
  enrollment_stats: {
    enrolled_count: number; // Visible roster count
    active_roster_count: number;
    visible_roster_count: number;
    inactive_roster_count: number;
    completed_count: number;
    deferred_count: number;
    capacity_utilization: number;
    available_spots: number;
    active_available_spots: number;
    is_full: boolean;
  };
  
  session_stats: {
    total_sessions: number;
    completed_sessions: number;
    upcoming_sessions: number;
    sessions_with_attendance: number;
    pending_attendance: number;
  };
  
  recent_sessions: Array<{
    id: number;
    title: string;
    date: string;
    start_time: string;
    status: string;
    attendance_marked: boolean;
  }>;
  
  quick_actions: {
    can_mark_attendance: boolean;
    has_upcoming_sessions: boolean;
    needs_attention: boolean;
  };
}
```

#### Get Course Details
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}

interface CourseDetail {
  course_offering: CourseOffering;
  enrollment_statistics: {
    total_registrations: number;
    enrolled_students: number; // Visible roster count
    active_roster_students: number;
    visible_roster_students: number;
    inactive_roster_students: number;
    completed_students: number;
    deferred_students: number;
    waitlisted_students: number;
    dropped_students: number;
    capacity_utilization: number;
    available_spots: number;
    active_available_spots: number;
  };
  attendance_statistics: {
    overall_attendance_rate: number;
    sessions_with_attendance: number;
    pending_attendance: number;
    average_session_attendance: number;
  };
  session_overview: {
    total_sessions: number;
    completed_sessions: number;
    upcoming_sessions: number;
    next_session?: {
      id: number;
      title: string;
      date: string;
      start_time: string;
    };
  };
  student_performance: {
    total_students: number;
    at_risk_students: number;
    excellent_attendance: number;
    good_attendance: number;
    warning_attendance: number;
  };
  health_indicators: {
    enrollment_health: 'excellent' | 'good' | 'fair' | 'poor';
    attendance_health: 'excellent' | 'good' | 'fair' | 'poor';
    session_health: 'excellent' | 'good' | 'fair' | 'poor';
    overall_health: 'excellent' | 'good' | 'fair' | 'poor';
  };
  recommendations: Array<{
    type: 'enrollment' | 'attendance' | 'session';
    priority: 'high' | 'medium' | 'low' | 'info';
    message: string;
    action: string;
  }>;
}
```

#### Get Unit Information
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/unit
```

#### Get Course Statistics
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/statistics
```

#### Get Course Students
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/students

// Query params
interface StudentFilters {
  search?: string;
  status?: 'excellent' | 'good' | 'warning' | 'at_risk';
  academic_standing?: 'good' | 'honors' | 'probation' | 'suspension';
  risk_level?: 'low' | 'medium' | 'high';
  sort_by?: 'name' | 'attendance' | 'last_attendance';
  sort_direction?: 'asc' | 'desc';
}

interface CourseStudent {
  student_id: number;
  student_number: string;
  full_name: string;
  email: string;
  registration_date: string;
  
  registration: {
    status: 'confirmed' | 'completed' | 'defer' | 'waitlisted' | 'dropped' | string;
    attempt_number: number;
    is_retake: boolean;
  };

  roster: {
    is_active: boolean;
    status: 'active' | 'completed' | 'defer' | 'deferred' | 'dropout' | 'dropout_transfer' | 'inactive' | string;
    status_label: string;
    can_mark_attendance: boolean;
  };
  
  academics: {
    final_score?: number;
    final_grade?: string;
    grade_status: 'provisional' | 'final' | 'pending' | 'submitted' | 'approved';
    grade_status_label: string;
  };
  
  attendance: {
    percentage: number;
    sessions_attended: number;
    total_sessions: number;
    last_attendance?: string;
    meets_requirement: boolean;
    status: 'excellent' | 'good' | 'warning' | 'at_risk' | 'inactive';
    status_label: string;
    status_color: 'green' | 'blue' | 'yellow' | 'red' | 'gray';
  };
  
  academic_standing: {
    status: string;
    label: string;
    color: string;
  };
  
  risk_assessment: {
    level: 'low' | 'medium' | 'high';
    factors: string[];
    recommendations: string[];
  };
  
  actions: {
    can_contact: boolean;
    can_add_note: boolean;
    needs_attention: boolean;
    can_view_details: boolean;
  };
}
```

#### Get Course Sessions
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/sessions

interface CourseSession {
  id: number;
  title: string;
  description?: string;
  session_date: string;
  start_time: string;
  end_time: string;
  duration_minutes: number;
  session_type: string;
  delivery_mode: string;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
  attendance_marked: boolean;
  attendance_percentage?: number;
  expected_attendees: number;
  actual_attendees?: number;
  learning_objectives?: string;
  topics_covered?: string;
}
```

#### Get Course Summary
```typescript
GET /api/v1/lecturer/courses/summary?semester_id={id}

interface CourseSummary {
  total_courses: number;
  active_courses: number;
  total_students: number;
  average_enrollment: number;
  delivery_mode_breakdown: Record<string, number>;
  capacity_utilization: number;
}
```

#### Get Filter Options
```typescript
GET /api/v1/lecturer/courses/filter-options

interface FilterOptions {
  semesters: Array<{
    id: number;
    name: string;
    code: string;
    is_active: boolean;
  }>;
  delivery_modes: string[];
  enrollment_statuses: string[];
}
```

---

### Attendance Management

#### Get Attendance Overview
```typescript
GET /api/v1/lecturer/attendance
```

#### Get Attendance Summary
```typescript
GET /api/v1/lecturer/attendance/summary
```

#### Get Attendance Alerts
```typescript
GET /api/v1/lecturer/attendance/alerts
```

#### Get Sessions Requiring Attention
```typescript
GET /api/v1/lecturer/attendance/sessions-requiring-attention
```

#### Get Session Attendance
```typescript
GET /api/v1/lecturer/attendance/sessions/{sessionId}
```

#### Mark Session Attendance
```typescript
POST /api/v1/lecturer/attendance/sessions/{sessionId}/mark
{
  attendees: Array<{
    student_id: number;
    status: 'present' | 'absent' | 'late' | 'excused';
    notes?: string;
  }>;
}
```

#### Bulk Mark Attendance
```typescript
POST /api/v1/lecturer/attendance/bulk-mark
{
  session_ids: number[];
  attendance_data: Record<number, Array<{
    student_id: number;
    status: 'present' | 'absent' | 'late' | 'excused';
    notes?: string;
  }>>;
}
```

#### Get Course Attendance Analytics
```typescript
GET /api/v1/lecturer/attendance/courses/{courseOfferingId}/analytics
```

#### Export Attendance
```typescript
GET /api/v1/lecturer/attendance/courses/{courseOfferingId}/export?format=excel|pdf
```

---

### Timetable & Sessions

#### Get Timetable
```typescript
GET /api/v1/lecturer/timetable
```

#### Get Timetable Summary
```typescript
GET /api/v1/lecturer/timetable/summary
```

#### Get Upcoming Sessions
```typescript
GET /api/v1/lecturer/timetable/upcoming-sessions
```

#### Get Available Rooms
```typescript
GET /api/v1/lecturer/timetable/available-rooms
```

#### Create Session
```typescript
POST /api/v1/lecturer/sessions
{
  course_offering_id: number;
  session_title: string;
  session_description?: string;
  session_date: string; // YYYY-MM-DD
  start_time: string;   // HH:mm
  end_time: string;     // HH:mm
  session_type: string;
  delivery_mode: string;
  location?: string;
  learning_objectives?: string;
  topics_covered?: string;
}
```

#### Get Session Details
```typescript
GET /api/v1/lecturer/sessions/{sessionId}
```

#### Update Session
```typescript
PUT /api/v1/lecturer/sessions/{sessionId}
{
  session_title?: string;
  session_description?: string;
  session_date?: string;
  start_time?: string;
  end_time?: string;
  session_type?: string;
  delivery_mode?: string;
  location?: string;
  learning_objectives?: string;
  topics_covered?: string;
}
```

#### Cancel Session
```typescript
DELETE /api/v1/lecturer/sessions/{sessionId}
{
  cancellation_reason?: string;
  notify_students?: boolean;
}
```

#### Bulk Update Sessions
```typescript
POST /api/v1/lecturer/sessions/bulk-update
{
  session_ids: number[];
  updates: {
    session_type?: string;
    delivery_mode?: string;
    location?: string;
  };
}
```

---

### Student Management

#### Get Students Overview
```typescript
GET /api/v1/lecturer/students
```

#### Get Students Summary
```typescript
GET /api/v1/lecturer/students/summary
```

#### Get Student Alerts
```typescript
GET /api/v1/lecturer/students/alerts
```

#### Get Students Requiring Attention
```typescript
GET /api/v1/lecturer/students/requires-attention
```

#### Get Student Analytics
```typescript
GET /api/v1/lecturer/students/analytics
```

#### Get Student Details
```typescript
GET /api/v1/lecturer/students/{studentId}
```

#### Add Student Note
```typescript
POST /api/v1/lecturer/students/{studentId}/notes
{
  note: string;
  priority: 'low' | 'medium' | 'high';
  category?: string;
}
```

#### Update Student Note
```typescript
PUT /api/v1/lecturer/students/{studentId}/notes/{noteId}
{
  note?: string;
  priority?: 'low' | 'medium' | 'high';
  category?: string;
}
```

#### Delete Student Note
```typescript
DELETE /api/v1/lecturer/students/{studentId}/notes/{noteId}
```

#### Bulk Student Actions
```typescript
POST /api/v1/lecturer/students/bulk-actions
{
  student_ids: number[];
  action: 'send_reminder' | 'mark_for_intervention' | 'add_to_watch_list';
  additional_data?: any;
}
```

---

### Assessment Management

#### Get Course Assessments
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments
```

#### Create Assessment
```typescript
POST /api/v1/lecturer/courses/{courseOfferingId}/assessments
{
  name: string;
  description?: string;
  weight_percentage: number;
  assessment_type: string;
  due_date?: string;
  is_active: boolean;
}
```

#### Update Assessment
```typescript
PUT /api/v1/lecturer/courses/{courseOfferingId}/assessments/{assessmentId}
{
  name?: string;
  description?: string;
  weight_percentage?: number;
  assessment_type?: string;
  due_date?: string;
  is_active?: boolean;
}
```

#### Delete Assessment
```typescript
DELETE /api/v1/lecturer/courses/{courseOfferingId}/assessments/{assessmentId}
```

#### Assessment Details Management
```typescript
POST /api/v1/lecturer/courses/{courseOfferingId}/assessments/{assessmentId}/details
PUT /api/v1/lecturer/courses/{courseOfferingId}/assessments/{assessmentId}/details/{detailId}
DELETE /api/v1/lecturer/courses/{courseOfferingId}/assessments/{assessmentId}/details/{detailId}
```

#### Grading
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/grade/student/{studentId}
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/grade/component/{assessmentId}
PUT /api/v1/lecturer/courses/{courseOfferingId}/assessments/scores/{scoreId}
POST /api/v1/lecturer/courses/{courseOfferingId}/assessments/scores/bulk-update
```

#### Assessment Reports
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/report/overview
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/report/grade-matrix
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/report/statistics
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/report/export/excel
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/report/export/pdf
```

#### Assessment Weight Validation
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/validate-weights
```

#### Excel Grade Management
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{detailId}/export-template
POST /api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{detailId}/import-grades
```

---

## Error Handling

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden  
- `404` - Not Found
- `422` - Validation Error
- `429` - Rate Limited
- `500` - Server Error

Example error response:
```typescript
{
  success: false,
  message: "Validation failed",
  errors: [
    {
      code: "VALIDATION_ERROR",
      field: "email",
      detail: "The email field is required"
    }
  ],
  timestamp: "2025-08-27T16:23:08.000Z"
}
```

## TypeScript Types Usage

```typescript
// Import types in your frontend
import type { 
  ApiResponse, 
  CourseOffering, 
  CourseDetail,
  CourseStudent,
  CourseSession 
} from '@/types/api/lecturer';

// Example API call with proper typing
const fetchCourses = async (): Promise<ApiResponse<CourseOffering[]>> => {
  const response = await fetch('/api/v1/lecturer/courses', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.json();
};
```
