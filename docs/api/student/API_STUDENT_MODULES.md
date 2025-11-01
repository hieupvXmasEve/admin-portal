# Student Module API Documentation

## Overview

API endpoints for students to view their module progress in the Finland campus modular curriculum system. These endpoints calculate module grades on-the-fly by aggregating academic records from completed course offerings.

**Base URL:** `/api/v1/student/modules`

**Authentication:** Required - `Bearer token` (Sanctum)

---

## Endpoints

### 1. Get All Modules

**GET** `/api/v1/student/modules`

Get all modules in authenticated student's curriculum with progress information.

#### Request

```http
GET /api/v1/student/modules HTTP/1.1
Host: api.example.com
Authorization: Bearer {token}
```

#### Response

```json
{
  "success": true,
  "data": {
    "curriculum_version": {
      "id": 1,
      "version_code": "IT2025",
      "program": {
        "id": 1,
        "name": "Information Technology",
        "code": "IT"
      },
      "specialization": {
        "id": 1,
        "name": "Software Engineering",
        "code": "SE"
      }
    },
    "modules": [
      {
        "curriculum_module_id": 1,
        "module_id": 1,
        "module": {
          "id": 1,
          "code": "SW1",
          "name": "Software Engineering 1",
          "total_credits": 15.0,
          "grading_type": "grade"
        },
        "year_level": 1,
        "semester_number": 1,
        "is_required": true,
        "group_name": "core",
        "order": 0,
        "progress": {
          "grade": 4.0,
          "status": "completed",
          "completion": "3/3",
          "completed_count": 3,
          "total_count": 3
        },
        "prerequisites_met": true
      },
      {
        "curriculum_module_id": 2,
        "module_id": 2,
        "module": {
          "id": 2,
          "code": "SW2",
          "name": "Software Engineering 2",
          "total_credits": 12.0,
          "grading_type": "grade"
        },
        "year_level": 1,
        "semester_number": 2,
        "is_required": true,
        "group_name": "core",
        "order": 1,
        "progress": {
          "grade": 3.2,
          "status": "in_progress",
          "completion": "2/3",
          "completed_count": 2,
          "total_count": 3
        },
        "prerequisites_met": true
      },
      {
        "curriculum_module_id": 3,
        "module_id": 3,
        "module": {
          "id": 3,
          "code": "SW3",
          "name": "Software Engineering 3",
          "total_credits": 18.0,
          "grading_type": "grade"
        },
        "year_level": 2,
        "semester_number": 1,
        "is_required": true,
        "group_name": "core",
        "order": 2,
        "progress": {
          "grade": null,
          "status": "not_enrolled",
          "completion": "0/4",
          "completed_count": 0,
          "total_count": 4
        },
        "prerequisites_met": false
      }
    ],
    "statistics": {
      "total_modules": 12,
      "completed_modules": 2,
      "in_progress_modules": 1,
      "not_started_modules": 9,
      "completion_percentage": 16.7,
      "total_credits": 150.0,
      "completed_credits": 27.0,
      "credits_percentage": 18.0,
      "module_gpa": 3.6
    }
  }
}
```

#### Status Codes

- `200 OK` - Success
- `404 Not Found` - Student has no assigned curriculum
- `401 Unauthorized` - Invalid or missing token

---

### 2. Get Module Detail

**GET** `/api/v1/student/modules/{module_id}`

Get detailed progress for a specific module including sub-units breakdown.

#### Request

```http
GET /api/v1/student/modules/1 HTTP/1.1
Host: api.example.com
Authorization: Bearer {token}
```

#### Response

```json
{
  "success": true,
  "data": {
    "module": {
      "id": 1,
      "code": "SW1",
      "name": "Software Engineering 1",
      "description": "Introduction to software engineering principles",
      "total_credits": 15.0,
      "grading_type": "grade"
    },
    "curriculum_info": {
      "year_level": 1,
      "semester_number": 1,
      "is_required": true,
      "group_name": "core",
      "note": null
    },
    "progress": {
      "grade": 4.0,
      "status": "completed",
      "completion": "3/3",
      "completed_count": 3,
      "total_count": 3,
      "sub_units": [
        {
          "unit": {
            "id": 14,
            "code": "MATH",
            "name": "Mathematics Fundamentals",
            "credit_points": 5.0,
            "grading_type": "grade"
          },
          "grading_type": "grade",
          "grade": 4.5,
          "letter_grade": "A",
          "status": "completed",
          "academic_record_id": 101,
          "weight": 50.0
        },
        {
          "unit": {
            "id": 15,
            "code": "PHYSICS",
            "name": "Physics Principles",
            "credit_points": 5.0,
            "grading_type": "grade"
          },
          "grading_type": "grade",
          "grade": 3.5,
          "letter_grade": "B+",
          "status": "completed",
          "academic_record_id": 102,
          "weight": 50.0
        },
        {
          "unit": {
            "id": 16,
            "code": "PE",
            "name": "Physical Education",
            "credit_points": 2.0,
            "grading_type": "pass_fail"
          },
          "grading_type": "pass_fail",
          "grade": null,
          "letter_grade": "P",
          "status": "completed",
          "academic_record_id": 103,
          "weight": null
        }
      ]
    },
    "prerequisites": {
      "module": null,
      "met": true
    },
    "required_for": [
      {
        "id": 2,
        "code": "SW2",
        "name": "Software Engineering 2"
      }
    ]
  }
}
```

