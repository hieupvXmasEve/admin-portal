# Lecturer Course Management API

## Base URL
```
/api/v1/lecturer/courses
```

## Authentication
```typescript
headers: {
  'Authorization': 'Bearer {token}',
  'Accept': 'application/json',
  'Content-Type': 'application/json'
}
```

---

## 📚 Course Listing

### List Lecturer's Courses
```typescript
GET /api/v1/lecturer/courses
```

#### Query Parameters
```typescript
interface CourseFilters {
  semester_id?: number;
  delivery_mode?: 'online' | 'in_person' | 'hybrid' | 'blended';
  enrollment_status?: 'open' | 'closed' | 'full';
  search?: string;  // Search by unit code or name
  per_page?: number; // 5-50, default: 15
  page?: number;     // default: 1
}
```

#### Response
```typescript
interface CourseListResponse {
  success: true;
  data: CourseOffering[];
  meta: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
  message: string;
  timestamp: string;
}

interface CourseOffering {
  id: number;
  section_code: string;
  delivery_mode: 'online' | 'in_person' | 'hybrid' | 'blended';
  location?: string;
  max_capacity: number;
  current_enrollment: number; // Visible roster count (confirmed + completed + defer)
  active_roster_students: number; // Active/markable attendance targets
  visible_roster_students: number;
  enrollment_status: 'open' | 'closed' | 'full';
  schedule_days: string[]; // ["Monday", "Wednesday", "Friday"]
  schedule_time_start?: string; // "09:00"
  schedule_time_end?: string;   // "10:30"
  
  // Curriculum Unit Information
  curriculum_unit: {
    id: number;
    code: string;        // Unit code like "COS10009"
    name: string;        // Unit name
    credit_hours: number;
    description?: string;
  };
  
  // Semester Information
  semester: {
    id: number;
    name: string;        // "Semester 1, 2024"
    code: string;        // "2024S1"
    start_date: string;  // "2024-02-26"
    end_date: string;    // "2024-06-21"
    is_active: boolean;
  };
  
  // Enrollment Statistics
  enrollment_stats: {
    enrolled_count: number; // Visible roster count
    active_roster_count: number;
    visible_roster_count: number;
    inactive_roster_count: number;
    completed_count: number;
    deferred_count: number;
    capacity_utilization: number; // Percentage (0-100+)
    available_spots: number;
    active_available_spots: number;
    is_full: boolean;
  };
  
  // Session Statistics
  session_stats: {
    total_sessions: number;
    completed_sessions: number;
    upcoming_sessions: number;
    sessions_with_attendance: number;
    pending_attendance: number; // Sessions completed but attendance not marked
  };
  
  // Recent Sessions (Last 3)
  recent_sessions: Array<{
    id: number;
    title: string;
    date: string;      // "2024-03-15"
    start_time: string; // "09:00"
    status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
    attendance_marked: boolean;
  }>;
  
  // Quick Action Indicators
  quick_actions: {
    can_mark_attendance: boolean;    // Has completed sessions without attendance
    has_upcoming_sessions: boolean;  // Has scheduled future sessions
    needs_attention: boolean;        // Requires lecturer attention
  };
  
  // Timestamps
  created_at: string; // ISO string
  updated_at: string; // ISO string
}
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "section_code": "COS10009_S1_01",
      "delivery_mode": "in_person",
      "location": "Building A, Room 101",
      "max_capacity": 30,
      "current_enrollment": 28,
      "enrollment_status": "open",
      "schedule_days": ["Monday", "Wednesday"],
      "schedule_time_start": "09:00",
      "schedule_time_end": "10:30",
      "curriculum_unit": {
        "id": 1,
        "code": "COS10009",
        "name": "Introduction to Programming",
        "credit_hours": 12.5,
        "description": "Foundational programming concepts"
      },
      "semester": {
        "id": 1,
        "name": "Semester 1, 2024",
        "code": "2024S1",
        "start_date": "2024-02-26",
        "end_date": "2024-06-21",
        "is_active": true
      },
      "enrollment_stats": {
        "enrolled_count": 28,
        "capacity_utilization": 93.3,
        "available_spots": 2,
        "is_full": false
      },
      "session_stats": {
        "total_sessions": 24,
        "completed_sessions": 18,
        "upcoming_sessions": 6,
        "sessions_with_attendance": 16,
        "pending_attendance": 2
      },
      "recent_sessions": [
        {
          "id": 45,
          "title": "Week 9: Object-Oriented Programming",
          "date": "2024-04-15",
          "start_time": "09:00",
          "status": "completed",
          "attendance_marked": true
        }
      ],
      "quick_actions": {
        "can_mark_attendance": true,
        "has_upcoming_sessions": true,
        "needs_attention": true
      },
      "created_at": "2024-01-15T08:00:00.000Z",
      "updated_at": "2024-04-15T14:30:00.000Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 15,
    "total": 5,
    "total_pages": 1
  },
  "message": "Course offerings retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 📖 Course Details

### Get Course Details
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}
```

