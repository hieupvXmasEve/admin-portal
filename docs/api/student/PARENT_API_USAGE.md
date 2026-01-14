# Parent API Usage Guide

## Overview

This document explains how parents can access their children's student data through the API system.

## Authentication Flow

### 1. Parent Registration
```http
POST /api/v1/parent/auth/register
Content-Type: application/json

{
  "full_name": "Nguyen Van A",
  "student_id": "SYD2024001", 
  "phone": "0901234567",
  "email": "parent@example.com",
  "password": "password123",
  "device_name": "Parent Mobile App"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Nguyen Van A",
      "email": "parent@example.com",
      "phone": "0901234567",
      "status": "active"
    },
    "linked_student": {
      "id": 123,
      "student_id": "SYD2024001",
      "full_name": "Nguyen Van B",
      "campus_id": 1
    },
    "token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": "2024-09-09T23:39:00Z"
  },
  "message": "Parent registered successfully"
}
```

### 2. Parent Login
```http
POST /api/v1/parent/auth/login
Content-Type: application/json

{
  "identifier": "parent@example.com",  // can be email or phone
  "password": "password123",
  "remember_me": true,
  "device_name": "Parent Mobile App"
}
```

### 3. Google OAuth Login
```http
POST /api/v1/parent/auth/login/google
Content-Type: application/json

{
  "access_token": "google_access_token",
  "device_name": "Parent Mobile App"
}
```

## Accessing Student Data

Once authenticated as a parent, you can access your child's student data by providing their `student_id` in any of these ways:

### Method 1: Query Parameter
```http
GET /api/v1/student/dashboard?student_id=SYD2024001
Authorization: Bearer {parent_token}
```

### Method 2: Header
```http
GET /api/v1/student/dashboard
Authorization: Bearer {parent_token}
X-Student-ID: SYD2024001
```

### Method 3: Request Body (for POST/PUT requests)
```http
POST /api/v1/student/some-endpoint
Authorization: Bearer {parent_token}
Content-Type: application/json

{
  "student_id": "SYD2024001",
  "other_data": "..."
}
```

## Available Student Endpoints for Parents

Parents can access ANY student endpoint by providing the `student_id`. Examples:

### Dashboard Data
```http
GET /api/v1/student/dashboard?student_id=SYD2024001
GET /api/v1/student/dashboard/gpa?student_id=SYD2024001
GET /api/v1/student/dashboard/credit-progress?student_id=SYD2024001
GET /api/v1/student/dashboard/academic-holds?student_id=SYD2024001
```

### Grades & Assessments
```http
GET /api/v1/student/grades?student_id=SYD2024001
GET /api/v1/student/grades/gpa-trend?student_id=SYD2024001
GET /api/v1/student/grades/assessments?student_id=SYD2024001
```

### Attendance
```http
GET /api/v1/student/attendance?student_id=SYD2024001
GET /api/v1/student/attendance/course/123?student_id=SYD2024001
```

### Timetable
```http
GET /api/v1/student/timetable?student_id=SYD2024001
GET /api/v1/student/timetable/weekly?student_id=SYD2024001
```

### Profile (Read-only)
```http
GET /api/v1/student/profile?student_id=SYD2024001
GET /api/v1/student/profile/academic-history?student_id=SYD2024001
```

## Parent-Specific Endpoints

### Get Children List
```http
GET /api/v1/parent/children
Authorization: Bearer {parent_token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "student_id": "SYD2024001",
      "full_name": "Nguyen Van B",
      "email": "student@example.com",
      "phone": "0987654321",
      "status": "active",
      "academic_status": "active",
      "campus": {
        "id": 1,
        "name": "Sydney Campus",
        "code": "SYD"
      },
      "program": {
        "id": 1,
        "name": "Bachelor of Computer Science",
        "code": "BCS"
      }
    }
  ],
  "message": "Children retrieved successfully"
}
```

## Security & Permissions

1. **Relationship Verification**: Parents can only access data for students linked to them through the `parent_student` pivot table and `parents` profile table.

2. **Campus Scoping**: Parent roles are assigned per campus, following the existing campus-scoped permission system.

3. **Read-Only Access**: Parents have read-only access to student data. They cannot modify student information.

4. **Active Student Check**: Parents can only access data for active students (status: 'active' or 'enrolled').

5. **Rate Limiting**: Parent API calls are subject to rate limiting (`api.rate:parent-api`).

## Error Handling

### Missing student_id
```json
{
  "success": false,
  "error": "validation_error",
  "message": "Validation failed",
  "data": {
    "student_id": ["Parent must specify student_id to access student data"]
  }
}
```

### No Permission
```json
{
  "success": false,
  "error": "authorization_error", 
  "message": "You do not have permission to access this student's data"
}
```

### Student Not Found
```json
{
  "success": false,
  "error": "not_found",
  "message": "Student not found"
}
```

## Frontend Implementation Notes

When implementing the parent portal frontend:

1. **Store Student List**: After login, call `/api/v1/parent/children` to get available students.

2. **Student Selection**: Allow parent to select which child's data to view.

3. **Auto-inject student_id**: Automatically include the selected student's ID in all API calls.

4. **Clear Student Context**: When switching between children, update the student_id parameter.

5. **Handle Multiple Children**: If parent has multiple children, provide easy switching between them.

## Example Frontend Implementation

```javascript
// After parent login
const children = await api.get('/api/v1/parent/children');
const selectedStudent = children.data[0]; // or user selection

// Set up API client to auto-inject student_id
api.interceptors.request.use(config => {
  if (config.url.includes('/student/') && selectedStudent) {
    config.params = config.params || {};
    config.params.student_id = selectedStudent.student_id;
  }
  return config;
});

// Now all student API calls will include student_id automatically
const dashboard = await api.get('/api/v1/student/dashboard');
const grades = await api.get('/api/v1/student/grades');
```