#### Grade Calculation Example

For the above module:
```
Graded units (weighted average):
MATH: 4.5 × 50% = 2.25
PHYSICS: 3.5 × 50% = 1.75
─────────────────────
Total: 4.0 / 5.0

Pass/Fail units (not counted):
PE: Pass ✓
```

#### Status Codes

- `200 OK` - Success
- `404 Not Found` - Module not found in student's curriculum
- `401 Unauthorized` - Invalid or missing token

---

### 3. Get Module Roadmap

**GET** `/api/v1/student/modules/roadmap`

Get visual timeline of all modules grouped by year/semester with prerequisite information.

#### Request

```http
GET /api/v1/student/modules/roadmap HTTP/1.1
Host: api.example.com
Authorization: Bearer {token}
```

#### Response

```json
{
  "success": true,
  "data": {
    "curriculum_version": {
      "id": 1,
      "version_code": "IT2025"
    },
    "roadmap": [
      {
        "period": "Y1S1",
        "year_level": 1,
        "semester_number": 1,
        "modules": [
          {
            "id": 1,
            "code": "SW1",
            "name": "Software Engineering 1",
            "credits": 15.0,
            "is_required": true,
            "status": "completed",
            "grade": 4.0,
            "prerequisites": null,
            "prerequisites_met": true
          },
          {
            "id": 4,
            "code": "ENG1",
            "name": "English 1",
            "credits": 5.0,
            "is_required": true,
            "status": "completed",
            "grade": null,
            "prerequisites": null,
            "prerequisites_met": true
          }
        ]
      },
      {
        "period": "Y1S2",
        "year_level": 1,
        "semester_number": 2,
        "modules": [
          {
            "id": 2,
            "code": "SW2",
            "name": "Software Engineering 2",
            "credits": 12.0,
            "is_required": true,
            "status": "in_progress",
            "grade": 3.2,
            "prerequisites": {
              "id": 1,
              "code": "SW1",
              "met": true
            },
            "prerequisites_met": true
          }
        ]
      },
      {
        "period": "Y2S1",
        "year_level": 2,
        "semester_number": 1,
        "modules": [
          {
            "id": 3,
            "code": "SW3",
            "name": "Software Engineering 3",
            "credits": 18.0,
            "is_required": true,
            "status": "not_enrolled",
            "grade": null,
            "prerequisites": {
              "id": 2,
              "code": "SW2",
              "met": false
            },
            "prerequisites_met": false
          }
        ]
      }
    ]
  }
}
```

#### Status Codes

- `200 OK` - Success
- `404 Not Found` - Student has no assigned curriculum
- `401 Unauthorized` - Invalid or missing token

---

### 4. Get Dashboard Widget Data

**GET** `/api/v1/student/modules/dashboard`

Get current module progress summary for dashboard widget display.

#### Request

```http
GET /api/v1/student/modules/dashboard HTTP/1.1
Host: api.example.com
Authorization: Bearer {token}
```

#### Response

```json
{
  "success": true,
  "data": {
    "current_modules": [
      {
        "id": 2,
        "code": "SW2",
        "name": "Software Engineering 2",
        "grade": 3.2,
        "completion": "2/3",
        "status": "in_progress"
      }
    ],
    "statistics": {
      "total_modules": 12,
      "completed_modules": 2,
      "in_progress_modules": 1,
      "completion_percentage": 16.7,
      "module_gpa": 3.6
    }
  }
}
```

#### Status Codes

- `200 OK` - Success
- `401 Unauthorized` - Invalid or missing token

---

## Data Models

### ModuleProgress

| Field | Type | Description |
|-------|------|-------------|
| `grade` | `float\|null` | Weighted average grade (null if not completed) |
| `status` | `string` | `completed`, `in_progress`, `not_enrolled` |
| `completion` | `string` | Format: "X/Y units" |
| `completed_count` | `int` | Number of completed sub-units |
| `total_count` | `int` | Total number of sub-units |
| `sub_units` | `array` | Array of SubUnitProgress (detail endpoint only) |

