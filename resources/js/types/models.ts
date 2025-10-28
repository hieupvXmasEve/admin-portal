import { BadgeVariants } from '@/components/ui/badge';
import { EmergencyContactStatus } from '@/types/student';

export interface Specialization {
    id: number;
    program_id: number;
    name: string;
    code: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    program?: Program;
    curriculum_versions_count?: number;
    curriculum_versions?: CurriculumVersion[];
}

export interface Program {
    id: number;
    name: string;
    code?: string;
    description?: string;
    created_at: string;
    updated_at: string;
    specializations?: Specialization[];
    curriculum_versions?: CurriculumVersion[];
}

export interface CurriculumVersion {
    id: number;
    program_id: number;
    specialization_id?: number;
    version_code: string;
    semester_id?: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    specialization?: Specialization;
    program?: Program;
    effective_from_semester?: Semester;
    curriculum_units_count?: number;
    curriculum_units?: CurriculumUnit[];
}

export interface Semester {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    created_at: string;
    updated_at: string;
}

export interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    level: number;
    unit_type: string;
    created_at: string;
    updated_at: string;
}

export interface UnitType {
    id: number;
    name: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

export interface Syllabus {
    id: number;
    curriculum_unit_id: number;
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    total_assessment_weight: number;
}

export interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    type: 'core' | 'major' | 'elective';
    unit_scope: 'program' | 'common' | 'specialization_specific';
    year_level?: number;
    semester_number?: number;
    note?: string;
    created_at: string;
    updated_at: string;
    unit: Unit;
    curriculum_version?: CurriculumVersion;
    syllabus: Syllabus;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Lecture {
    id: number;
    employee_id: string;
    title?: string;
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    mobile_phone?: string;
    campus_id: number;
    department?: string;
    faculty?: string;
    specialization?: string;
    expertise_areas?: string[];
    academic_rank: 'lecturer' | 'senior_lecturer' | 'associate_professor' | 'professor' | 'emeritus_professor' | 'visiting_lecturer' | 'adjunct_professor';
    highest_degree?: string;
    degree_field?: string;
    alma_mater?: string;
    graduation_year?: number;
    hire_date: string;
    contract_start_date?: string;
    contract_end_date?: string;
    employment_type: 'full_time' | 'part_time' | 'contract' | 'visiting' | 'emeritus';
    employment_status: 'active' | 'on_leave' | 'sabbatical' | 'retired' | 'terminated' | 'suspended';
    preferred_teaching_days?: string[];
    preferred_start_time?: string;
    preferred_end_time?: string;
    max_teaching_hours_per_week: number;
    teaching_modalities?: ('in_person' | 'online' | 'hybrid')[];
    office_address?: string;
    office_phone?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    emergency_contact_relationship?: string;
    biography?: string;
    certifications?: string[];
    languages?: string[];
    hourly_rate?: number;
    salary?: number;
    is_active: boolean;
    can_teach_online: boolean;
    is_available_for_assignment: boolean;
    notes?: string;
    campus?: Campus;
    created_at: string;
    updated_at: string;
    // Computed properties from model accessors
    full_name: string;
    display_name: string;
    years_of_service: number;
    is_contract_active: boolean;
}

export interface Campus {
    id: number;
    name: string;
    code: string;
    address?: string;
    buildings_count?: number;
    users_count?: number;
    buildings?: Building[];
    created_at: string;
    updated_at: string;
}

export interface Room {
    id: number;
    campus_id: number;
    name: string;
    code: string;
    building: Building;
    floor: string;
    type: 'classroom' | 'laboratory' | 'computer_lab' | 'auditorium' | 'meeting_room' | 'library' | 'study_room' | 'workshop' | 'office' | 'other';
    capacity: number;
    status: 'available' | 'occupied' | 'maintenance' | 'out_of_service' | 'reserved';
    is_bookable: boolean;
    requires_approval: boolean;
    available_from: string;
    available_until: string;
    blocked_days: string[];
    description: string;
    usage_guidelines: string;
    booking_notes: string;
    campus?: Campus;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Building {
    id: number;
    campus_id: number;
    name: string;
    code: string;
    type: 'academic' | 'administrative' | 'dormitory' | 'library' | 'other';
    description: string | null;
    address?: string;
    campus?: Campus;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export enum StudentStatus {
    // active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending
    Active = 'active',
    Inactive = 'inactive',
    Suspended = 'suspended',
    Graduated = 'graduated',
    IntakePreUniGC = 'intake_pre_uni_gc',
    IntakeCourse = 'intake_course',
    Deferred = 'deferred',
    Dropout = 'dropout',
    DropoutTransfer = 'dropout_transfer',
    Pending = 'pending',
}

export interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    phone: string;
    avatar_url: string | null;
    status: StudentStatus;
    admission_date: string;
    admission_notes?: string;
    // expected_graduation_date?: string;
    date_of_birth: string;
    gender: 'male' | 'female' | 'other';
    nationality: string;
    national_id: string;
    address: string;
    cccd_address?: string;
    high_school_name: string;
    high_school_graduation_year: string;
    entrance_exam_score?: string;

    emergency_contact_name?: string;
    emergency_contact_email?: string;
    emergency_contact_phone?: string;
    emergency_contact_relationship?: EmergencyContactStatus;

    emergency_contact_name_1?: string;
    emergency_contact_email_1?: string;
    emergency_contact_phone_1?: string;
    emergency_contact_relationship_1?: EmergencyContactStatus;

    // Relationships
    campus_id: number;
    program_id: number;
    specialization_id: number;
    curriculum_version_id: number;

    campus: Campus;
    program: Program;
    specialization: Specialization;
    curriculum_version: CurriculumVersion;

    // Computed fields
    display_name?: string;
    status_label?: string;

    // gc level
    gc_starting_level?: number;
    gc_current_level?: number;
    gc_total_levels?: number;

    // Timestamps
    created_at: string;
    updated_at: string;
}

// New interface for admission form
export interface StudentCreateForm {
    full_name: string;
    email: string;
    phone?: string;
    program_id: number;
    specialization_id?: number;
    curriculum_version_id: number;
    admission_date: string;
    notes?: string;
    expected_graduation_date?: string;
}

// For API responses
export interface StudentResponse {
    success: boolean;
    message: string;
    data?: {
        student: Student;
    };
}

export interface StudentsListResponse {
    success: boolean;
    data: {
        students: Student[];
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
}

export interface StudentStats {
    total: number;
    by_status: Record<string, number>;
}

// Student Academic Summary interfaces
export interface StudentOverview {
    student_info: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
        phone?: string;
        date_of_birth?: string;
        gender?: 'male' | 'female' | 'other';
        nationality?: string;
        national_id?: string;
        address?: string;
        status: 'admitted' | 'active' | 'inactive' | 'graduated' | 'dropped_out';
        academic_status: string;
        admission_date?: string;
        expected_graduation_date?: string;
        status_change_date?: string;
        status_reason?: string;
        avatar_url?: string;
        cccd_address: string;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        emergency_contact_email: string | null;
        emergency_contact_relationship: string | null;

