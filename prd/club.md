
## Clubs & Roles

### Tables

#### `clubs`

* **Purpose**: Manage student organizations.
* **Fields**:

  * `id`
  * `campus_id`
  * `name`, `description`
  * `founded_date`
  * `status` (active/inactive)

#### `club_members`

* **Purpose**: Define membership and roles within a club.
* **Fields**:

  * `id`
  * `club_id` (FK → clubs)
  * `student_id` (FK → students)
  * `role` (ENUM: president, vice\_president, member, secretary, treasurer)
  * `joined_at`, `left_at`

### Goals & Features

* Club presidents & vice presidents can create/manage events.
* Role-based permissions for club governance.
* API: `POST /clubs`, `POST /clubs/{id}/members`, `POST /events` (restricted by role).
