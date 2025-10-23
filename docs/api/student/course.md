# Student Course Detail API

## Endpoint
`GET /api/v1/student/courses/{courseOfferingId}/detail`

## Description
Retrieves detailed information about a specific course including course info, class schedule, and grades. This endpoint returns assessment groups with their details in a table-ready format, plus the total grade from academic records.

## Authentication
Requires authentication with student credentials via Sanctum token.

## Request Parameters

### Path Parameters
- `courseOfferingId` (integer, required): The ID of the course offering

## Response Structure

### TypeScript Interfaces

```typescript
interface CourseDetailResponse {
  success: boolean;
  message: string;
  data: CourseDetailData;
}

interface CourseDetailData {
  course_info: CourseInfo;
  schedule: ClassSession[];
  grades: GradesData;
}

interface CourseInfo {
  id: number;
  code: string;
  name: string;
  credit_points: number;
  section_code: string | null;
  semester: {
    id: number;
    name: string;
    code: string;
  };
  lecturer: {
    id: number;
    name: string;
    email: string;
  } | null;
}

interface ClassSession {
  id: number;
  session_title: string | null;
  session_date: string | null; // YYYY-MM-DD
  start_time: string | null; // HH:mm
  end_time: string | null; // HH:mm
  duration_minutes: number | null;
  session_type: string | null;
  delivery_mode: string | null;
  status: string | null;
  room: {
    id: number;
    code: string;
    name: string;
    building: string | null;
    capacity: number | null;
  } | null;
  learning_objectives: string | null;
  required_materials: string | null;
  topics_covered: string | null;
  online_meeting_url: string | null;
  student_instructions: string | null;
  lecturer: {
    id: number;
    name: string;
    email: string;
  } | null;
}

interface GradesData {
  assessment_groups: AssessmentGroup[];
  total_grade: TotalGrade;
}

interface AssessmentGroup {
  group_id: number;
  group_name: string;
  group_code: string | null;
  group_type: string; // 'quiz', 'assignment', 'project', 'exam', 'online_activity', 'attendance', 'other'
  group_weight: number; // Percentage weight (0-100)
  details: AssessmentDetail[];
}

interface AssessmentDetail {
  id: number;
  name: string;
  due_date: string | null; // YYYY-MM-DD
  points_earned: number | null;
  max_points: number | null;
  percentage_score: number | null; // 0-100
  letter_grade: string | null;
  status: string | null; // 'submitted', 'graded', etc.
  graded_at: string | null; // YYYY-MM-DD
}

interface TotalGrade {
  final_percentage: number | null; // 0-100
  final_letter_grade: string | null; // 'A+', 'A', 'B+', etc.
  grade_points: number | null; // GPA points (0-4.0)
  grade_status: string | null; // 'in_progress', 'provisional', 'final', etc.
  completion_status: string | null; // 'enrolled', 'completed', 'withdrawn', 'failed', 'incomplete', 'in_progress'
}
```

## Response Example

```json
{
  "success": true,
  "message": "Course details retrieved successfully",
  "data": {
    "course_info": {
      "id": 123,
      "code": "COMP101",
      "name": "Introduction to Programming",
      "credit_points": 12.5,
      "section_code": "A",
      "semester": {
        "id": 5,
        "name": "Semester 1, 2024",
        "code": "2024-S1"
      },
      "lecturer": {
        "id": 45,
        "name": "Dr. John Smith",
        "email": "john.smith@university.edu"
      }
    },
    "schedule": [
      {
        "id": 1,
        "session_title": "Week 1 - Introduction to Python",
        "session_date": "2024-03-01",
        "start_time": "09:00",
        "end_time": "11:00",
        "duration_minutes": 120,
        "session_type": "lecture",
        "delivery_mode": "on_campus",
        "status": "scheduled",
        "room": {
          "id": 10,
          "code": "EN201",
          "name": "Engineering Building Room 201",
          "building": "Engineering Building",
          "capacity": 50
        },
        "learning_objectives": "Understand Python basics and syntax",
        "required_materials": "Laptop with Python installed",
        "topics_covered": "Variables, data types, basic operators",
        "online_meeting_url": null,
        "student_instructions": "Bring your laptop",
        "lecturer": {
          "id": 45,
          "name": "Dr. John Smith",
          "email": "john.smith@university.edu"
        }
      }
    ],
    "grades": {
      "assessment_groups": [
        {
          "group_id": 1,
          "group_name": "Assignments",
          "group_code": "ASG",
          "group_type": "assignment",
          "group_weight": 40.0,
          "details": [
            {
              "id": 101,
              "name": "Assignment 1 - Python Basics",
              "due_date": "2024-03-15",
              "points_earned": 85,
              "max_points": 100,
              "percentage_score": 85.0,
              "letter_grade": "HD",
              "status": "graded",
              "graded_at": "2024-03-20"
            },
            {
              "id": 102,
              "name": "Assignment 2 - Data Structures",
              "due_date": "2024-04-15",
              "points_earned": 90,
              "max_points": 100,
              "percentage_score": 90.0,
              "letter_grade": "HD",
              "status": "graded",
              "graded_at": "2024-04-20"
            }
          ]
        },
        {
          "group_id": 2,
          "group_name": "Quizzes",
          "group_code": "QZ",
          "group_type": "quiz",
          "group_weight": 20.0,
          "details": [
            {
              "id": 201,
              "name": "Quiz 1",
              "due_date": "2024-03-22",
              "points_earned": 18,
              "max_points": 20,
              "percentage_score": 90.0,
              "letter_grade": "HD",
              "status": "graded",
              "graded_at": "2024-03-22"
            }
          ]
        },
        {
          "group_id": 3,
          "group_name": "Final Exam",
          "group_code": "EXAM",
          "group_type": "exam",
          "group_weight": 40.0,
          "details": [
            {
              "id": 301,
              "name": "Final Examination",
              "due_date": "2024-06-15",
              "points_earned": null,
              "max_points": 100,
              "percentage_score": null,
              "letter_grade": null,
              "status": "not_submitted",
              "graded_at": null
            }
          ]
        }
      ],
      "total_grade": {
        "final_percentage": 87.5,
        "final_letter_grade": "HD",
        "grade_points": 4.0,
        "grade_status": "provisional",
        "completion_status": "in_progress"
      }
    }
  }
}
```