        emergency_contact_name_1: string | null;
        emergency_contact_phone_1: string | null;
        emergency_contact_email_1: string | null;
        emergency_contact_relationship_1: string | null;
        intake_semester: {
            code: string;
            id: number;
            name: string;
        };
        scholarship?: {
            name: string;
            code: string;
            amount?: number;
            type?: 'percentage' | 'fixed_amount';
            awarded_at?: string;
        };
    };
    program_info: {
        campus?: {
            id: number;
            name: string;
            code: string;
        };
        program?: {
            id: number;
            name: string;
            code: string;
        };
        specialization?: {
            id: number;
            name: string;
            code: string;
        };
        curriculum_version?: {
            id: number;
            name: string;
            version: string;
            version_code: string;
        };
        semester?: {
            code: string;
            intake_year: string;
        };
    };
    academic_stats: {
        total_registrations: number;
        completed_courses: number;
        active_registrations: number;
        current_semester_enrollments: number;
        total_credits_earned: number;
        total_credits_attempted: number;
        current_gpa: number;
        cumulative_gpa: number;
        academic_standing: string;
        active_holds: number;
        retake_courses: number;
    };
    emergency_contact: {
        name?: string;
        phone?: string;
        relationship?: string;
    };
    additional_info: {
        high_school_name?: string;
        high_school_graduation_year?: string;
        entrance_exam_score?: string;
        admission_notes?: string;
    };
}

// Course Registration interfaces for Academic Summary
export interface CourseRegistrationRecord {
    course_offering_id: number;
    id: number;
    course_name: string;
    course_code: string;
    section_code?: string;
    credit_points: number;
    semester: string;
    semester_code: string;
    academic_year: string;
    semester_start_date: string | null;
    semester_end_date: string | null;
    registration_status: 'enrolled' | 'active' | 'registered' | 'confirmed' | 'completed' | 'dropped' | 'withdrawn';
    registration_date: string;
    registration_method: string;
    final_grade: string | null;
    grade_points: number | null;
    final_percentage: number | null;
    meets_attendance_requirement: boolean | null;
    grade_status: string | null;
    completion_status: string | null;
    pass_fail_status: 'pass' | 'fail' | null;
    credit_hours: number;
    is_retake: boolean;
    attempt_number: number;
    completion_date: string | null;
    drop_date: string | null;
    withdrawal_date: string | null;
    retake_fee: number;
    is_retake_paid: 'yes' | 'no';
    notes: string | null;
    status_badge_color: BadgeVariants['variant'];
    grade_badge_color: BadgeVariants['variant'];
    pass_fail_badge_color: BadgeVariants['variant'];
    is_passing_grade: boolean;
    formatted_registration_date: string | null;
    formatted_completion_date: string | null;
}

export interface RegistrationsSummary {
    total_registrations: number;
    completed: number;
    active: number;
    dropped: number;
    withdrawn: number;
    retakes: number;
    total_credits_attempted: number;
    total_credits_earned: number;
    completion_rate: number;
    retake_rate: number;
    average_grade_points: number;
}

export interface SemesterGroup {
    academic_year: string;
    semesters: Array<{
        id: number;
        name: string;
        code: string;
        academic_year: string;
    }>;
    total_registrations: number;
}

export interface StatusBreakdown {
    status: string;
    count: number;
    percentage: number;
    badge_color: 'success' | 'primary' | 'warning' | 'destructive' | 'secondary';
}

export interface RegistrationsPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
}

export interface RegistrationsData {
    data: CourseRegistrationRecord[];
    pagination: RegistrationsPagination;
    summary: RegistrationsSummary;
    semester_groups: SemesterGroup[];
    status_breakdown: StatusBreakdown[];
    filters: Record<string, any>;
}

export interface RegistrationsFilters {
    semester_id?: number;
    academic_year?: string;
    status?: string;
    is_retake?: string;
    per_page?: number;
}

// Scores interfaces
export interface AssessmentScore {
    id: number;
    assessment_name: string;
    assessment_type: string;
    due_date?: string;
    max_points: number;
    points_earned: number;
    percentage_score: number;
    letter_grade?: string;
    gpa_points?: number;
    submitted_at?: string;
    graded_at?: string;
    is_late: boolean;
    status: 'pending' | 'submitted' | 'graded' | 'missing';
}

export interface CourseScores {
    course_offering_id: number;
    course_name: string;
    course_code: string;
    semester: string;
    scores: AssessmentScore[];
    course_average: number;
    total_assessments: number;
    completed_assessments: number;
}

export interface ScoresData {
    data: CourseScores[];
    summary: {
        total_courses: number;
        total_assessments: number;
        completed_assessments: number;
        overall_average: number;
    };
}

// Attendance interfaces
export interface AttendanceSession {
    session_date: string;
    start_time: string;
    end_time: string;
    status: 'present' | 'late' | 'absent' | 'excused';
    check_in_time?: string;
    minutes_late?: number;
}

export interface UnitAttendance {
    unit_id: number;
    unit_name: string;
    unit_code: string;
    semester: string;
    course_offering_id: number;
    total_sessions: number;
    attended_count: number;
    present_count: number;
    late_count: number;
    absent_count: number;
    excused_count: number;
    attendance_percentage: number;
    attendance_status: 'excellent' | 'good' | 'warning' | 'critical';
    sessions: AttendanceSession[];
}

export interface AttendanceData {
    data: UnitAttendance[];
    summary: {
        total_units: number;
        total_sessions: number;
        total_attended: number;
        total_absent: number;
        overall_percentage: number;
        units_at_risk: number;
    };
}

// GPA interfaces
export interface GpaRecord {
    id: number;
    semester: string;
    semester_code: string;
    academic_year: string;
    semester_gpa: number;
    cumulative_gpa: number;
    credit_hours_attempted: number;
    credit_hours_earned: number;
    quality_points: number;
    academic_standing: string;
    dean_list_eligible: boolean;
    honors_eligible: boolean;
    probation_status: boolean;
    warning_status: boolean;
    calculation_date: string;
}

export interface GpaData {
    data: GpaRecord[];
    summary: {
        current_semester_gpa: number;
        current_cumulative_gpa: number;
        total_credit_hours_earned: number;
        total_quality_points: number;
        current_academic_standing: string;
        dean_list_semesters: number;
        probation_semesters: number;
        warning_semesters: number;
        gpa_trend: 'improving' | 'declining' | 'stable' | 'insufficient_data';
    };
}

// Graduation interfaces
export interface RequirementStatus {
    required: number | boolean;
    earned?: number;
    completed?: boolean;
    status: 'completed' | 'in_progress' | 'pending' | 'not_started';
}

export interface GraduationRequirements {
    core_credits: RequirementStatus;
    elective_credits: RequirementStatus;
    internship: RequirementStatus;
    thesis: RequirementStatus;
    english_requirement: RequirementStatus;
}

export interface GraduationData {
    credit_summary: {
        total_required: number;
        total_earned: number;
        remaining: number;
        completion_percentage: number;
    };
    requirements: GraduationRequirements;
    graduation_status: {
        ready_to_graduate: boolean;
        expected_graduation?: string;
        risks: string[];
        risk_level: 'low' | 'medium' | 'high';
    };
    progress_timeline: {
        current_semester: {
            semester?: string;
            enrolled_credits: number;
            status: 'enrolled' | 'not_enrolled' | 'no_current_semester';
        };
        projected_completion: {
            semesters_remaining: number;
            projected_date: string;
            on_track: boolean;
        };
    };
}

export interface StudentAcademicSummary {
    overview: StudentOverview;
    registrations: RegistrationsData;
    scores: ScoresData;
    attendance: AttendanceData;
    gpa: GpaData;
    graduation: GraduationData;
}

export interface Enrollment {
    id: number;
    student_id: number;
    semester_id: number;
    curriculum_version_id: number;
    semester_number: number;
    status: 'in_progress' | 'completed' | 'withdrawn';
    notes?: string;
    student?: Student;
    semester?: Semester;
    curriculum_version?: CurriculumVersion;
    created_at: string;
    updated_at: string;
}

export interface AcademicRecord {
    id: number;
    student_id: number;
    course_offering_id: number;
    is_repeat_course: boolean;
    attempt_number: number;
    original_record_id?: number;
    final_letter_grade?: string;
    final_percentage?: number;
    completion_status?: string;
    original_record?: {
        id: number;
        final_letter_grade?: string;
        final_percentage?: number;
        completion_status?: string;
    };
}

export interface CourseRegistration {
    id: number;
    student_id: number;
    course_offering_id: number;
    semester_id: number;
    registration_status: 'registered' | 'confirmed' | 'dropped' | 'withdrawn' | 'completed';
    registration_date: string;
    registration_method: 'online' | 'advisor' | 'admin_override';
    credit_hours: number;
    final_grade?: string;
    grade_points?: number;
    attempt_number: number;
    is_retake: boolean;
    drop_date?: string;
    withdrawal_date?: string;
    completion_date?: string;
    retake_fee: number;
    is_retake_paid: 'yes' | 'no';
    notes?: string;
    student?: Student;
    course_offering?: CourseOffering;
    semester?: Semester;
    academic_record?: AcademicRecord;
    created_at: string;
    updated_at: string;
}

// Form data type for course registration validation
export interface CourseRegistrationFormData {
    student_id: string;
    course_offering_id: string;
    notes?: string;
}

// Form data types for Campus and Building
export interface CampusFormData {
    name: string;
    code: string;
    address: string;
}

export interface BuildingFormData {
    campus_id: string;
    name: string;
    code: string;
    description?: string;
    address?: string;
}
export enum ScheduleDay {
    Monday = 'Monday',
    Tuesday = 'Tuesday',
    Wednesday = 'Wednesday',
    Thursday = 'Thursday',
    Friday = 'Friday',
    Saturday = 'Saturday',
}
export interface SyllabusTemplate {
    id: number;
    unit_id: number;
    title: string;
    version: string;
    description: string;
    total_hours: number;
    total_sessions: number;
    learning_outcomes?: any[];
    grading_criteria?: any[];
    required_materials?: any[];
    assessment_policy?: string;
    applicable_program_id?: number;
    applicable_campus_id?: number;
    delivery_mode: string;
    is_default: boolean;
    is_active: boolean;
    source_template_id?: number;
    created_by?: number;
    unit?: Unit;
    applicable_campus?: Campus;
    applicable_program?: Program;
    created_at?: string;
    updated_at?: string;
}
export interface CourseOffering {
    id: number;
    semester_id: number;
    curriculum_unit_id: number;
    campus_id: number;
    unit_id: number;
    unit: Unit;
    lecture_id?: number;
    section_code?: string;
    max_capacity: number;
    current_enrollment: number;
    waitlist_capacity: number;
    current_waitlist: number;
    delivery_mode: 'in_person' | 'online' | 'hybrid' | 'blended';
    schedule_days?: ScheduleDay[];
    schedule_time_start?: string;
    schedule_time_end?: string;
    location?: string;
    is_active: boolean;
    enrollment_status: 'open' | 'closed' | 'waitlist_only' | 'cancelled';
    course_status?: 'not_started' | 'in_progress' | 'completed' | 'cancelled';
    registration_start_date?: string;
    registration_end_date?: string;
    special_requirements?: string;
    notes?: string;
    curriculum_unit: CurriculumUnit;
    lecture?: Lecture;
    semester: Semester;
    campus?: Campus;
    courseRegistrations?: CourseRegistration[];
    course_registrations?: CourseRegistration[];
    academicRecords?: AcademicRecord[];
    class_sessions?: ClassSession[];
    syllabus_template?: SyllabusTemplate;
    created_at: string;
    updated_at: string;
    // Computed properties from model accessors
    course_code?: string;
    course_title?: string;
    credit_hours?: number;
    status: string;
    max_enrollment: number;
    tuition_per_credit: number;
    additional_fees: number;
    drop_deadline?: string;
    withdrawal_deadline?: string;
}

export enum ClassSessionStatus {
    SCHEDULED = 'scheduled',
    IN_PROGRESS = 'in_progress',
    COMPLETED = 'completed',
    CANCELLED = 'cancelled',
}
export enum ClassSessionType {
    LECTURE = 'lecture',
    TUTORIAL = 'tutorial',
    LAB = 'lab',
    EXAM = 'exam',
    ASSESSMENT = 'assessment',
    OTHER = 'other',
}
export enum ClassSessionDeliveryMode {
    IN_PERSON = 'in_person',
    ONLINE = 'online',
    HYBRID = 'hybrid',
}
export interface ClassSession {
    id: number;
    course_offering_id: number;
    course_offering?: CourseOffering;
    formatted_time?: string;
    room_id?: number;
    room?: Room;
    room_booking_id?: number;
    lecture_id?: number;
    lecture?: Lecture;
    session_title?: string;
    session_description?: string;
    session_date: string;
    start_time: string;
    end_time: string;
    duration_minutes: number;
    session_type: ClassSessionType;
    delivery_mode: ClassSessionDeliveryMode;
    status: ClassSessionStatus;
    online_meeting_url?: string;
    meeting_id?: string;
    meeting_password?: string;
    learning_objectives?: string[];
    required_materials?: string[];
    topics_covered?: string[];
    attendance_required: boolean;
    attendance_tracking_enabled: boolean;
    expected_attendees?: number;
    actual_attendees?: number;
    attendance_percentage?: number;
    is_assessment: boolean;
    instructor_notes?: string;
    attendance_stats?: {
        total: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
        attendance_percentage: number;
    };
}

// Student search and eligibility interfaces
export interface EgcEligibility {
    can_enroll: boolean;
    eligibility_reason: string;
    current_level_code?: string;
    current_level_higher: boolean;
    prerequisite_not_met: boolean;
}

export interface StudentEligibilityInfo {
    student_id: string;
    exists: boolean;
    student_data?: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
        program?: {
            code: string;
            name: string;
        };
        specialization?: {
            code: string;
            name: string;
        };
    };
    is_eligible: boolean;
    is_already_registered: boolean;
    eligibility_reasons: string[];
    major_code?: string;
    egc_eligibility?: EgcEligibility;
}

