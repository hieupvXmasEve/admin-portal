import {
    CAMPUS_ROUTE_NAMES,
    CLASS_SESSION_ROUTE_NAMES,
    CLUB_ROUTE_NAMES,
    COURSE_OFFERING_ROUTE_NAMES,
    COURSE_REGISTRATION_ROUTE_NAMES,
    CURRICULUM_ROUTE_NAMES,
    DEPARTMENT_ROUTE_NAMES,
    LECTURE_ROUTE_NAMES,
    NOTIFICATION_ROUTE_NAMES,
    PROGRAM_ROUTE_NAMES,
    ROLE_ROUTE_NAMES,
    ROOM_BOOKING_ROUTE_NAMES,
    ROOM_ROUTE_NAMES,
    SEMESTER_ROUTE_NAMES,
    SETTINGS_ROUTE_NAMES,
    SPECIALIZATION_ROUTE_NAMES,
    STUDENT_ROUTE_NAMES,
    SYLLABUS_ROUTE_NAMES,
    UNIT_ROUTE_NAMES,
    USER_ROUTE_NAMES,
} from '@/constants';
import { FINANCE_ROUTE_NAMES } from '@/constants/finance-routes';
import { SYSTEM_ACTIVITY_LOG_ROUTE_NAMES, SYSTEM_AI_COPILOT_ROUTE_NAMES, SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES, SYSTEM_CONFIG_ROUTE_NAMES, SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES } from '@/constants/system-routes';
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
            // Route-based modal URLs (opened via <ModalLink> from @inertiaui/modal-vue)
            create: (campusId: number) => route('campuses.buildings.create', { campus: campusId }),
            edit: (campusId: number, buildingId: number) => route('campuses.buildings.edit', { campus: campusId, building: buildingId }),
            // Mutation routes (used by useForm.post/put/delete inside modal pages)
            store: (campusId: number) => route('campuses.buildings.store', { campus: campusId }),
            update: (campusId: number, buildingId: number) => route('campuses.buildings.update', { campus: campusId, building: buildingId }),
            destroy: (campusId: number, buildingId: number) => route('campuses.buildings.destroy', { campus: campusId, building: buildingId }),
        },
    },
    // Building Management Routes
    buildings: {
        index: () => route('buildings.index'),
        show: (id: number) => route('buildings.show', { building: id }),
    },
    // Department Management Routes
    departments: {
        index: () => route(DEPARTMENT_ROUTE_NAMES.INDEX),
        members: {
            index: (departmentId: number) => route(DEPARTMENT_ROUTE_NAMES.MEMBERS_INDEX, { department: departmentId }),
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
    aiProviderSettings: {
        index: () => route(SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES.INDEX),
        update: () => route(SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES.UPDATE),
        test: () => route(SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES.TEST),
        destroyKey: () => route(SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES.KEY_DESTROY),
    },
    aiCopilot: {
        index: () => route(SYSTEM_AI_COPILOT_ROUTE_NAMES.INDEX),
        storeMessage: () => route(SYSTEM_AI_COPILOT_ROUTE_NAMES.MESSAGES_STORE),
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
        create: (params?: Record<string, unknown>) => route(ROOM_BOOKING_ROUTE_NAMES.CREATE, params ?? {}),
        store: () => route(ROOM_BOOKING_ROUTE_NAMES.STORE),
        availability: () => route(ROOM_BOOKING_ROUTE_NAMES.AVAILABILITY),
        show: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.SHOW, { roomBooking: id }),
        edit: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.EDIT, { roomBooking: id }),
        update: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.UPDATE, { roomBooking: id }),
        destroy: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.DESTROY, { roomBooking: id }),
        myBookings: () => route(ROOM_BOOKING_ROUTE_NAMES.MY_BOOKINGS),
        pending: () => route(ROOM_BOOKING_ROUTE_NAMES.PENDING),
        calendar: (params?: Record<string, unknown>) => route(ROOM_BOOKING_ROUTE_NAMES.CALENDAR, params ?? {}),
        logs: () => route(ROOM_BOOKING_ROUTE_NAMES.LOGS),
        approve: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.APPROVE, { roomBooking: id }),
        reject: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.REJECT, { roomBooking: id }),
        cancel: (id: number) => route(ROOM_BOOKING_ROUTE_NAMES.CANCEL, { roomBooking: id }),
        apiPreviewSeries: () => route(ROOM_BOOKING_ROUTE_NAMES.API_PREVIEW_SERIES),
    },
    notifications: {
        send: () => route(NOTIFICATION_ROUTE_NAMES.SEND),
        store: () => route(NOTIFICATION_ROUTE_NAMES.STORE),
        searchStudents: () => route(NOTIFICATION_ROUTE_NAMES.SEARCH_TARGETS),
        ops: {
            outbox: () => route(NOTIFICATION_ROUTE_NAMES.OPS_OUTBOX),
            messages: () => route(NOTIFICATION_ROUTE_NAMES.OPS_MESSAGES),
            deliveries: () => route(NOTIFICATION_ROUTE_NAMES.OPS_DELIVERIES),
        },
    },
} as const;

