
## Clubs & Roles

### Tables

#### `clubs`

* **Purpose**: Manage student organizations.
* **Fields**:

  * `id`
  * `campus_id`
  * `name`, `description`
  * `founded_date`
  * `avatar_url`
  * `thumbnail_url`
  * `cover_url`
  * `social_links` (json)
  * `contact_email`
  * `contact_phone`
  * `status` (active/inactive)
  * `achievements` (json)
  * `created_at`, `updated_at`

#### `club_members`

* **Purpose**: Define membership and roles within a club.
* **Fields**:

  * `id`
  * `club_id` (FK → clubs)
  * `student_id` (FK → students)
  * `role` (ENUM: president, vice_president, member, secretary, treasurer)
  * `status` (enum: active, pending, rejected, left, banned)
  * `application_notes` (text)
  * `approved_by` (FK → students)
  * `responsibilities` (json)
  * `participation_score` (integer)
  * `last_active_at` (timestamp)
  * `joined_at`, `left_at`
* **Field explanations**
  * `club_id`: links to the specific organization.
  * `student_id`: links to the specific student.
  * `role`: the member's role.
  * `status`: the member's status.
  * `application_notes`: notes when applying (why they want to join, interests).
  * `approved_by`: who approved (when approved).
  * `responsibilities`: the member's responsibilities.
  * `participation_score`: the member's evaluation score.
  * `last_active_at`: the member's last active time.
  * `joined_at`, `left_at`: the time the member joined and left the club.

#### `club_member_roles_history`

* **Purpose**: Track role changes for club members.
* **Fields**:

  * `id`
  * `club_member_id` (FK → club_members)
  * `old_role` (ENUM: president, vice_president, member, secretary, treasurer)
  * `new_role` (ENUM: president, vice_president, member, secretary, treasurer)
  * `changed_by` (FK → students)
  * `change_reason` (text)
  * `started_at`, `ended_at`
  * `created_at`, `updated_at`

```sql
create table club_member_roles_history
(
    id             bigint unsigned auto_increment primary key,
    club_member_id bigint unsigned not null,
    old_role       enum ('president', 'vice_president', 'secretary', 'treasurer', 'member') null,
    new_role       enum ('president', 'vice_president', 'secretary', 'treasurer', 'member') not null,
    changed_by     bigint unsigned null comment 'FK → students.id hoặc users.id (tùy hệ thống bạn chọn)',
    change_reason  text null,
    started_at     timestamp not null,
    ended_at       timestamp null,
    created_at     timestamp null,
    updated_at     timestamp null,

    constraint fk_club_member_roles_history_member
        foreign key (club_member_id) references club_members(id)
            on delete cascade
);

```
* **Field explanations**
  * `club_member_id`: links to the specific member.
  * `old_role` / `new_role`: track changes.
  * `changed_by`: who performed the change (can be nullable if only auto-update).
  * `change_reason`: describes the reason (election, dismissal, violation, etc.).
  * `started_at` / `ended_at`: indicates when this role was effective.
  * `created_at`, `updated_at`: standard system audit fields.

### Goals & Features

* Club presidents & vice presidents can create/manage events.
* Each club can have only one president, one vice president, secretary and treasurer.
* Role-based permissions for club governance.
* Club members can be promoted to president, vice president, secretary and treasurer.
* Club members can be demoted to member.
* Club members can be removed from the club.
* API: `POST /clubs`, `POST /clubs/{id}/members`, `POST /events` (restricted by role).
