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
    created_at: string;
    updated_at: string;
}

export interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
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

export interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    type: 'core' | 'major' | 'elective';
    year_level?: number;
    semester_number?: number;
    note?: string;
    created_at: string;
    updated_at: string;
    unit: Unit;
    curriculum_version?: CurriculumVersion;
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
    academic_rank:
        | 'lecturer'
        | 'senior_lecturer'
        | 'associate_professor'
        | 'professor'
        | 'emeritus_professor'
        | 'visiting_lecturer'
        | 'adjunct_professor';
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

export interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    phone: string;
    status: 'admitted' | 'active' | 'inactive' | 'graduated' | 'dropped_out';
    admission_date: string;
    admission_notes?: string;
    // expected_graduation_date?: string;
    date_of_birth: string;
    gender: 'male' | 'female' | 'other';
    nationality: string;
    national_id: string;
    address: string;
    high_school_name: string;
    high_school_graduation_year: string;
    entrance_exam_score?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    emergency_contact_relationship?: string;

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
export interface CourseOffering {
    id: number;
    semester_id: number;
    curriculum_unit_id: number;
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
    registration_start_date?: string;
    registration_end_date?: string;
    special_requirements?: string;
    notes?: string;
    curriculum_unit: CurriculumUnit;
    lecture?: Lecture;
    semester?: Semester;
    campus?: Campus;
    courseRegistrations?: CourseRegistration[];
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
    academic_rank:
        | 'lecturer'
        | 'senior_lecturer'
        | 'associate_professor'
        | 'professor'
        | 'emeritus_professor'
        | 'visiting_lecturer'
        | 'adjunct_professor';
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
