# Lecturer Schedule API Documentation

## Overview
API endpoint for retrieving lecturer teaching schedule with flexible date range filtering.

## Authentication
- **Auth**: Bearer token (Sanctum)
- **Middleware**: `lecturer.api.auth`

---

## GET /api/v1/lecturer/timetable/schedule

**Purpose**: Get lecturer's teaching schedule for any date range (supports day, week, month views)

**Query Parameters**:
- `start`: Start date (YYYY-MM-DD, optional, defaults to start of current month)
- `end`: End date (YYYY-MM-DD, optional, defaults to end of current month)
- `course_offering_id`: Filter by course offering ID (integer, optional)
- `status`: Filter by session status (scheduled|completed|cancelled|in_progress, optional)
- `include_cancelled`: Include cancelled sessions (boolean, optional, default: false)

**Examples**:
- Daily: `?start=2025-09-20&end=2025-09-20`
- Weekly: `?start=2025-09-16&end=2025-09-22`
- Monthly: `?start=2025-09-01&end=2025-09-30`
- Custom range: `?start=2025-09-01&end=2025-10-15`

**Response** (200):
```typescript
interface ScheduleResponse {
  success: true;
  message: string;
  data: {
    period: {
      start_date: string;     // YYYY-MM-DD
      end_date: string;       // YYYY-MM-DD  
      total_days: number;
    };
    sessions: Record<string, SessionItem[]>; // Grouped by date (YYYY-MM-DD)
    summary: {
      overview: {
        total_sessions: number;
        completed_sessions: number;
        upcoming_sessions: number;
        cancelled_sessions: number;
        in_progress_sessions: number;
        unique_courses: number;
        total_teaching_hours: number;
        average_sessions_per_day: number;
      };
      schedule_pattern: {
        busiest_day: string;        // Day name with most sessions
        earliest_start: string;     // HH:mm:ss
        latest_end: string;         // HH:mm:ss
        earliest_start_display: string; // e.g. "8:00 AM"
        latest_end_display: string;     // e.g. "6:00 PM"
      };
      distribution: {
        by_day: Record<string, number>;          // Sessions count by day name
        by_session_type: Record<string, number>; // Sessions count by type
      };
    };
    generated_at: string; // ISO timestamp
  };
  timestamp: string;
}

interface SessionItem {
  id: number;
  course_code: string;
  course_name: string;
  section_code: string;
  session_title: string;
  session_type: string;           // lecture|tutorial|lab|seminar|workshop
  session_type_display: string;   // "Lecture", "Tutorial", etc.
  date: string;                   // YYYY-MM-DD
  day_of_week: string;           // "Monday", "Tuesday", etc.
  day_abbreviation: string;       // "Mon", "Tue", etc.
  time: {
    start: string;               // HH:mm:ss
    end: string;                 // HH:mm:ss
    start_display: string;       // e.g. "9:00 AM"
    end_display: string;         // e.g. "10:30 AM"
    display: string;             // e.g. "9:00 AM - 10:30 AM"
    duration_minutes: number;
    duration_display: string;    // e.g. "1h 30m"
  };
  location: {
    room_code: string;
    room_name: string;
    building: string | null;
    full_location: string;       // e.g. "Building A - Room 101"
  } | null;
  delivery_mode: string;         // in_person|online|hybrid
  status: string;                // scheduled|completed|cancelled|in_progress
  status_display: string;        // "Scheduled", "Completed", etc.
  expected_attendees: number;
  attendance_marked: boolean;
  is_today: boolean;
  is_upcoming: boolean;
  is_current: boolean;           // Currently happening
  color: string;                 // Hex color for UI display
}
```

