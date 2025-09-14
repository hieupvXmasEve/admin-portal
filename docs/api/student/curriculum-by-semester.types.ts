/**
 * TypeScript definitions for Student Curriculum By Semester API
 * API Endpoint: GET /api/v1/student/curriculum/by-semester
 * 
 * Copy this file to your Next.js project's types directory
 * Usage: import { CurriculumBySemesterResponse, SemesterData } from '@/types/api/curriculum-by-semester';
 */

// Main API Response
export interface CurriculumBySemesterResponse {
  success: boolean;
  message: string;
  data: CurriculumBySemesterData;
}

// Root Data Structure
export interface CurriculumBySemesterData {
  semesters: SemesterData[];
  overall_summary: OverallSummary;
  study_progress: StudyProgress;
  registration_insights: RegistrationInsights;
  generated_at: string;
}

// Semester-level Data
export interface SemesterData {
  semester_info: SemesterInfo;
  subjects: SubjectData[];
  summary: SemesterSummary;
  progress_indicators: SemesterProgressIndicators;
}

export interface SemesterInfo {
  year_level: number;
  semester_number: number;
  display_name: string;
  short_name: string;
  academic_period: string;
}

// Subject-level Data
export interface SubjectData {
  curriculum_unit: CurriculumUnitInfo;
  unit: UnitInfo;
  study_status: StudyStatus;
  grade_info: GradeInfo | null;
  registration_info: RegistrationInfo;
  academic_indicators: AcademicIndicators;
}

export interface CurriculumUnitInfo {
  id: number;
  year_level: number;
  semester_number: number;
  unit_scope: UnitScope;
  unit_scope_display: string;
  note: string | null;
}

export interface UnitInfo {
  id: number;
  code: string;
  name: string;
  credit_points: number;
  full_name: string;
}

export interface StudyStatus {
  status: StudyStatusType;
  label: string;
  description: string;
  color: string;
  icon: string;
  priority: number;
}

export interface GradeInfo {
  final_percentage: number | null;
  final_letter_grade: string | null;
  grade_points: number | null;
  completion_date: string | null;
  grade_status: string | null;
  is_passing: boolean;
  grade_display: string | null;
  performance_indicator: PerformanceIndicator | null;
}

export interface PerformanceIndicator {
  performance_level: PerformanceLevel;
  color: string;
}

export interface RegistrationInfo {
  has_registration: boolean;
  registrations: RegistrationData[];
  registration_count: number;
  latest_registration_status: string | null;
}

export interface RegistrationData {
  id: number;
  status: string;
  status_display: string;
  registration_date: string | null;
  semester: RegistrationSemester;
  status_indicators: StatusIndicators;
}

export interface RegistrationSemester {
  id: number;
  name: string;
  code: string;
}

export interface StatusIndicators {
  color: string;
  icon: string;
  is_active: boolean;
}

export interface AcademicIndicators {
  priority_indicator: PriorityLevel;
  academic_weight: number;
  progress_contribution: ProgressContribution;
}

export interface ProgressContribution {
  credit_contribution: number;
  completion_impact: number;
}

// Semester Summary Data
export interface SemesterSummary {
  totals: SemesterTotals;
  percentages: SemesterPercentages;
  semester_status: SemesterStatusType;
}

export interface SemesterTotals {
  total_subjects: number;
  total_credit_points: number;
  completed_subjects: number;
  current_subjects: number;
  not_started_subjects: number;
  registered_subjects: number;
}

export interface SemesterPercentages {
  completion_percentage: number;
  registration_percentage: number;
  progress_percentage: number;
}

export interface SemesterProgressIndicators {
  completion_rate: number;
  engagement_score: number;
  semester_health: HealthStatusType;
}

// Overall Summary Data
export interface OverallSummary {
  curriculum_totals: CurriculumTotals;
  progress_totals: ProgressTotals;
  completion_metrics: CompletionMetrics;
  curriculum_health: CurriculumHealth;
}

export interface CurriculumTotals {
  total_semesters: number;
  total_subjects: number;
  total_credit_points: number;
}

export interface ProgressTotals {
  completed_subjects: number;
  current_subjects: number;
  not_started_subjects: number;
  registered_subjects: number;
}

export interface CompletionMetrics {
  overall_completion_percentage: number;
  registration_coverage: number;
  active_participation: number;
}

