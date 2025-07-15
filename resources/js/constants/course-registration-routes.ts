/**
 * Course Registration Routes Constants
 * Centralized route management for Course Registration module
 */

// Route Names - these should match Laravel route names
export const COURSE_REGISTRATION_ROUTE_NAMES = {
    INDEX: 'course-registrations.index',
    CREATE: 'course-registrations.create',
    STORE: 'course-registrations.store',
    SHOW: 'course-registrations.show',
    EDIT: 'course-registrations.edit',
    UPDATE: 'course-registrations.update',
    DESTROY: 'course-registrations.destroy',
    DROP: 'course-registrations.drop',
    WITHDRAW: 'course-registrations.withdraw',

    // API
    API_BULK_DELETE: 'api.course-registrations.bulk-delete',
    API_AVAILABLE_COURSES: 'api.course-registrations.available-courses',
    API_CHECK_ELIGIBILITY: 'api.course-registrations.check-eligibility',
    API_STUDENT_REGISTRATIONS: 'api.course-registrations.student-registrations',
} as const;

// Route Paths - for frontend routing and navigation
export const COURSE_REGISTRATION_ROUTE_PATHS = {
    INDEX: '/course-registrations',
    CREATE: '/course-registrations/create',
    SHOW: '/course-registrations/:adminCourseRegistration',
    EDIT: '/course-registrations/:adminCourseRegistration/edit',
} as const;

// Route Helper Functions
export const courseRegistrationRoutes = {
    index: () => COURSE_REGISTRATION_ROUTE_NAMES.INDEX,
    create: () => COURSE_REGISTRATION_ROUTE_NAMES.CREATE,
    store: () => COURSE_REGISTRATION_ROUTE_NAMES.STORE,
    show: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.SHOW.replace(':adminCourseRegistration', id.toString()),
    edit: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.EDIT.replace(':adminCourseRegistration', id.toString()),
    update: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.UPDATE.replace(':adminCourseRegistration', id.toString()),
    destroy: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.DESTROY.replace(':adminCourseRegistration', id.toString()),
    drop: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.DROP.replace(':adminCourseRegistration', id.toString()),
    withdraw: (id: number | string) => COURSE_REGISTRATION_ROUTE_NAMES.WITHDRAW.replace(':adminCourseRegistration', id.toString()),

    // API
    apiBulkDelete: () => COURSE_REGISTRATION_ROUTE_NAMES.API_BULK_DELETE,
    apiAvailableCourses: () => COURSE_REGISTRATION_ROUTE_NAMES.API_AVAILABLE_COURSES,
    apiCheckEligibility: () => COURSE_REGISTRATION_ROUTE_NAMES.API_CHECK_ELIGIBILITY,
    apiStudentRegistrations: () => COURSE_REGISTRATION_ROUTE_NAMES.API_STUDENT_REGISTRATIONS,

    // Helper functions for frontend paths
    indexPath: () => COURSE_REGISTRATION_ROUTE_PATHS.INDEX,
    createPath: () => COURSE_REGISTRATION_ROUTE_PATHS.CREATE,
    showPath: (id: number | string) => COURSE_REGISTRATION_ROUTE_PATHS.SHOW.replace(':adminCourseRegistration', id.toString()),
    editPath: (id: number | string) => COURSE_REGISTRATION_ROUTE_PATHS.EDIT.replace(':adminCourseRegistration', id.toString()),
} as const;

// Type definitions for better TypeScript support
export type CourseRegistrationRouteNames = (typeof COURSE_REGISTRATION_ROUTE_NAMES)[keyof typeof COURSE_REGISTRATION_ROUTE_NAMES];
export type CourseRegistrationRoutePaths = typeof COURSE_REGISTRATION_ROUTE_PATHS;
