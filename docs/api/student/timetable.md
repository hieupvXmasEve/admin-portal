# Student Timetable API Documentation

## Authentication
All endpoints require Bearer token (Sanctum) authentication.

---

## GET /api/v1/student/timetable

**Purpose**: Get student's complete timetable

**Query Parameters** (all optional):
- `semester_id`: Semester ID (integer, must exist in semesters table)
- `day_of_week`: Filter by day (monday|tuesday|wednesday|thursday|friday|saturday|sunday)
- `session_type`: Filter by session type (string, max 50 chars)
- `lecturer_name`: Filter by lecturer name (string, max 255 chars)
- `building`: Filter by building name (string, max 100 chars)
- `room_code`: Filter by room code (string, max 20 chars)
- `course_code`: Filter by course code (string, max 20 chars)
- `start_time_after`: Show sessions starting after time (HH:MM format)
- `end_time_before`: Show sessions ending before time (HH:MM format)
- `time_range`: Time range object with start/end properties
  - `time_range[start]`: Start time (HH:MM format, required if time_range provided)
  - `time_range[end]`: End time (HH:MM format, required if time_range provided)

**Response** (200):
```typescript
interface TimetableResponse {
  success: true;
  message: string;
  data: {
    semester: {
      id: number;
      name: string;
      code: string;
      start_date: string; // YYYY-MM-DD
      end_date: string;   // YYYY-MM-DD
    };
    weekly_schedule: {
      [day: string]: { // monday, tuesday, etc.
        day_name: string;
        day_abbreviation: string;
        session_count: number;
        sessions: SessionItem[];
        total_duration: {
          total_minutes: number;
          display: string; // e.g. "3h 30m"
        };
      };
    };
    schedule_summary: {
      overview: {
        total_sessions_per_week: number;
        unique_courses: number;
        total_hours_per_week: number;
        average_hours_per_day: number;
      };
      schedule_pattern: {
        busiest_day: string;
        earliest_start: string;     // HH:mm:ss
        latest_end: string;         // HH:mm:ss
        earliest_start_display: string; // e.g. "8:00 AM"
        latest_end_display: string;     // e.g. "6:00 PM"
      };
      distribution: {
        by_day: Record<string, number>;
        by_session_type: Record<string, number>;
      };
    };
    time_blocks: TimeBlock[];
    filters_applied: object;
    generated_at: string; // ISO timestamp
  };
  timestamp: string;
}

interface SessionItem {
  id: number;
  course_code: string;
  course_name: string;
  session_type: string;
  session_type_display: string;
  time: {
    start: string;     // HH:mm:ss
    end: string;       // HH:mm:ss
    display: string;   // e.g. "9:00 AM - 10:30 AM"
    duration_minutes: number;
    duration_display: string; // e.g. "1h 30m"
  };
  lecturer: {
    name: string;
    email: string;
  };
  room: {
    code: string;
    name: string;
    building: string | null;
    full_location: string;
  };
  color: string;
  is_current: boolean;
  is_upcoming: boolean;
}

interface TimeBlock {
  time: string;         // HH:mm
  display_time: string; // e.g. "9:00 AM"
  sessions: Record<string, SessionItem[]>; // Sessions by day
  has_sessions: boolean;
}
```

---

## GET /api/v1/student/timetable/weekly

**Purpose**: Get weekly timetable view (simplified version)

**Query Parameters**: Same as `/timetable` endpoint

**Response** (200):
```typescript
interface WeeklyTimetableResponse {
  success: true;
  message: string;
  data: {
    semester: {
      id: number;
      name: string;
      code: string;
      start_date: string;
      end_date: string;
    };
    weekly_schedule: {
      [day: string]: {
        day_name: string;
        day_abbreviation: string;
        session_count: number;
        sessions: SessionItem[];
        total_duration: {
          total_minutes: number;
          display: string;
        };
      };
    };
    schedule_summary: {
      overview: {
        total_sessions_per_week: number;
        unique_courses: number;
        total_hours_per_week: number;
        average_hours_per_day: number;
      };
      schedule_pattern: {
        busiest_day: string;
        earliest_start: string;
        latest_end: string;
        earliest_start_display: string;
        latest_end_display: string;
      };
      distribution: {
        by_day: Record<string, number>;
        by_session_type: Record<string, number>;
      };
    };
  };
  timestamp: string;
}
```