## Error Responses

### Not Enrolled (400)
```json
{
  "success": false,
  "message": "You are not enrolled in this course",
  "data": null
}
```

### Course Not Found (404)
```json
{
  "success": false,
  "message": "Course not found",
  "data": null
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthenticated",
  "data": null
}
```

## Notes

### Grade Data Source
- All grade data (`assessment_groups` and their `details`) comes directly from Canvas LMS synchronization
- No calculations are performed on the backend - data is displayed as-is from Canvas
- `total_grade` comes from the `academic_records` table which is also synced from Canvas

### Assessment Groups vs Details
- **Assessment Groups**: Represent Canvas assignment groups (e.g., "Assignments", "Quizzes", "Exams")
- **Details**: Individual assignments/assessments within each group
- Each group has a weight that represents its contribution to the final grade (sum of all group weights = 100%)

### Grade Status Values
- `in_progress`: Course is ongoing, grades may change
- `provisional`: Grades are preliminary, not yet finalized
- `final`: Grades are locked and finalized
- `incomplete`: Student has incomplete status
- `withdrawn`: Student withdrew from course
- `failed`: Student failed the course
- `pass_no_credit`: Passed but no credit awarded
- `audit`: Student is auditing the course
- `transfer_credit`: Credit transferred from another institution

### Completion Status Values
- `enrolled`: Student is currently enrolled
- `in_progress`: Course is actively in progress
- `completed`: Course successfully completed
- `failed`: Course failed
- `withdrawn`: Student withdrew
- `incomplete`: Incomplete status assigned

## Usage Example

```typescript
// Fetch course detail
const response = await api.get<CourseDetailResponse>(
  `/api/v1/student/courses/${courseOfferingId}/detail`
);

const { course_info, schedule, grades } = response.data.data;

// Display course info
console.log(`${course_info.code} - ${course_info.name}`);
console.log(`Lecturer: ${course_info.lecturer?.name}`);

// Display assessment groups and details in a table
grades.assessment_groups.forEach(group => {
  console.log(`\n${group.group_name} (${group.group_weight}%)`);
  group.details.forEach(detail => {
    console.log(`  ${detail.name}: ${detail.points_earned}/${detail.max_points}`);
  });
});

// Display total grade
console.log(`\nTotal: ${grades.total_grade.final_percentage}% - ${grades.total_grade.final_letter_grade}`);
```

## Migration Notes (for Frontend Developers)

### What Changed from Old API

**Old Structure:**
```typescript
// Old: grades was array of components with nested assessments
{
  grades: [
    {
      component_id: 1,
      component_name: "Assignments",
      assessments: [/* nested array */]
    }
  ]
}
```

**New Structure:**
```typescript
// New: grades is object with assessment_groups array and total_grade
{
  grades: {
    assessment_groups: [
      {
        group_id: 1,
        group_name: "Assignments",
        details: [/* flattened array */]
      }
    ],
    total_grade: {/* total grade from academic_records */}
  }
}
```

### Key Changes:
1. `grades` is now an object (not array) containing `assessment_groups` and `total_grade`
2. Renamed: `component_*` → `group_*`
3. Renamed: `assessments` → `details`
4. Removed: Nested submission/feedback objects - now flat structure
5. Added: `total_grade` object from `academic_records` table
6. Simplified: Each detail only contains essential fields for display

### Update Your Types:
Replace your old `CourseGrade` type with the new `GradesData` interface above.
