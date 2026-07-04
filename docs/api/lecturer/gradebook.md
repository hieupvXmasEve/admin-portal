# Lecturer Gradebook API

The Gradebook API is the lecturer-facing grading workspace contract. It returns
a matrix-first payload for compact gradable item tracking and batch score saves.

## Get Gradebook

**GET** `/api/v1/lecturer/courses/{courseOfferingId}/gradebook`

Returns the authenticated lecturer's gradebook for a course offering they teach.
Students include confirmed, registered, and completed course registrations.
Assessment items are derived from the course offering's syllabus template.

```typescript
interface LecturerGradebookResponse {
  success: boolean;
  message: string;
  data: LecturerGradebook;
}

interface LecturerGradebook {
  course_offering: {
    id: number;
    course_code: string;
    course_title: string;
    section_code: string | null;
    semester: { id: number; name: string } | null;
  };
  summary: {
    students: number;
    items: number;
    graded_cells: number;
    pending_cells: number;
    missing_cells: number;
    class_average: number | null;
  };
  items: GradebookItem[];
  students: GradebookStudentRow[];
}

interface GradebookItem {
  detail_id: number;
  component_id: number;
  component_code: string;
  component_name: string;
  detail_name: string;
  type: string;
  weight: number;
  component_weight: number;
  max_points: number;
  editable: boolean;
  graded_count: number;
  total_students: number;
  pending_count: number;
  missing_count: number;
  average_percentage: number | null;
}

interface GradebookStudentRow {
  student_id: number;
  student_code: string;
  full_name: string;
  email: string | null;
  cells: GradebookCell[];
  final_percentage: number | null;
  final_letter_grade: string | null;
  grade_display: GradeDisplay | null;
}

interface GradebookCell {
  detail_id: number;
  score_id: number | null;
  points_earned: number | null;
  percentage_score: number | null;
  status: string;
  score_status: string | null;
  editable: boolean;
}
```

## Save Gradebook Scores

**POST** `/api/v1/lecturer/courses/{courseOfferingId}/gradebook/scores`

Saves multiple edited matrix cells as one all-or-nothing operation. Scores are
entered as points against each item's `max_points`; the backend calculates
percentage and sets each saved cell to `status = graded`. A single invalid cell
rejects the whole request.

```typescript
interface SaveGradebookScoresRequest {
  scores: Array<{
    detail_id: number;
    student_id: number;
    points_earned: number;
  }>;
}

interface SaveGradebookScoresResponse {
  success: boolean;
  message: string;
  data: {
    created: Array<{ student_id: number; detail_id: number; score_id: number }>;
    updated: Array<{ student_id: number; detail_id: number; score_id: number }>;
    total_saved: number;
    aggregated: boolean;
  };
}
```

## Validation

- `detail_id` must belong to the course offering's current syllabus template.
- `student_id` must be enrolled in the course offering.
- `points_earned` is required, numeric, and cannot exceed the item's
  `max_points`.
- Duplicate `{detail_id, student_id}` cells in one request are rejected.

Validation failures use the standard API envelope:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "field": "scores.0.points_earned",
      "detail": "Points earned may not be greater than 100."
    }
  ]
}
```

## Authorization

The authenticated lecturer must have a class session for the course offering.
Unauthorized course offerings return `403`.