**Example Response**:
```json
{
  "success": true,
  "message": "Schedule retrieved successfully",
  "data": {
    "period": {
      "start_date": "2025-09-01",
      "end_date": "2025-09-30",
      "total_days": 30
    },
    "sessions": {
      "2025-09-01": [
        {
          "id": 123,
          "course_code": "COS10001",
          "course_name": "Introduction to Programming",
          "section_code": "A",
          "session_title": "Variables and Data Types",
          "session_type": "lecture",
          "session_type_display": "Lecture",
          "date": "2025-09-01",
          "day_of_week": "Monday",
          "day_abbreviation": "Mon",
          "time": {
            "start": "09:00:00",
            "end": "10:30:00",
            "start_display": "9:00 AM",
            "end_display": "10:30 AM",
            "display": "9:00 AM - 10:30 AM",
            "duration_minutes": 90,
            "duration_display": "1h 30m"
          },
          "location": {
            "room_code": "A101",
            "room_name": "A101",
            "building": "Building A",
            "full_location": "Building A - A101"
          },
          "delivery_mode": "in_person",
          "status": "scheduled",
          "status_display": "Scheduled",
          "expected_attendees": 25,
          "attendance_marked": false,
          "is_today": false,
          "is_upcoming": true,
          "is_current": false,
          "color": "#3B82F6"
        }
      ],
      "2025-09-02": [
        // More sessions...
      ]
    },
    "summary": {
      "overview": {
        "total_sessions": 45,
        "completed_sessions": 20,
        "upcoming_sessions": 23,
        "cancelled_sessions": 2,
        "in_progress_sessions": 0,
        "unique_courses": 3,
        "total_teaching_hours": 67.5,
        "average_sessions_per_day": 2.8
      },
      "schedule_pattern": {
        "busiest_day": "Tuesday",
        "earliest_start": "08:00:00",
        "latest_end": "18:00:00",
        "earliest_start_display": "8:00 AM",
        "latest_end_display": "6:00 PM"
      },
      "distribution": {
        "by_day": {
          "Monday": 8,
          "Tuesday": 12,
          "Wednesday": 10,
          "Thursday": 8,
          "Friday": 7
        },
        "by_session_type": {
          "lecture": 20,
          "tutorial": 15,
          "lab": 10
        }
      }
    },
    "generated_at": "2025-09-20T12:00:00.000000Z"
  },
  "timestamp": "2025-09-20T12:00:00.000000Z"
}
```

---

## Usage Examples

### Frontend Calendar Integration
The API response is optimized for calendar/schedule views:

```typescript
// Daily view
const dailySchedule = await api.get('/lecturer/timetable/schedule', {
  params: { start: '2025-09-20', end: '2025-09-20' }
});

// Weekly view
const weeklySchedule = await api.get('/lecturer/timetable/schedule', {
  params: { start: '2025-09-16', end: '2025-09-22' }
});

// Monthly view  
const monthlySchedule = await api.get('/lecturer/timetable/schedule', {
  params: { start: '2025-09-01', end: '2025-09-30' }
});

// Custom range with filters
const filteredSchedule = await api.get('/lecturer/timetable/schedule', {
  params: { 
    start: '2025-09-01', 
    end: '2025-10-15',
    course_offering_id: 123,
    status: 'scheduled'
  }
});
```

### Session Colors
Sessions include a `color` field for consistent UI styling:
- **Lecture**: `#3B82F6` (blue)
- **Tutorial**: `#8B5CF6` (purple)
- **Lab**: `#F59E0B` (amber)
- **Seminar**: `#EF4444` (red)
- **Workshop**: `#06B6D4` (cyan)
- **Completed**: `#10B981` (green)
- **Cancelled**: `#9CA3AF` (gray)

---

## Error Responses
Standard error format:
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
- `VALIDATION_ERROR` (400): Invalid date format or parameters
- `SERVER_ERROR` (500): Internal server error

---

## Notes
- Default date range is current month if no dates provided
- Sessions are grouped by date for easy calendar rendering
- All times include both 24-hour and 12-hour display formats
- Summary statistics provide overview for dashboard widgets
- Response is cached for 5 minutes for performance
- The `is_current` flag indicates if a session is happening right now