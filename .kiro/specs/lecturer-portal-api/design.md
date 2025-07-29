# Design Document

## Overview

The Lecturer Portal API is designed as a RESTful API service built on Laravel 12 that provides comprehensive functionality for lecturers to manage their teaching activities. The system follows the established implementation pattern used in the Student Portal API, ensuring consistency in code structure, middleware usage, and architectural patterns.

The API is structured around six main functional domains: Authentication Management, Dashboard Management, Course Management, Attendance Management, Timetable Management, and Student Management. Each domain provides specialized endpoints that aggregate and present data in formats optimized for lecturer workflows, following the same middleware stack and response patterns as the student API.

## Architecture

### System Architecture

The Lecture API follows a layered architecture pattern:

```
┌─────────────────────────────────────────┐
│              Frontend Layer             │
│        (Lecture Portal Web App)         │
└─────────────────────────────────────────┘
                    │ HTTPS/REST
┌─────────────────────────────────────────┐
│             API Gateway Layer           │
│     (Authentication, Rate Limiting,     │
│      CORS, Request Validation)          │
└─────────────────────────────────────────┘
                    │
┌─────────────────────────────────────────┐
│           Application Layer             │
│    (Controllers, Middleware, Routes)    │
└─────────────────────────────────────────┘
                    │
┌─────────────────────────────────────────┐
│            Business Layer               │
│     (Services, Business Logic,          │
│      Validation, Calculations)          │
└─────────────────────────────────────────┘
                    │
┌─────────────────────────────────────────┐
│             Data Layer                  │
│    (Models, Repositories, Database)     │
└─────────────────────────────────────────┘
                    │
┌─────────────────────────────────────────┐
│           Infrastructure                │
│   (MySQL, Redis, File Storage, Jobs)    │
└─────────────────────────────────────────┘
```

### Route Structure and Middleware Stack

Following the student API implementation pattern, the lecturer API uses a consistent middleware stack:

**Public Routes (Authentication)**
```php
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['lecturer.api.rate:lecturer-auth']);
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware(['lecturer.api.rate:lecturer-auth']);
});
```

**Protected Routes (All Lecturer Endpoints)**
```php
Route::middleware([
    'auth:sanctum',
    'lecturer.api.auth',
    'lecturer.api.rate:lecturer-api',
    'api.logging'
])->group(function () {
    // All protected lecturer endpoints
});
```

**Middleware Registration (bootstrap/app.php)**
```php
$middleware->alias([
    'lecturer.api.auth' => \App\Http\Middleware\LecturerApiAuthorization::class,
    'lecturer.api.rate' => \App\Http\Middleware\LecturerApiRateLimiter::class,
    'api.logging' => \App\Http\Middleware\ApiLogging::class,
]);
```

### Database Integration

The API integrates with the existing database schema, utilizing these key tables:
- `lectures` - Lecturer profiles and authentication
- `course_offerings` - Course assignments and details
- `class_sessions` - Scheduled teaching sessions
- `attendances` - Student attendance records
- `students` - Student information and enrollment
- `semesters` - Academic term management
- `curriculum_units` - Course curriculum details

### API Response Format

All API responses follow a consistent JSON structure:

```json
{
  "success": boolean,
  "message": string,
  "data": object|array|null,
  "meta": {
    "timestamp": "ISO 8601 datetime",
    "request_id": "unique_identifier",
    "pagination": {
      "current_page": number,
      "total_pages": number,
      "per_page": number,
      "total_items": number
    }
  }
}
```

## Components and Interfaces

### Authentication Component

**Middleware Stack (following student API pattern)**
- `auth:sanctum` - Laravel Sanctum JWT token validation
- `lecturer.api.auth` - Custom lecturer authorization middleware
- `lecturer.api.rate` - Rate limiting with lecturer-specific throttling
- `api.logging` - Request/response logging for audit trails

