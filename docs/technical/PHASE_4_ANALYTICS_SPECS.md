# Phase 4: Advanced Analytics & Workflows
## Detailed Implementation Specifications

### Priority: 🔵 **MEDIUM-LOW** - Enhanced Features
**Estimated Timeline:** 6-7 weeks  
**Dependencies:** Phase 1, 2, 3 must be complete

---

## 4.1 Assessments & Grading

### 4.1.1 Assessment Components Management
**Route:** `/assessments/components`  
**Component:** `pages/assessments/Components.vue` (New)

#### Data Requirements
```typescript
interface AssessmentComponent {
  id: number;
  course_offering_id: number;
  course_offering: CourseOffering;
  name: string; // 'Assignment 1', 'Mid-term Exam', 'Final Project'
  type: 'assignment' | 'exam' | 'quiz' | 'project' | 'presentation' | 'participation';
  description?: string;
  weighting: number; // 0-100 (percentage of total grade)
  max_score: number; // 100, 50, etc.
  due_date?: string;
  submission_method: 'online' | 'paper' | 'presentation' | 'practical';
  is_group_work: boolean;
  late_penalty?: number; // Percentage per day
  assessment_criteria: AssessmentCriterion[];
  rubric?: AssessmentRubric;
  created_at: string;
  updated_at: string;
}

interface AssessmentCriterion {
  id: number;
  name: string; // 'Technical Implementation', 'Documentation Quality'
  description: string;
  max_points: number;
  weighting: number; // Within this assessment component
}

interface AssessmentRubric {
  id: number;
  name: string;
  criteria: RubricCriterion[];
  performance_levels: PerformanceLevel[];
}

interface AssessmentScore {
  id: number;
  assessment_component_id: number;
  student_id: number;
  student: Student;
  raw_score: number;
  weighted_score: number;
  letter_grade?: string;
  submission_date?: string;
  is_late: boolean;
  late_penalty_applied: number;
  feedback?: string;
  graded_by: number; // lecturer_id
  graded_at?: string;
  created_at: string;
  updated_at: string;
}
```

#### API Endpoints Required
- `GET /api/assessment-components` - List assessment components
- `POST /api/assessment-components` - Create assessment component
- `GET /api/assessment-components/{id}` - Get component details
- `PUT /api/assessment-components/{id}` - Update component
- `DELETE /api/assessment-components/{id}` - Delete component
- `GET /api/assessment-components/{id}/scores` - Get all scores for component
- `POST /api/assessment-scores` - Enter/update assessment scores
- `GET /api/assessment-components/{id}/statistics` - Component statistics

#### Features Required
- ⏳ Assessment component builder
- ⏳ Rubric management interface
- ⏳ Grade entry and editing
- ⏳ Bulk grade import/export
- ⏳ Late submission tracking
- ⏳ Assessment analytics
- ⏳ Feedback management

### 4.1.2 Grading & Academic Results
**Route:** `/assessments/grading`  
**Component:** `pages/assessments/Grading.vue` (New)

#### Data Requirements
```typescript
interface FinalGrade {
  id: number;
  course_registration_id: number;
  course_registration: CourseRegistration;
  student_id: number;
  course_offering_id: number;
  assessment_scores: AssessmentScore[];
  total_weighted_score: number; // 0-100
  letter_grade: string; // 'HD', 'D', 'C', 'P', 'N'
  grade_points: number; // For GPA calculation
  status: 'in_progress' | 'provisional' | 'final' | 'under_review';
  moderation_required: boolean;
  moderated_by?: number; // lecturer_id
  moderation_date?: string;
  external_examiner_review?: boolean;
  release_date?: string;
  student_notified: boolean;
  created_at: string;
  updated_at: string;
}

interface GradeDistribution {
  course_offering: CourseOffering;
  total_students: number;
  grade_breakdown: {
    HD: number; // High Distinction: 80-100
    D: number;  // Distinction: 70-79
    C: number;  // Credit: 60-69
    P: number;  // Pass: 50-59
    N: number;  // Fail: 0-49
  };
  average_score: number;
  median_score: number;
  standard_deviation: number;
}

interface GradeModerationRecord {
  id: number;
  course_offering_id: number;
  moderator_id: number;
  moderation_type: 'internal' | 'external' | 'peer_review';
  samples_reviewed: number;
  adjustments_made: number;
  recommendation: string;
  completed_date: string;
}
```

