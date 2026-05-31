## Lecturer Attendance API (FE Next.js)

Simple documentation for FE to integrate lecturer attendance endpoints. All endpoints require headers:

```
Authorization: Bearer {token}
Content-Type: application/json
```

Base path: `/api/v1/lecturer/attendance`

### General notes
- Success shape: `{ data, message? }` or paginated: `{ data, meta, links }` per project `ApiResponse`.
- Date/time follow ISO or sample formatting in examples.
- Nullable fields may appear when data is not available (e.g., `attendance_percentage`).
- Lecturer attendance rosters return the full confirmed class roster. Students who entered a class and later move to DE states (`deferred`, `dropout`, `dropout_transfer`, or `inactive`) remain in `students` with inactive roster metadata, but they are excluded from active attendance counts across EGC and Major classes. Historical registrations and attendance records are retained.

---

## 1) Attendance sessions (overview)

GET `/`

Optional query:
- `per_page` (number, 5-50)
- `course_offering_id` (number)
- `semester_id` (number)
- `status` (string)
- `attendance_status` (`marked | unmarked`)
- `date_from` (YYYY-MM-DD)
- `date_to` (YYYY-MM-DD)

Example Response (200):
```json
{
  "data": [
    {
      "id": 123,
      "session_date": "2024-05-10",
      "start_time": "09:00",
      "end_time": "11:00",
      "status": "completed",
      "session_type": "lecture",
      "delivery_mode": "on_campus",
      "attendance_marked": true,
      "attendance_percentage": 92.5,
      "expected_attendees": 30,
      "actual_attendees": 28,
      "courseOffering": {
        "id": 45,
        "curriculumUnit": { "unit_code": "COS10009", "unit_name": "Introduction to Programming" },
        "semester": { "name": "S1 2024" }
      }
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 20 },
  "links": { "next": null, "prev": null }
}
```

TypeScript suggestion:
```typescript
export interface AttendanceSessionItem {
  id: number;
  session_date: string;
  start_time: string;
  end_time: string;
  status: string;
  session_type: string;
  delivery_mode: string;
  attendance_marked: boolean;
  attendance_percentage: number | null;
  expected_attendees: number | null;
  actual_attendees: number | null;
  courseOffering: {
    id: number;
    curriculumUnit: { unit_code: string; unit_name?: string };
    semester?: { name: string } | null;
  };
}
```

---

## 2) Sessions requiring attention (unmarked)

GET `/sessions-requiring-attention`

Response (200):
```json
{
  "data": [
    {
      "id": 321,
      "session_date": "2024-05-02",
      "attendance_marked": false,
      "courseOffering": { "curriculumUnit": { "unit_code": "COS20007" } }
    }
  ]
}
```

---

## 3) Session attendance details

GET `/sessions/{sessionId}`

The `students` list includes active and inactive class-roster students. DE/inactive rows include `is_roster_active: false`, `can_mark_attendance: false`, and a `roster_status`/`roster_status_label` for UI badges. `summary.total_enrolled`, `expected_attendees`, `actual_attendees`, attendance counts, and rates are calculated from active roster students only.