**AuthController**
- `POST /api/v1/lecturer/auth/login` - Lecturer authentication
- `POST /api/v1/lecturer/auth/refresh` - Token refresh
- `POST /api/v1/lecturer/auth/logout` - Session termination
- `GET /api/v1/lecturer/auth/me` - Current lecturer profile

**Authorization Service**
- Verifies lecturer access to specific courses
- Implements role-based permissions for teaching activities
- Handles admin override capabilities
- Logs access attempts for audit compliance

### Dashboard Component

**DashboardController**
- `GET /api/v1/lecturer/dashboard` - Main dashboard with teaching overview
- `GET /api/v1/lecturer/dashboard/teaching-summary` - Course and session statistics
- `GET /api/v1/lecturer/dashboard/attendance-overview` - Attendance trends and alerts
- `GET /api/v1/lecturer/dashboard/student-alerts` - At-risk student notifications
- `GET /api/v1/lecturer/dashboard/upcoming-sessions` - Next scheduled classes
- `GET /api/v1/lecturer/dashboard/recent-activities` - Teaching activity log

**DashboardService**
- Aggregates multi-source teaching data efficiently
- Calculates real-time teaching statistics and metrics
- Generates automated student alerts based on attendance/performance thresholds
- Manages activity logging and chronological retrieval
- Implements caching for dashboard performance optimization

### Course Management Component

**CourseController**
- `GET /api/v1/lecturer/courses` - List lecturer's assigned courses with filtering
- `GET /api/v1/lecturer/courses/{courseOffering}` - Detailed course information
- `GET /api/v1/lecturer/courses/{courseOffering}/students` - Enrolled student roster
- `GET /api/v1/lecturer/courses/{courseOffering}/statistics` - Course analytics and metrics
- `GET /api/v1/lecturer/courses/{courseOffering}/sessions` - Class session schedule
- `POST /api/v1/lecturer/courses/{courseOffering}/materials` - Upload course materials
- `PUT /api/v1/lecturer/courses/{courseOffering}/syllabus` - Update course syllabus
- `GET /api/v1/lecturer/courses/filter-options` - Available filtering options

**CourseService**
- Manages course offering queries with lecturer authorization
- Handles enrollment statistics and capacity tracking
- Processes secure material uploads with validation
- Generates comprehensive course analytics and performance metrics
- Implements efficient data retrieval with eager loading

### Attendance Management Component

**AttendanceController**
- `GET /api/v1/lecturer/attendance` - Overview of all attendance sessions
- `GET /api/v1/lecturer/attendance/sessions` - List sessions requiring attention
- `GET /api/v1/lecturer/attendance/sessions/{classSession}` - Session attendance details
- `POST /api/v1/lecturer/attendance/sessions/{classSession}/mark` - Mark student attendance
- `PUT /api/v1/lecturer/attendance/sessions/{classSession}/bulk-update` - Bulk attendance updates
- `GET /api/v1/lecturer/attendance/course/{courseOffering}` - Course attendance analytics
- `GET /api/v1/lecturer/attendance/alerts` - Low attendance alerts
- `POST /api/v1/lecturer/attendance/export/{courseOffering}` - Export attendance reports

**AttendanceService**
- Processes individual and bulk attendance marking with validation
- Calculates real-time attendance statistics and percentages
- Generates trend analysis and pattern recognition
- Handles secure report generation with multiple export formats
- Implements attendance alert generation based on configurable thresholds

### Timetable Management Component

**TimetableController**
- `GET /api/v1/lecturer/timetable` - Current week teaching schedule
- `GET /api/v1/lecturer/timetable/weekly` - Weekly timetable with date range
- `GET /api/v1/lecturer/timetable/sessions/{classSession}` - Session details
- `POST /api/v1/lecturer/timetable/sessions` - Create new class session
- `PUT /api/v1/lecturer/timetable/sessions/{classSession}` - Update session details
- `DELETE /api/v1/lecturer/timetable/sessions/{classSession}` - Cancel/remove session
- `GET /api/v1/lecturer/timetable/conflicts` - Check scheduling conflicts
- `GET /api/v1/lecturer/timetable/filter-options` - Available filtering options