---

## GET /api/v1/student/timetable/class-session/{classSession}

**Purpose**: Get detailed information about a specific class session

**Path Parameters**:
- `classSession`: Class session ID (integer)

**Response** (200):
```typescript
interface ClassSessionDetailResponse {
  success: true;
  message: string;
  data: {
    session: {
      id: number;
      day_of_week: string;
      day_display: string;     // e.g. "Monday"
      start_time: string;      // HH:mm:ss
      end_time: string;        // HH:mm:ss
      time_display: string;    // e.g. "9:00 AM - 10:30 AM"
      session_type: string;
      session_type_display: string;
      duration_minutes: number;
      duration_display: string; // e.g. "1h 30m"
    };
    course: {
      code: string;
      name: string;
      credit_hours: number;
      full_title: string; // e.g. "CS101 - Introduction to Computer Science"
    };
    lecturer: {
      id: number;
      name: string;
      email: string;
      phone: string | null;
      contact_info: ContactInfo[];
    } | null;
    room: {
      id: number;
      code: string;
      name: string;
      building: string | null;
      capacity: number;
      facilities: string[] | null;
      full_location: string;
    } | null;
    attendance_info: {
      summary: {
        total_sessions: number;
        attended_sessions: number;
        attendance_percentage: number;
        attendance_status: string; // excellent|good|satisfactory|warning|critical
      };
      recent_attendance: AttendanceRecord[];
    };
    upcoming_sessions: UpcomingSession[];
    session_status: {
      status: string; // upcoming_today|in_progress|completed_today|scheduled
      display: string;
      minutes_until?: number;
      time_until_display?: string;
      minutes_remaining?: number;
      time_remaining_display?: string;
      next_occurrence?: string;
    };
  };
  timestamp: string;
}

interface ContactInfo {
  type: string;    // email|phone
  value: string;
  display: string; // e.g. "Email: john@example.com"
}

interface AttendanceRecord {
  date: string | null;
  status: string;         // present|absent|late|excused|unknown
  status_display: string; // Present|Absent|Late|Excused|Unknown
}

interface UpcomingSession {
  date: string | null;
  day_of_week: string | null;
  start_time: string | null;
  end_time: string | null;
  time_display: string | null;
}
```

---

## GET /api/v1/student/timetable/filter-options

**Purpose**: Get available filter options for the student's timetable

**Query Parameters**:
- `semester_id`: Semester ID (integer, optional)

**Response** (200):
```typescript
interface FilterOptionsResponse {
  success: true;
  message: string;
  data: {
    days_of_week: string[];    // Available days: ["monday", "tuesday", ...]
    session_types: string[];   // Available session types: ["lecture", "tutorial", ...]
    lecturers: LecturerOption[];
    buildings: string[];       // Available buildings
    time_slots: TimeSlot[];   // Available time slots
  };
  timestamp: string;
}

interface LecturerOption {
  id: number;
  name: string;
  email: string;
}

interface TimeSlot {
  start_time: string; // HH:mm:ss
  end_time: string;   // HH:mm:ss
  display: string;    // e.g. "9:00 AM - 10:30 AM"
}
```

---

## Error Responses

All endpoints return standard error format:
```typescript
interface ErrorResponse {
  success: false;
  message: string;
  error_code: string;
  errors?: Record<string, string[]>;
  timestamp: string;
}
```

**Common Error Codes**:
- `VALIDATION_ERROR` (400): Invalid query parameters
- `NOT_FOUND` (404): Class session not found or student not enrolled
- `SERVER_ERROR` (500): Internal server error

**Example Error** (400):
```json
{
  "success": false,
  "message": "Invalid timetable filter parameters",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "semester_id": ["The selected semester does not exist"],
    "day_of_week": ["Day of week must be a valid day (monday-sunday)"]
  },
  "timestamp": "2025-08-18T15:42:42Z"
}
```