export interface StudentSearchResponse {
    success: boolean;
    data: {
        students: StudentEligibilityInfo[];
    };
    message?: string;
}

export interface BulkRegistrationRequest {
    student_ids: string[];
}

export interface BulkRegistrationResponse {
    success: boolean;
    data: {
        successful_registrations: number;
        failed_registrations: number;
        results: Array<{
            student_id: string;
            success: boolean;
            message?: string;
        }>;
    };
    message: string;
    assessment_weight?: number;
    assessment_duration_minutes?: number;
    assessment_materials_allowed?: string[];
    is_recurring: boolean;
    parent_session_id?: number;
    sequence_number?: number;
    instructor_notes?: string;
    admin_notes?: string;
    student_instructions?: string;
    cancellation_reason?: string;
    scheduled_at?: string;
    started_at?: string;
    ended_at?: string;
    cancelled_at?: string;
    course_offering?: CourseOffering;
    lecture?: Lecture;
    created_at: string;
    updated_at: string;
    // Additional properties
    attendances?: Attendance[];
    attendance_stats?: {
        total: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
        attendance_percentage: number;
    };
    status_badge_color?: BadgeVariants['variant'];
    // Computed properties
    formatted_date?: string;
    formatted_time?: string;
    duration_in_minutes?: number;
    attendance_marked?: boolean;
}

