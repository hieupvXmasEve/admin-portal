import {
    CAMPUS_ROUTE_NAMES,
    CLASS_SESSION_ROUTE_NAMES,
    CLUB_ROUTE_NAMES,
    COURSE_OFFERING_ROUTE_NAMES,
    COURSE_REGISTRATION_ROUTE_NAMES,
    CURRICULUM_ROUTE_NAMES,
    LECTURE_ROUTE_NAMES,
    PROGRAM_ROUTE_NAMES,
    ROLE_ROUTE_NAMES,
    ROOM_ROUTE_NAMES,
    ROOM_BOOKING_ROUTE_NAMES,
    SEMESTER_ROUTE_NAMES,
    SETTINGS_ROUTE_NAMES,
    SPECIALIZATION_ROUTE_NAMES,
    STUDENT_ROUTE_NAMES,
    SYLLABUS_ROUTE_NAMES,
    UNIT_ROUTE_NAMES,
    USER_ROUTE_NAMES,
} from '@/constants';
import { SYSTEM_ACTIVITY_LOG_ROUTE_NAMES, SYSTEM_CONFIG_ROUTE_NAMES, SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES } from '@/constants/system-routes';
import { route } from 'ziggy-js';

/**
 * Shared Routes Utility
 * Provides centralized route management using Ziggy.js Laravel named routes
 * Ensures type safety and consistency across the application
 */

