# Student Dashboard API

## Endpoint
```
GET /api/v1/student/dashboard
```

## Description
Retrieves comprehensive dashboard data for the authenticated student, including current semester information, GPA data, credit progress, academic holds, upcoming assessments, enrollment status, and quick statistics.

## Authentication
Requires Bearer token authentication via Laravel Sanctum.

## Response Structure

### Success Response (200 OK)
```typescript
interface DashboardResponse {
  success: true;
  // message is present on success responses
  message: string;
  data: {
    current_semester: CurrentSemester;
    gpa_data: GPAData;
    credit_progress: CreditProgress;
    academic_holds: AcademicHolds;
    upcoming_assessments: UpcomingAssessments;
    enrollment_status: EnrollmentStatus;
    quick_stats: QuickStats;
    last_updated: string; // ISO 8601 format
  };
  // meta is omitted when not applicable
  // meta?: Record<string, unknown>;
  timestamp: string; // ISO 8601 format
}
```

## Data Type Definitions

### CurrentSemester
```typescript
interface CurrentSemester {
  semester: {
    id: number;
    name: string;
    code: string;
    start_date: string; // YYYY-MM-DD format
    end_date: string;   // YYYY-MM-DD format
    is_registration_open: boolean;
  } | null;
  enrollment: {
    id: number;
    status: string;
    semester_number: number;
  } | null;
  course_summary: {
    registered_courses: number;
    total_credits: number;
  };
  courses: Array<{
    id: number;
    course_code: string;
    course_name: string;
    credits: number;
    status: string;
  }>;
}
```

### GPAData
```typescript
interface GPAData {
  // From GPACalculationService::calculateCurrentGPA
  current: {
    gpa: number; // rounded to 2 decimals
    quality_points: number;
    credit_hours_attempted: number;
    credit_hours_earned: number;
    total_courses: number;
  } | null;

  // From GPACalculationService::getGPATrend
  trend: {
    trend_data: Array<{
      semester: string;
      semester_code: string;
      gpa: number; // rounded to 2 decimals
      credit_hours: number;
      academic_standing: string;
      date: string; // YYYY-MM-DD
    }>;
    trend_analysis: {
      direction: 'improving' | 'declining' | 'stable' | 'insufficient_data';
      change: number; // rounded to 2 decimals
      consistency: 'consistent' | 'moderate' | 'variable' | 'unknown';
      standard_deviation?: number;
    };
  } | null;

  // From GPACalculationService::getGradeDistribution
  distribution: {
    distribution: Record<'HD' | 'D' | 'C' | 'P' | 'N' | 'F', number>;
    percentages: Record<'HD' | 'D' | 'C' | 'P' | 'N' | 'F', number>;
    total_courses: number;
  } | null;

  // From GPACalculationService::getAcademicStanding
  standing: {
    standing: 'excellent' | 'good' | 'satisfactory' | 'probation' | 'unsatisfactory' | 'unknown';
    gpa: number; // rounded to 2 decimals
    required_gpa: number;
    meets_requirement: boolean;
    warning_level: 'critical' | 'warning' | 'watch' | null;
    dean_list_eligible?: boolean;
    honors_eligible?: boolean;
  } | null;
}
```

### CreditProgress
```typescript
interface CreditProgress {
  overall: any | null;
  by_category: Array<any>;
  semester_breakdown: Array<any>;
  remaining_requirements: Array<any>;
  graduation_readiness: any | null;
}
```

### AcademicHolds
```typescript
interface AcademicHolds {
  summary: {
    total_holds: number;
    blocking_registration: number;
    blocking_graduation: number;
  };
  holds: Array<{
    id: number;
    type: string;
    category: string;
    title: string;
    description: string;
    amount: number | null;
    priority: number;
    placed_date: string; // YYYY-MM-DD format
    due_date: string | null; // YYYY-MM-DD format
  }>;
}
```

### UpcomingAssessments
```typescript
interface UpcomingAssessments {
  summary: {
    total_upcoming: number;
  };
  assessments: Array<{
    id: number;
    title: string;
    type: string;
    course: {
      code: string;
      name: string;
    };
    due_date: string | null; // YYYY-MM-DD format
    max_score: number;
    weight: number;
    status: string;
    urgency: 'overdue' | 'critical' | 'high' | 'medium' | 'low' | 'unknown';
  }>;
}
```

### EnrollmentStatus
```typescript
interface EnrollmentStatus {
  status: string;
  semester_number: number | null;
}
```

### QuickStats
```typescript
interface QuickStats {
  academic_performance: {
    total_courses_completed: number;
    current_semester_courses: number;
    attendance_rate: number;
  };
}
```
## Notes
- The response is cached for 5 minutes (300 seconds) to improve performance
- All date fields use YYYY-MM-DD format
- All timestamp fields use ISO 8601 format
- The urgency level for assessments is automatically calculated based on due date:
  - `overdue`: Past due date
  - `critical`: Due within 1 day
  - `high`: Due within 3 days
  - `medium`: Due within 7 days
  - `low`: Due beyond 7 days
  - `unknown`: No due date specified