export interface Attendance {
    id: number;
    class_session_id: number;
    student_id: number;
    recorded_by_lecture_id?: number;
    status: 'present' | 'late' | 'absent' | 'excused';
    check_in_time?: string;
    check_out_time?: string;
    minutes_late?: number;
    minutes_present?: number;
    recording_method: 'manual' | 'qr_code' | 'rfid' | 'geolocation' | 'biometric' | 'mobile_app';
    notes?: string;
    excuse_reason?: string;
    excuse_document_path?: string;
    participation_level?: 'excellent' | 'good' | 'average' | 'poor';
    participation_score?: number;
    participation_notes?: string;
    is_verified: boolean;
    affects_grade: boolean;
    is_makeup_allowed: boolean;
    verified_at?: string;
    verified_by_user_id?: number;
    batch_id?: string;
    device_info?: Record<string, any>;
    ip_address?: string;
    latitude?: number;
    longitude?: number;
    created_at: string;
    updated_at: string;
    // Relationships
    class_session?: ClassSession;
    student?: Student;
    recorded_by?: User;
    verified_by?: User;
    // Computed properties
    formatted_check_in_time?: string;
    formatted_check_out_time?: string;
    status_badge_color?: string;
}

// Form data types for strict validation
export interface EnrollmentFormData {
    student_id: string;
    semester_id: string;
    curriculum_version_id: string;
    semester_number: number;
    status?: 'in_progress' | 'completed' | 'withdrawn';
    notes?: string;
}