#### Response
```typescript
interface CourseDetailResponse {
  success: true;
  data: CourseDetail;
  message: string;
  timestamp: string;
}

interface CourseDetail {
  // Basic Course Information (same as CourseOffering above)
  course_offering: {
    id: number;
    section_code: string;
    delivery_mode: string;
    location?: string;
    max_capacity: number;
    current_enrollment: number; // Visible roster count
    active_roster_students: number;
    visible_roster_students: number;
    enrollment_status: string;
    schedule_days: string[];
    schedule_time_start?: string;
    schedule_time_end?: string;
    curriculum_unit: {
      id: number;
      unit_code: string;
      unit_name: string;
      credit_hours: number;
      description?: string;
    };
    semester: {
      id: number;
      name: string;
      code: string;
      start_date: string;
      end_date: string;
    };
  };
  
  // Detailed Enrollment Statistics
  enrollment_statistics: {
    total_registrations: number;    // All registrations (including waitlisted/dropped)
    enrolled_students: number;      // Visible roster count
    active_roster_students: number; // Active/markable attendance targets
    visible_roster_students: number;
    inactive_roster_students: number;
    completed_students: number;
    deferred_students: number;
    waitlisted_students: number;    // Students on waitlist
    dropped_students: number;       // Students who dropped
    capacity_utilization: number;   // Percentage of capacity used
    available_spots: number;        // Remaining capacity
    active_available_spots: number; // Remaining active capacity
  };
  
  // Detailed Attendance Statistics
  attendance_statistics: {
    total_sessions: number;
    completed_sessions: number;
    sessions_with_attendance: number;
    pending_attendance: number;
    overall_attendance_rate: number; // Average attendance percentage across all sessions
  };
  
  // Session Overview
  session_overview: {
    total_sessions: number;
    upcoming_sessions: number;
    completed_sessions: number;
    cancelled_sessions: number;
    next_session?: {
      id: number;
      title: string;
      date: string;
      start_time: string;
      end_time: string;
      delivery_mode: string;
    };
  };
  
  // Student Performance Overview
  student_performance: {
    average_grade?: number;
    grade_distribution: Record<string, number>; // {"A": 5, "B": 12, "C": 8, ...}
    at_risk_students: number;
  };
  
  // Health Indicators (computed by CourseDetailResource)
  health_indicators: {
    enrollment_health: 'excellent' | 'good' | 'fair' | 'poor';
    attendance_health: 'excellent' | 'good' | 'fair' | 'poor';
    session_health: 'excellent' | 'good' | 'fair' | 'poor';
    overall_health: 'excellent' | 'good' | 'fair' | 'poor';
  };
  
  // AI-Generated Recommendations
  recommendations: Array<{
    type: 'enrollment' | 'attendance' | 'session';
    priority: 'high' | 'medium' | 'low' | 'info';
    message: string;
    action: 'increase_enrollment' | 'manage_overenrollment' | 'mark_attendance' | 'improve_engagement' | 'prepare_session';
  }>;
}
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "course_offering": {
      "id": 1,
      "section_code": "COS10009_S1_01",
      "delivery_mode": "in_person",
      "location": "Building A, Room 101",
      "max_capacity": 30,
      "current_enrollment": 28,
      "enrollment_status": "open",
      "schedule_days": ["Monday", "Wednesday"],
      "schedule_time_start": "09:00",
      "schedule_time_end": "10:30",
      "curriculum_unit": {
        "id": 1,
        "unit_code": "COS10009",
        "unit_name": "Introduction to Programming",
        "credit_hours": 12.5,
        "description": "Foundational programming concepts and problem-solving techniques"
      },
      "semester": {
        "id": 1,
        "name": "Semester 1, 2024",
        "code": "2024S1",
        "start_date": "2024-02-26",
        "end_date": "2024-06-21"
      }
    },
    "enrollment_statistics": {
      "total_registrations": 32,
      "enrolled_students": 28,
      "waitlisted_students": 2,
      "dropped_students": 2,
      "capacity_utilization": 93.3,
      "available_spots": 2
    },
    "attendance_statistics": {
      "total_sessions": 24,
      "completed_sessions": 18,
      "sessions_with_attendance": 16,
      "pending_attendance": 2,
      "overall_attendance_rate": 84.5
    },
    "session_overview": {
      "total_sessions": 24,
      "upcoming_sessions": 6,
      "completed_sessions": 18,
      "cancelled_sessions": 0,
      "next_session": {
        "id": 46,
        "title": "Week 10: Data Structures",
        "date": "2024-04-17",
        "start_time": "09:00",
        "end_time": "10:30",
        "delivery_mode": "in_person"
      }
    },
    "student_performance": {
      "average_grade": null,
      "grade_distribution": {},
      "at_risk_students": 0
    },
    "health_indicators": {
      "enrollment_health": "excellent",
      "attendance_health": "good",
      "session_health": "fair",
      "overall_health": "good"
    },
    "recommendations": [
      {
        "type": "attendance",
        "priority": "high",
        "message": "You have 2 sessions with unmarked attendance",
        "action": "mark_attendance"
      },
      {
        "type": "session",
        "priority": "info",
        "message": "You have a session today: Week 10: Data Structures at 09:00",
        "action": "prepare_session"
      }
    ]
  },
  "message": "Course details retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 📊 Course Statistics

### Get Course Statistics
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/statistics
```

