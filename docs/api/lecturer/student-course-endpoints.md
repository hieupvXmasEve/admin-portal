# Student Course API Endpoints

## Overview

Two new API endpoints have been created for students to access course information:

1. **Course Listings** - Returns all currently available courses with class sections in the active semester
2. **Course Details** - Returns detailed information including schedule and grades for an enrolled course

## Endpoints

### 1. GET `/api/v1/student/course/available`

**Description**: Returns a list of all courses currently being offered in the active semester that have associated class sections.

**Authentication**: Required (Student)

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "unit": {
                "code": "COS10009",
                "name": "Introduction to Programming",
                "description": "Learn programming fundamentals",
                "credit_hours": 12.5
            },
            "lecturer": {
                "id": 1,
                "name": "Dr. John Smith",
                "email": "john.smith@swinburne.edu.au"
            }
        }
    ],
    "message": "Available courses retrieved successfully"
}
```

**Features**:
- Only returns courses with associated class sessions
- Filters out courses the student is already registered for
- Shows registration eligibility status
- Includes prerequisite checking
- Shows schedule conflict detection
- Displays capacity availability

### 2. GET `/api/v1/student/course/available/{courseOfferingId}`

**Description**: Returns detailed information about a specific course the student is enrolled in, including full schedule and grade breakdown.

**Authentication**: Required (Student must be enrolled in the course)

**Parameters**:
- `courseOfferingId` (integer): The ID of the course offering

**Response**:
```json
{
    "success": true,
    "data": {
        "course_info": {
            "id": 123,
            "code": "COS10009",
            "name": "Introduction to Programming",
            "credit_hours": 12.5,
            "section_code": "01",
            "semester": {
                "id": 1,
                "name": "Semester 1, 2025",
                "code": "S1-2025"
            },
            "lecturer": {
                "id": 1,
                "name": "Dr. John Smith",
                "email": "john.smith@swinburne.edu.au"
            }
        },
        "schedule": [
            {
                "id": 456,
                "session_title": "Introduction to Variables",
                "session_date": "2025-02-15",
                "start_time": "10:00",
                "end_time": "12:00",
                "duration_minutes": 120,
                "session_type": "lecture",
                "delivery_mode": "in_person",
                "status": "scheduled",
                "room": {
                    "id": 10,
                    "code": "EN202",
                    "name": "Engineering Lab 2",
                    "building": "Engineering Building",
                    "capacity": 40
                },
                "learning_objectives": ["Understand variable concepts"],
                "required_materials": ["Textbook", "Laptop"],
                "topics_covered": ["Variable declaration", "Data types"],
                "formatted_time": "10:00 AM - 12:00 PM",
                "formatted_date": "Feb 15, 2025"
            }
        ],
        "grades": [
            {
                "component_id": 1,
                "component_name": "Programming Assignments",
                "component_code": "PROG_ASG",
                "component_type": "assignment",
                "component_weight": 40,
                "due_date": "2025-03-15",
                "assessments": [
                    {
                        "id": 789,
                        "name": "Assignment 1: Basic Programming",
                        "description": "Create a simple program",
                        "weight": 50,
                        "max_points": 100,
                        "due_date": "2025-02-28",
                        "score": {
                            "points_earned": 85,
                            "percentage_score": 85.0,
                            "letter_grade": "HD",
                            "status": "released"
                        },
                        "submission": {
                            "submitted_at": "2025-02-27",
                            "status": "submitted",
                            "is_late": false,
                            "late_penalty_applied": 0
                        },
                        "feedback": {
                            "instructor_feedback": "Great work! Well structured code.",
                            "graded_at": "2025-03-01"
                        },
                        "formatted_due_date": "Feb 28, 2025"
                    }
                ],
                "component_summary": {
                    "total_assessments": 3,
                    "completed_assessments": 1,
                    "pending_assessments": 2,
                    "component_score": 85.0,
                    "completion_rate": 33.3
                }
            }
        ],
        "summary": {
            "schedule_summary": {
                "total_sessions": 24,
                "completed_sessions": 8,
                "upcoming_sessions": 16,
                "attendance_progress": 33.3
            },
            "grade_summary": {
                "total_assessments": 6,
                "completed_assessments": 2,
                "pending_assessments": 4,
                "overall_grade": 78.5,
                "completion_rate": 33.3
            }
        }
    },
    "message": "Course details retrieved successfully"
}
```

**Features**:
- Full schedule with all class sessions sorted by date and time
- Complete grade breakdown by assessment components
- Grade status indicates if grades are available or "not_available"
- Rich session information including room details, learning objectives
- Summary statistics for both schedule and grades
- Formatted dates and times for easy display

## Security

Both endpoints require authentication and implement the following security measures:

1. **Authentication**: Must be authenticated as a student
2. **Authorization**: Course details endpoint verifies student enrollment
3. **Campus Scoping**: Automatically filters by student's campus context
4. **Data Privacy**: Only shows data relevant to the authenticated student

## Error Handling

**Common Error Responses**:

- **401 Unauthorized**: Not authenticated
- **403 Forbidden**: Not enrolled in the course (for course details)
- **404 Not Found**: Course offering not found
- **500 Internal Server Error**: Server error with appropriate logging

## Implementation Details

### Service Layer
- `CourseRegistrationService::getAvailableCourses()` - Enhanced to filter courses with class sessions
- New formatting methods for schedule and grade data

### Resource Layer
- `CourseDetailResource` - Provides consistent formatting and additional computed fields
- Enhanced with summary statistics and formatted display values

### Data Sources
- **Course Data**: `CourseOffering` model with related curriculum units
- **Schedule Data**: `ClassSession` model with room and timing information  
- **Grade Data**: `AssessmentComponentDetailScore` model with component relationships

### Performance Considerations
- Eager loading of relationships to prevent N+1 queries
- Efficient filtering at database level
- Caching opportunities for frequently accessed course lists