**TimetableService**
- Manages comprehensive session scheduling with conflict detection
- Validates room availability and lecturer schedule conflicts
- Handles recurring session creation and management
- Processes session modifications with proper audit trails
- Implements intelligent scheduling suggestions and optimization

### Student Management Component

**StudentController**
- `GET /api/v1/lecturer/students` - Overview of all students across courses
- `GET /api/v1/lecturer/students/alerts` - Student performance and attendance alerts
- `GET /api/v1/lecturer/students/{student}` - Individual student profile and progress
- `POST /api/v1/lecturer/students/{student}/notes` - Add private lecturer notes
- `PUT /api/v1/lecturer/students/{student}/notes/{note}` - Update student notes
- `DELETE /api/v1/lecturer/students/alerts/{alert}` - Dismiss student alert
- `GET /api/v1/lecturer/students/performance-summary` - Cross-course student analytics
- `POST /api/v1/lecturer/students/{student}/flag` - Flag student for attention

**StudentService**
- Generates intelligent student alerts based on multiple performance indicators
- Manages comprehensive alert lifecycle with resolution tracking
- Handles secure student note management with privacy controls
- Tracks cross-course student performance and engagement patterns
- Implements predictive analytics for at-risk student identification

### Profile Management Component

**ProfileController**
- `GET /api/v1/lecturer/profile` - Lecturer profile information
- `PUT /api/v1/lecturer/profile` - Update profile details
- `POST /api/v1/lecturer/profile/avatar` - Upload profile avatar
- `GET /api/v1/lecturer/profile/teaching-preferences` - Teaching preferences and settings
- `PUT /api/v1/lecturer/profile/teaching-preferences` - Update teaching preferences
- `GET /api/v1/lecturer/profile/teaching-history` - Historical teaching assignments

**ProfileService**
- Manages lecturer profile data with validation
- Handles secure avatar upload and processing
- Manages teaching preferences and notification settings
- Tracks teaching history and performance metrics
- Implements profile data synchronization with HR systems

### Notification Management Component

**NotificationController**
- `GET /api/v1/lecturer/notifications` - Lecturer notifications and alerts
- `POST /api/v1/lecturer/notifications/{notification}/mark-read` - Mark notification as read
- `POST /api/v1/lecturer/notifications/mark-all-read` - Mark all notifications as read
- `GET /api/v1/lecturer/notifications/preferences` - Notification preferences
- `PUT /api/v1/lecturer/notifications/preferences` - Update notification settings
- `POST /api/v1/lecturer/notifications/push-subscription` - Subscribe to push notifications

**NotificationService**
- Manages lecturer-specific notification delivery
- Handles notification preferences and channel management
- Implements push notification subscription management
- Processes notification templates for teaching-related events
- Manages notification history and read status tracking

## Data Models

### Core Models

**Lecture Model (Existing)**
```php
class Lecture extends Model
{
    protected $fillable = [
        'employee_id', 'title', 'first_name', 'last_name', 'email',
        'campus_id', 'department', 'academic_rank', 'employment_status',
        'phone', 'office_location', 'office_hours', 'bio'
    ];
    
    protected $casts = [
        'office_hours' => 'array',
        'is_active' => 'boolean'
    ];
    
    public function courseOfferings()
    {
        return $this->hasMany(CourseOffering::class, 'lecture_id');
    }
    
    public function classSessions()
    {
        return $this->hasMany(ClassSession::class, 'instructor_id');
    }
    
    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
```