// System Management Routes
export const systemRoutes = {
    users: {
        index: () => route(USER_ROUTE_NAMES.INDEX),
        create: () => route(USER_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(USER_ROUTE_NAMES.EDIT, { user: id }),
        show: (id: number) => route(USER_ROUTE_NAMES.SHOW, { user: id }),
        import: () => route(USER_ROUTE_NAMES.IMPORT_FORM),
        exportFiltered: () => route(USER_ROUTE_NAMES.EXPORT_EXCEL_FILTERED),
    },
    roles: {
        index: () => route(ROLE_ROUTE_NAMES.INDEX),
        create: () => route(ROLE_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(ROLE_ROUTE_NAMES.EDIT, { role: id }),
        show: (id: number) => route(ROLE_ROUTE_NAMES.SHOW, { role: id }),
    },
    semesters: {
        index: () => route(SEMESTER_ROUTE_NAMES.INDEX),
        create: () => route(SEMESTER_ROUTE_NAMES.CREATE),
        store: () => route(SEMESTER_ROUTE_NAMES.STORE),
        edit: (id: number) => route(SEMESTER_ROUTE_NAMES.EDIT, { semester: id }),
        update: (id: number) => route(SEMESTER_ROUTE_NAMES.UPDATE, { semester: id }),
        destroy: (id: number) => route(SEMESTER_ROUTE_NAMES.DESTROY, { semester: id }),
        show: (id: number) => route(SEMESTER_ROUTE_NAMES.SHOW, { semester: id }),
        enrollment: (id: number) => route(SEMESTER_ROUTE_NAMES.ENROLLMENT_SHOW, { semester: id }),
        apiUpdate: (id: number) => route(SEMESTER_ROUTE_NAMES.API_UPDATE, { semester: id }),
    },
    // Campus Management Routes
    campuses: {
        index: () => route(CAMPUS_ROUTE_NAMES.INDEX),
        create: () => route(CAMPUS_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(CAMPUS_ROUTE_NAMES.EDIT, { campus: id }),
        show: (id: number) => route(CAMPUS_ROUTE_NAMES.SHOW, { campus: id }),
        buildings: {
            store: (campusId: number) => route('api.admin.campuses.buildings.store', { campus: campusId }),
            update: (campusId: number, buildingId: number) => route('api.admin.campuses.buildings.update', { campus: campusId, building: buildingId }),
            destroy: (campusId: number, buildingId: number) => route('api.admin.campuses.buildings.destroy', { campus: campusId, building: buildingId }),
        },
    },
    // Room Management Routes
    rooms: {
        index: () => route(ROOM_ROUTE_NAMES.INDEX),
        create: () => route(ROOM_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(ROOM_ROUTE_NAMES.EDIT, { room: id }),
        show: (id: number) => route(ROOM_ROUTE_NAMES.SHOW, { room: id }),
        store: () => route(ROOM_ROUTE_NAMES.STORE),
        update: (id: number) => route(ROOM_ROUTE_NAMES.UPDATE, { room: id }),
        destroy: (id: number) => route(ROOM_ROUTE_NAMES.DESTROY, { room: id }),
    },
    // system configuration
    config: {
        index: () => route(SYSTEM_CONFIG_ROUTE_NAMES.INDEX),
    },
    // activity logs
    activityLogs: {
        index: () => route(SYSTEM_ACTIVITY_LOG_ROUTE_NAMES.INDEX),
    },
    // email
    emailConfiguration: {
        index: () => route(SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES.INDEX),
        templates: () => route(SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES.TEMPLATES),
        bulkEmail: () => route(SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES.BULK_EMAIL),
        emailHistory: () => route(SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES.EMAIL_HISTORY),
    },
    // form
    form: {
        index: () => route('forms.review.index'),
    },
    // Club Management Routes
    clubs: {
        index: () => route(CLUB_ROUTE_NAMES.INDEX),
        create: () => route(CLUB_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(CLUB_ROUTE_NAMES.EDIT, { club: id }),
        show: (id: number) => route(CLUB_ROUTE_NAMES.SHOW, { club: id }),
        store: () => route(CLUB_ROUTE_NAMES.STORE),
        update: (id: number) => route(CLUB_ROUTE_NAMES.UPDATE, { club: id }),
        destroy: (id: number) => route(CLUB_ROUTE_NAMES.DESTROY, { club: id }),
        assignPresident: (id: number) => route(CLUB_ROUTE_NAMES.ASSIGN_PRESIDENT, { club: id }),
        addMember: (id: number) => route(CLUB_ROUTE_NAMES.ADD_MEMBER, { club: id }),
        // API routes
        studentsForAssignment: (id: number) => route(CLUB_ROUTE_NAMES.API_STUDENTS_FOR_ASSIGNMENT, { club: id }),
        studentsForCampus: () => route(CLUB_ROUTE_NAMES.API_STUDENTS_FOR_CAMPUS),
    },
    events: {
        index: () => route('events.index'),
        create: () => route('events.create'),
        edit: (id: number) => route('events.edit', { event: id }),
        show: (id: number) => route('events.show', { event: id }),
        reports: () => route('events.reports'),
    },
    // Room Booking Routes
    roomBookings: {
        index: () => route(ROOM_BOOKING_ROUTE_NAMES.INDEX),
        create: () => route(ROOM_BOOKING_ROUTE_NAMES.CREATE),
        show: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.SHOW, { roomBooking: id }),
        edit: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.EDIT, { roomBooking: id }),
        myBookings: () => route(ROOM_BOOKING_ROUTE_NAMES.MY_BOOKINGS),
        pending: () => route(ROOM_BOOKING_ROUTE_NAMES.PENDING),
        calendar: () => route(ROOM_BOOKING_ROUTE_NAMES.CALENDAR),
        logs: () => route(ROOM_BOOKING_ROUTE_NAMES.LOGS),
    },
} as const;

// Student Management Routes
export const studentRoutes = {
    // Web routes
    list: () => route(STUDENT_ROUTE_NAMES.INDEX),
    create: () => route(STUDENT_ROUTE_NAMES.CREATE),
    edit: (id: number) => route(STUDENT_ROUTE_NAMES.EDIT, { student: id }),
    show: (id: number) => route(STUDENT_ROUTE_NAMES.SHOW, { student: id }),
    store: () => route(STUDENT_ROUTE_NAMES.STORE),

    // API routes
    index: () => route('api.admin.students.index'),

    stats: () => route('api.admin.students.stats'),
    update: (studentId: number) => route('api.admin.students.update', { student: studentId }),
    destroy: (studentId: number) => route('api.admin.students.destroy', { student: studentId }),

    // Student Management Features - General Access (for menu)
    newStudents: () => route('students.new-students.index'),
    academicRecords: () => route('academic-records.index'),
    programChange: () => route('program-changes.index'),
    repeatCourses: () => route('course-retakes.index'),
    academicStanding: () => route('academic-standings.index'),
    enrollments: () => route('student-enrollments.index'),
    statusTracking: () => route('students.status.index'),

    // Student Management Features - Student Specific
    studentAcademicRecords: (id: number) => route('students.academic-records.index', { student: id }),
    studentAcademicRecordsTranscript: (id: number) => route('students.academic-records.transcript', { student: id }),
    studentAcademicRecordsGpaHistory: (id: number) => route('students.academic-records.gpa-history', { student: id }),
    studentProgramChange: (id: number) => route('students.program-changes.create', { student: id }),
    studentRepeatCourses: (id: number) => route('students.retakes.index', { student: id }),
    studentAcademicStanding: (id: number) => route('students.standing.index', { student: id }),

    // Student Academic Summary Routes
    studentAcademicSummary: (id: number) => route('students.academic-summary.show', { student: id }),
} as const;

// Lecturer Management Routes
export const lecturerRoutes = {
    index: () => route(LECTURE_ROUTE_NAMES.INDEX),
    create: () => route(LECTURE_ROUTE_NAMES.CREATE),
    edit: (id: number) => route(LECTURE_ROUTE_NAMES.EDIT, { lecture: id }),
    show: (id: number) => route(LECTURE_ROUTE_NAMES.SHOW, { lecture: id }),
    store: () => route(LECTURE_ROUTE_NAMES.STORE),
    update: (id: number) => route(LECTURE_ROUTE_NAMES.UPDATE, { lecture: id }),
    destroy: (id: number) => route(LECTURE_ROUTE_NAMES.DESTROY, { lecture: id }),
    import: () => route(LECTURE_ROUTE_NAMES.IMPORT_FORM),
    exportFiltered: () => route(LECTURE_ROUTE_NAMES.EXPORT_EXCEL_FILTERED),
    teachingHours: () => route(LECTURE_ROUTE_NAMES.TEACHING_HOURS),
    // API routes
    apiSearch: () => route(LECTURE_ROUTE_NAMES.API_SEARCH),
    apiStatistics: () => route(LECTURE_ROUTE_NAMES.API_STATISTICS),
} as const;

// Curriculum & Courses Routes
export const curriculumRoutes = {
    programs: {
        index: () => route(PROGRAM_ROUTE_NAMES.INDEX),
        show: (id: number) => route(PROGRAM_ROUTE_NAMES.SHOW, { program: id }),
    },
    specializations: {
        index: () => route(SPECIALIZATION_ROUTE_NAMES.INDEX),
        create: () => route(SPECIALIZATION_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(SPECIALIZATION_ROUTE_NAMES.EDIT, { specialization: id }),
        show: (id: number) => route(SPECIALIZATION_ROUTE_NAMES.SHOW, { specialization: id }),
    },
    curriculumVersions: {
        index: () => route(CURRICULUM_ROUTE_NAMES.VERSION_INDEX),
        create: () => route(CURRICULUM_ROUTE_NAMES.VERSION_CREATE),
        edit: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_EDIT, { curriculum_version: id }),
        show: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SHOW, { curriculum_version: id }),
        electives: (id: number) => route('curriculum_versions.electives', { curriculumVersion: id }),
        // Tab-based summary routes
        summary: {
            overview: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SUMMARY_OVERVIEW, { curriculum_version: id }),
            units: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SUMMARY_UNITS, { curriculum_version: id }),
            students: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SUMMARY_STUDENTS, { curriculum_version: id }),
            deployments: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SUMMARY_DEPLOYMENTS, { curriculum_version: id }),
        },
    },
    units: {
        index: () => route(UNIT_ROUTE_NAMES.INDEX),
        create: () => route(UNIT_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(UNIT_ROUTE_NAMES.EDIT, { unit: id }),
        show: (id: number) => route(UNIT_ROUTE_NAMES.SHOW, { unit: id }),
        import: () => route(UNIT_ROUTE_NAMES.IMPORT),
        export: () => route(UNIT_ROUTE_NAMES.EXPORT_EXCEL),
        syllabus: (unitId: number) => route(SYLLABUS_ROUTE_NAMES.INDEX, { unit: unitId }),
        syllabusCreate: (unitId: number) => route(SYLLABUS_ROUTE_NAMES.CREATE, { unit: unitId }),
        syllabusEdit: (unitId: number, syllabusId: number) => route(SYLLABUS_ROUTE_NAMES.EDIT, { unit: unitId, syllabus: syllabusId }),
        syllabusShow: (unitId: number, syllabusId: number) => route(SYLLABUS_ROUTE_NAMES.SHOW, { unit: unitId, syllabus: syllabusId }),
    },
    curriculumUnits: {
        index: () => route(CURRICULUM_ROUTE_NAMES.UNIT_INDEX),
        create: () => route(CURRICULUM_ROUTE_NAMES.UNIT_CREATE),
        edit: (id: number) => route(CURRICULUM_ROUTE_NAMES.UNIT_EDIT, { curriculum_unit: id }),
    },
    syllabusTemplates: {
        index: () => route(CURRICULUM_ROUTE_NAMES.SYLLABUS_TEMPLATE_INDEX),
        create: () => route(CURRICULUM_ROUTE_NAMES.SYLLABUS_TEMPLATE_CREATE),
        edit: (id: number) => route(CURRICULUM_ROUTE_NAMES.SYLLABUS_TEMPLATE_EDIT, { syllabusTemplate: id }),
        show: (id: number) => route(CURRICULUM_ROUTE_NAMES.SYLLABUS_TEMPLATE_SHOW, { syllabusTemplate: id }),
    },
    // Placeholder for future features
    equivalentCourses: () => '#', // Will be implemented later
    curriculumStructure: () => '#',
} as const;