export interface CourseOfferingFormData {
    semester_id: string;
    curriculum_unit_id: string;
    syllabus_template_id?: string;
    lecture_id?: string;
    section_code?: string;
    max_capacity: number;
    waitlist_capacity?: number;
    delivery_mode: 'in_person' | 'online' | 'hybrid' | 'blended';
    schedule_days?: string[];
    schedule_time_start?: string;
    schedule_time_end?: string;
    location?: string;
    enrollment_status?: 'open' | 'closed' | 'waitlist_only' | 'cancelled';
    registration_start_date?: string;
    registration_end_date?: string;
    special_requirements?: string;
    notes?: string;
}

export interface LectureFormData {
    employee_id: string;
    title?: string;
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    mobile_phone?: string;
    campus_id: string;
    department?: string;
    faculty?: string;
    specialization?: string;
    expertise_areas?: string[];
    academic_rank: 'lecturer' | 'senior_lecturer' | 'associate_professor' | 'professor' | 'emeritus_professor' | 'visiting_lecturer' | 'adjunct_professor';
    highest_degree?: string;
    degree_field?: string;
    alma_mater?: string;
    graduation_year?: number;
    hire_date: string;
    contract_start_date?: string;
    contract_end_date?: string;
    employment_type: 'full_time' | 'part_time' | 'contract' | 'visiting' | 'emeritus';
    employment_status: 'active' | 'on_leave' | 'sabbatical' | 'retired' | 'terminated' | 'suspended';
    preferred_teaching_days?: string[];
    preferred_start_time?: string;
    preferred_end_time?: string;
    max_teaching_hours_per_week?: number;
    teaching_modalities?: ('in_person' | 'online' | 'hybrid')[];
    office_address?: string;
    office_phone?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    emergency_contact_relationship?: string;
    biography?: string;
    certifications?: string[];
    languages?: string[];
    hourly_rate?: number;
    salary?: number;
    is_active?: boolean;
    can_teach_online?: boolean;
    is_available_for_assignment?: boolean;
    notes?: string;
}

