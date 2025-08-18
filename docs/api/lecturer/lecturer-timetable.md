# Lecturer Timetable API Documentation

## Overview
API endpoints for managing lecturer timetables, sessions, and scheduling.

## Authentication
All endpoints require:
- **Auth**: Bearer token (Sanctum)
- **Middleware**: `lecturer.api.auth`

---

## GET /api/v1/lecturer/timetable

**Purpose**: Get lecturer's timetable with filtering options

**Query Parameters**:
- `start_date`: Start date (YYYY-MM-DD, optional, defaults to start of current week)
- `end_date`: End date (YYYY-MM-DD, optional, defaults to end of current week)
- `view`: View type (day|week|month, optional, default: week)
- `course_offering_id`: Filter by course offering ID (integer, optional)
- `status`: Filter by session status (scheduled|completed|cancelled|in_progress, optional)
- `include_cancelled`: Include cancelled sessions (boolean, optional)

**Response** (200):
```typescript
interface TimetableResponse {
  success: true;
  message: string;
  data: {
    period: {
      start_date: string; // YYYY-MM-DD
      end_date: string;   // YYYY-MM-DD
      view: string;       // day|week|month
    };
    sessions: Record<string, SessionItem[]>; // Grouped by date
    summary: {
      total_sessions: number;
      completed_sessions: number;
      upcoming_sessions: number;
      cancelled_sessions: number;
      total_teaching_hours: number;
    };
    conflicts: ConflictItem[];
    availability: AvailabilitySlot[];
  };
  timestamp: string;
}

interface SessionItem {
  id: number;
  course_code: string;
  course_name: string;
  session_title: string;
  session_type: string;      // lecture|tutorial|lab|seminar
  date: string;              // YYYY-MM-DD
  start_time: string;        // HH:mm:ss
  end_time: string;          // HH:mm:ss
  duration_minutes: number;
  location: string | null;
  delivery_mode: string;     // in_person|online|hybrid
  status: string;            // scheduled|completed|cancelled|in_progress
  expected_attendees: number;
  attendance_marked: boolean;
}
```

---

## GET /api/v1/lecturer/timetable/summary

**Purpose**: Get timetable summary with key metrics

**Query Parameters**:
- `start_date`: Start date (YYYY-MM-DD, optional)
- `end_date`: End date (YYYY-MM-DD, optional)

**Response** (200):
```typescript
interface TimetableSummaryResponse {
  success: true;
  message: string;
  data: {
    period: {
      start_date: string;
      end_date: string;
      view: string;
    };
    summary: {
      total_sessions: number;
      completed_sessions: number;
      upcoming_sessions: number;
      cancelled_sessions: number;
      total_teaching_hours: number;
    };
    conflicts_count: number;
    has_conflicts: boolean;
    next_session: SessionItem | null;
    today_sessions: SessionItem[];
  };
  timestamp: string;
}
```

---

## GET /api/v1/lecturer/timetable/upcoming-sessions

**Purpose**: Get upcoming sessions for lecturer

**Query Parameters**:
- `days`: Number of days to look ahead (1-30, default: 7)
- `limit`: Maximum sessions to return (1-50, default: 20)

**Response** (200):
```typescript
interface UpcomingSessionsResponse {
  success: true;
  message: string;
  data: SessionItem[];
  timestamp: string;
}
```

---

## GET /api/v1/lecturer/timetable/available-rooms

**Purpose**: Get available rooms for a specific time slot

**Query Parameters** (required):
- `date`: Session date (YYYY-MM-DD, must be today or future)
- `start_time`: Start time (HH:mm format)
- `end_time`: End time (HH:mm format, must be after start_time)
- `exclude_session_id`: Session ID to exclude from conflict check (integer, optional)

**Response** (200):
```typescript
interface AvailableRoomsResponse {
  success: true;
  message: string;
  data: RoomItem[];
  timestamp: string;
}

interface RoomItem {
  id: number;
  name: string;
  building: string;
  floor: string | null;
  capacity: number;
  room_type: string;
  equipment: string[] | null;
}
```

---

## GET /api/v1/lecturer/sessions/{sessionId}

**Purpose**: Get detailed information about a specific session

**Response** (200):
```typescript
interface SessionDetailsResponse {
  success: true;
  message: string;
  data: {
    id: number;
    course_code: string;
    course_name: string;
    session_title: string;
    session_description: string | null;
    session_type: string;
    date: string;
    start_time: string;
    end_time: string;
    duration_minutes: number;
    location: string | null;
    delivery_mode: string;
    status: string;
    expected_attendees: number;
    attendance_marked: boolean;
    learning_objectives: string[] | null;
    topics_covered: string[] | null;
    required_materials: string[] | null;
    preparation_notes: string | null;
  };
  timestamp: string;
}
```

---

## Notes

### Route Variations
The endpoints documented above reflect the current implementation. If you're looking for these specific routes:

- `/timetable/weekly` - Use `/timetable` with `view=week` parameter
- `/timetable/class-session/{classSession}` - Use `/sessions/{sessionId}`  
- `/timetable/filter-options` - Filter options are built into the main endpoints

### Error Responses
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

Common error codes:
- `VALIDATION_ERROR` (400): Invalid request parameters
- `NOT_FOUND` (404): Session or resource not found
- `SERVER_ERROR` (500): Internal server error

### Frontend Integration
- Use `TimetableFilterRequest` validation schema on frontend
- Default view is 'week' if not specified
- Dates default to current week if not provided
- Include proper error handling for scheduling conflicts
