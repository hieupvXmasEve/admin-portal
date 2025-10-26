# Course Completion & EGC Level Progression System

## Overview

This document describes the complete flow for finalizing courses and handling EGC (English Global Citizen) level progression when a course offering is marked as "completed".

---

## 📋 Features

### 1. Course Completion
- Validates all students have academic records
- Validates all students have final grades
- Finalizes academic records with completion status
- Updates course registrations with final grades
- Prevents modification of completed courses

### 2. EGC Level Progression
- Automatically progresses students through EGC levels
- Validates level matching (student level must match unit level)
- Handles pass/fail scenarios
- Auto-transitions students from `intake_pre_uni_gc` to `intake_course` upon completion
- Sends notifications to students and admins

---

## 🔄 Process Flow

```
Admin marks Course as "Completed"
          ↓
┌─────────────────────────────────────────┐
│ 1. Validation Phase                     │
│    ✓ All students have academic records │
│    ✓ All students have final grades     │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ 2. Finalize Academic Records            │
│    • grade_status = 'final'             │
│    • completion_status = 'completed'/'failed' │
│    • credit_hours_earned calculated     │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ 3. Update Course Registrations          │
│    • registration_status = 'completed'  │
│    • Copy grades from academic_records  │
│    • Set completion_date               │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ 4. EGC Level Progression (if EGC)       │
│    • Check if unit_type = 'egc'         │
│    • Process each student               │
│    • Send notifications                 │
└─────────────────┬───────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ 5. Update Course Offering Status        │
│    • course_status = 'completed'        │
│    • Lock from further modifications    │
└─────────────────────────────────────────┘
```

---

## 🎓 EGC Level Progression Logic

### Student Requirements
```php
status = 'intake_pre_uni_gc'
gc_starting_level = 0 (or configured value)
gc_current_level = 0-6 (current level)
gc_total_levels = 6 (or configured value)
```

### Unit Requirements
```php
unit_type = 'egc'
level = 0-6 (must match student's gc_current_level)
```

### Progression Rules

#### ✅ **PASS Scenario (Level Match)**
```
IF student.status === 'intake_pre_uni_gc'
AND student.gc_current_level === unit.level
AND grade_points > 0
THEN:
  1. student.gc_current_level += 1
  2. Add progression note to academic_record
  3. Send success notification to student
  
IF new gc_current_level >= gc_total_levels:
  4. student.status = 'intake_course'
  5. Send program completion notification
```

#### ❌ **FAIL Scenario**
```
IF grade_points === 0 (Failed)
THEN:
  1. Keep gc_current_level unchanged
  2. Send failure notification to student
  3. Student can retake the course
```

#### ⚠️ **PASS but Level Mismatch**
```
IF student.gc_current_level !== unit.level
AND grade_points > 0
THEN:
  1. Grade recorded in academic_record
  2. gc_current_level NOT progressed
  3. Send warning to student
  4. Send admin notification for review
```

---

## 📊 Database Changes

### `course_offerings`
```sql
course_status = 'completed'  -- Locked, no further changes allowed
```

### `academic_records`
```sql
grade_status = 'final'
grade_finalized_date = NOW()
completion_status = 'completed' OR 'failed'
credit_hours_earned = CASE WHEN passed THEN credit_hours ELSE 0 END
affects_graduation_requirement = true
satisfies_prerequisite = CASE WHEN passed THEN true ELSE false END
grade_history = {
  "egc_level_progression": {
    "previous_level": 1,
    "new_level": 2,
    "progressed_at": "2025-10-26T10:00:00Z",
    "unit_level_match": true,
    "unit_code": "EGC101"
  }
}
administrative_notes = "EGC Level Progression: Level 1 → Level 2"
```

### `course_registrations`
```sql
registration_status = 'completed'
final_grade = 'A+' (copied from academic_record)
grade_points = 4.0 (copied from academic_record)
completion_date = NOW()
```

### `students` (EGC only)
```sql
gc_current_level = gc_current_level + 1
status = 'intake_course' (when all levels completed)
```

---

## 📧 Notifications

### 1. **EgcCourseCompletedNotification** (to Student)

**Sent when:** Course is completed (pass or fail)

**For PASS:**
```
Subject: 🎉 Course Completed: EGC101
Body: 
  - Result: ✅ PASSED
  - Grade: A
  - Current Level: Level 2
  - Message: Level progressed: Level 1 → Level 2
```

**For FAIL:**
```
Subject: Course Result: EGC101
Body:
  - Result: ❌ NOT PASSED
  - Grade: F
  - Current Level: Level 1 (unchanged)
  - Message: You did not pass this course...
```

### 2. **EgcProgramCompletedNotification** (to Student)

**Sent when:** Student completes all EGC levels

```
Subject: 🎓 Congratulations! EGC Program Completed
Body:
  - Total Levels Completed: 6
  - New Status: Intake Course
  - Message: You can now register for regular courses
```

### 3. **EgcLevelMismatchNotification** (to Admin)

**Sent when:** Student passes but level doesn't match

```
Subject: ⚠️ EGC Level Mismatch Detected
Body:
  - Student: S123456 - John Doe
  - Student's Current Level: Level 2
  - Unit Level: Level 1
  - Grade: A
  - Action: Grade recorded but level NOT progressed
```