Response (200):
```json
{
  "data": {
    "session": {
      "id": 123,
      "title": "Week 3 Lecture",
      "date": "2024-05-10",
      "start_time": "09:00",
      "end_time": "11:00",
      "status": "completed",
      "session_type": "lecture",
      "delivery_mode": "on_campus",
      "attendance_required": true,
      "attendance_marked": true,
      "expected_attendees": 30,
      "actual_attendees": 28,
      "course": {
        "id": 45,
        "unit_code": "COS10009",
        "unit_name": "Introduction to Programming",
        "section_code": "P01",
        "semester": "S1 2024"
      }
    },
    "students": [
      {
        "student_id": 1001,
        "student_number": "S1234567",
        "full_name": "Nguyen Van A",
        "email": "a@example.com",
        "phone": "0123456789",
        "is_roster_active": true,
        "roster_status": "active",
        "roster_status_label": "Active",
        "can_mark_attendance": true,
        "roster": {
          "is_active": true,
          "status": "active",
          "status_label": "Active",
          "can_mark_attendance": true
        },
        "attendance": {
          "id": 555,
          "status": "present",
          "check_in_time": "2024-05-10 09:01:00",
          "check_out_time": null,
          "minutes_late": 1,
          "minutes_present": 118,
          "participation_level": null,
          "participation_score": null,
          "notes": null,
          "excuse_reason": null,
          "recording_method": "manual",
          "is_verified": false,
          "recorded_by": "Lecturer Name",
          "recorded_at": "2024-05-10 11:05:00"
        },
        "student_info": {
          "program": "IT",
          "specialization": null,
          "academic_status": "active",
          "status": "studying",
          "campus": "Hanoi",
          "admission_date": "2022-10-01",
          "expected_graduation_date": "2026-06-30"
        }
      }
    ],
    "summary": {
      "total_enrolled": 30,
      "total_roster": 32,
      "active_roster": 30,
      "inactive_roster": 2,
      "attendance_counts": {
        "present": 26,
        "absent": 2,
        "late": 2,
        "excused": 0,
        "not_marked": 0
      },
      "attendance_rate": 93.3,
      "completion_rate": 100
    }
  }
}
```

---

## 4) Mark attendance for a session

POST `/sessions/{sessionId}/mark`

Requests containing a DE/inactive student are accepted for the active students and return a per-student error for the inactive roster entry: `Student is not active in this class roster`. The lecturer portal should not submit rows with `can_mark_attendance: false`.

Body:
```json
{
  "attendance_data": [
    { "student_id": 1001, "status": "present", "check_in_time": "2024-05-10 09:01:00", "minutes_late": 1 },
    { "student_id": 1002, "status": "absent" }
  ]
}
```

Response (200):
```json
{
  "data": {
    "session_id": 123,
    "total_marked": 2,
    "total_errors": 0,
    "results": [
      { "student_id": 1001, "status": "success", "attendance_id": 555 },
      { "student_id": 1002, "status": "success", "attendance_id": 556 }
    ],
    "errors": []
  }
}
```

---

## 5) Bulk mark attendance (multiple sessions)

POST `/bulk-mark`

Body:
```json
{
  "sessions": [
    {
      "session_id": 123,
      "attendance_data": [
        { "student_id": 1001, "status": "present" }
      ]
    },
    {
      "session_id": 124,
      "attendance_data": [
        { "student_id": 1002, "status": "late", "minutes_late": 10 }
      ]
    }
  ]
}
```

Response (200):
```json
{
  "data": {
    "total_sessions_processed": 2,
    "total_attendance_marked": 2,
    "total_errors": 0,
    "results": [
      { "session_id": 123, "total_marked": 1, "total_errors": 0 },
      { "session_id": 124, "total_marked": 1, "total_errors": 0 }
    ]
  }
}
```

---

## 6) Course attendance analytics

GET `/courses/{courseOfferingId}/analytics`

Optional query: `date_from`, `date_to`, `student_id`

Response (200):
```json
{
  "data": {
    "course_info": {
      "id": 45,
      "unit_code": "COS10009",
      "unit_name": "Introduction to Programming",
      "section_code": "P01",
      "semester": "S1 2024",
      "enrollment": 30
    },
    "overall_statistics": { "overall_rate": 85.5, "total_sessions": 12, "sessions_with_attendance": 10 },
    "session_breakdown": [],
    "student_breakdown": [],
    "trends": [],
    "alerts": []
  }
}
```

---

## 7) Attendance alerts

GET `/alerts`

Response (200):
```json
{
  "data": [
    {
      "type": "unmarked_attendance",
      "priority": "high",
      "session_id": 123,
      "message": "Attendance not marked for COS10009 on May 10",
      "course": "COS10009",
      "date": "2024-05-10",
      "days_overdue": 2
    }
  ]
}
```

---

## 8) Export attendance data

GET `/courses/{courseOfferingId}/export?format=csv|excel|pdf`

Response (200):
```json
{
  "data": {
    "course_info": { "id": 45, "unit_code": "COS10009", "unit_name": "Introduction to Programming", "section_code": "P01", "semester": "S1 2024", "enrollment": 30 },
    "export_format": "csv",
    "data": [],
    "generated_at": "2024-05-10T04:10:00.000Z",
    "generated_by": "Lecturer Name"
  }
}
```

