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

#### `event_participants`

* **Purpose**: Track student participation & reward eligibility.
* **Fields**:

  * `id`
  * `event_id` (FK → events)
  * `student_id` (FK → students)
  * `status` (ENUM: registered, checked\_in, completed)
  * `checkin_time`
  * `device_info`

### Goals & Features

* Students check-in via QR to earn Gold.
* Track attendance and completion.
* API: `POST /events/{id}/checkin`, `GET /events` in `routes/api/v1/student.php`.
