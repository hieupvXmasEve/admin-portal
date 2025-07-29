# Assessment Weight Validation API

## Overview

The Assessment Weight Validation API endpoint allows lecturers to validate the assessment structure and weights for their courses. This endpoint provides comprehensive validation of assessment component weights and their distribution.

## Endpoint

```
GET /api/v1/lecturer/courses/{courseId}/assessments/validate-weights
```

## Authentication

This endpoint requires authentication via Sanctum token and lecturer permissions.

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| courseId  | integer | Yes | The ID of the course offering to validate |

## Response Format

The endpoint returns a JSON response matching the following TypeScript interfaces:

```typescript
export interface WeightValidation {
  total_weight: number
  is_valid: boolean
  exceeds_limit: boolean
  missing_weight: number
  component_validations: ComponentWeightValidation[]
}

export interface ComponentWeightValidation {
  assessment_id: number
  assessment_name: string
  current_weight: number
  detail_weights_sum: number
  is_valid: boolean
  errors: string[]
}
```

## Example Request

```bash
curl -X GET \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  "https://api.example.com/api/v1/lecturer/courses/123/assessments/validate-weights"
```

## Example Responses

### Valid Assessment Structure (100% total weight)

```json
{
  "success": true,
  "message": "Assessment weights validation completed successfully",
  "data": {
    "total_weight": 100,
    "is_valid": true,
    "exceeds_limit": false,
    "missing_weight": 0,
    "component_validations": [
      {
        "assessment_id": 1,
        "assessment_name": "Assignment",
        "current_weight": 40,
        "detail_weights_sum": 40,
        "is_valid": true,
        "errors": []
      },
      {
        "assessment_id": 2,
        "assessment_name": "Final Exam",
        "current_weight": 60,
        "detail_weights_sum": 0,
        "is_valid": true,
        "errors": []
      }
    ]
  }
}
```

### Invalid Assessment Structure (Exceeding 100%)

```json
{
  "success": true,
  "message": "Assessment weights validation completed successfully",
  "data": {
    "total_weight": 120,
    "is_valid": false,
    "exceeds_limit": true,
    "missing_weight": 0,
    "component_validations": [
      {
        "assessment_id": 1,
        "assessment_name": "Assignment",
        "current_weight": 70,
        "detail_weights_sum": 60,
        "is_valid": true,
        "errors": [
          "Sum of detail weights (60%) is less than component weight (70%) - 10% unaccounted"
        ]
      },
      {
        "assessment_id": 2,
        "assessment_name": "Final Exam",
        "current_weight": 50,
        "detail_weights_sum": 55,
        "is_valid": false,
        "errors": [
          "Sum of detail weights (55%) exceeds component weight (50%)"
        ]
      }
    ],
    "errors": [
      "Total assessment weight (120%) exceeds 100%"
    ]
  }
}
```

### No Syllabus Found

```json
{
  "success": true,
  "message": "Assessment weights validation completed successfully",
  "data": {
    "total_weight": 0,
    "is_valid": false,
    "exceeds_limit": false,
    "missing_weight": 100,
    "component_validations": [],
    "errors": [
      "No syllabus found for this course offering"
    ]
  }
}
```

## HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Validation completed successfully |
| 403 | Unauthorized access to course offering |
| 404 | Course offering not found |
| 500 | Internal server error |

## Error Responses

### Unauthorized Access

```json
{
  "success": false,
  "message": "Unauthorized access to course offering",
  "error_code": "UNAUTHORIZED"
}
```

### Course Not Found

```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\CourseOffering] {id}",
  "error_code": "MODEL_NOT_FOUND"
}
```

## Validation Rules

### Component Weight Validation
- Component weight must be greater than 0
- Component weight cannot exceed 100%
- Sum of detail weights should not exceed component weight
- Sum of detail weights should ideally equal component weight

### Course Weight Validation
- Total assessment weight should equal 100%
- Total assessment weight cannot exceed 100%
- All components must have valid weights
- All component details must have valid weights

## Use Cases

1. **Pre-Submission Validation**: Validate assessment structure before submitting for approval
2. **Audit and Compliance**: Ensure assessment weights meet institutional requirements
3. **Real-time Feedback**: Provide immediate feedback during assessment setup
4. **Bulk Validation**: Validate multiple courses programmatically

## Integration Notes

- This endpoint is designed to be called during assessment setup workflows
- The validation is non-destructive and does not modify any data
- Results can be cached temporarily to improve performance
- The endpoint supports both manual validation and automated validation workflows

## Related Endpoints

- `GET /api/v1/lecturer/courses/{courseId}/assessments` - Get assessment structure
- `POST /api/v1/lecturer/courses/{courseId}/assessments` - Create assessment component
- `PUT /api/v1/lecturer/courses/{courseId}/assessments/{assessmentId}` - Update assessment component