**CourseOffering Model (Existing)**
```php
class CourseOffering extends Model
{
    protected $fillable = [
        'semester_id', 'curriculum_unit_id', 'lecture_id', 'section_code',
        'max_capacity', 'current_enrollment', 'delivery_mode', 'location',
        'schedule_days', 'start_time', 'end_time', 'is_active', 'enrollment_status'
    ];
    
    protected $casts = [
        'schedule_days' => 'array',
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i'
    ];
    
    public function lecturer()
    {
        return $this->belongsTo(Lecture::class, 'lecture_id');
    }
    
    public function curriculumUnit()
    {
        return $this->belongsTo(CurriculumUnit::class);
    }
    
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
    
    public function classSessions()
    {
        return $this->hasMany(ClassSession::class);
    }
    
    public function courseRegistrations()
    {
        return $this->hasMany(CourseRegistration::class);
    }
    
    public function enrolledStudents()
    {
        return $this->hasManyThrough(Student::class, CourseRegistration::class, 
            'course_offering_id', 'id', 'id', 'student_id')
            ->where('course_registrations.registration_status', 'enrolled');
    }
}
```

**ClassSession Model (Existing)**
```php
class ClassSession extends Model
{
    protected $fillable = [
        'course_offering_id', 'instructor_id', 'session_title', 'session_date',
        'start_time', 'end_time', 'session_type', 'delivery_mode', 'status',
        'room_id', 'learning_objectives', 'required_materials', 'topics_covered',
        'session_notes', 'attendance_marked'
    ];
    
    protected $casts = [
        'session_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'learning_objectives' => 'array',
        'required_materials' => 'array',
        'topics_covered' => 'array',
        'attendance_marked' => 'boolean'
    ];
    
    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }
    
    public function instructor()
    {
        return $this->belongsTo(Lecture::class, 'instructor_id');
    }
    
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
    
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    
    public function getAttendanceStatsAttribute()
    {
        $total = $this->attendances()->count();
        $present = $this->attendances()->where('status', 'present')->count();
        return [
            'total' => $total,
            'present' => $present,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
        ];
    }
}
```

**Attendance Model (Existing)**
```php
class Attendance extends Model
{
    protected $fillable = [
        'class_session_id', 'student_id', 'status', 'check_in_time',
        'minutes_late', 'participation_level', 'participation_score', 
        'notes', 'marked_by', 'marked_at'
    ];
    
    protected $casts = [
        'check_in_time' => 'datetime:H:i:s',
        'marked_at' => 'datetime',
        'participation_score' => 'decimal:2'
    ];
    
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }
    
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    
    public function markedBy()
    {
        return $this->belongsTo(Lecture::class, 'marked_by');
    }
    
    public function getIsLateAttribute()
    {
        return $this->status === 'late' || $this->minutes_late > 0;
    }
    
    public function getIsPresentAttribute()
    {
        return in_array($this->status, ['present', 'late']);
    }
}
```

### Repository Pattern

**LecturerRepository**
```php
class LecturerRepository
{
    public function findWithCourses($lecturerId, $semesterId = null)
    {
        return Lecture::with(['courseOfferings' => function($query) use ($semesterId) {
            $query->with(['curriculumUnit', 'semester', 'enrolledStudents']);
            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }
            $query->where('is_active', true);
        }])->find($lecturerId);
    }
    
    public function getTeachingStatistics($lecturerId, $semesterId = null)
    {
        $query = CourseOffering::where('lecture_id', $lecturerId)
            ->with(['classSessions', 'courseRegistrations']);
            
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }
        
        return $query->get();
    }
    
    public function getAttendanceOverview($lecturerId, $semesterId = null)
    {
        return ClassSession::where('instructor_id', $lecturerId)
            ->with(['attendances', 'courseOffering.curriculumUnit'])
            ->when($semesterId, function($query, $semesterId) {
                $query->whereHas('courseOffering', function($q) use ($semesterId) {
                    $q->where('semester_id', $semesterId);
                });
            })
            ->orderBy('session_date', 'desc')
            ->get();
    }
}
```

