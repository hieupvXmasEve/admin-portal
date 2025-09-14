# Student Curriculum By Semester API

## Endpoint
`GET /api/v1/student/curriculum/by-semester`

## Description
Retrieves all subjects in a student's curriculum organized by semester, including grades, study status, and course registration information. Results are ordered by registered courses within each semester.

## Authentication
Requires authentication with student credentials via Sanctum token.

## Response Structure

### TypeScript Interfaces

```typescript
interface CurriculumBySemesterResponse {
  success: boolean;
  message: string;
  data: CurriculumBySemesterData;
}

interface CurriculumBySemesterData {
  semesters: SemesterData[];
  overall_summary: OverallSummary;
  study_progress: StudyProgress;
  registration_insights: RegistrationInsights;
  generated_at: string;
}

interface SemesterData {
  semester_info: SemesterInfo;
  subjects: SubjectData[];
  summary: SemesterSummary;
  progress_indicators: SemesterProgressIndicators;
}

interface SemesterInfo {
  year_level: number;
  semester_number: number;
  display_name: string;
  short_name: string;
  academic_period: string;
}

interface SubjectData {
  curriculum_unit: CurriculumUnitInfo;
  unit: UnitInfo;
  study_status: StudyStatus;
  grade_info: GradeInfo | null;
  registration_info: RegistrationInfo;
  academic_indicators: AcademicIndicators;
}

interface CurriculumUnitInfo {
  id: number;
  year_level: number;
  semester_number: number;
  unit_scope: string;
  unit_scope_display: string;
  note: string | null;
}

interface UnitInfo {
  id: number;
  code: string;
  name: string;
  credit_points: number;
  full_name: string;
}

interface StudyStatus {
  status: 'completed' | 'in_progress' | 'registered' | 'failed' | 'not_started';
  label: string;
  description: string;
  color: string;
  icon: string;
  priority: number;
}

interface GradeInfo {
  final_percentage: number | null;
  final_letter_grade: string | null;
  grade_points: number | null;
  completion_date: string | null;
  grade_status: string | null;
  is_passing: boolean;
  grade_display: string | null;
  performance_indicator: PerformanceIndicator | null;
}

interface PerformanceIndicator {
  performance_level: 'excellent' | 'good' | 'satisfactory' | 'needs_improvement' | 'poor';
  color: string;
}

interface RegistrationInfo {
  has_registration: boolean;
  registrations: RegistrationData[];
  registration_count: number;
  latest_registration_status: string | null;
}

interface RegistrationData {
  id: number;
  status: string;
  status_display: string;
  registration_date: string | null;
  semester: RegistrationSemester;
  status_indicators: StatusIndicators;
}

interface RegistrationSemester {
  id: number;
  name: string;
  code: string;
}

interface StatusIndicators {
  color: string;
  icon: string;
  is_active: boolean;
}

interface AcademicIndicators {
  priority_indicator: 'high' | 'normal';
  academic_weight: number;
  progress_contribution: ProgressContribution;
}

interface ProgressContribution {
  credit_contribution: number;
  completion_impact: number;
}

interface SemesterSummary {
  totals: SemesterTotals;
  percentages: SemesterPercentages;
  semester_status: 'completed' | 'in_progress' | 'active' | 'planned';
}

interface SemesterTotals {
  total_subjects: number;
  total_credit_points: number;
  completed_subjects: number;
  current_subjects: number;
  not_started_subjects: number;
  registered_subjects: number;
}

interface SemesterPercentages {
  completion_percentage: number;
  registration_percentage: number;
  progress_percentage: number;
}

interface SemesterProgressIndicators {
  completion_rate: number;
  engagement_score: number;
  semester_health: 'excellent' | 'good' | 'fair' | 'needs_attention' | 'critical';
}

interface OverallSummary {
  curriculum_totals: CurriculumTotals;
  progress_totals: ProgressTotals;
  completion_metrics: CompletionMetrics;
  curriculum_health: CurriculumHealth;
}

interface CurriculumTotals {
  total_semesters: number;
  total_subjects: number;
  total_credit_points: number;
}

interface ProgressTotals {
  completed_subjects: number;
  current_subjects: number;
  not_started_subjects: number;
  registered_subjects: number;
}

interface CompletionMetrics {
  overall_completion_percentage: number;
  registration_coverage: number;
  active_participation: number;
}

interface CurriculumHealth {
  health_score: number;
  health_status: 'excellent' | 'good' | 'fair' | 'needs_improvement' | 'critical';
  improvement_areas: string[];
}

interface StudyProgress {
  overall_progress: OverallProgress;
  study_momentum: StudyMomentum;
  completion_forecast: CompletionForecast;
}

interface OverallProgress {
  percentage: number;
  status: 'nearing_completion' | 'advanced' | 'intermediate' | 'beginning' | 'just_started';
  color: string;
  milestone: 'graduation_ready' | 'senior_level' | 'mid_program' | 'foundation_complete' | 'getting_started';
}

interface StudyMomentum {
  momentum_score: number;
  trend: 'positive' | 'neutral' | 'negative';
  velocity: 'fast' | 'steady' | 'slow';
}

interface CompletionForecast {
  estimated_completion_date: string | null;
  projected_graduation_semester: string | null;
  acceleration_opportunities: string[];
}

interface RegistrationInsights {
  registration_analytics: RegistrationAnalytics;
  recommendations: Recommendations;
}

interface RegistrationAnalytics {
  current_registration_rate: number;
  registration_trend: 'highly_engaged' | 'well_engaged' | 'moderately_engaged' | 'low_engagement' | 'minimal_engagement';
  engagement_level: 'high' | 'medium' | 'low' | 'very_low';
}

interface Recommendations {
  priority_actions: string[];
  course_suggestions: string[];
  timeline_adjustments: string[];
}
```

