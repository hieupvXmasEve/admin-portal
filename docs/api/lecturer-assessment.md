# Lecturer Grading Endpoints and Reporting API Documentation

This document provides comprehensive API documentation for the grading and assessment reporting endpoints in the lecturer API. These features enable lecturers to view, manage, and update student grades and generate reports from their course assessments.

## Table of Contents

1. [Grading Endpoints](#grading-endpoints)
   - [Get Grading Data by Student](#1-get-grading-data-by-student)
   - [Get Grading Data by Assessment Component](#2-get-grading-data-by-assessment-component)
   - [Update Individual Grade](#3-update-individual-grade)
   - [Bulk Update Grades](#4-bulk-update-grades)
2. [Reporting Endpoints](#reporting-endpoints)
   - [Get Overview Statistics](#5-get-overview-statistics)
   - [Get Grade Matrix](#6-get-grade-matrix)
   - [Get Detailed Statistics](#7-get-detailed-statistics)
   - [Export Report to Excel](#8-export-report-to-excel)
   - [Export Report to PDF](#9-export-report-to-pdf)

---

## Authentication  Authorization

All endpoints for grading and reporting require: 
- **Authentication**: Valid Sanctum token in Authorization header.
- **Authorization**: Lecturer must be assigned to the specified course offering.
- **Middleware**: auth:sanctum, lecturer.api.auth, lecturer.api.rate:lecturer-api, api.logging.

**Headers Required:**
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
Accept: application/json
```

---

## Common Response Formats

### Success Response Structure
```json
{
 "success": true,
 "message": "Operation completed successfully",
 "data": {
 // Response data specific to endpoint
 }
}
```

### Error Response Structure
```json
{
 "success": false,
 "message": "Error description",
 "errors": {
 // Validation errors or error details
 },
 "error_code": "ERROR_CODE",
 "status_code": 422
}
```

---

## Error Handling

### Common HTTP Status Codes

| Status Code | Description | When It Occurs |
|-------------|-------------|----------------|
| 200 | Success | Request completed successfully |
| 401 | Unauthorized | Invalid or missing authentication token |
| 403 | Forbidden | Lecturer not authorized for this course offering |
| 404 | Not Found | Course offering, student, or score not found |
| 422 | Unprocessable Entity | Validation errors in request data |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected server error |

### Common Error Codes

- `UNAUTHORIZED`: Access denied to course offering
- `VALIDATION_ERROR`: Input validation failed
- `INVALID_SCORE`: Score doesn't belong to course offering
- `PARTIAL_FAILURE`: Some operations in bulk request failed
- `SERVER_ERROR`: Internal server error

---

## Assessment Management Endpoints

### 0. Get Assessment Structure (Index)

Retrieves the complete assessment structure for a course offering, including all assessment components, their details, statistics, and weight calculations.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments
```

#### **Route Name**
`lecturer.assessments.index`

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
None

#### **Request Body**
None

#### **Success Response (200)**
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
        "weight": 25.0,
        "is_required_to_sit_final_exam": false,
        "details": [
          {
            "id": 1,
            "name": "Part A - Research Component",
            "weight": 60.0,
            "grading_statistics": {
              "average_score": 78.5,
              "highest_score": 98.0,
              "lowest_score": 45.0,
              "total_graded": 42
            },
            "submission_counts": {
              "total": 45,
              "submitted": 43,
              "graded": 42,
              "final": 42,
              "late": 3,
              "plagiarism_flagged": 1
            }
          },
          {
            "id": 2,
            "name": "Part B - Analysis Component",
            "weight": 40.0,
            "grading_statistics": {
              "average_score": 82.1,
              "highest_score": 95.0,
              "lowest_score": 52.0,
              "total_graded": 40
            },
            "submission_counts": {
              "total": 45,
              "submitted": 41,
              "graded": 40,
              "final": 40,
              "late": 2,
              "plagiarism_flagged": 0
            }
          }
        ],
        "grading_statistics": {
          "average_score": 80.2,
          "highest_score": 96.5,
          "lowest_score": 48.5,
          "total_submissions": 90,
          "graded_submissions": 82,
          "pending_submissions": 8
        },
        "submission_counts": {
          "total": 90,
          "submitted": 84,
          "graded": 82,
          "final": 82,
          "late": 5,
          "plagiarism_flagged": 1
        }
      },
      {
        "id": 2,
        "name": "Midterm Exam",
        "type": "exam",
        "type_name": "Examination",
        "weight": 30.0,
        "is_required_to_sit_final_exam": true,
        "details": [
          {
            "id": 3,
            "name": "Written Examination",
            "weight": 100.0,
            "grading_statistics": {
              "average_score": 74.8,
              "highest_score": 92.0,
              "lowest_score": 38.0,
              "total_graded": 44
            },
            "submission_counts": {
              "total": 45,
              "submitted": 44,
              "graded": 44,
              "final": 44,
              "late": 0,
              "plagiarism_flagged": 0
            }
          }
        ],
        "grading_statistics": {
          "average_score": 74.8,
          "highest_score": 92.0,
          "lowest_score": 38.0,
          "total_submissions": 45,
          "graded_submissions": 44,
          "pending_submissions": 1
        },
        "submission_counts": {
          "total": 45,
          "submitted": 44,
          "graded": 44,
          "final": 44,
          "late": 0,
          "plagiarism_flagged": 0
        }
      }
    ],
    "total_weight": 55.0,
    "is_complete": false,
    "statistics": {
      "total_components": 2,
      "total_details": 3,
      "graded_submissions": 126,
      "pending_submissions": 9
    },
    "course_offering": {
      "id": 15,
      "course_code": "CS101",
      "course_title": "Introduction to Computer Science",
      "section_code": "001"
    }
  }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
  ```json
  {
    "success": false,
    "message": "Unauthorized access to course offering",
    "errors": [],
    "error_code": "UNAUTHORIZED",
    "status_code": 403
  }
  ```
- **500 Internal Server Error**: Failed to retrieve assessment structure
  ```json
  {
    "success": false,
    "message": "Failed to retrieve assessment structure",
    "errors": [],
    "error_code": "SERVER_ERROR",
    "status_code": 500
  }
  ```

#### **Authentication/Authorization Requirements**
- **Authentication**: Valid Sanctum token required
- **Authorization**: Lecturer must be assigned to the specified course offering
- **Permissions**: Lecturer must have access to view assessments for the course
- **Rate Limiting**: Subject to `lecturer-assessment-management` rate limiting

#### **Business Logic**
- Verifies lecturer authorization for the course offering
- Retrieves syllabus associated with the course offering
- Fetches all assessment components with their details and associated scores
- Calculates comprehensive statistics for each component and detail:
  - **Grading Statistics**: Average, highest, lowest scores and submission counts
  - **Submission Counts**: Total, submitted, graded, final, late, and flagged submissions
- Determines if assessment structure is complete (total weight = 100%)
- Returns hierarchical structure with components, details, and nested statistics
- Handles cases where no syllabus or components exist gracefully

#### **Data Structure Details**

**Component Object:**
- `id`: Unique identifier for the assessment component
- `name`: Display name of the component (e.g., "Assignment 1")
- `type`: Component type identifier (e.g., "assignment", "exam")
- `type_name`: Human-readable type name (e.g., "Assignment", "Examination")
- `weight`: Percentage weight of component in final grade
- `is_required_to_sit_final_exam`: Boolean indicating if component is prerequisite for final exam
- `details`: Array of component details (sub-assessments)
- `grading_statistics`: Aggregated statistics across all component details
- `submission_counts`: Aggregated submission counts across all component details

**Detail Object:**
- `id`: Unique identifier for the assessment detail
- `name`: Display name of the detail (e.g., "Part A - Research Component")
- `weight`: Percentage weight within the parent component
- `grading_statistics`: Statistics specific to this detail
- `submission_counts`: Submission counts specific to this detail

**Statistics Object:**
- `average_score`: Mean percentage score for graded submissions
- `highest_score`: Maximum percentage score achieved
- `lowest_score`: Minimum percentage score achieved
- `total_graded`: Number of submissions with finalized grades

**Submission Counts Object:**
- `total`: Total number of score records
- `submitted`: Number of submissions marked as submitted
- `graded`: Number of submissions that have been graded
- `final`: Number of submissions with finalized grades
- `late`: Number of late submissions
- `plagiarism_flagged`: Number of submissions flagged for plagiarism

#### **Usage Examples**

**Basic Request:**
```bash
curl -X GET \
  "https://api.swinx.edu.au/api/v1/lecturer/courses/15/assessments" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

**JavaScript/Axios Example:**
```javascript
const response = await axios.get('/api/v1/lecturer/courses/15/assessments', {
  headers: {
    'Authorization': 'Bearer ' + token,
    'Accept': 'application/json'
  }
});

const assessmentData = response.data.data;
console.log('Total Weight:', assessmentData.total_weight);
console.log('Is Complete:', assessmentData.is_complete);
console.log('Components:', assessmentData.components.length);
```

**Vue.js Composable Usage:**
```javascript
// In a Vue component or composable
const { data: assessmentStructure, error, loading } = await useLecturerApi().get(
  `/courses/${courseOfferingId}/assessments`
);

if (assessmentStructure.value) {
  const totalWeight = assessmentStructure.value.total_weight;
  const isComplete = assessmentStructure.value.is_complete;
  const components = assessmentStructure.value.components;
}
```

#### **Performance Considerations**
- **Database Optimization**: Uses eager loading to minimize N+1 queries
- **Caching**: Consider caching results for frequently accessed course offerings
- **Rate Limiting**: Subject to lecturer assessment management rate limits
- **Memory Usage**: Large courses with many components may require memory optimization

#### **Integration Notes**
- **Frontend Usage**: Primary endpoint for assessment management interfaces
- **Real-time Updates**: Consider WebSocket integration for live statistics updates
- **Filtering**: Future versions may support filtering by component type or status
- **Pagination**: Currently returns all components; pagination may be needed for large syllabi

---

## Grading Endpoints

### 1. Get Grading Data by Student

Retrieves comprehensive grading data for a specific student across all assessments in a course offering.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/grade/student/{student}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `student` | integer | Yes | ID of the student |

#### **Query Parameters**
None

#### **Request Body**
None

#### **Success Response (200)**
```json
{
 "success": true,
 "message": "Student grading data retrieved successfully",
 "data": {
 "student": {
 "id": 123,
 "student_id": "s2023001234",
 "name": "John Smith",
 "first_name": "John",
 "last_name": "Smith",
 "email": "john.smith@student.swin.edu.au"
 },
 "assessments": [
 {
 "id": 1,
 "name": "Assignment 1",
 "code": "ASS1",
 "type": "assignment",
 "type_name": "Assignment",
 "weight": 25.0,
 "due_date": "2024-03-15T23:59:59.000Z",
 "is_required_to_sit_final_exam": false,
 "details": [
 {
 "id": 1,
 "name": "Part A - Research Component",
 "weight": 60.0,
 "max_points": 100,
 "due_date": "2024-03-15T23:59:59.000Z",
 "score": {
 "id": 456,
 "points_earned": 85,
 "percentage_score": 85.0,
 "letter_grade": "B+",
 "status": "graded",
 "score_status": "final",
 "is_late": false,
 "minutes_late": 0,
 "late_penalty_applied": 0.0,
 "bonus_points": 0,
 "score_excluded": false,
 "plagiarism_suspected": false,
 "integrity_status": "clean",
 "instructor_feedback": "Good analysis and well-structured argument. Consider expanding on the conclusion.",
 "graded_at": "2024-03-16T10:30:00.000Z"
 }
 }
 ]
 }
 ],
 "summary": {
 "total_assessments": 4,
 "completed_assessments": 3,
 "pending_assessments": 1,
 "overall_percentage": 76.5,
 "weighted_total_score": 306.0,
 "total_weight_completed": 75.0
 }
 }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **404 Not Found**: Student not found or not enrolled in course offering

#### **Business Logic**
- Verifies lecturer has access to the course offering
- Retrieves all assessment components for the course
- Gets all scores for the specified student
- Calculates summary statistics including completion rates and overall performance
- Formats scores with detailed information including late penalties, bonuses, and academic integrity flags

#### **Usage Example**
```bash
curl -X GET \
 "https://api.swinx.edu.au/api/v1/lecturer/courses/15/assessments/grade/student/123" \
 -H "Authorization: Bearer your_sanctum_token" \
 -H "Accept: application/json"
```

---

### 2. Get Grading Data by Assessment Component

Retrieves grading data for all students in a specific assessment component, providing a component-focused view for grading workflows.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/grade/component/{assessmentComponent}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `assessmentComponent` | integer | Yes | ID of the assessment component |

#### **Success Response (200)**
```json
{
 "success": true,
 "message": "Assessment component grading data retrieved successfully",
 "data": {
 "assessment_component": {
 "id": 1,
 "name": "Assignment 1",
 "code": "ASS1",
 "type": "assignment",
 "type_name": "Assignment",
 "weight": 25.0,
 "due_date": "2024-03-15T23:59:59.000Z",
 "is_required_to_sit_final_exam": false
 },
 "details": [
 {
 "id": 1,
 "name": "Part A - Research Component",
 "description": "Research and analysis component focusing on theoretical foundations",
 "weight": 60.0,
 "max_points": 100,
 "due_date": "2024-03-15T23:59:59.000Z",
 "student_scores": [
 {
 "student": {
 "id": 123,
 "student_id": "s2023001234",
 "name": "John Smith"
 },
 "score": {
 "id": 456,
 "points_earned": 85,
 "percentage_score": 85.0,
 "letter_grade": "B+",
 "score_status": "final",
 "is_late": false,
 "plagiarism_suspected": false,
 "integrity_status": "clean"
 }
 }
 ],
 "statistics": {
 "total_students": 45,
 "submitted_count": 43,
 "graded_count": 42,
 "average_score": 78.5,
 "highest_score": 98.0,
 "lowest_score": 45.0,
 "completion_rate": 93.33
 }
 }
 ],
 "statistics": {
 "total_students": 45,
 "total_submissions": 43,
 "graded_submissions": 42,
 "average_score": 78.5,
 "completion_rate": 93.33
 }
 }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **404 Not Found**: Assessment component not found or doesn't belong to course

#### **Business Logic**
- Validates assessment component belongs to the course offering's syllabus
- Retrieves all enrolled students in the course
- Gets scores for each student in the assessment component
- Calculates detailed statistics for each assessment detail
- Provides aggregated statistics for the entire component

---

### 3. Update Individual Grade

Updates a single grade entry with comprehensive validation and audit trail.

#### **HTTP Method and URL**
```
PUT /api/v1/lecturer/courses/{courseOffering}/assessments/scores/{score}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `score` | integer | Yes | ID of the assessment component detail score |

#### **Request Body Parameters**
| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `points_earned` | numeric | No | ≥ 0, ≤ max_points | Points awarded to student |
| `percentage_score` | numeric | No | 0-100 | Percentage score (0-100) |
| `letter_grade` | string | No | Valid grade values | Letter grade (A+, A, A-, B+, B, B-, C+, C, C-, D+, D, F, I, W, P, NP) |
| `status` | string | No | Valid status values | Submission status (not_submitted, submitted, grading, graded, returned) |
| `score_status` | string | No | Valid score status | Score status (draft, provisional, final) |
| `instructor_feedback` | string | No | Max 1000 chars | Feedback for student |
| `private_notes` | string | No | Max 1000 chars | Private notes for lecturer |
| `bonus_points` | numeric | No | 0-100 | Additional bonus points |
| `bonus_reason` | string | No | Max 255 chars | Reason for bonus points (required if bonus_points provided) |
| `score_excluded` | boolean | No | - | Whether to exclude score from calculations |
| `exclusion_reason` | string | No | Max 255 chars | Reason for exclusion (required if score_excluded is true) |
| `late_excuse_approved` | boolean | No | - | Whether late excuse is approved |
| `late_excuse_reason` | string | No | Max 500 chars | Reason for late excuse |
| `plagiarism_suspected` | boolean | No | - | Flag for suspected plagiarism |
| `plagiarism_score` | numeric | No | 0-100 | Plagiarism detection score (required if plagiarism_suspected is true) |
| `plagiarism_notes` | string | No | Max 1000 chars | Notes about plagiarism concerns |
| `integrity_status` | string | No | Valid integrity status | Academic integrity status (clean, flagged, under_review, violation_confirmed, cleared) |
| `appeal_requested` | boolean | No | - | Whether student requested appeal |
| `appeal_reason` | string | No | Max 500 chars | Reason for appeal (required if appeal_requested is true) |
| `appeal_status` | string | No | Valid appeal status | Appeal status (pending, under_review, approved, denied) |

#### **Request Example**
```json
{
 "points_earned": 85,
 "percentage_score": 85.0,
 "letter_grade": "B+",
 "score_status": "final",
 "instructor_feedback": "Good work overall. Strong analysis in part A, but could improve the conclusion section. Consider reviewing the rubric for part C.",
 "bonus_points": 2,
 "bonus_reason": "Excellent presentation during class discussion"
}
```

#### **Success Response (200)**
```json
{
 "success": true,
 "message": "Grade updated successfully",
 "data": {
 "id": 456,
 "points_earned": 85,
 "percentage_score": 85.0,
 "letter_grade": "B+",
 "status": "graded",
 "score_status": "final",
 "is_late": false,
 "minutes_late": 0,
 "late_penalty_applied": 0.0,
 "late_excuse_approved": false,
 "bonus_points": 2,
 "bonus_reason": "Excellent presentation during class discussion",
 "score_excluded": false,
 "plagiarism_suspected": false,
 "plagiarism_score": null,
 "integrity_status": "clean",
 "appeal_requested": false,
 "instructor_feedback": "Good work overall. Strong analysis in part A, but could improve the conclusion section.",
 "private_notes": null,
 "graded_by_lecture_id": 789,
 "graded_at": "2024-03-20T14:30:00.000Z",
 "last_modified_by_lecture_id": 789,
 "last_modified_at": "2024-03-20T14:30:00.000Z",
 "created_at": "2024-03-01T09:00:00.000Z",
 "updated_at": "2024-03-20T14:30:00.000Z"
 }
}
```

#### **Validation Rules**
- Points earned cannot exceed the maximum points for the assessment detail
- Percentage score must be consistent with points earned if both are provided
- Final scores must have either points earned or percentage score
- Bonus reason is required when awarding bonus points
- Exclusion reason is required when excluding a score
- Plagiarism score is required when flagging for plagiarism
- Appeal reason is required when requesting an appeal

#### **Error Responses**
- **422 Validation Error**: Invalid input data
- **404 Not Found**: Score not found or doesn't belong to course offering

#### **Business Logic**
- Validates score belongs to the course offering
- Updates score with provided data and adds audit metadata
- Automatically sets grading timestamps and lecturer information
- Maintains consistency between points and percentage scores
- Creates audit trail for grade changes

---

### 4. Bulk Update Grades

Updates multiple grades in a single transaction for efficient bulk grading operations.

#### **HTTP Method and URL**
```
POST /api/v1/lecturer/courses/{courseOffering}/assessments/scores/bulk-update
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Request Body Parameters**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `scores` | array | Yes | Array of score updates (1-100 items) |
| `scores[].id` | integer | Yes | ID of the score to update |
| `scores[].points_earned` | numeric | No | Points awarded |
| `scores[].percentage_score` | numeric | No | Percentage score (0-100) |
| `scores[].letter_grade` | string | No | Letter grade |
| `scores[].score_status` | string | No | Score status |
| `scores[].instructor_feedback` | string | No | Feedback (max 1000 chars) |
| `scores[].bonus_points` | numeric | No | Bonus points (0-100) |
| `scores[].bonus_reason` | string | No | Bonus reason |
| `scores[].score_excluded` | boolean | No | Exclude from calculations |
| `scores[].exclusion_reason` | string | No | Exclusion reason |

#### **Request Example**
```json
{
 "scores": [
 {
 "id": 456,
 "points_earned": 85,
 "percentage_score": 85.0,
 "score_status": "final",
 "instructor_feedback": "Good work"
 },
 {
 "id": 457,
 "points_earned": 92,
 "percentage_score": 92.0,
 "score_status": "final",
 "bonus_points": 3,
 "bonus_reason": "Exceptional creativity"
 }
 ]
}
```

#### **Success Response (200)**
```json
{
 "success": true,
 "message": "All grades updated successfully",
 "data": {
 "updated_scores": [
 {
 "id": 456,
 "student_id": 123,
 "assessment_component_detail_id": 1
 },
 {
 "id": 457,
 "student_id": 124,
 "assessment_component_detail_id": 1
 }
 ],
 "total_updated": 2,
 "operation_completed_at": "2024-03-20T14:30:00.000Z"
 }
}
```

#### **Partial Failure Response (422)**
```json
{
 "success": false,
 "message": "Some grades could not be updated",
 "data": {
 "bulk_errors": [
 "Failed to update score ID 458: Points earned cannot exceed maximum points (100)"
 ],
 "successfully_updated": 1,
 "failed_updates": 1
 },
 "error_code": "PARTIAL_FAILURE",
 "status_code": 422
}
```

#### **Validation Constraints**
- Maximum 100 scores per bulk operation
- All scores must belong to the specified course offering
- Same validation rules as individual grade updates apply to each score
- Transaction-based processing ensures data consistency

#### **Business Logic**
- Validates all scores belong to the course offering
- Processes updates within a database transaction
- Applies same validation rules as individual updates
- Automatically adds grading metadata (timestamps, lecturer ID)
- Returns detailed information about successful and failed updates
- Rolls back entire operation if critical errors occur

#### **Usage Example**
```bash
curl -X POST \
 "https://api.swinx.edu.au/api/v1/lecturer/courses/15/assessments/scores/bulk-update" \
 -H "Authorization: Bearer your_sanctum_token" \
 -H "Content-Type: application/json" \
 -d '{
 "scores": [
 {
 "id": 456,
 "points_earned": 85,
 "percentage_score": 85.0,
 "score_status": "final"
 }
 ]
}'
```

---

## Reporting Endpoints

### 5. Get Overview Statistics

Provides a summary of statistics for the course assessments.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/report/overview
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
None

#### **Success Response (200)**
```json
{
 "success": true,
 "data": {
 "course_offering": {
 "id": 10,
 "course_code": "CS101",
 "course_title": "Introduction to Computer Science",
 "section_code": "001",
 "semester": {
 "id": 3,
 "name": "Fall",
 "year": 2024
 },
 "instructor": {
 "id": 2,
 "name": "Dr. John Doe"
 }
 },
 "statistics": {
 "enrollment_statistics": {
 "total_enrolled_students": 150,
 "active_students": 150
 },
 "assessment_structure": {
 "total_components": 5,
 "total_details": 20,
 "total_weight": 100,
 "weight_complete": true
 },
 "score_statistics": {
 "average_score": 75.5,
 "highest_score": 100,
 "lowest_score": 50,
 "total_graded_submissions": 120
 },
 "completion_statistics": {
 "overall_completion_rate": 80
 },
 "component_breakdown": []
 }
 }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **500 Internal Server Error**: Failed to generate statistics

#### **Business Logic**
- Checks lecturer authorization
- Compiles statistics from course offering data, enrollment, and assessment results
- Formats the data for easy consumption by client applications

---

### 6. Get Grade Matrix

Retrieves a comprehensive grade matrix for all students and assessments in a course.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/report/grade-matrix
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `include_excluded` | boolean | No | Include excluded scores (default: false) |
| `score_status` | string | No | Filter by score status (draft, provisional, final) |
| `student_ids` | array | No | Filter by student IDs |
| `component_ids` | array | No | Filter by component IDs |

#### **Success Response (200)**
```json
{
 "success": true,
 "data": {
 "course_offering": {
 "id": 10,
 "course_code": "CS101",
 "course_title": "Introduction to Computer Science",
 "section_code": "001"
 },
 "grade_matrix": {
 "components": [
 "Assignment 1",
 "Midterm Exam"
 ],
 "students": [
 {
 "id": 1,
 "name": "Alice Smith",
 "component_scores": [
 85,
 90
 ],
 "weighted_total": 87.5
 }
 ]
 },
 "filters_applied": {
 "include_excluded": false,
 "score_status": "final",
 "student_ids": [],
 "component_ids": []
 }
 }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **500 Internal Server Error**: Failed to generate grade matrix

#### **Business Logic**
- Ensures lecturer authorization
- Generates matrix based on current scores and optional filtering
- Provides detailed breakdown for use in grade analysis

---

### 7. Get Detailed Statistics

Provides detailed statistics and analytics for a course offering.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/report/statistics
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | No | Type of statistics (overview, score_distribution, completion, all) |

#### **Success Response (200)**
```json
{
 "success": true,
 "data": [
 {
 "course_offering": {
 "id": 10,
 "course_code": "CS101",
 "course_title": "Introduction to Computer Science",
 "section_code": "001"
 }
 }
 ],
 "statistics": [
 "overview": {},
 "score_distribution": {},
 "completion": {}
 ],
 "generated_at": "2024-10-10T12:00:00.000Z"
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **500 Internal Server Error**: Failed to generate statistics

#### **Business Logic**
- Validates lecturer access rights
- Aggregates data across various facets of course performance
- Useful for spotting trends and areas needing attention in teaching

---

### 8. Export Report to Excel

Allows the export of assessment data to an Excel format for offline analysis.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/report/export/excel
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `include_excluded` | boolean | No | Include excluded scores (default: false) |
| `score_status` | string | No | Filter by score status (draft, provisional, final) |
| `student_ids` | array | No | Filter by student IDs |
| `component_ids` | array | No | Filter by component IDs |
| `include_statistics` | boolean | No | Include statistics in the report |
| `include_grade_matrix` | boolean | No | Include grade matrix in the report |

#### **Success Response**
- **200 OK**: File is downloaded as XLSX

#### **Error Responses**
- **422 Validation Error**: Invalid input data
- **500 Internal Server Error**: Failed to export data

#### **Business Logic**
- Checks access permissions
- Formats report based on user-selected criteria
- Uses underlying services to compile and output data in Excel

---

### 9. Export Report to PDF

Allows the export of assessment data to a PDF format, providing a printable report format.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/report/export/pdf
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Query Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `include_charts` | boolean | No | Include charts in the report |
| `include_statistics` | boolean | No | Include statistics in the PDF |
| `include_grade_matrix` | boolean | No | Include grade matrix in the PDF |
| `page_orientation` | string | No | Format the page (portrait, landscape) |
| `include_student_details` | boolean | No | Include student details in the report |

#### **Success Response**
- **200 OK**: File is downloaded as PDF

#### **Error Responses**
- **422 Validation Error**: Invalid options provided
- **500 Internal Server Error**: Failed to export to PDF

#### **Business Logic**
- Validates export options
- Generates detailed, formatted PDF report
- Ensures data integrity and user access compliance

---

## Additional Considerations

### Rate Limiting
All endpoints are subject to lecturer API rate limiting. If you exceed the rate limit, you'll receive a 429 status code with retry-after headers.

### Data Validation
- All numeric scores are validated against assessment maximums
- Letter grades must match institutional grading scales
- Status values are restricted to predefined enumerations
- Text fields have maximum length constraints for database integrity

### Audit Trail
- All grade updates automatically record lecturer ID and timestamps
- Grade change history is maintained for academic integrity
- Bonus points and exclusions require explanatory reasons
- Appeals and plagiarism flags create detailed audit records

### Academic Integrity
- Plagiarism detection scores are recorded when flagging submissions
- Integrity status workflow supports investigation processes
- Appeal system maintains proper documentation
- All academic integrity actions are fully auditable

### Performance Considerations
- Bulk operations are limited to 100 scores per request
- Database queries are optimized with appropriate indexes
- Response data includes only necessary fields for UI rendering
- Caching strategies may be applied for frequently accessed data

---

## Support and Contact

For technical support or questions about these API endpoints:
- Internal Development Team: technical-support@swin.edu.au
- API Documentation: https://docs.swinx.edu.au/api
- System Status: https://status.swinx.edu.au

# Lecturer Grading Endpoints API Documentation

This document provides comprehensive API documentation for the grading endpoints in the lecturer API. These endpoints enable lecturers to view, manage, and update student grades across assessment components in their course offerings.

## Table of Contents

1. [Authentication & Authorization](#authentication--authorization)
2. [Common Response Formats](#common-response-formats)
3. [Error Handling](#error-handling)
4. [Grading Endpoints](#grading-endpoints)
   - [Get Grading Data by Student](#1-get-grading-data-by-student)
   - [Get Grading Data by Assessment Component](#2-get-grading-data-by-assessment-component)
   - [Update Individual Grade](#3-update-individual-grade)
   - [Bulk Update Grades](#4-bulk-update-grades)

---

## Authentication & Authorization

All grading endpoints require:
- **Authentication**: Valid Sanctum token in Authorization header
- **Authorization**: Lecturer must be assigned to the specified course offering
- **Rate Limiting**: Subject to lecturer API rate limits
- **Middleware**: `auth:sanctum`, `lecturer.api.auth`, `lecturer.api.rate:lecturer-api`, `api.logging`

**Headers Required:**
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
Accept: application/json
```

---

## Common Response Formats

### Success Response Structure
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data specific to endpoint
  }
}
```

### Error Response Structure
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    // Validation errors or error details
  },
  "error_code": "ERROR_CODE",
  "status_code": 422
}
```

---

## Error Handling

### Common HTTP Status Codes

| Status Code | Description | When It Occurs |
|-------------|-------------|----------------|
| 200 | Success | Request completed successfully |
| 401 | Unauthorized | Invalid or missing authentication token |
| 403 | Forbidden | Lecturer not authorized for this course offering |
| 404 | Not Found | Course offering, student, or score not found |
| 422 | Unprocessable Entity | Validation errors in request data |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected server error |

### Common Error Codes

- `UNAUTHORIZED`: Access denied to course offering
- `VALIDATION_ERROR`: Input validation failed
- `INVALID_SCORE`: Score doesn't belong to course offering
- `PARTIAL_FAILURE`: Some operations in bulk request failed
- `SERVER_ERROR`: Internal server error

---

## Grading Endpoints

### 1. Get Grading Data by Student

Retrieves comprehensive grading data for a specific student across all assessments in a course offering.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/grade/student/{student}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `student` | integer | Yes | ID of the student |

#### **Query Parameters**
None

#### **Request Body**
None

#### **Success Response (200)**
```json
{
  "success": true,
  "message": "Student grading data retrieved successfully",
  "data": {
    "student": {
      "id": 123,
      "student_id": "s2023001234",
      "name": "John Smith",
      "first_name": "John",
      "last_name": "Smith",
      "email": "john.smith@student.swin.edu.au"
    },
    "assessments": [
      {
        "id": 1,
        "name": "Assignment 1",
        "code": "ASS1",
        "type": "assignment",
        "type_name": "Assignment",
        "weight": 25.0,
        "due_date": "2024-03-15T23:59:59.000Z",
        "is_required_to_sit_final_exam": false,
        "details": [
          {
            "id": 1,
            "name": "Part A - Research Component",
            "weight": 60.0,
            "max_points": 100,
            "due_date": "2024-03-15T23:59:59.000Z",
            "score": {
              "id": 456,
              "points_earned": 85,
              "percentage_score": 85.0,
              "letter_grade": "B+",
              "status": "graded",
              "score_status": "final",
              "is_late": false,
              "minutes_late": 0,
              "late_penalty_applied": 0.0,
              "bonus_points": 0,
              "score_excluded": false,
              "plagiarism_suspected": false,
              "integrity_status": "clean",
              "instructor_feedback": "Good analysis and well-structured argument. Consider expanding on the conclusion.",
              "graded_at": "2024-03-16T10:30:00.000Z"
            }
          }
        ]
      }
    ],
    "summary": {
      "total_assessments": 4,
      "completed_assessments": 3,
      "pending_assessments": 1,
      "overall_percentage": 76.5,
      "weighted_total_score": 306.0,
      "total_weight_completed": 75.0
    }
  }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **404 Not Found**: Student not found or not enrolled in course offering

#### **Business Logic**
- Verifies lecturer has access to the course offering
- Retrieves all assessment components for the course
- Gets all scores for the specified student
- Calculates summary statistics including completion rates and overall performance
- Formats scores with detailed information including late penalties, bonuses, and academic integrity flags

#### **Usage Example**
```bash
curl -X GET \
  "https://api.swinx.edu.au/api/v1/lecturer/courses/15/assessments/grade/student/123" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Accept: application/json"
```

---

### 2. Get Grading Data by Assessment Component

Retrieves grading data for all students in a specific assessment component, providing a component-focused view for grading workflows.

#### **HTTP Method and URL**
```
GET /api/v1/lecturer/courses/{courseOffering}/assessments/grade/component/{assessmentComponent}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `assessmentComponent` | integer | Yes | ID of the assessment component |

#### **Success Response (200)**
```json
{
  "success": true,
  "message": "Assessment component grading data retrieved successfully",
  "data": {
    "assessment_component": {
      "id": 1,
      "name": "Assignment 1",
      "code": "ASS1",
      "type": "assignment",
      "type_name": "Assignment",
      "weight": 25.0,
      "due_date": "2024-03-15T23:59:59.000Z",
      "is_required_to_sit_final_exam": false
    },
    "details": [
      {
        "id": 1,
        "name": "Part A - Research Component",
        "description": "Research and analysis component focusing on theoretical foundations",
        "weight": 60.0,
        "max_points": 100,
        "due_date": "2024-03-15T23:59:59.000Z",
        "student_scores": [
          {
            "student": {
              "id": 123,
              "student_id": "s2023001234",
              "name": "John Smith"
            },
            "score": {
              "id": 456,
              "points_earned": 85,
              "percentage_score": 85.0,
              "letter_grade": "B+",
              "score_status": "final",
              "is_late": false,
              "plagiarism_suspected": false,
              "integrity_status": "clean"
            }
          }
        ],
        "statistics": {
          "total_students": 45,
          "submitted_count": 43,
          "graded_count": 42,
          "average_score": 78.5,
          "highest_score": 98.0,
          "lowest_score": 45.0,
          "completion_rate": 93.33
        }
      }
    ],
    "statistics": {
      "total_students": 45,
      "total_submissions": 43,
      "graded_submissions": 42,
      "average_score": 78.5,
      "completion_rate": 93.33
    }
  }
}
```

#### **Error Responses**
- **403 Forbidden**: Lecturer not authorized for this course offering
- **404 Not Found**: Assessment component not found or doesn't belong to course

#### **Business Logic**
- Validates assessment component belongs to the course offering's syllabus
- Retrieves all enrolled students in the course
- Gets scores for each student in the assessment component
- Calculates detailed statistics for each assessment detail
- Provides aggregated statistics for the entire component

---

### 3. Update Individual Grade

Updates a single grade entry with comprehensive validation and audit trail.

#### **HTTP Method and URL**
```
PUT /api/v1/lecturer/courses/{courseOffering}/assessments/scores/{score}
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |
| `score` | integer | Yes | ID of the assessment component detail score |

#### **Request Body Parameters**
| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `points_earned` | numeric | No | ≥ 0, ≤ max_points | Points awarded to student |
| `percentage_score` | numeric | No | 0-100 | Percentage score (0-100) |
| `letter_grade` | string | No | Valid grade values | Letter grade (A+, A, A-, B+, B, B-, C+, C, C-, D+, D, F, I, W, P, NP) |
| `status` | string | No | Valid status values | Submission status (not_submitted, submitted, grading, graded, returned) |
| `score_status` | string | No | Valid score status | Score status (draft, provisional, final) |
| `instructor_feedback` | string | No | Max 1000 chars | Feedback for student |
| `private_notes` | string | No | Max 1000 chars | Private notes for lecturer |
| `bonus_points` | numeric | No | 0-100 | Additional bonus points |
| `bonus_reason` | string | No | Max 255 chars | Reason for bonus points (required if bonus_points provided) |
| `score_excluded` | boolean | No | - | Whether to exclude score from calculations |
| `exclusion_reason` | string | No | Max 255 chars | Reason for exclusion (required if score_excluded is true) |
| `late_excuse_approved` | boolean | No | - | Whether late excuse is approved |
| `late_excuse_reason` | string | No | Max 500 chars | Reason for late excuse |
| `plagiarism_suspected` | boolean | No | - | Flag for suspected plagiarism |
| `plagiarism_score` | numeric | No | 0-100 | Plagiarism detection score (required if plagiarism_suspected is true) |
| `plagiarism_notes` | string | No | Max 1000 chars | Notes about plagiarism concerns |
| `integrity_status` | string | No | Valid integrity status | Academic integrity status (clean, flagged, under_review, violation_confirmed, cleared) |
| `appeal_requested` | boolean | No | - | Whether student requested appeal |
| `appeal_reason` | string | No | Max 500 chars | Reason for appeal (required if appeal_requested is true) |
| `appeal_status` | string | No | Valid appeal status | Appeal status (pending, under_review, approved, denied) |

#### **Request Example**
```json
{
  "points_earned": 85,
  "percentage_score": 85.0,
  "letter_grade": "B+",
  "score_status": "final",
  "instructor_feedback": "Good work overall. Strong analysis in part A, but could improve the conclusion section. Consider reviewing the rubric for part C.",
  "bonus_points": 2,
  "bonus_reason": "Excellent presentation during class discussion"
}
```

#### **Success Response (200)**
```json
{
  "success": true,
  "message": "Grade updated successfully",
  "data": {
    "id": 456,
    "points_earned": 85,
    "percentage_score": 85.0,
    "letter_grade": "B+",
    "status": "graded",
    "score_status": "final",
    "is_late": false,
    "minutes_late": 0,
    "late_penalty_applied": 0.0,
    "late_excuse_approved": false,
    "bonus_points": 2,
    "bonus_reason": "Excellent presentation during class discussion",
    "score_excluded": false,
    "plagiarism_suspected": false,
    "plagiarism_score": null,
    "integrity_status": "clean",
    "appeal_requested": false,
    "instructor_feedback": "Good work overall. Strong analysis in part A, but could improve the conclusion section.",
    "private_notes": null,
    "graded_by_lecture_id": 789,
    "graded_at": "2024-03-20T14:30:00.000Z",
    "last_modified_by_lecture_id": 789,
    "last_modified_at": "2024-03-20T14:30:00.000Z",
    "created_at": "2024-03-01T09:00:00.000Z",
    "updated_at": "2024-03-20T14:30:00.000Z"
  }
}
```

#### **Validation Rules**
- Points earned cannot exceed the maximum points for the assessment detail
- Percentage score must be consistent with points earned if both are provided
- Final scores must have either points earned or percentage score
- Bonus reason is required when awarding bonus points
- Exclusion reason is required when excluding a score
- Plagiarism score is required when flagging for plagiarism
- Appeal reason is required when requesting an appeal

#### **Error Responses**
- **422 Validation Error**: Invalid input data
- **404 Not Found**: Score not found or doesn't belong to course offering

#### **Business Logic**
- Validates score belongs to the course offering
- Updates score with provided data and adds audit metadata
- Automatically sets grading timestamps and lecturer information
- Maintains consistency between points and percentage scores
- Creates audit trail for grade changes

---

### 4. Bulk Update Grades

Updates multiple grades in a single transaction for efficient bulk grading operations.

#### **HTTP Method and URL**
```
POST /api/v1/lecturer/courses/{courseOffering}/assessments/scores/bulk-update
```

#### **Path Parameters**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `courseOffering` | integer | Yes | ID of the course offering |

#### **Request Body Parameters**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `scores` | array | Yes | Array of score updates (1-100 items) |
| `scores[].id` | integer | Yes | ID of the score to update |
| `scores[].points_earned` | numeric | No | Points awarded |
| `scores[].percentage_score` | numeric | No | Percentage score (0-100) |
| `scores[].letter_grade` | string | No | Letter grade |
| `scores[].score_status` | string | No | Score status |
| `scores[].instructor_feedback` | string | No | Feedback (max 1000 chars) |
| `scores[].bonus_points` | numeric | No | Bonus points (0-100) |
| `scores[].bonus_reason` | string | No | Bonus reason |
| `scores[].score_excluded` | boolean | No | Exclude from calculations |
| `scores[].exclusion_reason` | string | No | Exclusion reason |

#### **Request Example**
```json
{
  "scores": [
    {
      "id": 456,
      "points_earned": 85,
      "percentage_score": 85.0,
      "score_status": "final",
      "instructor_feedback": "Good work"
    },
    {
      "id": 457,
      "points_earned": 92,
      "percentage_score": 92.0,
      "score_status": "final",
      "bonus_points": 3,
      "bonus_reason": "Exceptional creativity"
    }
  ]
}
```

#### **Success Response (200)**
```json
{
  "success": true,
  "message": "All grades updated successfully",
  "data": {
    "updated_scores": [
      {
        "id": 456,
        "student_id": 123,
        "assessment_component_detail_id": 1
      },
      {
        "id": 457,
        "student_id": 124,
        "assessment_component_detail_id": 1
      }
    ],
    "total_updated": 2,
    "operation_completed_at": "2024-03-20T14:30:00.000Z"
  }
}
```

#### **Partial Failure Response (422)**
```json
{
  "success": false,
  "message": "Some grades could not be updated",
  "data": {
    "bulk_errors": [
      "Failed to update score ID 458: Points earned cannot exceed maximum points (100)"
    ],
    "successfully_updated": 1,
    "failed_updates": 1
  },
  "error_code": "PARTIAL_FAILURE",
  "status_code": 422
}
```

#### **Validation Constraints**
- Maximum 100 scores per bulk operation
- All scores must belong to the specified course offering
- Same validation rules as individual grade updates apply to each score
- Transaction-based processing ensures data consistency

#### **Business Logic**
- Validates all scores belong to the course offering
- Processes updates within a database transaction
- Applies same validation rules as individual updates
- Automatically adds grading metadata (timestamps, lecturer ID)
- Returns detailed information about successful and failed updates
- Rolls back entire operation if critical errors occur

#### **Usage Example**
```bash
curl -X POST \
  "https://api.swinx.edu.au/api/v1/lecturer/courses/15/assessments/scores/bulk-update" \
  -H "Authorization: Bearer your_sanctum_token" \
  -H "Content-Type: application/json" \
  -d '{
    "scores": [
      {
        "id": 456,
        "points_earned": 85,
        "percentage_score": 85.0,
        "score_status": "final"
      }
    ]
  }'
```

---

## Additional Considerations

### Rate Limiting
All endpoints are subject to lecturer API rate limiting. If you exceed the rate limit, you'll receive a 429 status code with retry-after headers.

### Data Validation
- All numeric scores are validated against assessment maximums
- Letter grades must match institutional grading scales  
- Status values are restricted to predefined enumerations
- Text fields have maximum length constraints for database integrity

### Audit Trail
- All grade updates automatically record lecturer ID and timestamps
- Grade change history is maintained for academic integrity
- Bonus points and exclusions require explanatory reasons
- Appeals and plagiarism flags create detailed audit records

### Academic Integrity
- Plagiarism detection scores are recorded when flagging submissions
- Integrity status workflow supports investigation processes
- Appeal system maintains proper documentation
- All academic integrity actions are fully auditable

### Performance Considerations
- Bulk operations are limited to 100 scores per request
- Database queries are optimized with appropriate indexes
- Response data includes only necessary fields for UI rendering
- Caching strategies may be applied for frequently accessed data

---

## Support and Contact

For technical support or questions about these API endpoints:
- Internal Development Team: technical-support@swin.edu.au
- API Documentation: https://docs.swinx.edu.au/api
- System Status: https://status.swinx.edu.au