---

## 🔧 API Usage

### Mark Course as Completed

```http
PATCH /course-offerings/{id}/update-course-status
Content-Type: application/json

{
  "course_status": "completed"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Course 'EGC101' marked as completed successfully. | EGC: 25 student(s) processed, 20 progressed to next level, 3 failed (level unchanged). ⚠️ 2 level mismatch warning(s) - check notifications"
}
```

**Error Response (Missing Grades):**
```json
{
  "error": "Cannot complete course: 5 student(s) are missing final grades. Students: S001, S002, S003, S004, S005"
}
```

---

## 🛡️ Validations

### Before Course Completion

1. **Academic Records Exist**
   ```php
   registered_count === academic_records_count
   ```

2. **All Grades Entered**
   ```php
   missing_grades === 0
   ```

3. **Course Not Already Completed**
   ```php
   course_status !== 'completed'
   ```

### During EGC Progression

1. **Student Status Check**
   ```php
   student.status === 'intake_pre_uni_gc'
   ```

2. **Level Match Validation**
   ```php
   student.gc_current_level === unit.level
   ```

3. **Unit Type Check**
   ```php
   unit.unit_type === 'egc'
   ```

---

## 📝 Logging

All actions are logged with comprehensive details:

```php
Log::info('Course status updated to completed', [
    'course_offering_id' => 123,
    'course_code' => 'EGC101',
    'old_status' => 'in_progress',
    'new_status' => 'completed',
    'egc_result' => [
        'processed' => true,
        'total_students' => 25,
        'progressed' => [...],
        'failed_students' => [...],
        'warnings' => [...]
    ]
]);

Log::info('EGC Level Progressed', [
    'student_id' => 'S123456',
    'student_name' => 'John Doe',
    'from_level' => 1,
    'to_level' => 2,
    'unit_code' => 'EGC101',
    'grade' => 'A',
    'completed_program' => false
]);
```

---

## 🚫 Restrictions

### Cannot Be Done:

1. ❌ Change status of completed course
2. ❌ Manually adjust `gc_current_level` (admin)
3. ❌ Rollback completed course
4. ❌ Bulk complete multiple courses
5. ❌ Progress level if level mismatch detected

### Can Be Done:

1. ✅ View academic records of completed course
2. ✅ Duplicate completed course for new semester
3. ✅ Export completion reports
4. ✅ View progression history in academic records

---

## 🧪 Testing Scenarios

### Test 1: Normal EGC Progression
```
Given: Student at Level 1, enrolled in Level 1 EGC course
When: Student passes with grade A
Then: Student progresses to Level 2
And: Notification sent
```

### Test 2: EGC Failure
```
Given: Student at Level 1, enrolled in Level 1 EGC course
When: Student fails with grade F
Then: Student remains at Level 1
And: Failure notification sent
```

### Test 3: Level Mismatch
```
Given: Student at Level 2, enrolled in Level 1 EGC course
When: Student passes with grade A
Then: Grade recorded but level NOT progressed
And: Warning notification sent to admin
```

### Test 4: EGC Program Completion
```
Given: Student at Level 5 (last level), gc_total_levels = 6
When: Student passes Level 5 EGC course
Then: Student progresses to Level 6
And: student.status changes to 'intake_course'
And: Program completion notification sent
```

### Test 5: Non-EGC Course
```
Given: Regular course (unit_type != 'egc')
When: Course marked as completed
Then: Only grades finalized
And: No EGC progression processed
```

---

## 🔍 Troubleshooting

### Issue: Cannot mark course as completed

**Symptom:**
```
Error: Missing academic records for some students. 
Registered: 30, Records: 28
```

**Solution:**
- Ensure all registered students have academic records created
- Run: `php artisan sync:academic-records {course_offering_id}`

---

### Issue: Cannot complete - missing grades

**Symptom:**
```
Error: Cannot complete course: 5 student(s) are missing final grades
```

**Solution:**
- Navigate to Assessment/Grading section
- Enter final grades for all students
- Ensure grades are saved in academic_records

---

### Issue: Level mismatch warnings

**Symptom:**
```
Warning: Student at level 2 passed level 1 unit
```

**Solution:**
- Review student enrollment records
- Check if student was enrolled in correct level
- Contact student if necessary
- Grade is recorded but manual intervention may be needed

---

## 📚 Related Files

### Services
- `app/Services/CourseCompletionService.php`
- `app/Services/EgcLevelProgressionService.php`

### Controllers
- `app/Http/Controllers/Web/CourseOfferingController.php`

### Notifications
- `app/Notifications/EgcCourseCompletedNotification.php`
- `app/Notifications/EgcProgramCompletedNotification.php`
- `app/Notifications/EgcLevelMismatchNotification.php`

### Models
- `app/Models/CourseOffering.php`
- `app/Models/AcademicRecord.php`
- `app/Models/CourseRegistration.php`
- `app/Models/Student.php`
- `app/Models/Unit.php`

### Frontend
- `resources/js/pages/course-offerings/Index.vue`

---

## 📞 Support

For questions or issues, contact:
- Academic Office
- System Administrator
- Development Team

Last Updated: 2025-10-26
