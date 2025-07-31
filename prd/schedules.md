## Product Requirements Document: **Admin - Schedule Management**

### 1. **Objective**

Provide administrators with a visual interface to browse and manage class schedules (`class_sessions`) using a grid-based layout with drag-and-drop functionality for rescheduling. **Creation or deletion of sessions is not allowed**.
### 2. **Scope - Phase 1 (Demo)**

### **Core Features**

1. **View class sessions in timetable (grid view)**
- Weekly grid layout
- Columns: days of the week
- Rows: time slots (e.g., 08:00–20:00)
- Cards show: Unit code, Section, Lecturer, Room
2. **Filter & Search**
- By `Semester`
- By `Campus`
- By `Lecturer`
- By `Room`
- By `Date range`
3. **Drag & Drop to reschedule**
- Move a session to another time or day
- Update `session_date`, `start_time`, `end_time`, `room_id` (if room also dropped)
- Conflict validation (room or lecturer) before accepting drop
4. **View session details**
- Click session to view: full metadata, linked course, lecturer, room
### **Restrictions**

- **No creation** of new `class_session`
- **No deletion** of sessions
- No attendance or assessment integration in Phase 1
### 3. **UI Components**

- `ScheduleGridView.vue`: main calendar grid with drag & drop
- `ScheduleFilterPanel.vue`: sidebar filters
- `ScheduleDetailDrawer.vue`: session metadata

### 4. **Relevant Data Models**

- `class_sessions` (editable: date/time/room)
- `course_offerings` → unit + section
- `lectures`, `rooms`, `room_bookings`
### 5. **Essential API Endpoints**

- `PATCH /api/admin/schedules/{id}/reschedule` — update time/room
- `GET /api/admin/schedules/{id}` — session detail
