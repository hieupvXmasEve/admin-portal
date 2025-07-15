import {
    CAMPUS_ROUTE_NAMES,
    COURSE_OFFERING_ROUTE_NAMES,
    COURSE_REGISTRATION_ROUTE_NAMES,
    CURRICULUM_ROUTE_NAMES,
    PROGRAM_ROUTE_NAMES,
    ROLE_ROUTE_NAMES,
    SEMESTER_ROUTE_NAMES,
    SETTINGS_ROUTE_NAMES,
    SPECIALIZATION_ROUTE_NAMES,
    STUDENT_ROUTE_NAMES,
    SYLLABUS_ROUTE_NAMES,
    UNIT_ROUTE_NAMES,
    USER_ROUTE_NAMES,
} from '@/constants';
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
    },
    // Campus Management Routes
    campuses: {
        index: () => route(CAMPUS_ROUTE_NAMES.INDEX),
        create: () => route(CAMPUS_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(CAMPUS_ROUTE_NAMES.EDIT, { campus: id }),
        show: (id: number) => route(CAMPUS_ROUTE_NAMES.SHOW, { campus: id }),
        buildings: {
            create: (campusId: number) => route(CAMPUS_ROUTE_NAMES.BUILDINGS_CREATE, { campus: campusId }),
            store: (campusId: number) => route(CAMPUS_ROUTE_NAMES.BUILDINGS_STORE, { campus: campusId }),
            edit: (campusId: number, buildingId: number) => route(CAMPUS_ROUTE_NAMES.BUILDINGS_EDIT, { campus: campusId, building: buildingId }),
            update: (campusId: number, buildingId: number) => route(CAMPUS_ROUTE_NAMES.BUILDINGS_UPDATE, { campus: campusId, building: buildingId }),
            destroy: (campusId: number, buildingId: number) =>
                route(CAMPUS_ROUTE_NAMES.BUILDINGS_DESTROY, { campus: campusId, building: buildingId }),
        },
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
} as const;

// Lecturer Management Routes (Placeholder for future implementation)
export const lecturerRoutes = {
    list: () => '#', // Will be implemented later
    create: () => '#',
    edit: (/* id: number */) => '#',
    assignments: (/* id: number */) => '#',
    timetable: (/* id: number */) => '#',
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
    },
    units: {
        index: () => route(UNIT_ROUTE_NAMES.INDEX),
        create: () => route(UNIT_ROUTE_NAMES.CREATE),
        edit: (id: number) => route(UNIT_ROUTE_NAMES.EDIT, { unit: id }),
        show: (id: number) => route(UNIT_ROUTE_NAMES.SHOW, { unit: id }),
        import: () => route(UNIT_ROUTE_NAMES.IMPORT),
        export: () => route(UNIT_ROUTE_NAMES.EXPORT_EXCEL),
        syllabus: (unitId: number) => route(SYLLABUS_ROUTE_NAMES.INDEX, { unit: unitId }),
    },
    curriculumUnits: {
        index: () => route(CURRICULUM_ROUTE_NAMES.UNIT_INDEX),
        create: () => route(CURRICULUM_ROUTE_NAMES.UNIT_CREATE),
        edit: (id: number) => route(CURRICULUM_ROUTE_NAMES.UNIT_EDIT, { curriculum_unit: id }),
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
    classSchedule: () => '#',
    enrollmentSummary: () => '#',
} as const;

// Attendance Management Routes (Placeholder for future implementation)
export const attendanceRoutes = {
    classSessions: () => '#', // Will be implemented later
    takeAttendance: () => '#',
    reports: () => '#',
    gpsTracking: () => '#',
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
