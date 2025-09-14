# Lecturer Assessment Management API

This document provides TypeScript interface definitions and usage examples for the lecturer assessment management endpoints.

## Base Endpoint
```
GET /api/v1/lecturer/courses/{courseOfferingId}/assessments
```
## TypeScript Interfaces

### Main Response Interface

```typescript
interface AssessmentStructureResponse {
  success: boolean;
  message: string;
  data: {
    components: AssessmentComponent[];
    total_weight: number;
    is_complete: boolean;
    statistics: AssessmentStatistics;
    course_offering: CourseOfferingInfo;
  };
}

interface AssessmentComponent {
  id: number;
  name: string;
  type: string;
  type_name: string;
  weight: number;
  is_required_to_sit_final_exam: boolean;
  details: AssessmentDetail[];
  grading_statistics: GradingStatistics;
  submission_counts: SubmissionCounts;
}

interface AssessmentDetail {
  id: number;
  name: string;
  weight: number;
  grading_statistics: DetailGradingStatistics;
  submission_counts: DetailSubmissionCounts;
}

interface GradingStatistics {
  average_score: number;
  highest_score: number;
  lowest_score: number;
  total_submissions: number;
  graded_submissions: number;
  pending_submissions: number;
}

interface DetailGradingStatistics {
  average_score: number;
  highest_score: number;
  lowest_score: number;
  total_graded: number;
}

interface SubmissionCounts {
  total: number;
  submitted: number;
  graded: number;
  final: number;
  late: number;
  plagiarism_flagged: number;
}

interface DetailSubmissionCounts {
  total: number;
  submitted: number;
  graded: number;
  final: number;
  late: number;
  plagiarism_flagged: number;
}

interface AssessmentStatistics {
  total_components: number;
  total_details: number;
  graded_submissions: number;
  pending_submissions: number;
}

interface CourseOfferingInfo {
  id: number;
  course_code: string;
  course_title: string;
  section_code: string;
}
```

### Assessment Types

```typescript
type AssessmentType = 
  | 'assignment'
  | 'quiz' 
  | 'exam'
  | 'project'
  | 'presentation'
  | 'lab_work'
  | 'participation'
  | 'other';

type ScoreStatus = 
  | 'draft'
  | 'provisional' 
  | 'final';

type SubmissionStatus = 
  | 'not_submitted'
  | 'submitted' 
  | 'graded';
```

## API Usage Examples

## HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| `200` | Success - Assessment structure retrieved |
| `403` | Forbidden - Unauthorized access to course offering |
| `404` | Not Found - Course offering not found |
| `422` | Unprocessable Entity - Validation errors |
| `500` | Internal Server Error - Server processing error |

## Response Examples

### Success Response

```json
{
  "success": true,
  "message": "Assessment structure retrieved successfully",
  "data": {
    "components": [
      {
        "id": 1,
        "name": "Assignment 1",
        "type": "assignment",
        "type_name": "Assignment",
        "weight": 25,
        "is_required_to_sit_final_exam": false,
        "details": [
          {
            "id": 1,
            "name": "Written Report",
            "weight": 15,
            "grading_statistics": {
              "average_score": 82.5,
              "highest_score": 95,
              "lowest_score": 65,
              "total_graded": 28
            },
            "submission_counts": {
              "total": 30,
              "submitted": 30,
              "graded": 28,
              "final": 28,
              "late": 3,
              "plagiarism_flagged": 1
            }
          }
        ],
        "grading_statistics": {
          "average_score": 82.5,
          "highest_score": 95,
          "lowest_score": 65,
          "total_submissions": 30,
          "graded_submissions": 28,
          "pending_submissions": 2
        },
        "submission_counts": {
          "total": 30,
          "submitted": 30,
          "graded": 28,
          "final": 28,
          "late": 3,
          "plagiarism_flagged": 1
        }
      }
    ],
    "total_weight": 100,
    "is_complete": true,
    "statistics": {
      "total_components": 4,
      "total_details": 8,
      "graded_submissions": 112,
      "pending_submissions": 8
    },
    "course_offering": {
      "id": 123,
      "course_code": "COS30043",
      "course_title": "Interface Design and Development",
      "section_code": "HD"
    }
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Unauthorized access to course offering",
  "data": {}
}
```