#### Response
```typescript
interface CourseStatisticsResponse {
  success: true;
  data: CourseStatistics;
  message: string;
  timestamp: string;
}

interface CourseStatistics {
  enrollment_stats: {
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
  
  attendance_stats: {
    total_sessions: number;
    completed_sessions: number;
    sessions_with_attendance: number;
    pending_attendance: number;
    overall_attendance_rate: number;
  };
  
  session_stats: {
    total_sessions: number;
    upcoming_sessions: number;
    completed_sessions: number;
    cancelled_sessions: number;
    next_session?: {
      id: number;
      title: string;
      date: string;
      start_time: string;
      end_time: string;
      delivery_mode: string;
    };
  };
  
  performance_trends: {
    trend: 'improving' | 'stable' | 'declining';
    change: number; // Percentage change
  };
  
  weekly_breakdown: Array<{
    week: number;
    attendance_rate: number;
    sessions_conducted: number;
  }>;
}
```

---

## 👥 Course Students

### Get Course Students
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/students
```

#### Query Parameters
```typescript
interface StudentFilters {
  search?: string;    // Student ID, name, or email
  status?: 'excellent' | 'good' | 'warning' | 'at_risk';
  academic_standing?: 'good' | 'honors' | 'probation' | 'suspension';
  risk_level?: 'low' | 'medium' | 'high';
  sort_by?: 'name' | 'attendance' | 'last_attendance' | 'grade';
  sort_direction?: 'asc' | 'desc';
}
```

#### Response
```typescript
interface CourseStudentsResponse {
  success: true;
  data: CourseStudent[];
  message: string;
  timestamp: string;
}

interface CourseStudent {
  student_id: number;
  student_number: string;  // Student ID like "12345678"
  full_name: string;
  email: string;
  registration_date: string; // "2024-02-01"
  
  // Course Registration Information
  registration: {
    status: 'confirmed' | 'completed' | 'defer' | 'waitlisted' | 'dropped';
    attempt_number: number;  // 1, 2, 3... for retakes
    is_retake: boolean;
  };

  // Class Roster Status
  // The endpoint includes visible roster history. Only rows with
  // roster.is_active=true are active/markable attendance targets.
  roster: {
    is_active: boolean;
    status: 'active' | 'completed' | 'defer' | 'deferred' | 'dropout' | 'dropout_transfer' | 'inactive' | string;
    status_label: string;
    can_mark_attendance: boolean;
  };
  
  // Academic Scores and Grades
  academics: {
    final_score?: number;    // 0-100 calculated from assessments
    final_grade?: string;    // "HD", "D", "C", "P", "N"
    grade_status: 'provisional' | 'final' | 'pending' | 'submitted' | 'approved';
    grade_status_label: string;
  };
  
  // Attendance Information
  attendance: {
    percentage: number;           // 0-100
    sessions_attended: number;
    total_sessions: number;
    last_attendance?: string;     // "2024-04-10"
    meets_requirement: boolean;   // >= 75% typically
    status: 'excellent' | 'good' | 'warning' | 'at_risk' | 'inactive';
    status_label: string;
    status_color: 'green' | 'blue' | 'yellow' | 'red' | 'gray';
  };
  
  // Academic Standing
  academic_standing: {
    status: 'good' | 'honors' | 'probation' | 'suspension';
    label: string;
    color: string;
  };
  