export interface AcademicHold {
    id: number;
    student_id: number;
    hold_type: 'financial' | 'academic' | 'disciplinary' | 'administrative' | 'health' | 'library';
    hold_category: 'registration' | 'graduation' | 'transcript' | 'all';
    title: string;
    description?: string;
    amount?: number;
    priority: 'high' | 'medium' | 'low';
    status: 'active' | 'resolved' | 'waived' | 'expired';
    placed_date: string;
    due_date?: string;
    resolved_date?: string;
    placed_by_user_id?: number;
    resolved_by_user_id?: number;
    resolution_notes?: string;
    student?: Student;
    placed_by_user?: User;
    resolved_by_user?: User;
    created_at: string;
    updated_at: string;
}

export interface GraduationRequirement {
    id: number;
    program_id: number;
    specialization_id?: number;
    total_credits_required: number;
    core_credits_required: number;
    major_credits_required: number;
    elective_credits_required: number;
    minimum_gpa: number;
    minimum_major_gpa: number;
    maximum_study_years: number;
    required_internship: boolean;
    required_thesis: boolean;
    required_english_certification: boolean;
    special_requirements?: any;
    effective_from: string;
    effective_to?: string;
    is_active: boolean;
    program?: Program;
    specialization?: Specialization;
    created_at: string;
    updated_at: string;
}