#### API Endpoints Required
- `GET /api/final-grades` - List final grades with filters
- `POST /api/final-grades/calculate` - Calculate final grades
- `PUT /api/final-grades/{id}` - Update final grade
- `GET /api/final-grades/{id}/breakdown` - Grade calculation breakdown
- `GET /api/courses/{id}/grade-distribution` - Grade distribution for course
- `POST /api/grades/moderate` - Grade moderation workflow
- `POST /api/grades/release` - Release grades to students

#### Features Required
- ⏳ Automated grade calculation
- ⏳ Grade distribution analysis
- ⏳ Moderation workflow
- ⏳ Grade release management
- ⏳ Student notification system
- ⏳ Grade appeal process
- ⏳ External examiner integration

---

## 4.2 Academic Summary & GPA Management

### 4.2.1 GPA Calculations & Academic Standing
**Route:** `/academic-summary/gpa`  
**Component:** `pages/academic-summary/GPA.vue` (New)

#### Data Requirements
```typescript
interface GPACalculation {
  id: number;
  student_id: number;
  student: Student;
  semester_id: number;
  semester: Semester;
  semester_gpa: number;
  cumulative_gpa: number;
  credit_points_attempted: number;
  credit_points_completed: number;
  total_grade_points: number;
  units_included: GPAUnit[];
  academic_standing: AcademicStanding;
  calculation_date: string;
  created_at: string;
  updated_at: string;
}

interface GPAUnit {
  unit_code: string;
  unit_name: string;
  credit_points: number;
  grade: string;
  grade_points: number;
  is_repeated_unit: boolean;
  semester: string;
}

interface AcademicStanding {
  status: 'good_standing' | 'academic_warning' | 'academic_probation' | 'exclusion_risk';
  gpa_threshold: number;
  consecutive_low_semesters: number;
  actions_required: string[];
  next_review_date?: string;
  improvement_plan?: string;
}

interface DegreeClassification {
  student_id: number;
  program: Program;
  overall_gpa: number;
  final_year_gpa: number;
  classification: 'first_class' | 'second_class_upper' | 'second_class_lower' | 'third_class' | 'pass';
  honors_eligible: boolean;
  graduation_date?: string;
  transcript_notes: string[];
}
```

#### API Endpoints Required
- `GET /api/gpa-calculations/{student_id}` - Student GPA history
- `POST /api/gpa-calculations/calculate` - Recalculate GPA
- `GET /api/academic-standing/{student_id}` - Academic standing status
- `GET /api/degree-classification/{student_id}` - Degree classification
- `GET /api/gpa-statistics` - GPA statistics by program/semester
- `POST /api/academic-standing/review` - Academic standing review
- `GET /api/graduation-eligibility/{student_id}` - Graduation eligibility check

#### Features Required
- ⏳ GPA calculation engine
- ⏳ Academic standing monitoring
- ⏳ Degree classification calculator
- ⏳ GPA trend analysis
- ⏳ Academic intervention alerts
- ⏳ Graduation eligibility tracking
- ⏳ Historical GPA comparison

### 4.2.2 Transcript & Academic Records
**Route:** `/academic-summary/transcripts`  
**Component:** `pages/academic-summary/Transcripts.vue` (New)

#### Data Requirements
```typescript
interface OfficialTranscript {
  id: number;
  student_id: number;
  transcript_type: 'academic' | 'completion' | 'interim' | 'verification';
  generation_date: string;
  requested_by: string;
  purpose: string; // 'employment', 'further_study', 'immigration'
  academic_records: TranscriptRecord[];
  program_details: TranscriptProgramDetail[];
  overall_summary: TranscriptSummary;
  certifications: TranscriptCertification[];
  digital_signature: string;
  verification_code: string;
  pdf_path: string;
  is_official: boolean;
  release_authorized: boolean;
  created_at: string;
}

interface TranscriptRecord {
  semester: string;
  unit_code: string;
  unit_name: string;
  credit_points: number;
  grade: string;
  status: 'completed' | 'failed' | 'withdrawn';
  attempt_number: number;
}

interface TranscriptSummary {
  total_credit_points_attempted: number;
  total_credit_points_completed: number;
  overall_gpa: number;
  degree_classification: string;
  graduation_date?: string;
  academic_awards: string[];
}

interface AcademicAward {
  id: number;
  student_id: number;
  award_type: 'deans_list' | 'honors' | 'scholarship' | 'academic_excellence';
  award_name: string;
  semester_id?: number;
  year_awarded: number;
  criteria_met: string;
  is_transcript_eligible: boolean;
}
```