// Student Management Routes
export const studentRoutes = {
    // Web routes
    list: () => route(STUDENT_ROUTE_NAMES.INDEX),
    export: () => route(STUDENT_ROUTE_NAMES.EXPORT),
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
    programChange: () => route('program-changes.index'),
    repeatCourses: () => route('course-retakes.index'),
    academicStanding: () => route('academic-standings.index'),
    enrollments: () => route('student-enrollments.index'),
    statusTracking: () => route('students.status.index'),

    // Student Academic Summary Routes
    studentAcademicSummary: (id: number) => route('students.academic-summary.overview', { student: id }),

    // Student Hub tab deep links (one-way targets from the management Reports area, ADR-0007).
    // The Hub is the academic-summary surface; reports drill into the relevant tab for a student.
    hub: {
        overview: (id: number) => route('students.academic-summary.overview', { student: id }),
        registrations: (id: number) => route('students.academic-summary.registrations', { student: id }),
        scores: (id: number) => route('students.academic-summary.scores', { student: id }),
        attendance: (id: number) => route('students.academic-summary.attendance', { student: id }),
        lifecycle: (id: number) => route('students.academic-summary.lifecycle', { student: id }),
        graduation: (id: number) => route('students.academic-summary.graduation', { student: id }),
        finance: (id: number) => route('students.academic-summary.finance', { student: id }),
    },

    studentStatusActionIndex: (id: number) => route('students.actions.index', { student: id }),
    studentStatusAction: () => route('reports.student-actions.index'),
    studentStatusActionImport: () => route('reports.student-actions.import'),
    studentStatusActionImportTemplate: () => route('reports.student-actions.import.template'),
    studentStatusActionImportPreview: () => route('reports.student-actions.import.preview'),
    studentStatusActionImportExecute: () => route('reports.student-actions.import.execute'),
    studentStatusActionShow: (id: number) => route('reports.student-actions.show', { actionLog: id }),
    studentStatusActionUpdate: (id: number) => route('reports.student-actions.update', { actionLog: id }),
    studentStatusActionAttachmentsStore: (id: number) => route('reports.student-actions.attachments.store', { actionLog: id }),

    // Academic Placement & Progression
    studentPlacement: (id: number) => route('students.placement.index', { student: id }),
    studentPlacementInitialize: (id: number) => route('students.placement.initialize', { student: id }),
    studentPlacementIeltsStore: (id: number) => route('students.placement.ielts.store', { student: id }),
    studentPlacementLevelUpdate: (id: number) => route('students.placement.level.update', { student: id }),
    studentPlacementTransition: (id: number) => route('students.placement.transition', { student: id }),
    ieltsCertificateDocumentUpload: (id: number) => route('students.ielts-certificates.document.upload', { certificate: id }),

    // Academic Progression Audit Reports
    academicProgressionAudit: () => route('reports.academic-progression.index'),
    academicProgressionMissingDocuments: () => route('reports.academic-progression.missing-documents'),
    academicProgressionMissingDecisions: () => route('reports.academic-progression.missing-decisions'),
    academicProgressionExport: () => route('reports.academic-progression.export'),
    studentLifecycleYearlyAnalysis: () => route('reports.student-lifecycle-yearly.index'),
    studentLifecycleYearlyAnalysisExport: () => route('reports.student-lifecycle-yearly.export'),
    studentDecisionsIndex: () => route('reports.student-decisions.index'),
    studentDecisionsShow: (id: number) => route('reports.student-decisions.show', { studentDecision: id }),
    studentDecisionsStore: () => route('reports.student-decisions.store'),
    studentDecisionsUpdate: (id: number) => route('reports.student-decisions.update', { studentDecision: id }),
    studentDecisionsPreviewStudents: (id: number) => route('reports.student-decisions.students.preview', { studentDecision: id }),
    studentDecisionsBulkLinkStudents: (id: number) => route('reports.student-decisions.students.bulk-link', { studentDecision: id }),
    studentDecisionsUnlinkStudent: (decisionId: number, actionLogId: number) => route('reports.student-decisions.students.unlink', { studentDecision: decisionId, actionLog: actionLogId }),
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
    gpa: () => route(LECTURE_ROUTE_NAMES.LECTURER_GPA_INDEX),
    gpaExport: () => route(LECTURE_ROUTE_NAMES.LECTURER_GPA_EXPORT),
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
            roadmap: (id: number) => route(CURRICULUM_ROUTE_NAMES.VERSION_SUMMARY_ROADMAP, { curriculum_version: id }),
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
    // Cross-offering reporting only (ADR 0013 phase B) — recording routes
    // live on the Course Offering Cockpit instead.
    attendance: {
        index: () => route('attendance.index'),
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
    gpaFinalization: () => route('academic.gpa.finalize.index'),
    gpaHistory: () => route('academic.gpa.history'),
    performanceDashboard: () => route('academic.students.performance'),
    warningCenter: () => route('academic.warnings.index'),
    academicReport: () => route('academic.report.index'),
    courseRanking: () => route('academic.course-ranking.index'),
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

// Finance Office Routes
export const financeRoutes = {
    audit: () => route(FINANCE_ROUTE_NAMES.AUDIT_INDEX),
    pricingOperations: {
        index: (params?: Record<string, unknown>) => route(FINANCE_ROUTE_NAMES.PRICING_OPERATIONS_INDEX, params ?? {}),
        store: () => route(FINANCE_ROUTE_NAMES.PRICING_OPERATIONS_STORE),
        activate: (pricingRuleId: number) => route(FINANCE_ROUTE_NAMES.PRICING_OPERATIONS_ACTIVATE, { pricingRule: pricingRuleId }),
        deactivate: (pricingRuleId: number) => route(FINANCE_ROUTE_NAMES.PRICING_OPERATIONS_DEACTIVATE, { pricingRule: pricingRuleId }),
    },
    today: {
        dashboard: () => route(FINANCE_ROUTE_NAMES.OPERATIONS_DASHBOARD),
    },
    cockpit: {
        index: () => route(FINANCE_ROUTE_NAMES.COCKPIT_INDEX),
        queueRows: (queue: string) => route(FINANCE_ROUTE_NAMES.COCKPIT_QUEUE_ROWS, { queue }),
        phase: () => route(FINANCE_ROUTE_NAMES.COCKPIT_PHASE),
    },
    reporting: {
        index: (params?: Record<string, unknown>) => route(FINANCE_ROUTE_NAMES.REPORTING_INDEX, params ?? {}),
    },
    feeGeneration: {
        majorCharges: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES, { fee_category: 'major' }),
        batchCharges: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES),
        egcCharges: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES, { fee_category: 'egc' }),
        egcBlockResults: () => route(FINANCE_ROUTE_NAMES.EGC_BLOCK_RESULTS_INDEX),
        egcRetakeAdjustments: () => route(FINANCE_ROUTE_NAMES.EGC_RETAKE_ADJUSTMENTS_INDEX),
        egcCarryForward: () => route(FINANCE_ROUTE_NAMES.EGC_CARRY_FORWARD_INDEX),
    },
    batchStudio: {
        hub: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_HUB),
        charges: (params?: Record<string, unknown>) => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES, params ?? {}),
        chargesPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_PREVIEW),
        chargesExport: (params: Record<string, unknown>) => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_EXPORT, params),
        chargesCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_COMMIT),
        dng: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG),
        dngPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG_PREVIEW),
        dngCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG_COMMIT),
        reminders: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS),
        remindersPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS_PREVIEW),
        remindersCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS_COMMIT),
    },
    collect: {
        dngPaymentRequests: () => route(FINANCE_ROUTE_NAMES.DNG_PAYMENT_REQUESTS_INDEX),
        dngPaymentRequestDetail: (dngPaymentRequestId: number) => route(FINANCE_ROUTE_NAMES.DNG_PAYMENT_REQUESTS_SHOW, { dngPaymentRequest: dngPaymentRequestId }),
        dngReceiptExceptions: () => route('finance.dng.receipt-exceptions.index'),
        dngWebhookEvents: () => route(FINANCE_ROUTE_NAMES.DNG_WEBHOOK_EVENTS_INDEX),
        dngWebhookEventDetail: (dngWebhookEventId: number) => route(FINANCE_ROUTE_NAMES.DNG_WEBHOOK_EVENTS_SHOW, { dngWebhookEvent: dngWebhookEventId }),
        settlement: () => route(FINANCE_ROUTE_NAMES.SETTLEMENT_INDEX),
        payments: () => route(FINANCE_ROUTE_NAMES.PAYMENTS_INDEX),
        paymentDetail: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENTS_SHOW, { payment: paymentId }),
        dueReminders: () => route(FINANCE_ROUTE_NAMES.DUE_CALENDAR),
    },
    exceptions: {
        queue: () => route(FINANCE_ROUTE_NAMES.OPERATIONS_EXCEPTIONS),
        lifecycle: () => route(FINANCE_ROUTE_NAMES.LIFECYCLE_EXCEPTIONS),
        lifecycleHistory: () => route(FINANCE_ROUTE_NAMES.LIFECYCLE_EXCEPTION_HISTORY),
    },
    lookup: {
        chargeLedger: () => route(FINANCE_ROUTE_NAMES.CHARGES_INDEX),
        chargeDetail: (chargeId: number) => route(FINANCE_ROUTE_NAMES.CHARGES_SHOW, { charge: chargeId }),
        invoices: () => route(FINANCE_ROUTE_NAMES.INVOICES_INDEX),
        invoiceDetail: (invoiceId: number) => route(FINANCE_ROUTE_NAMES.INVOICES_SHOW, { invoice: invoiceId }),
    },
    invoices: {
        export: (params?: Record<string, unknown>) => route(FINANCE_ROUTE_NAMES.INVOICES_EXPORT, params ?? {}),
    },
    students: {
        overview: (studentId: number, focus?: string) => route(FINANCE_ROUTE_NAMES.STUDENT_OVERVIEW, focus ? { student: studentId, focus } : { student: studentId }),
    },
    search: () => route(FINANCE_ROUTE_NAMES.GLOBAL_SEARCH),
    semesterContext: {
        update: () => route(FINANCE_ROUTE_NAMES.SEMESTER_CONTEXT_UPDATE),
    },
    student360: {
        allocatePreview: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENT_ALLOCATE_PREVIEW, { payment: paymentId }),
        allocate: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENT_ALLOCATE, { payment: paymentId }),
        disposeSurplus: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENT_SURPLUS_DISPOSE, { payment: paymentId }),
        recordPayment: (studentId: number) => route(FINANCE_ROUTE_NAMES.STUDENT_PAYMENT_STORE, { student: studentId }),
        dngCancelImpact: (dngId: number) => route(FINANCE_ROUTE_NAMES.DNG_CANCEL_IMPACT, { dngPaymentRequest: dngId }),
        dngCancelReviewed: (dngId: number) => route(FINANCE_ROUTE_NAMES.DNG_CANCEL_REVIEWED, { dngPaymentRequest: dngId }),
        retryInstallmentPush: (chargeId: number, installmentId: number) => route(FINANCE_ROUTE_NAMES.INSTALLMENT_RETRY_PUSH, { charge: chargeId, installment: installmentId }),
    },
} as const;