// Club Management interfaces
export interface Club {
    id: number;
    campus_id: number;
    name: string;
    description?: string;
    founded_date?: string;
    avatar_url?: string;
    thumbnail_url?: string;
    cover_url?: string;
    social_links?: Record<string, string>;
    contact_email?: string;
    contact_phone?: string;
    status: 'active' | 'inactive';
    achievements?: string[];
    campus?: Campus;
    president?: ClubMember;
    members?: ClubMember[];
    active_members_count?: number;
    pending_applications_count?: number;
    officers_count?: number;
    member_count?: number;
    created_at: string;
    updated_at: string;
}

export interface ClubMember {
    id: number;
    club_id: number;
    student_id: number;
    role: 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member';
    status: 'active' | 'pending' | 'rejected' | 'left' | 'banned';
    application_notes?: string;
    approved_by?: number;
    responsibilities?: string[];
    participation_score: number;
    last_active_at?: string;
    joined_at?: string;
    left_at?: string;
    club?: Club;
    student?: Student;
    approver?: Student;
    role_history?: ClubMemberRoleHistory[];
    created_at: string;
    updated_at: string;
}

export interface ClubMemberRoleHistory {
    id: number;
    club_member_id: number;
    old_role?: 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member';
    new_role: 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member';
    changed_by?: number;
    change_reason?: string;
    started_at: string;
    ended_at?: string;
    club_member?: ClubMember;
    changed_by_user?: User;
    created_at: string;
    updated_at: string;
}