#### API Endpoints Required
- `GET /api/transcripts/{student_id}` - Student transcript history
- `POST /api/transcripts/generate` - Generate new transcript
- `POST /api/transcripts/{id}/certify` - Certify transcript
- `GET /api/transcripts/{id}/verify` - Verify transcript authenticity
- `POST /api/transcripts/{id}/send` - Send transcript electronically
- `GET /api/academic-awards/{student_id}` - Student academic awards
- `POST /api/academic-awards` - Add academic award

#### Features Required
- ⏳ Official transcript generator (PDF)
- ⏳ Digital certification system
- ⏳ Transcript verification portal
- ⏳ Electronic transcript delivery
- ⏳ Academic award tracking
- ⏳ Transcript request workflow
- ⏳ Authentication and security

---

## 4.3 Program Transfers & Course Management

### 4.3.1 Program Change Requests
**Route:** `/transfers/program-changes`  
**Component:** `pages/transfers/ProgramChanges.vue` (New)

#### Data Requirements
```typescript
interface ProgramChangeRequest {
  id: number;
  student_id: number;
  student: Student;
  current_program_id: number;
  current_program: Program;
  requested_program_id: number;
  requested_program: Program;
  current_specialization_id?: number;
  requested_specialization_id?: number;
  request_date: string;
  reason: string;
  status: 'pending' | 'under_review' | 'approved' | 'rejected' | 'completed';
  reviewed_by?: number; // staff_id
  review_date?: string;
  review_notes?: string;
  effective_date?: string;
  credit_transfer_analysis: CreditTransferAnalysis;
  additional_requirements: string[];
  approval_conditions: string[];
  created_at: string;
  updated_at: string;
}

interface CreditTransferAnalysis {
  current_credits_completed: number;
  transferable_credits: number;
  non_transferable_credits: number;
  additional_credits_required: number;
  unit_mappings: UnitMapping[];
  exemptions_granted: UnitExemption[];
  timeline_impact: TimelineImpact;
}

interface UnitMapping {
  current_unit_id: number;
  current_unit: Unit;
  mapped_unit_id?: number;
  mapped_unit?: Unit;
  mapping_type: 'direct' | 'equivalent' | 'credit_only' | 'not_applicable';
  credit_value: number;
  additional_requirements?: string;
}

interface TimelineImpact {
  current_expected_graduation: string;
  new_expected_graduation: string;
  additional_semesters: number;
  summer_school_required: boolean;
  overload_semesters: number;
}
```

#### API Endpoints Required
- `GET /api/program-change-requests` - List program change requests
- `POST /api/program-change-requests` - Submit program change request
- `GET /api/program-change-requests/{id}` - Get request details
- `PUT /api/program-change-requests/{id}` - Update request
- `POST /api/program-change-requests/{id}/review` - Review request
- `POST /api/program-change-requests/{id}/approve` - Approve request
- `GET /api/program-change-requests/{id}/credit-analysis` - Credit transfer analysis
- `POST /api/program-change-requests/{id}/implement` - Implement approved change

#### Features Required
- ⏳ Program change request form
- ⏳ Credit transfer calculator
- ⏳ Unit mapping interface
- ⏳ Approval workflow
- ⏳ Timeline impact analysis
- ⏳ Automatic credit transfer
- ⏳ Document generation

### 4.3.2 Course Retakes & Exemptions
**Route:** `/transfers/retakes-exemptions`  
**Component:** `pages/transfers/RetakesExemptions.vue` (New)

