export interface TeachingAssignment {
    id: number;
    semester: Semester;
    unit: Unit;
    curriculum_unit: CurriculumUnit;
    section_code: string | null;
    lecturer: Lecturer | null;
    schedule: Schedule;
    enrollment: Enrollment;
    delivery_mode: string;
    assignment_status: 'assigned' | 'unassigned' | 'urgent';
    assignment_status_label: string;
    assignment_priority: 'assigned' | 'urgent' | 'high' | 'normal';
    is_active: boolean;
    campus: Campus;
    registration: Registration;
    flags: AssignmentFlags;
    special_requirements: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface Semester {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
}

export interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    level: number;
    unit_type: string;
}

export interface CurriculumUnit {
    id: number;
    unit_type: string;
    is_elective: boolean;
}

export interface Lecturer {
    id: number;
    employee_id: string;
    full_name: string;
    display_name: string;
    title: string | null;
    first_name: string;
    last_name: string;
    email: string;
    department: string | null;
    faculty: string | null;
    academic_rank: string | null;
    employment_status: string;
}

export interface Schedule {
    days: string[];
    time_start: string | null;
    time_end: string | null;
    location: string | null;
}

export interface Enrollment {
    current: number;
    maximum: number;
    waitlist_current: number;
    waitlist_maximum: number;
    status: string;
    available_spots: number;
    available_waitlist_spots: number;
}

export interface Campus {
    id: number;
    name: string;
    code: string;
}

export interface Registration {
    start_date: string | null;
    end_date: string | null;
    is_open: boolean;
}

export interface AssignmentFlags {
    has_instructor: boolean;
    needs_instructor_urgently: boolean;
    can_enroll: boolean;
    can_join_waitlist: boolean;
    is_full: boolean;
    is_waitlist_full: boolean;
}

export interface AvailableLecturer {
    id: number;
    employee_id: string;
    full_name: string;
    display_name: string;
    title: string | null;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    mobile_phone: string | null;
    department: string | null;
    faculty: string | null;
    academic_rank: string | null;
    employment_type: string;
    employment_status: string;
    specialization: string | null;
    expertise_areas: string[];
    preferred_teaching_days: string[];
    preferred_start_time: string | null;
    preferred_end_time: string | null;
    max_teaching_hours_per_week: number | null;
    teaching_modalities: string[];
    can_teach_online: boolean;
    is_active: boolean;
    is_available_for_assignment: boolean;
    contract_start_date: string | null;
    contract_end_date: string | null;
    is_contract_active: boolean;
    campus: Campus;
    office_address: string | null;
    office_phone: string | null;
    current_course_load: number;
    years_of_service: number;
    can_be_assigned: boolean;
    conflicts: ScheduleConflict[];
    hire_date: string | null;
    highest_degree: string | null;
    degree_field: string | null;
    alma_mater: string | null;
    graduation_year: number | null;
    certifications: string[];
    languages: string[];
    availability_status: 'available' | 'unavailable' | 'inactive' | 'contract_expired';
    teaching_capacity_status: 'unlimited' | 'light_load' | 'moderate_load' | 'near_capacity' | 'at_capacity';
    created_at: string;
    updated_at: string;
}

export interface ScheduleConflict {
    conflicting_course_id: number;
    unit: Unit;
    section_code: string | null;
    semester: Semester;
    schedule: Schedule;
    delivery_mode: string;
    enrollment: {
        current: number;
        maximum: number;
    };
    conflict_type: 'same_time' | 'time_overlap' | 'adjacent_time' | 'same_day_different_time';
    conflict_severity: 'critical' | 'high' | 'medium' | 'low';
    conflict_description: string;
    resolution_suggestions: string[];
}

export interface AssignmentFilters {
    semester_id?: number;
    faculty?: string;
    department?: string;
    unit_code?: string;
    lecturer_name?: string;
    enrollment_status?: string;
    assignment_status?: 'assigned' | 'unassigned' | 'urgent' | 'all';
    search?: string;
    per_page?: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface AssignLecturerRequest {
    course_offering_id: number;
    lecturer_id: number;
    force_assignment?: boolean;
}

export interface ConflictCheckRequest {
    lecturer_id: number;
    course_offering_id: number;
}

export interface ExportRequest {
    format: 'excel' | 'pdf';
    semester_id?: number;
    faculty?: string;
    department?: string;
    assignment_status?: string;
    search?: string;
}

export interface ApiResponse<T = any> {
    data?: T;
    message?: string;
    success: boolean;
    error?: string;
}

export interface ConflictCheckResponse {
    conflicts: ScheduleConflict[];
    has_conflicts: boolean;
    success: boolean;
}