### JSON Response Example

```json
{
  "success": true,
  "message": "Curriculum by semester retrieved successfully",
  "data": {
    "semesters": [
      {
        "semester_info": {
          "year_level": 1,
          "semester_number": 1,
          "display_name": "Year 1 - Semester 1",
          "short_name": "Y1S1",
          "academic_period": "Year 1, Semester 1"
        },
        "subjects": [
          {
            "curriculum_unit": {
              "id": 1,
              "year_level": 1,
              "semester_number": 1,
              "unit_scope": "common",
              "unit_scope_display": "Core Subject",
              "note": null
            },
            "unit": {
              "id": 101,
              "code": "MATH101",
              "name": "Calculus I",
              "credit_points": 3.0,
              "full_name": "MATH101 - Calculus I"
            },
            "study_status": {
              "status": "completed",
              "label": "Completed",
              "description": "Subject has been completed",
              "color": "green",
              "icon": "check-circle",
              "priority": 4
            },
            "grade_info": {
              "final_percentage": 85.5,
              "final_letter_grade": "B+",
              "grade_points": 3.3,
              "completion_date": "2024-06-15",
              "grade_status": "final",
              "is_passing": true,
              "grade_display": "B+ (85.5%)",
              "performance_indicator": {
                "performance_level": "good",
                "color": "blue"
              }
            },
            "registration_info": {
              "has_registration": true,
              "registrations": [
                {
                  "id": 1001,
                  "status": "completed",
                  "status_display": "Completed",
                  "registration_date": "2024-01-15",
                  "semester": {
                    "id": 1,
                    "name": "Semester 1 2024",
                    "code": "2024_1"
                  },
                  "status_indicators": {
                    "color": "green",
                    "icon": "check-circle",
                    "is_active": false
                  }
                }
              ],
              "registration_count": 1,
              "latest_registration_status": "completed"
            },
            "academic_indicators": {
              "priority_indicator": "high",
              "academic_weight": 3.0,
              "progress_contribution": {
                "credit_contribution": 3.0,
                "completion_impact": 3.0
              }
            }
          }
        ],
        "summary": {
          "totals": {
            "total_subjects": 4,
            "total_credit_points": 12.0,
            "completed_subjects": 3,
            "current_subjects": 1,
            "not_started_subjects": 0,
            "registered_subjects": 4
          },
          "percentages": {
            "completion_percentage": 75.0,
            "registration_percentage": 100.0,
            "progress_percentage": 100.0
          },
          "semester_status": "in_progress"
        },
        "progress_indicators": {
          "completion_rate": 75.0,
          "engagement_score": 100,
          "semester_health": "excellent"
        }
      }
    ],
    "overall_summary": {
      "curriculum_totals": {
        "total_semesters": 8,
        "total_subjects": 32,
        "total_credit_points": 120.0
      },
      "progress_totals": {
        "completed_subjects": 8,
        "current_subjects": 4,
        "not_started_subjects": 20,
        "registered_subjects": 12
      },
      "completion_metrics": {
        "overall_completion_percentage": 25.0,
        "registration_coverage": 37.5,
        "active_participation": 37.5
      },
      "curriculum_health": {
        "health_score": 35,
        "health_status": "needs_improvement",
        "improvement_areas": ["course_registration", "academic_progress"]
      }
    },
    "study_progress": {
      "overall_progress": {
        "percentage": 25.0,
        "status": "beginning",
        "color": "orange",
        "milestone": "foundation_complete"
      },
      "study_momentum": {
        "momentum_score": 75,
        "trend": "positive",
        "velocity": "steady"
      },
      "completion_forecast": {
        "estimated_completion_date": null,
        "projected_graduation_semester": null,
        "acceleration_opportunities": []
      }
    },
    "registration_insights": {
      "registration_analytics": {
        "current_registration_rate": 37.5,
        "registration_trend": "moderately_engaged",
        "engagement_level": "low"
      },
      "recommendations": {
        "priority_actions": [],
        "course_suggestions": [],
        "timeline_adjustments": []
      }
    },
    "generated_at": "2024-01-20T10:30:00Z"
  }
}
```

## Study Status Values

- `completed`: Subject has been completed
- `in_progress`: Subject is currently being studied
- `registered`: Registered for this semester
- `failed`: Subject did not meet requirements
- `not_started`: Subject has not been started yet

## Subject Ordering

Within each semester, subjects are ordered by:
1. **Registered subjects first**: Subjects with active course registrations appear first
2. **Alphabetical by unit code**: Secondary sorting by unit code (e.g., MATH101, PHYS101)

## TypeScript Definitions File
The definitions file includes:
- Complete interface definitions
- Type unions and enums
- Helper type guards
- Utility types
- Constants for colors and icons

## Features

- ✅ Shows all curriculum subjects organized by academic semester
- ✅ Displays study status (completed, in progress, registered, not started, failed)
- ✅ Includes grade information and academic performance indicators
- ✅ Shows course registration history and status
- ✅ Provides semester-level and overall progress summaries  
- ✅ Orders subjects by registration status (registered subjects first)
- ✅ Comprehensive analytics and insights
- ✅ English language support for all status labels
- ✅ Full TypeScript support for Next.js integration
- ✅ Type-safe API consumption
- ✅ Caching for improved performance