#### Data Requirements
```typescript
interface CourseRetake {
  id: number;
  student_id: number;
  original_registration_id: number;
  original_registration: CourseRegistration;
  retake_registration_id: number;
  retake_registration: CourseRegistration;
  retake_reason: 'failed' | 'improved_grade' | 'academic_requirement';
  original_grade: string;
  retake_grade?: string;
  grade_replacement_policy: 'best_grade' | 'latest_grade' | 'average_grade';
  financial_impact: number; // Additional fees
  academic_impact: string;
  approval_required: boolean;
  approved_by?: number;
  created_at: string;
  updated_at: string;
}

interface UnitExemption {
  id: number;
  student_id: number;
  unit_id: number;
  unit: Unit;
  exemption_type: 'prior_learning' | 'work_experience' | 'external_study' | 'advanced_standing';
  evidence_provided: string;
  credit_points_granted: number;
  grade_assigned?: string; // If grade is given
  affects_gpa: boolean;
  assessment_method: string;
  assessor_id: number;
  assessment_date: string;
  validity_period?: string;
  conditions?: string;
  created_at: string;
  updated_at: string;
}

interface SubstituteCourse {
  id: number;
  original_unit_id: number;
  substitute_unit_id: number;
  program_id?: number; // If specific to a program
  substitution_reason: string;
  credit_equivalence: number;
  prerequisite_adjustments: string[];
  effective_from: string;
  effective_until?: string;
  approved_by: number;
  is_active: boolean;
}
```

#### API Endpoints Required
- `GET /api/course-retakes` - List course retakes
- `POST /api/course-retakes` - Register course retake
- `GET /api/unit-exemptions` - List unit exemptions
- `POST /api/unit-exemptions` - Apply for unit exemption
- `PUT /api/unit-exemptions/{id}` - Process exemption application
- `GET /api/substitute-courses` - List substitute course mappings
- `POST /api/substitute-courses` - Create substitute mapping
- `GET /api/students/{id}/credit-summary` - Student credit summary

#### Features Required
- ⏳ Course retake management
- ⏳ Exemption application workflow
- ⏳ Evidence upload and review
- ⏳ Substitute course mapping
- ⏳ Credit equivalence calculator
- ⏳ Prior learning assessment
- ⏳ Advanced standing processing

---

## Implementation Order

### Week 1-2: Assessment Infrastructure
1. **Assessment Component Builder**
   - Component creation interface
   - Rubric management system
   - Assessment criteria definition

2. **Grade Entry System**
   - Bulk grade import/export
   - Grade calculation engine
   - Late submission tracking

### Week 3-4: Grading & Moderation
3. **Final Grade Processing**
   - Automated grade calculation
   - Grade distribution analysis
   - Moderation workflow

4. **Grade Release Management**
   - Student notification system
   - Grade appeal process
   - Release authorization

### Week 5: GPA & Academic Standing
5. **GPA Calculation System**
   - Automated GPA calculations
   - Academic standing monitoring
   - Degree classification

6. **Academic Intervention**
   - At-risk student identification
   - Intervention workflow
   - Progress tracking

### Week 6: Transcript Management
7. **Official Transcript System**
   - PDF transcript generation
   - Digital certification
   - Verification portal

8. **Academic Award Tracking**
   - Award eligibility checking
   - Award notification system
   - Transcript integration

### Week 7: Transfer & Change Management
9. **Program Change Workflow**
   - Change request processing
   - Credit transfer analysis
   - Approval workflow

10. **Course Management**
    - Retake processing
    - Exemption workflow
    - Substitute course mapping

---

## Success Criteria

### Assessment & Grading Requirements
- ⏳ Comprehensive assessment management
- ⏳ Accurate grade calculations
- ⏳ Robust moderation process
- ⏳ Transparent grade release system

### Academic Progress Requirements
- ⏳ Real-time GPA calculations
- ⏳ Proactive academic intervention
- ⏳ Accurate degree classification
- ⏳ Official transcript generation

### Transfer & Change Requirements
- ⏳ Efficient program change processing
- ⏳ Accurate credit transfer analysis
- ⏳ Streamlined exemption workflow
- ⏳ Comprehensive course substitution

---

## Dependencies for Phase 5

Before starting Phase 5, ensure:
1. ⏳ Assessment system fully operational
2. ⏳ GPA calculations accurate and automated
3. ⏳ Transcript generation working
4. ⏳ Program change workflow functional
5. ⏳ All academic processes integrated

## Risk Mitigation

### High Risk Areas
- **Grade Calculation Accuracy** - Extensive testing with real academic scenarios
- **Transcript Legal Compliance** - Ensure all regulatory requirements met
- **Credit Transfer Complexity** - Comprehensive mapping and validation rules

### Mitigation Strategies
- Multiple validation layers for grade calculations
- Legal review of transcript and certification processes
- Extensive testing with historical data
- User acceptance testing with academic staff
- Gradual rollout with constant monitoring 