  // AI Risk Assessment
  risk_assessment: {
    level: 'low' | 'medium' | 'high';
    factors: string[];         // ["Very low attendance rate", "No recent attendance"]
    recommendations: string[]; // ["Contact student immediately", "Schedule meeting"]
  };
  
  // Available Actions
  actions: {
    can_contact: boolean;
    can_add_note: boolean;
    needs_attention: boolean;
    can_view_details: boolean;
  };
}
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "student_id": 123,
      "student_number": "12345678",
      "full_name": "John Smith",
      "email": "john.smith@student.swin.edu.au",
      "registration_date": "2024-02-01",
      "registration": {
        "status": "confirmed",
        "attempt_number": 1,
        "is_retake": false
      },
      "academics": {
        "final_score": 78.5,
        "final_grade": "D",
        "grade_status": "provisional",
        "grade_status_label": "Provisional Grade"
      },
      "attendance": {
        "percentage": 85.2,
        "sessions_attended": 23,
        "total_sessions": 27,
        "last_attendance": "2024-04-10",
        "meets_requirement": true,
        "status": "good",
        "status_label": "Good Attendance",
        "status_color": "blue"
      },
      "academic_standing": {
        "status": "good",
        "label": "Good Standing",
        "color": "green"
      },
      "risk_assessment": {
        "level": "low",
        "factors": [],
        "recommendations": ["Continue monitoring"]
      },
      "actions": {
        "can_contact": true,
        "can_add_note": true,
        "needs_attention": false,
        "can_view_details": true
      }
    }
  ],
  "message": "Course students retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 📅 Course Sessions

### Get Course Sessions
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/sessions
```

#### Response
```typescript
interface CourseSessionsResponse {
  success: true;
  data: CourseSession[];
  message: string;
  timestamp: string;
}

interface CourseSession {
  id: number;
  title: string;
  description?: string;
  session_date: string;         // "2024-04-15"
  start_time: string;           // "09:00"
  end_time: string;             // "10:30"
  duration_minutes: number;
  session_type: 'lecture' | 'tutorial' | 'lab' | 'workshop' | 'seminar';
  delivery_mode: 'online' | 'in_person' | 'hybrid';
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
  attendance_marked: boolean;
  attendance_percentage?: number; // Only if attendance is marked
  expected_attendees: number;
  actual_attendees?: number;      // Only if attendance is marked
  learning_objectives?: string;
  topics_covered?: string;
}
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "id": 45,
      "title": "Week 9: Object-Oriented Programming",
      "description": "Introduction to classes, objects, and inheritance",
      "session_date": "2024-04-15",
      "start_time": "09:00",
      "end_time": "10:30",
      "duration_minutes": 90,
      "session_type": "lecture",
      "delivery_mode": "in_person",
      "status": "completed",
      "attendance_marked": true,
      "attendance_percentage": 89.3,
      "expected_attendees": 28,
      "actual_attendees": 25,
      "learning_objectives": "Understand OOP concepts and implement basic classes",
      "topics_covered": "Classes, Objects, Methods, Inheritance"
    }
  ],
  "message": "Course sessions retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 📈 Course Summary

### Get Course Summary
```typescript
GET /api/v1/lecturer/courses/summary?semester_id={id}
```

#### Query Parameters
```typescript
interface SummaryFilters {
  semester_id?: number; // Filter by specific semester
}
```

#### Response
```typescript
interface CourseSummaryResponse {
  success: true;
  data: CourseSummary;
  message: string;
  timestamp: string;
}

interface CourseSummary {
  total_courses: number;
  active_courses: number;
  total_students: number;
  average_enrollment: number;
  delivery_mode_breakdown: Record<string, number>; // {"in_person": 3, "online": 1, "hybrid": 1}
  capacity_utilization: number; // Overall percentage
}
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "total_courses": 5,
    "active_courses": 5,
    "total_students": 142,
    "average_enrollment": 28.4,
    "delivery_mode_breakdown": {
      "in_person": 3,
      "online": 1,
      "hybrid": 1
    },
    "capacity_utilization": 89.2
  },
  "message": "Course summary retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 🏢 Unit Information

### Get Unit Information
```typescript
GET /api/v1/lecturer/courses/{courseOfferingId}/unit
```

#### Response
```typescript
interface UnitResponse {
  success: true;
  data: Unit;
  message: string;
  timestamp: string;
}

