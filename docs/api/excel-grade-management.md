# Excel Grade Management API

This document describes the Excel-based grade management endpoints for bulk grade import/export operations.

## Overview

The Excel grade management system allows lecturers to:
1. Export a grade template with all enrolled students for a specific assessment component detail
2. Import updated grades from an Excel file for bulk grade updates

## Endpoints

### 1. Export Grade Template

**Endpoint:** `GET /api/v1/lecturer/courses/{courseOffering}/assessments/details/{assessmentComponentDetail}/export-template`

**Description:** Exports an Excel template containing all students enrolled in the course with their current grades for the specified assessment component detail.

**Parameters:**
- `courseOffering` (path): Course offering ID
- `assessmentComponentDetail` (path): Assessment component detail ID

**Response:** Binary Excel file download (.xlsx)

**Excel Template Structure:**
- Student ID, Student Name, Email
- Current grade information (Points Earned, Percentage Score, Letter Grade)
- Status fields (Status, Score Status)
- Feedback fields (Instructor Feedback, Private Notes)
- Bonus and penalty fields
- Hidden metadata columns for validation

**Example Request:**
```bash
curl -X GET \
  "https://api.example.com/api/v1/lecturer/courses/123/assessments/details/456/export-template" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### 2. Import Grades

**Endpoint:** `POST /api/v1/lecturer/courses/{courseOffering}/assessments/details/{assessmentComponentDetail}/import-grades`

**Description:** Imports grades from an Excel file for all students in the specified assessment component detail.

**Parameters:**
- `courseOffering` (path): Course offering ID
- `assessmentComponentDetail` (path): Assessment component detail ID

**Request Body (multipart/form-data):**
- `file` (required): Excel file (.xlsx, .xls, .csv) - max 10MB
- `update_mode` (optional): How to handle existing scores
  - `update_existing` - Only update existing scores
  - `create_missing` - Only create new scores
  - `update_and_create` - Update existing and create missing (default)
- `overwrite_existing` (optional): Whether to overwrite existing scores (default: false)
- `validate_only` (optional): Only validate without importing (default: false)
- `skip_errors` (optional): Continue processing despite errors (default: false)
- `default_status` (optional): Default status for new scores (default: 'graded')
- `default_score_status` (optional): Default score status (default: 'provisional')

**Response:**
```json
{
  "success": true,
  "message": "Grade import completed successfully",
  "data": {
    "total_rows": 25,
    "successful_updates": 20,
    "successful_creates": 3,
    "errors": [],
    "warnings": [],
    "skipped": 2
  }
}
```

**Example Request:**
```bash
curl -X POST \
  "https://api.example.com/api/v1/lecturer/courses/123/assessments/details/456/import-grades" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "file=@grades.xlsx" \
  -F "update_mode=update_and_create" \
  -F "overwrite_existing=true"
```

## Excel File Format

### Required Columns
- **Student ID**: Unique identifier for the student
- **Student Name**: Full name (read-only, for reference)
- **Email**: Student email (read-only, for reference)

### Grade Columns
- **Points Earned**: Numeric value (0 to max_points)
- **Percentage Score**: Numeric value (0-100)
- **Letter Grade**: Valid letter grades (A+, A, A-, B+, B, B-, C+, C, C-, D+, D, F, I, W, P, NP)
- **Status**: Submission status (not_submitted, submitted, grading, graded, returned)
- **Score Status**: Score status (draft, provisional, final)

### Optional Columns
- **Instructor Feedback**: Text feedback for the student
- **Bonus Points**: Additional points awarded
- **Bonus Reason**: Reason for bonus points
- **Late Penalty Applied**: Penalty points deducted
- **Private Notes**: Internal notes (not visible to students)

### Hidden Metadata Columns
- **Assessment Detail ID**: Used for validation (do not modify)
- **Max Points**: Maximum possible points (do not modify)
- **Current Score ID**: Existing score record ID (do not modify)

## Validation Rules

### File Validation
- File size: Maximum 10MB
- File types: .xlsx, .xls, .csv
- File must not be empty

### Data Validation
- **Student ID**: Required, must match existing student
- **Points Earned**: Must be between 0 and max_points
- **Percentage Score**: Must be between 0 and 100
- **Letter Grade**: Must be from approved list
- **Status**: Must be valid submission status
- **Score Status**: Must be valid score status
- **Bonus Points**: Must be positive number
- **Late Penalty**: Must be positive number

### Business Rules
- Student must be enrolled in the course
- Points earned cannot exceed maximum points for the assessment
- Percentage score and points earned should be consistent
- Bonus reason required when bonus points are awarded

## Error Handling

### Common Errors
- **File too large**: File exceeds 10MB limit
- **Invalid file type**: File is not Excel or CSV format
- **Student not found**: Student ID doesn't match any enrolled student
- **Invalid grade values**: Grade values outside acceptable ranges
- **Permission denied**: Lecturer doesn't have access to the course

### Error Response Format
```json
{
  "success": false,
  "message": "Grade import failed",
  "error_code": "IMPORT_FAILED",
  "data": {
    "total_rows": 25,
    "successful_updates": 15,
    "successful_creates": 0,
    "errors": [
      {
        "row": 3,
        "message": "Student not found",
        "data": {"student_id": "12345"}
      }
    ],
    "warnings": [
      {
        "row": 5,
        "message": "Score already exists and overwrite is disabled",
        "data": {"student_id": "67890"}
      }
    ],
    "skipped": 10
  }
}
```

## Best Practices

1. **Always export template first**: Use the export endpoint to get the current state before making changes
2. **Validate before importing**: Use `validate_only=true` to check for errors before actual import
3. **Handle errors gracefully**: Check the response for errors and warnings
4. **Use appropriate update mode**: Choose the right update mode based on your needs
5. **Backup before bulk operations**: Consider exporting current grades before importing new ones
6. **Check permissions**: Ensure the lecturer has access to the course offering

## Security Considerations

- All endpoints require authentication via Bearer token
- Lecturers can only access their own course offerings
- Assessment component details must belong to the specified course offering
- File uploads are validated for type and size
- Sensitive data (private notes) is handled securely

## Rate Limiting

These endpoints are subject to the standard API rate limits:
- Export: 10 requests per minute
- Import: 5 requests per minute (due to processing overhead)

## Support

For technical support or questions about the Excel grade management API, please contact the development team or refer to the main API documentation.
