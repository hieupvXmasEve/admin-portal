# Course Schedule Planning After Auto Course Registration

After students have been automatically registered into `course_registrations`, this document outlines the recommended step-by-step plan to generate detailed course schedules and manage attendance.

---

## ✅ I. Objectives

| Goal | Outcome |
|------|---------|
| Generate detailed class schedules | For each course offering: includes date, time, room, and lecturer |
| Manage room allocation | Avoid time/room conflicts within the same campus |
| Track attendance | Record student attendance per session |

---

## ✅ II. Required Tables

### 1. `buildings` – Campus Buildings

```sql
CREATE TABLE buildings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    campus_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
);
```

> Each row = one physical building on a campus (e.g., "Block A", "Science Centre")

---

### 2. `rooms` – Rooms per Building

```sql
CREATE TABLE rooms (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    building_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    room_type ENUM('classroom','lab','lecture_hall','auditorium','meeting_room') NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
);
```

> Each row = one room that can host classes (e.g., "Lab 301")

---

### 3. `course_schedules` – Class Sessions

```sql
CREATE TABLE course_schedules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);
```

> Each row = one class session (e.g., Calculus, Aug 1st, 9:00–11:00, Room 101)

---

### 4. `attendances` – Attendance Records

```sql
CREATE TABLE attendances (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    schedule_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    note TEXT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (schedule_id) REFERENCES course_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

> Each row = one student's attendance for one session

---

## ✅ III. Step-by-Step Implementation Plan

---

### 🔹 Step 1: Collect Scheduling Data

From the `course_offerings` table, retrieve:

| Field | Description |
|-------|-------------|
| `unit_id` | The course being offered |
| `semester_id` | The semester of offering |
| `campus_id` | Campus where the course is offered (used to match buildings & rooms) |
| `instructor_id` | Assigned lecturer |
| `schedule_days` | Days of the week (e.g., `["mon", "wed"]`) |
| `schedule_time_start` / `schedule_time_end` | Time slot |
| `max_capacity` | Max student capacity (used for room matching) |

---

### 🔹 Step 2: Generate Specific Class Dates

Based on:

- Semester's `start_date` and `end_date`
- `schedule_days` for the course
- (Optional) Holiday exclusions

→ Automatically generate all relevant class dates

📌 Example:

```
Course MKT1001: Mondays and Wednesdays from Aug 1 to Nov 30 → 17 sessions
```

---

### 🔹 Step 3: Assign Rooms

From `rooms` table, select available rooms:

- Belonging to the same campus
- Building's `campus_id` matches the course offering's `campus_id`
- Room `room_type` is suitable for the class (e.g., lab vs lecture hall)
- Available at the desired `start_time` – `end_time`
- Capacity ≥ `max_capacity`

→ Assign `room_id` to each session

---

### 🔹 Step 4: Insert into `course_schedules`

Insert all generated sessions (with date, time, room) into `course_schedules`.

---

### 🔹 Step 5: (Optional) Generate Initial Attendance Records

You may pre-generate empty attendance rows with default status:

```php
foreach ($course_schedules as $schedule) {
    foreach ($studentsInClass as $student) {
        Attendance::create([
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'status' => 'pending'
        ]);
    }
}
```

---

## ✅ IV. Room Conflict Check Rule

When assigning rooms, ensure:

```sql
NOT EXISTS (
    SELECT 1
    FROM course_schedules
    WHERE room_id = :room_id
      AND schedule_date = :date
      AND (
           (start_time < :new_end AND end_time > :new_start)
      )
)
```

> Prevent time slot overlap in the same room

---

## ✅ V. Interface & Workflow After Scheduling

| Feature | Purpose |
|---------|---------|
| 📅 View timetable | Per student / per lecturer |
| ✅ Attendance marking | Lecturer marks attendance per session |
| 📊 Attendance summary | Used to calculate course completion eligibility |

---

## ✅ VI. Laravel Integration

- `Building` model → belongsTo `Campus`; hasMany `Room`
- `Room` model → belongsTo `Building`; hasMany `CourseSchedule`
- `CourseSchedule` model → belongsTo `CourseOffering`, `Room`
- `Attendance` model → belongsTo `CourseSchedule`, `Student`

---
