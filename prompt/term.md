## Please generate full CRUD functionality for the semesters table in Laravel, using Inertia.js with Vue 3 as the frontend.

- Laravel: create the migration, model, controller (SemesterController), routes, validation, and policies if needed.

- Vue 3 using Inertia.js: build components for listing, creating, editing, and deleting records (you may use modals or
  dedicated pages).

- UI: use Tailwind CSS (if applicable). Each record should display name, start_date, end_date, locked_status,
  is_attendance_locked, is_certificate_locked, has_tuition_fee, and has_gc_fee.

- Allow filtering or searching by name or year (parsed from the name field).

- The fields locked_status, is_attendance_locked, is_certificate_locked, has_tuition_fee, and has_gc_fee should be shown
  using icons or toggle switches.

- Use SoftDeletes.

- If locked_status is locked, disable edit and delete buttons.
- You use modal dialogs for create/edit semesters.

- The semesters table has a belongsTo relationship with the campuses table

```sql
CREATE TABLE semesters
(
    id (bigint, auto-increment, primary key)

    code (string)              -- Code term: 'SPR2025', 'FALL2025'
    name (string)              -- example: 'Spring 2025'
    start_date (date)          
    end_date (date)            
    enrollment_start_date (datetime, nullable)   
    enrollment_end_date (datetime, nullable)  
    is_active (boolean)        -- Is the current semester?
    is_archived (boolean)      -- Has ended and could not edit anymore?

    created_at (timestamp)
    updated_at (timestamp)
);
```
