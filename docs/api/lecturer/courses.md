# Lecturer Courses API

## Get Lecturer's Course Offerings

**Endpoint:** `GET /api/v1/lecturer/courses`

**Description:** Retrieves a paginated list of course offerings assigned to the authenticated lecturer with filtering capabilities.

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `per_page` | integer | No | Items per page (5-50, default: 15) |
| `page` | integer | No | Page number (default: 1) |
| Plus any filters from CourseFilterRequest |

### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "id": 1,
      "section_code": "SEC001",
      "delivery_mode": "on_campus",
      "location": "Building A, Room 101",
      "max_capacity": 30,
      "current_enrollment": 25,
      "enrollment_status": "open",
      "schedule_days": ["Monday", "Wednesday"],
      "schedule_time_start": "09:00",
      "schedule_time_end": "11:00",
      "curriculum_unit": {
        "id": 1,
        "code": "ICT101",
        "name": "Introduction to Programming",
        "credit_hours": 3,
        "description": "Basic programming concepts and practices"
      },
      "semester": {
        "id": 1,
        "name": "Semester 1",
        "code": "2024-1",
        "start_date": "2024-02-01",
        "end_date": "2024-06-30",
        "is_active": true
      },
      "enrollment_stats": {
        "enrolled_count": 25,
        "capacity_utilization": 83.3,
        "available_spots": 5,
        "is_full": false
      },
      "session_stats": {
        "total_sessions": 12,
        "completed_sessions": 8,
        "upcoming_sessions": 4,
        "sessions_with_attendance": 6,
        "pending_attendance": 2
      },
      "recent_sessions": [
        {
          "id": 45,
          "title": "Week 8 - Data Structures",
          "date": "2024-03-20",
          "start_time": "09:00",
          "status": "completed",
          "attendance_marked": true
        }
      ],
      "quick_actions": {
        "can_mark_attendance": true,
        "has_upcoming_sessions": true,
        "needs_attention": false
      },
      "created_at": "2024-01-15T08:30:00.000000Z",
      "updated_at": "2024-03-20T14:25:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/lecturer/courses?page=1",
    "last": "http://localhost:8000/api/v1/lecturer/courses?page=3",
    "prev": null,
    "next": "http://localhost:8000/api/v1/lecturer/courses?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "path": "http://localhost:8000/api/v1/lecturer/courses",
    "per_page": 15,
    "to": 15,
    "total": 42
  },
  "message": "Course offerings retrieved successfully"
}
```

**Error Response (500 Internal Server Error):**

```json
{
  "message": "Failed to retrieve course offerings"
}
```

### Authentication

- Requires valid Sanctum authentication token
- User must have lecturer role
- Access restricted to lecturer's own course offerings only

### TypeScript Types

```typescript
interface LecturerCourseOfferingResponse {
  data: CourseOffering[];
  links: PaginationLinks;
  meta: PaginationMeta;
  message: string;
}

interface CourseOffering {
  id: number;
  section_code: string;
  delivery_mode: string;
  location: string;
  max_capacity: number;
  current_enrollment: number;
  enrollment_status: string;
  schedule_days: string[];
  schedule_time_start: string;
  schedule_time_end: string;
  curriculum_unit: CurriculumUnit;
  semester: Semester;
  enrollment_stats: EnrollmentStats;
  session_stats: SessionStats;
  recent_sessions: RecentSession[];
  quick_actions: QuickActions;
  created_at: string;
  updated_at: string;
}

interface CurriculumUnit {
  id: number;
  code: string;
  name: string;
  credit_hours: number;
  description: string;
}

interface Semester {
  id: number;
  name: string;
  code: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

interface EnrollmentStats {
  enrolled_count: number;
  capacity_utilization: number;
  available_spots: number;
  is_full: boolean;
}

interface SessionStats {
  total_sessions: number;
  completed_sessions: number;
  upcoming_sessions: number;
  sessions_with_attendance: number;
  pending_attendance: number;
}

interface RecentSession {
  id: number;
  title: string;
  date: string;
  start_time: string;
  status: string;
  attendance_marked: boolean;
}

interface QuickActions {
  can_mark_attendance: boolean;
  has_upcoming_sessions: boolean;
  needs_attention: boolean;
}

interface PaginationLinks {
  first: string;
  last: string;
  prev: string | null;
  next: string | null;
}

interface PaginationMeta {
  current_page: number;
  from: number;
  last_page: number;
  path: string;
  per_page: number;
  to: number;
  total: number;
}

// Error response type
interface ApiError {
  message: string;
}
```

### Notes

- Response includes pagination metadata for navigation
- Course data includes enrollment statistics and session summaries
- Quick actions indicate available lecturer operations
- Timestamps are in ISO 8601 format
