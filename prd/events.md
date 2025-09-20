## Events (School & Club)

### Tables

#### `events`

* **Purpose**: Unified table for both school and club events.
* **Fields**:

  * `id`
  * `title`, `description`
  * `campus_id`
  * `organizer_type` (ENUM: school, club)
  * `organizer_id` (FK → campuses or clubs)
  * `start_time`, `end_time`
  * `location`
  * `qr_code` (unique per event)
  * `gold_reward` (default reward amount)
  * `created_at`, `updated_at`
  * `status` ENUM('draft','published','cancelled','completed') DEFAULT 'draft'
  * `created_by_user_id` (FK → users) BIGINT NULL
  * `created_by_student_id` (FK → students) BIGINT NULL
  * `published_at` TIMESTAMP NULL
  * `cancelled_at` TIMESTAMP NULL
  * `completed_at` TIMESTAMP NULL
  * `deleted_at` TIMESTAMP NULL
  * `deleted_by_user_id` (FK → users) BIGINT NULL
  * `deleted_by_student_id` (FK → students) BIGINT NULL

#### `event_participants`

* **Purpose**: Track student participation & reward eligibility.
* **Fields**:

  * `id`
  * `event_id` (FK → events)
  * `student_id` (FK → students)
  * `status` (ENUM: registered, checked\_in, completed, cancelled)
  * `checkin_time`
  * `gold_awarded` BOOLEAN DEFAULT 0
  * `awarded_at` TIMESTAMP NULL
  * `device_info`

### Goals & Features

* Students check-in via QR to earn Gold.
* Track attendance and completion.
* Award Gold to students who attend events.
* API: `POST /events/{id}/checkin`, `GET /events` in `routes/api/v1/student.php`.