**CourseOfferingRepository**
```php
class CourseOfferingRepository
{
    public function findByLecturerWithDetails($lecturerId, $filters = [])
    {
        $query = CourseOffering::where('lecture_id', $lecturerId)
            ->with([
                'curriculumUnit',
                'semester',
                'classSessions' => function($q) {
                    $q->orderBy('session_date', 'asc');
                },
                'enrolledStudents'
            ]);
            
        if (isset($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }
        
        if (isset($filters['delivery_mode'])) {
            $query->where('delivery_mode', $filters['delivery_mode']);
        }
        
        return $query->where('is_active', true)->get();
    }
    
    public function getEnrollmentStatistics($courseOfferingId)
    {
        return CourseOffering::with(['courseRegistrations', 'enrolledStudents'])
            ->find($courseOfferingId);
    }
}
```

**AttendanceRepository**
```php
class AttendanceRepository
{
    public function getSessionAttendance($classSessionId)
    {
        return Attendance::where('class_session_id', $classSessionId)
            ->with(['student', 'markedBy'])
            ->orderBy('student.last_name')
            ->get();
    }
    
    public function getCourseAttendanceAnalytics($courseOfferingId, $dateRange = null)
    {
        $query = Attendance::whereHas('classSession', function($q) use ($courseOfferingId) {
            $q->where('course_offering_id', $courseOfferingId);
        })->with(['classSession', 'student']);
        
        if ($dateRange) {
            $query->whereHas('classSession', function($q) use ($dateRange) {
                $q->whereBetween('session_date', $dateRange);
            });
        }
        
        return $query->get();
    }
    
    public function getStudentAttendanceAlerts($lecturerId, $threshold = 75)
    {
        // Complex query to identify students with low attendance
        return DB::select("
            SELECT s.id, s.first_name, s.last_name, co.id as course_offering_id,
                   cu.unit_code, cu.unit_name,
                   COUNT(a.id) as total_sessions,
                   SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_sessions,
                   ROUND((SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
            FROM students s
            JOIN course_registrations cr ON s.id = cr.student_id
            JOIN course_offerings co ON cr.course_offering_id = co.id
            JOIN curriculum_units cu ON co.curriculum_unit_id = cu.id
            JOIN class_sessions cs ON co.id = cs.course_offering_id
            LEFT JOIN attendances a ON cs.id = a.class_session_id AND s.id = a.student_id
            WHERE co.lecture_id = ? AND cr.registration_status = 'enrolled'
            GROUP BY s.id, co.id
            HAVING attendance_percentage < ?
            ORDER BY attendance_percentage ASC
        ", [$lecturerId, $threshold]);
    }
}
```

## Error Handling

#### Standardized Error Format
```php
class ApiResponse
{
    public static function error(
        string $message,
        array $errors = [],
        string $errorCode = 'GENERAL_ERROR',
        int $statusCode = 400
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'error_code' => $errorCode,
            'timestamp' => now()->toISOString()
        ], $statusCode);
    }
}
```

#### Error Categories and Handling

1. **Validation Errors (400)**
   - Invalid input data
   - Missing required fields
   - Format validation failures

2. **Authentication Errors (401)**
   - Invalid or expired JWT tokens
   - Missing authentication headers

3. **Authorization Errors (403)**
   - Insufficient permissions
   - Academic holds preventing access

4. **Resource Not Found (404)**
   - Student records not found
   - Course offerings not available

5. **Business Logic Errors (422)**
   - Prerequisite violations
   - Schedule conflicts
   - Enrollment capacity exceeded

6. **Server Errors (500)**
   - Database connection issues
   - External service failures
   - Unexpected system errors

## Testing Strategy

### Unit Testing

**Service Layer Tests**
- Test business logic in isolation
- Mock repository dependencies
- Verify calculation accuracy
- Test edge cases and error conditions

**Repository Tests**
- Test database queries
- Verify data relationships
- Test query optimization
- Validate data integrity

### Integration Testing

**API Endpoint Tests**
- Test complete request/response cycles
- Verify authentication and authorization
- Test error handling scenarios
- Validate response formats

**Database Integration Tests**
- Test complex queries with real data
- Verify transaction handling
- Test concurrent access scenarios
- Validate data consistency

