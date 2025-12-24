# Form Engine Implementation Plan (SRS v2)

Based on `srs.md`, `srs_map.md`, and `ui.md`, this plan details the technical steps to implement the distinct "Template vs Run" architecture, Portal Gate, and Query Workflow.

## 1. Database Schema Refinements

### 1.1 Form Targets (Refine for "Runs")

- [ ] Modify `form_targets` table:
    - Add `semester_id` (string/int, nullable) - purely for logic filtering in Department+Semester scope.
    - Ensure `context_type` can handle `course_offering`, `semester`, `department`, `global`.
    - Ensure `status` enum: `draft`, `active`, `closed`.

### 1.2 Student Assignments (The "Gate")

- [ ] Create `student_form_assignments` table:
    - `id` (PK)
    - `student_id` (FK -> students/users)
    - `form_target_id` (FK -> form_targets)
    - `status`: `not_started`, `completed` (default: `not_started`)
    - `response_id` (nullable, FK -> form_responses)
    - `completed_at` (nullable, datetime)
    - **Unique Constraint**: `[student_id, form_target_id]`

### 1.3 Query Workflow History

- [ ] Create `response_assignments` table (Audit Log):
    - `id` (PK)
    - `response_id` (FK -> form_responses)
    - `assigned_to_user_id` (FK -> users)
    - `assigned_by_user_id` (FK -> users)
    - `action`: `assign`, `reassign`, `unassign`
    - `created_at` (timestamp)

### 1.4 Response Threads (Query Discussion)

- [ ] Create `response_threads` table:
    - `id` (PK)
    - `response_id` (FK -> form_responses)
    - `sender_type`: `student`, `staff`
    - `sender_id` (FK -> users, nullable if we trust sender_type context)
    - `message` (text)
    - `attachments` (json, nullable)
    - `created_at` (timestamp)

## 2. Backend Logic & Services

### 2.1 FormService (Updates)

- [ ] Update `createTarget` (Create Run) to handle `semester_id`.
- [ ] Implement `activateTarget($target)`:
    - If `is_mandatory` & type=`survey`:
        - Fetch valid students based on Scope (Course/Semester/Dept).
        - Bulk insert into `student_form_assignments`.
- [ ] Implement `checkPortalGate($student)`:
    - Query `student_form_assignments`: `status=not_started` AND `target.status=active` AND `target.is_mandatory=true`.
    - Return blocked status + list of pending forms.

### 2.2 QueryService (New)

- [ ] Implement `assignTicket($response, $staff, $assigner)`:
    - Update `response.assigned_to_user_id` & `status`.
    - Log to `response_assignments`.
- [ ] Implement `replyType($response, $message, $sender)`:
    - Create `response_thread`.
    - Update `response.updated_at`.

## 3. API & Controllers (Admin)

### 3.1 Form Templates (`FormController`)

- [ ] Refactor `FormController` to focus on "Templates" (Library, Builder).
- [ ] Remove mixed logic regarding "Runs" if it complicates the template view.

### 3.2 Form Runs (`FormRunController` - New)

- [ ] `index`: List runs with filters (context, status).
- [ ] `store`: Create a run (Target).
- [ ] `activate`: Trigger activation logic.
- [ ] `close`: Trigger close logic.
- [ ] `show`: Dashboard stats for the run.

### 3.3 Query Management (`QueryController` - Admin/Staff)

- [ ] `index`: List queries (My Assigned / Department Wide).
- [ ] `show`: Ticket detail + Threads.
- [ ] `assign`: Endpoint to assign/reassign.
- [ ] `reply`: Endpoint to post thread message.

## 4. Frontend Implementation (Admin Portal)

### 4.1 Menu Structure (Refinement)

- [ ] Forms Library (Templates)
- [ ] Runs Management (Campaigns)
- [ ] Survey Gate Dashboard (Monitoring)
- [ ] Query Inbox (Staff View)

### 4.2 Views

- [ ] **Runs List**: Table showing active/draft runs.
- [ ] **Create Run**: Modal/Page to select Template + Scope + Config.
- [ ] **Query Detail**: Chat-like interface for threads + Sidebar for assignment.

## 5. Student Portal Integration

- [ ] **Middleware/Global State**: Check Gate on load.
- [ ] **Gate Screen**: If blocked, show mandatory surveys.
- [ ] **My Queries**: List created queries + Detail view.
