/**
 * Semester Routes Constants
 * Centralized route management for Semester module
 */

// Route Names - these should match Laravel route names from app/Constants/SemesterRoutes.php
export const SEMESTER_ROUTE_NAMES = {
    INDEX: 'semesters.index',
    CREATE: 'semesters.create',
    STORE: 'semesters.store',
    SHOW: 'semesters.show',
    EDIT: 'semesters.edit',
    UPDATE: 'semesters.update',
    DESTROY: 'semesters.destroy',
    API_UPDATE: 'api.semesters.update',
    CAMPUS_SCHEDULE_UPSERT: 'semesters.campus-schedules.upsert',

    // Activation
    ACTIVATE: 'semesters.activate',
    DEACTIVATE: 'semesters.deactivate',
    ACTIVATION_STATUSES: 'semesters.activation-statuses',

    // Enrollment
    ENROLLMENT_SHOW: 'semesters.enrollment.show',

    // API Enrollment
    API_ENROLLMENT_GENERATE: 'api.semesters.enrollment.generate',
} as const;

// Route Paths - for frontend routing and navigation
export const SEMESTER_ROUTE_PATHS = {
    INDEX: '/semesters',
    CREATE: '/semesters/create',
    SHOW: '/semesters/:semester',
    EDIT: '/semesters/:semester/edit',
    ENROLLMENT_SHOW: '/semesters/:semester/enrollment',
} as const;

// Route Helper Functions
export const semesterRoutes = {
    index: () => SEMESTER_ROUTE_NAMES.INDEX,
    create: () => SEMESTER_ROUTE_NAMES.CREATE,
    store: () => SEMESTER_ROUTE_NAMES.STORE,
    show: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.SHOW.replace(':semester', semesterId.toString()),
    edit: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.EDIT.replace(':semester', semesterId.toString()),
    update: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.UPDATE.replace(':semester', semesterId.toString()),
    destroy: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.DESTROY.replace(':semester', semesterId.toString()),

    // Activation
    activate: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.ACTIVATE.replace(':semester', semesterId.toString()),
    deactivate: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.DEACTIVATE.replace(':semester', semesterId.toString()),
    activationStatuses: () => SEMESTER_ROUTE_NAMES.ACTIVATION_STATUSES,

    // Enrollment
    enrollmentShow: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.ENROLLMENT_SHOW.replace(':semester', semesterId.toString()),

    // API Enrollment
    apiEnrollmentGenerate: (semesterId: number | string) => SEMESTER_ROUTE_NAMES.API_ENROLLMENT_GENERATE.replace(':semester', semesterId.toString()),

    // Helper functions for frontend paths
    indexPath: () => SEMESTER_ROUTE_PATHS.INDEX,
    createPath: () => SEMESTER_ROUTE_PATHS.CREATE,
    showPath: (semesterId: number | string) => SEMESTER_ROUTE_PATHS.SHOW.replace(':semester', semesterId.toString()),
    editPath: (semesterId: number | string) => SEMESTER_ROUTE_PATHS.EDIT.replace(':semester', semesterId.toString()),
    enrollmentShowPath: (semesterId: number | string) => SEMESTER_ROUTE_PATHS.ENROLLMENT_SHOW.replace(':semester', semesterId.toString()),
} as const;

// Type definitions for better TypeScript support
export type SemesterRouteNames = (typeof SEMESTER_ROUTE_NAMES)[keyof typeof SEMESTER_ROUTE_NAMES];
export type SemesterRoutePaths = typeof SEMESTER_ROUTE_PATHS;