### Performance Testing

**Load Testing**
- Test API endpoints under high load
- Measure response times
- Identify bottlenecks
- Validate caching effectiveness

**Database Performance**
- Test query execution times
- Validate index effectiveness
- Test with large datasets
- Monitor resource usage

### Security Testing

**Authentication Tests**
- Test JWT token validation
- Verify token expiration handling
- Test unauthorized access attempts
- Validate rate limiting

**Authorization Tests**
- Test lecturer data isolation
- Verify course access permissions
- Test admin override functionality
- Validate audit logging

## Performance Optimization

### Caching Strategy

**Redis Caching**
- Dashboard data: 5 minutes TTL
- Course listings: 15 minutes TTL
- Student data: 10 minutes TTL
- Static reference data: 1 hour TTL

**Query Optimization**
- Eager loading for related models
- Database indexes on frequently queried columns
- Query result caching for expensive operations
- Pagination for large datasets

### Database Indexes

**Primary Indexes**
```sql
-- Lecturer-specific queries
CREATE INDEX idx_course_offerings_lecturer_semester ON course_offerings(lecture_id, semester_id);
CREATE INDEX idx_class_sessions_instructor_date ON class_sessions(instructor_id, session_date);
CREATE INDEX idx_attendances_session_status ON attendances(class_session_id, status);

-- Performance optimization
CREATE INDEX idx_attendances_student_course ON attendances(student_id, class_session_id);
CREATE INDEX idx_course_offerings_active ON course_offerings(is_active, enrollment_status);
```

### Response Optimization

**Data Transformation**
- Use API Resources for consistent data formatting
- Implement field selection for reduced payload size
- Compress responses for large datasets
- Implement ETags for conditional requests

**Async Processing**
- Queue report generation
- Background alert processing
- Async file uploads
- Batch attendance processing

## Security Implementation

### Authentication Flow

Following the student API authentication pattern:

1. **Login Process**
   - Client sends credentials to `/api/v1/lecturer/auth/login`
   - System validates lecturer credentials against `lectures` table
   - Generate Sanctum JWT token with lecturer context
   - Return token with lecturer profile information

2. **Request Authentication**
   - Client includes `Authorization: Bearer {token}` header
   - `auth:sanctum` middleware validates JWT token
   - `lecturer.api.auth` middleware extracts lecturer context
   - Verify lecturer is active and has teaching permissions
   - Attach lecturer information to request

3. **Authorization Checks**
   - Course access: Verify lecturer is assigned to requested course
   - Student data: Ensure lecturer has access through course enrollment
   - Session management: Confirm lecturer is the instructor
   - Data isolation: Prevent cross-lecturer data access

### Data Protection

**Encryption**
- Encrypt sensitive data at rest
- Use HTTPS for all communications
- Implement field-level encryption for PII
- Secure file storage with access controls

**Input Validation**
- Sanitize all user inputs
- Validate file uploads
- Implement CSRF protection
- Use parameterized queries

### Audit Logging

**Activity Logging**
```php
class AuditLogger
{
    public function logAttendanceMarking($lecturerId, $sessionId, $records)
    {
        Log::info('Attendance marked', [
            'lecturer_id' => $lecturerId,
            'session_id' => $sessionId,
            'record_count' => count($records),
            'timestamp' => now(),
            'ip_address' => request()->ip()
        ]);
    }
}
```

## Deployment Considerations

### Environment Configuration

**Production Settings**
- Enable query logging for monitoring
- Configure Redis for caching
- Set up file storage (S3/local)
- Configure email services for notifications

**Monitoring**
- API response time monitoring
- Database query performance tracking
- Error rate monitoring
- Resource usage alerts

### Scalability

**Horizontal Scaling**
- Stateless API design
- Database connection pooling
- Load balancer configuration
- Session storage in Redis

**Vertical Scaling**
- Database query optimization
- Memory usage optimization
- CPU-intensive task optimization
- Storage optimization