interface Unit {
  id: number;
  code: string;              // "COS10009"
  name: string;              // "Introduction to Programming"
  credit_points: number;     // 12.5
  description?: string;
  prerequisites?: string;
  learning_outcomes?: string;
  assessment_overview?: string;
  created_at: string;
  updated_at: string;
}
```

---

## ⚙️ Filter Options

### Get Filter Options
```typescript
GET /api/v1/lecturer/courses/filter-options
```

#### Response
```typescript
interface FilterOptionsResponse {
  success: true;
  data: FilterOptions;
  message: string;
  timestamp: string;
}

interface FilterOptions {
  semesters: Array<{
    id: number;
    name: string;      // "Semester 1, 2024"
    code: string;      // "2024S1"
    start_date: string;
    end_date: string;
    is_active: boolean;
  }>;
  
  delivery_modes: Array<{
    value: string;     // "in_person"
    label: string;     // "In Person"
  }>;
  
  enrollment_statuses: Array<{
    value: 'open' | 'closed' | 'full';
    label: string;     // "Open for Enrollment"
  }>;
}
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "semesters": [
      {
        "id": 1,
        "name": "Semester 1, 2024",
        "code": "2024S1",
        "start_date": "2024-02-26",
        "end_date": "2024-06-21",
        "is_active": true
      }
    ],
    "delivery_modes": [
      {"value": "in_person", "label": "In Person"},
      {"value": "online", "label": "Online"},
      {"value": "hybrid", "label": "Hybrid"}
    ],
    "enrollment_statuses": [
      {"value": "open", "label": "Open for Enrollment"},
      {"value": "closed", "label": "Enrollment Closed"},
      {"value": "full", "label": "Full Capacity"}
    ]
  },
  "message": "Filter options retrieved successfully",
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 🚨 Error Responses

### Course Not Found
```json
{
  "success": false,
  "message": "Course offering not found or access denied",
  "errors": [
    {
      "code": "NOT_FOUND",
      "field": null,
      "detail": null
    }
  ],
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

### Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "field": "per_page",
      "detail": "The per page must be between 5 and 50"
    }
  ],
  "timestamp": "2024-04-15T16:30:00.000Z"
}
```

---

## 💡 Usage Examples

### TypeScript Implementation
```typescript
// Define the API client
class LecturerCourseAPI {
  private baseUrl = '/api/v1/lecturer/courses';
  
  async getCourses(filters: CourseFilters = {}): Promise<CourseListResponse> {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined) params.append(key, String(value));
    });
    
    const response = await fetch(`${this.baseUrl}?${params}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Accept': 'application/json'
      }
    });
    
    return response.json();
  }
  
  async getCourseDetails(courseId: number): Promise<CourseDetailResponse> {
    const response = await fetch(`${this.baseUrl}/${courseId}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Accept': 'application/json'
      }
    });
    
    return response.json();
  }
  
  async getCourseStudents(
    courseId: number, 
    filters: StudentFilters = {}
  ): Promise<CourseStudentsResponse> {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined) params.append(key, String(value));
    });
    
    const response = await fetch(`${this.baseUrl}/${courseId}/students?${params}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Accept': 'application/json'
      }
    });
    
    return response.json();
  }
}

// Usage in Vue Composition API
import { ref, computed } from 'vue'

export function useCourses() {
  const courses = ref<CourseOffering[]>([])
  const loading = ref(false)
  const courseAPI = new LecturerCourseAPI()
  
  const fetchCourses = async (filters: CourseFilters = {}) => {
    loading.value = true
    try {
      const response = await courseAPI.getCourses(filters)
      courses.value = response.data
    } catch (error) {
      console.error('Failed to fetch courses:', error)
    } finally {
      loading.value = false
    }
  }
  
  const coursesNeedingAttention = computed(() => 
    courses.value.filter(course => course.quick_actions.needs_attention)
  )
  
  return {
    courses,
    loading,
    fetchCourses,
    coursesNeedingAttention
  }
}
```

---

## 🔍 Response Data Analysis

### Health Indicators Logic
- **Enrollment Health**: Based on capacity utilization (90%+ = excellent, 70-89% = good, 50-69% = fair, <50% = poor)
- **Attendance Health**: Based on overall attendance rate (90%+ = excellent, 80-89% = good, 70-79% = fair, <70% = poor)  
- **Session Health**: Based on pending attendance (0 = excellent, 1-2 = good, 3-5 = fair, 6+ = poor)
- **Overall Health**: Average of all health indicators

### Risk Assessment Logic
- **High Risk**: Attendance <60% OR no attendance in last 2 weeks
- **Medium Risk**: Attendance 60-74%
- **Low Risk**: Attendance 75%+

### Recommendation Types
- **Enrollment**: Low/high enrollment issues
- **Attendance**: Unmarked attendance, low rates
- **Session**: Today's sessions, preparation reminders