---

## 9) Generate default attendance records for a session

POST `/sessions/{sessionId}/generate`

Purpose: Create default `absent` records for all active class-roster students if not existing. DE/inactive students are skipped.

Response (200):
```json
{
  "data": {
    "session_id": 123,
    "total_records_created": 30,
    "existing_records": 0,
    "message": "Attendance records generated successfully"
  }
}
```

Note: Implemented by `generateAttendanceRecords`. Route could be `/sessions/{sessionId}/generate` if declared. If not, it must be added on BE.

---

## 10) Dashboard summary

GET `/summary?semester_id={id}`

Response (200):
```json
{
  "data": {
    "total_sessions": 20,
    "sessions_with_attendance": 15,
    "pending_sessions": 3,
    "completion_rate": 75.0,
    "recent_sessions": [
      { "id": 123, "course": "COS10009", "date": "2024-05-10", "attendance_marked": true, "attendance_percentage": 92.5 }
    ]
  }
}
```

---

## Next.js usage examples

```typescript
// utils/fetcher.ts
export async function apiGet<T>(url: string, token: string): Promise<T> {
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` },
    cache: 'no-store'
  });
  if (!res.ok) throw new Error(await res.text());
  return res.json();
}

// app/(lecturer)/attendance/page.tsx - example to fetch overview
import { apiGet } from '@/utils/fetcher';

export default async function AttendancePage() {
  const token = ''; // get from cookies/session
  const data = await apiGet<{ data: AttendanceSessionItem[]; meta: any }>(
    '/api/v1/lecturer/attendance?per_page=20',
    token
  );

  return (
    <div>
      <h1>Attendance Sessions</h1>
      <ul>
        {data.data.map((s) => (
          <li key={s.id}>
            {s.courseOffering.curriculumUnit.unit_code} - {s.session_date} - {s.attendance_percentage ?? '-'}%
          </li>
        ))}
      </ul>
    </div>
  );
}
```

---

## Error Handling

Common error responses. Exact format may vary by `ApiResponse` helper.

- 400 Bad Request (invalid query/body):
```json
{ "message": "Invalid request" }
```

- 401 Unauthorized (missing/invalid token):
```json
{ "message": "Unauthenticated." }
```

- 403 Forbidden (no permission to access resource):
```json
{ "message": "Forbidden." }
```

- 404 Not Found (resource not found or access denied):
```json
{ "message": "Session not found or access denied" }
```

- 422 Validation Error (Laravel validation errors):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "attendance_data.0.status": [
      "The selected attendance_data.0.status is invalid."
    ]
  }
}
```

- 429 Too Many Requests (rate limit if enabled):
```json
{ "message": "Too many requests." }
```

- 500 Internal Server Error:
```json
{ "message": "Server error" }
```

Error cases per endpoint:
- `GET /attendance/sessions/{session}`: 404 if session not found or not owned by lecturer.
- `POST /attendance/sessions/{session}/mark`: 404 for missing session; 422 for invalid `status` or payload shape.
- `POST /attendance/bulk-mark`: 422 if `sessions` empty/too many or inner items invalid.
- `GET /attendance/courses/{courseOffering}/analytics|export`: 404 if offering not found.

## Quick mapping Endpoint ⇄ Controller
- `GET /attendance` → `AttendanceController@index`
- `GET /attendance/sessions-requiring-attention` → `AttendanceController@sessionsRequiringAttention`
- `GET /attendance/sessions/{session}` → `AttendanceController@sessionAttendance`
- `POST /attendance/sessions/{session}/mark` → `AttendanceController@markAttendance`
- `POST /attendance/bulk-mark` → `AttendanceController@bulkMarkAttendance`
- `GET /attendance/courses/{courseOffering}/analytics` → `AttendanceController@courseAnalytics`
- `GET /attendance/alerts` → `AttendanceController@alerts`
- `GET /attendance/courses/{courseOffering}/export` → `AttendanceController@exportAttendance`
- `POST /attendance/sessions/{session}/generate` → `AttendanceController@generateAttendance` (nếu route được khai báo)
- `GET /attendance/summary` → `AttendanceController@summary`
