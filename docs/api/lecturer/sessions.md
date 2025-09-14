# Lecturer Course Sessions API

## Get Course Sessions

**Endpoint:** `GET /api/v1/lecturer/{courseOffering}/sessions`

**Description:** Retrieves all class sessions for a specific course offering that the authenticated lecturer has access to, ordered by session date.

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | Course offering ID |

### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Introduction to Programming Concepts",
      "description": "Basic programming fundamentals and syntax",
      "session_date": "2024-03-15",
      "start_time": "09:00",
      "end_time": "11:00",
      "duration_minutes": 120,
      "session_type": "lecture",
      "delivery_mode": "on_campus",
      "status": "scheduled",
      "attendance_marked": false,
      "attendance_percentage": null,
      "expected_attendees": 25,
      "actual_attendees": null,
      "learning_objectives": [
        "Understand basic programming concepts",
        "Learn variable declaration and usage"
      ],
      "topics_covered": [
        "Variables and data types",
        "Basic operators",
        "Control structures"
      ]
    },
    {
      "id": 2,
      "title": "Data Structures and Algorithms",
      "description": "Arrays, lists, and basic sorting algorithms",
      "session_date": "2024-03-18",
      "start_time": "09:00",
      "end_time": "11:00",
      "duration_minutes": 120,
      "session_type": "practical",
      "delivery_mode": "on_campus",
      "status": "completed",
      "attendance_marked": true,
      "attendance_percentage": 92.0,
      "expected_attendees": 25,
      "actual_attendees": 23,
      "learning_objectives": [
        "Implement basic data structures",
        "Apply sorting algorithms"
      ],
      "topics_covered": [
        "Arrays and ArrayLists",
        "Bubble sort algorithm",
        "Linear search"
      ]
    }
  ],
  "message": "Course sessions retrieved successfully"
}
```

**Empty Response (200 OK):**
```json
{
  "data": [],
  "message": "Course sessions retrieved successfully"
}
```

**Error Response (500 Internal Server Error):**

```json
{
  "message": "Failed to retrieve course sessions"
}
```

### Authentication

- Requires valid Sanctum authentication token
- User must have lecturer role
- Access restricted to course offerings assigned to the lecturer
- Returns empty array if course offering not found or access denied

### TypeScript Types

```typescript
interface CourseSessionsResponse {
  data: CourseSession[];
  message: string;
}

interface CourseSession {
  id: number;
  title: string;
  description: string | null;
  session_date: string; // YYYY-MM-DD format
  start_time: string; // HH:mm format
  end_time: string; // HH:mm format
  duration_minutes: number;
  session_type: SessionType;
  delivery_mode: DeliveryMode;
  status: SessionStatus;
  attendance_marked: boolean;
  attendance_percentage: number | null;
  expected_attendees: number | null;
  actual_attendees: number | null;
  learning_objectives: string[] | null;
  topics_covered: string[] | null;
}

// Enums for session types
type SessionType = 
  | 'lecture' 
  | 'practical' 
  | 'tutorial' 
  | 'seminar' 
  | 'workshop';

type DeliveryMode = 
  | 'on_campus' 
  | 'online' 
  | 'hybrid';

type SessionStatus = 
  | 'scheduled' 
  | 'in_progress' 
  | 'completed' 
  | 'cancelled' 
  | 'postponed';

// Error response type
interface ApiError {
  message: string;
}
```

### Usage Example

```typescript
// Fetch sessions for course offering
const fetchCourseSessions = async (courseOfferingId: number): Promise<CourseSessionsResponse> => {
  const response = await fetch(`/api/v1/lecturer/${courseOfferingId}/sessions`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch course sessions');
  }
  
  return response.json();
};

// Usage with error handling
try {
  const sessionsData = await fetchCourseSessions(123);
  console.log(`Found ${sessionsData.data.length} sessions`);
} catch (error) {
  console.error('Error fetching sessions:', error);
}
```

### Notes

- Sessions are ordered by `session_date` in ascending order
- Attendance data (`attendance_percentage`, `actual_attendees`) is only available after attendance has been marked
- Learning objectives and topics covered are stored as JSON arrays
- Duration is automatically calculated but also stored for quick access
- Empty arrays are returned for courses with no access or non-existent course offerings
- All timestamps follow consistent formatting (dates: YYYY-MM-DD, times: HH:mm)
