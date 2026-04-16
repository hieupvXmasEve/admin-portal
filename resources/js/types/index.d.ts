import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
    permissions: string[];
    current_campus_id: number;
    current_campus: Campus;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    children?: NavItem[];
    requiredPermissions?: string[];
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Campus {
    id: number;
    name: string;
    code: string;
    dng_code?: string | null;
    address?: string;
    buildings_count?: number;
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedResponse<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export interface PageProps<T extends Record<string, unknown> = Record<string, unknown>> extends T {
    auth: Auth | null;
}

// Re-export event types
export type { Event, EventFilters, EventParticipant, EventStatistics, PaginatedEvents } from './event';

// Re-export model types
export type {
    AcademicHold,
    AcademicRecord,
    ApiResponse,
    AssessmentScore,
    AssignPresidentFormData,
    Attendance,
    AttendanceData,
    AttendanceSession,
    Building,
    BuildingFormData,
    BulkRegistrationRequest,
    BulkRegistrationResponse,
    CampusFormData,
    ClassSession,
    Club,
    ClubApplicationFormData,
    ClubFormData,
    ClubMember,
    ClubMemberRoleHistory,
    CourseOffering,
    CourseOfferingFormData,
    CourseRegistration,
    CourseRegistrationFormData,
    CourseRegistrationRecord,
    CourseScores,
    CurriculumUnit,
    CurriculumVersion,
    EgcEligibility,
    EmailLog,
    EmailTemplate,
    Enrollment,
    EnrollmentFormData,
    FailedStudent,
    FailedStudentsSummary,
    FailedUnitDistribution,
    FailReasonDistribution,
    GpaData,
    GpaRecord,
    GraduationData,
    GraduationRequirement,
    GraduationRequirements,
    Lecture,
    LectureFormData,
    Program,
    RegistrationsData,
    RegistrationsFilters,
    RegistrationsPagination,
    RegistrationsSummary,
    RejectMemberFormData,
    RequirementStatus,
    Room,
    RoomFormData,
    ScoresData,
    Semester,
    SemesterGroup,
    Specialization,
    StatusBreakdown,
    Student,
    StudentAcademicSummary,
    StudentCreateForm,
    StudentEligibilityInfo,
    StudentOverview,
    StudentResponse,
    StudentSearchResponse,
    StudentsListResponse,
    StudentStats,
    Syllabus,
    SyllabusTemplate,
    Unit,
    UnitAttendance,
    UnitType,
    UpdateRoleFormData,
} from './models';