export interface CurriculumHealth {
  health_score: number;
  health_status: HealthStatusType;
  improvement_areas: string[];
}

// Study Progress Data
export interface StudyProgress {
  overall_progress: OverallProgress;
  study_momentum: StudyMomentum;
  completion_forecast: CompletionForecast;
}

export interface OverallProgress {
  percentage: number;
  status: ProgressStatusType;
  color: string;
  milestone: MilestoneType;
}

export interface StudyMomentum {
  momentum_score: number;
  trend: TrendType;
  velocity: VelocityType;
}

export interface CompletionForecast {
  estimated_completion_date: string | null;
  projected_graduation_semester: string | null;
  acceleration_opportunities: string[];
}

// Registration Insights Data
export interface RegistrationInsights {
  registration_analytics: RegistrationAnalytics;
  recommendations: Recommendations;
}

export interface RegistrationAnalytics {
  current_registration_rate: number;
  registration_trend: RegistrationTrendType;
  engagement_level: EngagementLevelType;
}

export interface Recommendations {
  priority_actions: string[];
  course_suggestions: string[];
  timeline_adjustments: string[];
}

// Type Unions and Enums
export type StudyStatusType = 
  | 'completed' 
  | 'in_progress' 
  | 'registered' 
  | 'failed' 
  | 'not_started';

export type UnitScope = 
  | 'common' 
  | 'specialization_specific' 
  | 'cross_program';

export type PerformanceLevel = 
  | 'excellent' 
  | 'good' 
  | 'satisfactory' 
  | 'needs_improvement' 
  | 'poor';

export type PriorityLevel = 
  | 'high' 
  | 'normal';

export type SemesterStatusType = 
  | 'completed' 
  | 'in_progress' 
  | 'active' 
  | 'planned';

export type HealthStatusType = 
  | 'excellent' 
  | 'good' 
  | 'fair' 
  | 'needs_attention' 
  | 'critical' 
  | 'needs_improvement';

export type ProgressStatusType = 
  | 'nearing_completion' 
  | 'advanced' 
  | 'intermediate' 
  | 'beginning' 
  | 'just_started';

export type MilestoneType = 
  | 'graduation_ready' 
  | 'senior_level' 
  | 'mid_program' 
  | 'foundation_complete' 
  | 'getting_started';

export type TrendType = 
  | 'positive' 
  | 'neutral' 
  | 'negative';

export type VelocityType = 
  | 'fast' 
  | 'steady' 
  | 'slow';

export type RegistrationTrendType = 
  | 'highly_engaged' 
  | 'well_engaged' 
  | 'moderately_engaged' 
  | 'low_engagement' 
  | 'minimal_engagement';

export type EngagementLevelType = 
  | 'high' 
  | 'medium' 
  | 'low' 
  | 'very_low';

// Utility Types
export type StudyStatusColor = {
  [K in StudyStatusType]: string;
};

export type SubjectsByStatus = {
  [K in StudyStatusType]: SubjectData[];
};

// Helper Type Guards
export const isCompletedSubject = (subject: SubjectData): subject is SubjectData & {
  study_status: { status: 'completed' };
  grade_info: NonNullable<GradeInfo>;
} => {
  return subject.study_status.status === 'completed' && subject.grade_info !== null;
};

export const hasRegistration = (subject: SubjectData): subject is SubjectData & {
  registration_info: { has_registration: true; registrations: [RegistrationData, ...RegistrationData[]] };
} => {
  return subject.registration_info.has_registration && subject.registration_info.registrations.length > 0;
};

export const isPassingGrade = (gradeInfo: GradeInfo | null): gradeInfo is GradeInfo & {
  is_passing: true;
  grade_points: number;
} => {
  return gradeInfo?.is_passing === true && gradeInfo.grade_points !== null && gradeInfo.grade_points > 0;
};

// Constants
export const STUDY_STATUS_COLORS: StudyStatusColor = {
  completed: 'green',
  in_progress: 'blue',
  registered: 'purple',
  failed: 'red',
  not_started: 'gray',
} as const;

export const STUDY_STATUS_ICONS = {
  completed: 'check-circle',
  in_progress: 'clock',
  registered: 'bookmark',
  failed: 'x-circle',
  not_started: 'circle',
} as const;

export const UNIT_SCOPE_LABELS = {
  common: 'Core Subject',
  specialization_specific: 'Specialization Subject',
  cross_program: 'Elective Subject',
} as const;