// Form data types for Club management
export interface ClubFormData {
    name: string;
    description?: string;
    campus_id: string;
    founded_date?: string;
    contact_email?: string;
    contact_phone?: string;
    social_links?: Record<string, string>;
    president_student_id?: string;
}

export interface ClubApplicationFormData {
    application_notes?: string;
}

export interface AssignPresidentFormData {
    student_id: string;
    assignment_reason?: string;
}

export interface UpdateRoleFormData {
    role: 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member';
    change_reason?: string;
}

export interface RejectMemberFormData {
    rejection_reason?: string;
}

// Form data types for Room
export interface RoomFormData {
    name: string;
    code: string;
    building: string;
    floor: string;
    type: 'classroom' | 'laboratory' | 'computer_lab' | 'auditorium' | 'meeting_room' | 'library' | 'study_room' | 'workshop' | 'office' | 'other';
    capacity: number;
    status: 'available' | 'occupied' | 'maintenance' | 'out_of_service' | 'reserved';
    is_bookable: boolean;
    requires_approval: boolean;
    available_from?: string;
    available_until?: string;
    blocked_days?: string[];
    description?: string;
    usage_guidelines?: string;
    booking_notes?: string;
}

// Email Log interfaces
export interface EmailLog {
    id: number;
    recipient: string;
    sender: string;
    subject: string;
    template_id?: number;
    status: 'pending' | 'queued' | 'sending' | 'sent' | 'delivered' | 'failed' | 'bounced' | 'rejected';
    queued_at?: string;
    sent_at?: string;
    delivered_at?: string;
    failed_at?: string;
    error_message?: string;
    retry_count: number;
    metadata?: Record<string, any>;
    message_id?: string;
    batch_id?: string;
    user_id?: number;
    template?: EmailTemplate;
    user?: User;
    created_at: string;
    updated_at: string;
}

export interface EmailTemplate {
    id: number;
    name: string;
    type: string;
    subject: string;
    html_content: string;
    text_content?: string;
    variables: string[];
    is_active: boolean;
    created_at: string;
    updated_at: string;
    version: number;
    parent_id?: number;
    description?: string;
}

// API Response interfaces
export interface ApiResponse {
    success: boolean;
    message: string;
    error?: string;
    errors?: Record<string, string[]>;
}
