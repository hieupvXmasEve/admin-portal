# Grade Calculation & Passing Logic

## Overview

When a course is marked as "completed", the system calculates `grade_points` from `final_percentage` and determines pass/fail status based on course type.

---

## 📊 Passing Thresholds

### EGC Courses (`unit_type = 'egc'`)
```
final_percentage >= 70%  →  PASS ✅
final_percentage < 70%   →  FAIL ❌
```

### Non-EGC Courses (all other types)
```
final_percentage >= 60%  →  PASS ✅
final_percentage < 60%   →  FAIL ❌
```

---

## 🎯 Grade Points Scale

Grade points are calculated from `final_percentage`:

| Percentage Range | Grade Points | Letter Grade | Status |
|-----------------|--------------|--------------|--------|
| 90% - 100% | 4.0 | A+, A | Pass ✅ |
| 85% - 89% | 3.7 | A- | Pass ✅ |
| 80% - 84% | 3.3 | B+ | Pass ✅ |
| 75% - 79% | 3.0 | B | Pass ✅ |
| 70% - 74% | 2.7 | B- | Pass ✅ |
| 65% - 69% | 2.3 | C+ | Pass ✅ |
| 60% - 64% | 2.0 | C | Pass ✅ |
| 55% - 59% | 1.7 | C- | EGC: Fail ❌ / Other: Pass ✅ |
| 50% - 54% | 1.3 | D+ | Fail ❌ |
| 45% - 49% | 1.0 | D | Fail ❌ |
| 0% - 44% | 0.0 | F | Fail ❌ |

---

## 💻 Implementation

### Course Completion Flow

```php
// 1. Admin marks course as completed
CourseOfferingController::updateCourseStatus()

// 2. Finalize academic records
CourseCompletionService::finalizeCourse()
  ↓
  finalizeAcademicRecords()
    ↓
    For each student:
      1. Get final_percentage
      2. Calculate grade_points from percentage
      3. Determine pass/fail based on threshold
      4. Update academic_record
```

### Grade Calculation Logic

```php
private function finalizeAcademicRecords(CourseOffering $courseOffering): void
{
    $isEgcCourse = $courseOffering->unit->unit_type === 'egc';
    $passingThreshold = $isEgcCourse ? 70 : 60;

    foreach ($records as $record) {
        $finalPercentage = $record->final_percentage ?? 0;
        
        // Calculate grade points
        $gradePoints = $this->calculateGradePoints($finalPercentage);
        
        // Determine pass/fail
        $isPassing = $finalPercentage >= $passingThreshold;
        
        $record->update([
            'grade_points' => $gradePoints,
            'completion_status' => $isPassing ? 'completed' : 'failed',
            'credit_hours_earned' => $isPassing ? $record->credit_hours : 0,
            'satisfies_prerequisite' => $isPassing,
        ]);
    }
}

private function calculateGradePoints(float $percentage): float
{
    if ($percentage >= 90) return 4.0;      // A+, A
    elseif ($percentage >= 85) return 3.7;  // A-
    elseif ($percentage >= 80) return 3.3;  // B+
    elseif ($percentage >= 75) return 3.0;  // B
    elseif ($percentage >= 70) return 2.7;  // B-
    elseif ($percentage >= 65) return 2.3;  // C+
    elseif ($percentage >= 60) return 2.0;  // C
    elseif ($percentage >= 55) return 1.7;  // C-
    elseif ($percentage >= 50) return 1.3;  // D+
    elseif ($percentage >= 45) return 1.0;  // D
    else return 0.0;                        // F
}
```

---

## 📝 Examples

### Example 1: EGC Course
```
Unit: EGCF (unit_type = 'egc')
Passing Threshold: 70%

Student A: final_percentage = 75%
  → grade_points = 3.0 (B)
  → completion_status = 'completed' ✅
  → Level progressed: 0 → 1

Student B: final_percentage = 68%
  → grade_points = 2.3 (C+)
  → completion_status = 'failed' ❌ (below 70%)
  → Level unchanged: 0
```

### Example 2: Non-EGC Course
```
Unit: CS101 (unit_type = 'cs')
Passing Threshold: 60%

Student C: final_percentage = 65%
  → grade_points = 2.3 (C+)
  → completion_status = 'completed' ✅
  → Credits earned

Student D: final_percentage = 58%
  → grade_points = 1.7 (C-)
  → completion_status = 'failed' ❌ (below 60%)
  → No credits earned
```

### Example 3: Edge Cases
```
EGC Course:
  69.9% → 2.3 (C+) → FAIL ❌ (threshold 70%)
  70.0% → 2.7 (B-) → PASS ✅

Regular Course:
  59.9% → 1.7 (C-) → FAIL ❌ (threshold 60%)
  60.0% → 2.0 (C)  → PASS ✅
```

---

## 🔄 EGC Level Progression

For EGC students, passing also triggers level progression:

```php
// In EgcLevelProgressionService
$isPassing = $record->grade_points > 0;  // Now correctly calculated!

if ($isPassing && $levelMatch) {
    // Progress student level
    $student->gc_current_level += 1;
    
    // If completed all levels
    if ($student->gc_current_level >= $student->gc_total_levels) {
        $student->status = 'intake_course';
    }
}
```

**Important:** 
- `grade_points > 0` is used to check if grade is passing
- But actual pass/fail for EGC is `final_percentage >= 70`
- This means grades C-, D+, D (grade_points > 0 but < 70%) will:
  - Have `grade_points` (1.7, 1.3, 1.0)
  - But `completion_status = 'failed'`
  - And NOT progress level

---

## 📊 Database Updates

When course is completed, `academic_records` are updated:

```sql
UPDATE academic_records SET
  grade_points = [calculated from final_percentage],
  grade_status = 'final',
  grade_finalized_date = NOW(),
  completion_status = CASE 
    WHEN final_percentage >= [threshold] THEN 'completed'
    ELSE 'failed'
  END,
  credit_hours_earned = CASE
    WHEN final_percentage >= [threshold] THEN credit_hours
    ELSE 0
  END,
  affects_graduation_requirement = true,
  satisfies_prerequisite = CASE
    WHEN final_percentage >= [threshold] THEN true
    ELSE false
  END
WHERE course_offering_id = X;
```

---

## ✅ Validation

Before course can be completed, system validates:

1. ✅ All students have `final_percentage` set
2. ✅ All students have `final_letter_grade` set
3. ✅ All registered students have academic records

If validation fails:
```
Error: Cannot complete course: X student(s) are missing final grades
```

---

## 🧪 Testing

### Test Case 1: EGC Course with Mixed Results
```php
$egcCourse = CourseOffering::find(32);
// unit_type = 'egc', threshold = 70%

Students:
  A: 85% → grade_points 3.7 → Pass → Level 0→1
  B: 72% → grade_points 2.7 → Pass → Level 0→1
  C: 68% → grade_points 2.3 → Fail → Level 0 (unchanged)
  D: 45% → grade_points 1.0 → Fail → Level 0 (unchanged)
```

### Test Case 2: Regular Course
```php
$regularCourse = CourseOffering::find(45);
// unit_type = 'cs', threshold = 60%

Students:
  E: 62% → grade_points 2.0 → Pass → Credits earned
  F: 58% → grade_points 1.7 → Fail → No credits
```

---

## 📚 Related Files

- `app/Services/CourseCompletionService.php` - Grade calculation logic
- `app/Services/EgcLevelProgressionService.php` - EGC progression logic
- `app/Models/AcademicRecord.php` - Academic record model
- `app/Models/Unit.php` - Unit types and levels

---

**Last Updated:** 2025-10-26  
**Author:** Development Team
