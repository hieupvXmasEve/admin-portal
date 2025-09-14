# Grade Management API Documentation

## Endpoints

### 1. Get Grade Table
**GET** `/api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{assessmentDetailId}/grades`

#### Query Parameters
```typescript
interface GradeTableFilters {
  page?: number;                // Default: 1
  per_page?: number;           // Default: 20, Max: 100
  sort_by?: 'student_id' | 'student_name' | 'points_earned' | 'percentage_score' | 'letter_grade' | 'submitted_at' | 'graded_at' | 'status' | 'is_late';
  sort_order?: 'asc' | 'desc'; // Default: 'asc'
  status?: 'not_submitted' | 'submitted' | 'grading' | 'graded' | 'returned';
  score_status?: 'draft' | 'provisional' | 'final';
  min_score?: number;          // 0-100
  max_score?: number;          // 0-100
  letter_grade?: 'A+' | 'A' | 'A-' | 'B+' | 'B' | 'B-' | 'C+' | 'C' | 'C-' | 'D+' | 'D' | 'F';
  is_late?: boolean;
  plagiarism_suspected?: boolean;
  score_excluded?: boolean;
  appeal_requested?: boolean;
  search?: string;             // Search student name/ID
  submitted_after?: string;    // ISO date
  submitted_before?: string;   // ISO date
  graded_after?: string;       // ISO date
  graded_before?: string;      // ISO date
}
```

#### Response
```typescript
interface GradeTableResponse {
  data: GradeRecord[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
  };
  assessment_detail: {
    id: number;
    name: string;
    max_points: number;
    weight: number;
    due_date: string | null;
  };
}

interface GradeRecord {
  score_id: number | null;
  student: {
    id: number;
    student_id: string;
    name: string;
    first_name: string;
    last_name: string;
    email: string;
  };
  score_data: {
    id: number;
    points_earned: number | null;
    percentage_score: number | null;
    letter_grade: string | null;
    status: 'not_submitted' | 'submitted' | 'grading' | 'graded' | 'returned';
    score_status: 'draft' | 'provisional' | 'final';
    instructor_feedback: string | null;
    private_notes: string | null;
    graded_at: string | null;
    created_at: string;
    updated_at: string;
  } | null;
  submission_info: {
    submitted_at: string | null;
    submission_attempt: number | null;
    is_late: boolean;
    minutes_late: number | null;
    late_penalty_applied: number | null;
  } | null;
  grading_info: {
    graded_by: { id: number; name: string; } | null;
    graded_at: string | null;
    last_modified_by: { id: number; name: string; } | null;
    last_modified_at: string | null;
  } | null;
  flags: {
    plagiarism_suspected: boolean;
    score_excluded: boolean;
    appeal_requested: boolean;
    is_extra_credit: boolean;
    is_makeup: boolean;
  } | null;
}
```

### 2. Get Grade Statistics
**GET** `/api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{assessmentDetailId}/statistics`

#### Response
```typescript
interface GradeStatisticsResponse {
  summary: {
    total_students: number;
    graded_count: number;
    pending_count: number;
    mean: number;
    median: number;
    mode: number | null;
    standard_deviation: number;
    min_score: number;
    max_score: number;
    range: number;
  };
  quartiles: {
    q1: number;
    q2: number;
    q3: number;
  };
  grade_distribution: Array<{
    grade: string;
    count: number;
    percentage: number;
  }>;
  score_ranges: Array<{
    range: string;
    count: number;
    percentage: number;
  }>;
  performance_metrics: {
    submission_rate: number;
    completion_rate: number;
    pass_rate: number;
    fail_rate: number;
    late_submission_rate: number;
    average_late_penalty: number;
  };
  visualization_data: {
    histogram: Array<{
      range: string;
      min: number;
      max: number;
      count: number;
    }>;
    box_plot: {
      min: number;
      q1: number;
      median: number;
      q3: number;
      max: number;
      outliers: number[];
    };
    cumulative_frequency: Array<{
      score: number;
      cumulative_count: number;
      cumulative_percentage: number;
    }>;
  };
}
```

### 3. Bulk Update Grades
**POST** `/api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{assessmentDetailId}/bulk-grades`

#### Request Body
```typescript
interface BulkGradeRequest {
  grades: Array<{
    student_id: number;
    points_earned?: number;
    percentage_score?: number;
    letter_grade?: string;
    status?: 'not_submitted' | 'submitted' | 'grading' | 'graded' | 'returned';
    score_status?: 'draft' | 'provisional' | 'final';
    instructor_feedback?: string;
    private_notes?: string;
  }>;
}
```

#### Response
```typescript
interface BulkGradeResponse {
  created: Array<{
    student_id: number;
    score_id: number;
  }>;
  updated: Array<{
    student_id: number;
    score_id: number;
  }>;
  errors: Array<{
    data?: object;
    student_id?: number;
    error: string;
  }>;
}
```

### 4. Export Grades
**GET** `/api/v1/lecturer/courses/{courseOfferingId}/assessments/details/{assessmentDetailId}/export`

#### Query Parameters
```typescript
interface ExportParams {
  format?: 'excel' | 'csv'; // Default: 'excel'
}
```

#### Response
- **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (Excel) or `text/csv` (CSV)
- **Body**: Binary file data

### 5. Standard API Response Wrapper
All endpoints return responses wrapped in:
```typescript
interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
  errors?: Record<string, string[]>;
}
```
