import { route } from 'ziggy-js';

/**
 * Shared Routes Utility
 * Provides centralized route management using Ziggy.js Laravel named routes
 * Ensures type safety and consistency across the application
 */

// System Management Routes
export const systemRoutes = {
    users: {
        index: () => route('user.index'),
        create: () => route('user.create'),
        edit: (id: number) => route('user.edit', { user: id }),
        show: (id: number) => route('user.show', { user: id }),
        import: () => route('users.import.form'),
        export: () => route('user.export.excel'),
    },
    roles: {
        index: () => route('role.index'),
        create: () => route('role.create'),
        edit: (id: number) => route('role.edit', { role: id }),
        show: (id: number) => route('role.show', { role: id }),
    },
    semesters: {
        index: () => route('semester.index'),
        create: () => route('semester.create'),
        edit: (id: number) => route('semester.edit', { semester: id }),
        show: (id: number) => route('semester.show', { semester: id }),
        enrollment: (id: number) => route('semesters.enrollment.show', { semester: id }),
    },
    // Campus Management Routes
    campuses: {
        index: () => route('campuses.index'),
        create: () => route('campuses.create'),
        edit: (id: number) => route('campuses.edit', { campus: id }),
        show: (id: number) => route('campuses.show', { campus: id }),
    },
    buildings: {
        index: () => route('buildings.index'),
        create: () => route('buildings.create'),
        edit: (id: number) => route('buildings.edit', { building: id }),
        show: (id: number) => route('buildings.show', { building: id }),
    },
} as const;

// Student Management Routes
export const studentRoutes = {
    list: () => route('students.index'),
    create: () => route('students.create'),
    edit: (id: number) => route('students.edit', { student: id }),
    show: (id: number) => route('students.show', { student: id }),
    // Placeholder for future student management features
    academicRecords: (/* id: number */) => '#', // Will be implemented later
    programChange: (/* id: number */) => '#',
    repeatCourses: (/* id: number */) => '#',
    academicStanding: (/* id: number */) => '#',
    enrollments: (/* id: number */) => '#',
    statusTracking: () => '#',
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
        index: () => route('programs.index'),
        show: (id: number) => route('programs.show', { program: id }),
    },
    specializations: {
        index: () => route('specializations.index'),
        create: () => route('specializations.create'),
        edit: (id: number) => route('specializations.edit', { specialization: id }),
        show: (id: number) => route('specializations.show', { specialization: id }),
    },
    curriculumVersions: {
        index: () => route('curriculum_version.index'),
        create: () => route('curriculum_version.create'),
        edit: (id: number) => route('curriculum_version.edit', { curriculum_version: id }),
        show: (id: number) => route('curriculum_version.show', { curriculum_version: id }),
        electives: (id: number) => route('curriculum_version.electives', { curriculumVersion: id }),
    },
    units: {
        index: () => route('unit.index'),
        create: () => route('unit.create'),
        edit: (id: number) => route('unit.edit', { unit: id }),
        show: (id: number) => route('unit.show', { unit: id }),
        import: () => route('units.import'),
        export: () => route('units.export.excel'),
        syllabus: (unitId: number) => route('syllabus.index', { unit: unitId }),
    },
    curriculumUnits: {
        index: () => route('curriculum_unit.index'),
        create: () => route('curriculum_unit.create'),
        edit: (id: number) => route('curriculum_unit.edit', { curriculum_unit: id }),
    },
    // Placeholder for future features
    equivalentCourses: () => '#', // Will be implemented later
    curriculumStructure: () => '#',
} as const;

// Course Offerings & Registration Routes
export const courseRoutes = {
    offerings: {
        index: () => route('course-offerings.index'),
        create: () => route('course-offerings.create'),
        edit: (id: number) => route('course-offerings.edit', { courseOffering: id }),
        show: (id: number) => route('course-offerings.show', { courseOffering: id }),
        split: (id: number) => route('course-offerings.split.show', { courseOffering: id }),
    },
    registrations: {
        index: () => route('course-registrations.index'),
        create: () => route('course-registrations.create'),
        edit: (id: number) => route('course-registrations.edit', { adminCourseRegistration: id }),
        show: (id: number) => route('course-registrations.show', { adminCourseRegistration: id }),
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
    management: (unitId: number) => route('syllabus.index', { unit: unitId }),
    create: (unitId: number) => route('syllabus.create', { unit: unitId }),
    edit: (unitId: number, syllabusId: number) => route('syllabus.edit', { unit: unitId, syllabus: syllabusId }),
    show: (unitId: number, syllabusId: number) => route('syllabus.show', { unit: unitId, syllabus: syllabusId }),
    // Placeholder for future features
    learningMaterials: () => '#', // Will be implemented later
    learningOutcomes: () => '#',
    assessmentRubrics: () => '#',
} as const;

// Settings Routes
export const settingsRoutes = {
    profile: () => route('profile.edit'),
    password: () => route('password.edit'),
    appearance: () => route('appearance'),
} as const;

// Dashboard Route
export const dashboardRoute = () => route('dashboard');

// Campus Selection Route
export const campusSelectionRoute = () => route('select-campus.index');

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