// Course Offerings & Registration Routes
export const courseRoutes = {
    offerings: {
        index: () => route(COURSE_OFFERING_ROUTE_NAMES.INDEX),
        create: () => route(COURSE_OFFERING_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(COURSE_OFFERING_ROUTE_NAMES.EDIT, { courseOffering: id }),
        show: (id: number) => route(COURSE_OFFERING_ROUTE_NAMES.SHOW, { courseOffering: id }),
        split: (id: number) => route(COURSE_OFFERING_ROUTE_NAMES.SPLIT_SHOW, { courseOffering: id }),
    },
    registrations: {
        index: () => route(COURSE_REGISTRATION_ROUTE_NAMES.INDEX),
        create: () => route(COURSE_REGISTRATION_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(COURSE_REGISTRATION_ROUTE_NAMES.EDIT, { adminCourseRegistration: id }),
        show: (id: number) => route(COURSE_REGISTRATION_ROUTE_NAMES.SHOW, { adminCourseRegistration: id }),
    },
    // Placeholder for future features
    roomAssignment: () => '#', // Will be implemented later
    classSchedule: () => route('schedules.index'),
    enrollmentSummary: () => '#',
} as const;

// Attendance Management Routes
export const attendanceRoutes = {
    classSessions: {
        index: () => route('class-sessions.index'),
        create: () => route('class-sessions.create'),
        edit: (id: number) => route('class-sessions.edit', { classSession: id }),
        show: (id: number) => route('class-sessions.show', { classSession: id }),
        store: () => route('class-sessions.store'),
        update: (id: number) => route('class-sessions.update', { classSession: id }),
        destroy: (id: number) => route('class-sessions.destroy', { classSession: id }),
    },
    attendance: {
        index: () => route('attendance.index'),
        create: () => route('attendance.create'),
        edit: (id: number) => route('attendance.edit', { attendance: id }),
        show: (id: number) => route('attendance.show', { attendance: id }),
        store: () => route('attendance.store'),
        update: (id: number) => route('attendance.update', { attendance: id }),
        destroy: (id: number) => route('attendance.destroy', { attendance: id }),
        bulkUpdate: () => route('attendance.api.bulk-update'),
    },
} as const;

// Assessments & Grading Routes (Placeholder for future implementation)
export const assessmentRoutes = {
    components: () => '#', // Will be implemented later
    enterGrades: () => '#',
    academicResults: () => '#',
    reAssessments: () => '#',
    gradeReports: () => '#',
    gpaHistory: () => '#',
} as const;

// Academic Summary Routes (Placeholder for future implementation)
export const academicSummaryRoutes = {
    gpaCalculations: () => '#', // Will be implemented later
    degreeClassification: () => '#',
    transcriptHistory: () => '#',
    performanceReport: () => '#',
} as const;

// Program Transfers & Course Retakes Routes (Placeholder for future implementation)
export const transferRoutes = {
    programChangeRequests: () => '#', // Will be implemented later
    repeatedCourses: () => '#',
    substituteCourseMapping: () => '#',
    creditTransferEvaluation: () => '#',
} as const;

// Reports & Analytics Routes (Placeholder for future implementation)
export const reportRoutes = {
    studentStatistics: () => '#', // Will be implemented later
    performanceSummary: () => '#',
    attendanceSummary: () => '#',
    gpaDistribution: () => '#',
} as const;

// Course Syllabus & Content Routes
export const syllabusRoutes = {
    management: (unitId: number) => route(SYLLABUS_ROUTE_NAMES.INDEX, { unit: unitId }),
    create: (unitId: number) => route(SYLLABUS_ROUTE_NAMES.CREATE, { unit: unitId }),
    edit: (unitId: number, syllabusId: number) => route(SYLLABUS_ROUTE_NAMES.EDIT, { unit: unitId, syllabus: syllabusId }),
    show: (unitId: number, syllabusId: number) => route(SYLLABUS_ROUTE_NAMES.SHOW, { unit: unitId, syllabus: syllabusId }),
    // Placeholder for future features
    learningMaterials: () => '#', // Will be implemented later
    learningOutcomes: () => '#',
    assessmentRubrics: () => '#',
} as const;

// Settings Routes
export const settingsRoutes = {
    profile: () => route(SETTINGS_ROUTE_NAMES.PROFILE_EDIT),
    password: () => route(SETTINGS_ROUTE_NAMES.PASSWORD_UPDATE),
    appearance: () => route(SETTINGS_ROUTE_NAMES.APPEARANCE),
} as const;

// Dashboard Route
export const dashboardRoute = () => route('dashboard');

// Campus Selection Route
export const campusSelectionRoute = () => route(CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_INDEX);

/**
 * Helper function to generate route with parameters
 * @param routeName - Laravel route name
 * @param params - Route parameters
 */
export const generateRoute = (routeName: string, params?: Record<string, any>) => {
    return route(routeName, params);
};

/**
 * Check if current route matches the given route name
 * @param routeName - Route name to check
 * @param currentRoute - Current route name
 */
export const isActiveRoute = (routeName: string, currentRoute?: string) => {
    if (!currentRoute) return false;
    return currentRoute === routeName || currentRoute.startsWith(`${routeName}.`);
};

/**
 * Check if current route matches any of the given route names
 * @param routeNames - Array of route names to check
 * @param currentRoute - Current route name
 */
export const isActiveRoutes = (routeNames: string[], currentRoute?: string) => {
    if (!currentRoute) return false;
    return routeNames.some((routeName) => isActiveRoute(routeName, currentRoute));
};

// Class Session Routes
export const classSessionRoutes = {
    index: () => route(CLASS_SESSION_ROUTE_NAMES.INDEX),
    create: () => route(CLASS_SESSION_ROUTE_NAMES.CREATE),
    edit: (id: number) => route(CLASS_SESSION_ROUTE_NAMES.EDIT, { classSession: id }),
    show: (id: number) => route(CLASS_SESSION_ROUTE_NAMES.SHOW, { classSession: id }),
} as const;