### SubUnitProgress

| Field | Type | Description |
|-------|------|-------------|
| `unit` | `object` | Unit details (id, code, name, credits, grading_type) |
| `grading_type` | `string` | `grade` or `pass_fail` (from module_units pivot) |
| `grade` | `float\|null` | Numeric grade (null for pass/fail or not completed) |
| `letter_grade` | `string\|null` | Letter grade (A, B+, etc. or P/F) |
| `status` | `string` | Academic record completion status |
| `academic_record_id` | `int\|null` | Reference to academic record |
| `weight` | `float\|null` | Weight percentage in module grade calculation |

### Statistics

| Field | Type | Description |
|-------|------|-------------|
| `total_modules` | `int` | Total modules in curriculum |
| `completed_modules` | `int` | Number of completed modules |
| `in_progress_modules` | `int` | Number of modules in progress |
| `not_started_modules` | `int` | Number of modules not started |
| `completion_percentage` | `float` | Overall module completion % |
| `total_credits` | `float` | Total credits across all modules |
| `completed_credits` | `float` | Credits from completed modules |
| `credits_percentage` | `float` | Credits completion % |
| `module_gpa` | `float\|null` | Average of completed module grades |

---

## Status Values

### Module Status

| Status | Description |
|--------|-------------|
| `completed` | All sub-units completed and passed |
| `in_progress` | Some sub-units completed, some in progress |
| `not_enrolled` | No sub-units enrolled yet |
| `locked` | Prerequisites not met (not implemented in API yet) |

### Unit Status (from academic_records)

| Status | Description |
|--------|-------------|
| `completed` | Unit completed and grade finalized |
| `enrolled` | Currently enrolled in course |
| `in_progress` | Course in progress |
| `withdrawn` | Withdrawn from course |
| `failed` | Failed the course |

---

## Grade Calculation Logic

### Graded Modules

Module grade = Weighted average of graded sub-units

```
Formula:
grade = Σ(unit_grade × unit_weight) / Σ(unit_weight)

Example:
MATH: 4.5 × 50 = 225
PHYSICS: 3.5 × 50 = 175
─────────────────────
Total: 400 / 100 = 4.0
```

### Pass/Fail Units

- Pass/Fail units DO NOT contribute to module grade
- They only affect module completion status
- Module is completed only if ALL units (including pass/fail) are passed

### Module GPA

- Calculated as simple average of completed module grades
- Only includes modules with `grading_type = 'grade'`
- Pass/Fail modules excluded from GPA

---

## Prerequisites Checking

### Logic

1. If module has no `prerequisite_module_id` → Always met
2. If prerequisite module exists:
   - Check prerequisite module's progress status
   - `prerequisites_met = true` only if prerequisite status = `completed`

### Impact

- `prerequisites_met: false` should disable enrollment in UI
- Student cannot enroll in units of a locked module
- Admin can override in business logic layer if needed

---

## Error Responses

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found

```json
{
  "success": false,
  "message": "Student has no assigned curriculum"
}
```

```json
{
  "success": false,
  "message": "Module not found in your curriculum"
}
```

---

## Rate Limiting

- Standard API rate limit applies
- Currently: Not specifically limited for module endpoints
- Consider adding if needed: `student.api.rate:student-modules`

---

## Testing

### Test with Tinker

```php
php artisan tinker

use App\Models\Student;
use App\Services\ModuleProgressService;

$student = Student::find(1);
$service = app(ModuleProgressService::class);

// Get curriculum modules
$modules = $student->curriculumVersion->curriculumModules;

// Calculate progress for each
foreach ($modules as $cm) {
    $progress = $service->calculateModuleProgress($cm->module, $student);
    dump($progress);
}
```

### Test with HTTP Client

```bash
# Get all modules
curl -X GET "https://api.example.com/api/v1/student/modules" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Get module detail
curl -X GET "https://api.example.com/api/v1/student/modules/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Get roadmap
curl -X GET "https://api.example.com/api/v1/student/modules/roadmap" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Get dashboard widget
curl -X GET "https://api.example.com/api/v1/student/modules/dashboard" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Implementation Notes

### Performance Considerations

1. **Eager Loading**: Controller uses `with()` to prevent N+1 queries
2. **Caching**: Consider caching module progress for active students
3. **Background Jobs**: For large curricula, consider async calculation

### Data Consistency

- Module grades calculated on-the-fly from `academic_records`
- Always reflects current student progress
- No need for grade synchronization jobs

### Future Enhancements

1. Add `locked` status when prerequisites not met
2. Add filtering by year/semester/status
3. Add sorting options
4. Add module grade history/trends
5. Add comparison with cohort averages