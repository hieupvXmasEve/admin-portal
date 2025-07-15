/**
 * Course Offering Routes Constants
 * Centralized route management for Course Offering module
 */

// Route Names - these should match Laravel route names from app/Constants/CourseOfferingRoutes.php
export const COURSE_OFFERING_ROUTE_NAMES = {
    INDEX: 'course-offerings.index',
    CREATE: 'course-offerings.create',
    STORE: 'course-offerings.store',
    SHOW: 'course-offerings.show',
    EDIT: 'course-offerings.edit',
    UPDATE: 'course-offerings.update',
    DESTROY: 'course-offerings.destroy',

    // Management
    TOGGLE_STATUS: 'course-offerings.toggle-status',
    SPLIT_SHOW: 'course-offerings.split.show',
    SPLIT_PERFORM: 'course-offerings.split.perform',

    // API
    API_BULK_DELETE: 'api.course-offerings.bulk-delete',
    API_STATISTICS: 'api.course-offerings.statistics',
    API_CHECK_INSTRUCTOR_ASSIGNMENTS: 'api.course-offerings.check-instructor-assignments',
    API_BULK_ASSIGN_LECTURES: 'api.course-offerings.bulk-assign-lectures',
    API_BULK_UPDATE_STATUS: 'api.course-offerings.bulk-update-status',
} as const;

// Route Paths - for frontend routing and navigation
export const COURSE_OFFERING_ROUTE_PATHS = {
    INDEX: '/course-offerings',
    CREATE: '/course-offerings/create',
    SHOW: '/course-offerings/:courseOffering',
    EDIT: '/course-offerings/:courseOffering/edit',
    SPLIT_SHOW: '/course-offerings/:courseOffering/split',
} as const;

// Route Helper Functions
export const courseOfferingRoutes = {
    index: () => COURSE_OFFERING_ROUTE_NAMES.INDEX,
    create: () => COURSE_OFFERING_ROUTE_NAMES.CREATE,
    store: () => COURSE_OFFERING_ROUTE_NAMES.STORE,
    show: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.SHOW.replace(':courseOffering', id.toString()),
    edit: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.EDIT.replace(':courseOffering', id.toString()),
    update: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.UPDATE.replace(':courseOffering', id.toString()),
    destroy: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.DESTROY.replace(':courseOffering', id.toString()),

    // Management
    toggleStatus: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.TOGGLE_STATUS.replace(':courseOffering', id.toString()),
    splitShow: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.SPLIT_SHOW.replace(':courseOffering', id.toString()),
    splitPerform: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.SPLIT_PERFORM.replace(':courseOffering', id.toString()),

    // API
    apiBulkDelete: () => COURSE_OFFERING_ROUTE_NAMES.API_BULK_DELETE,
    apiStatistics: () => COURSE_OFFERING_ROUTE_NAMES.API_STATISTICS,
    apiCheckInstructorAssignments: () => COURSE_OFFERING_ROUTE_NAMES.API_CHECK_INSTRUCTOR_ASSIGNMENTS,
    apiBulkAssignLectures: () => COURSE_OFFERING_ROUTE_NAMES.API_BULK_ASSIGN_LECTURES,
    apiBulkUpdateStatus: (id: number | string) => COURSE_OFFERING_ROUTE_NAMES.API_BULK_UPDATE_STATUS.replace(':courseOffering', id.toString()),

    // Helper functions for frontend paths
    indexPath: () => COURSE_OFFERING_ROUTE_PATHS.INDEX,
    createPath: () => COURSE_OFFERING_ROUTE_PATHS.CREATE,
    showPath: (id: number | string) => COURSE_OFFERING_ROUTE_PATHS.SHOW.replace(':courseOffering', id.toString()),
    editPath: (id: number | string) => COURSE_OFFERING_ROUTE_PATHS.EDIT.replace(':courseOffering', id.toString()),
    splitShowPath: (id: number | string) => COURSE_OFFERING_ROUTE_PATHS.SPLIT_SHOW.replace(':courseOffering', id.toString()),
} as const;

// Type definitions for better TypeScript support
export type CourseOfferingRouteNames = (typeof COURSE_OFFERING_ROUTE_NAMES)[keyof typeof COURSE_OFFERING_ROUTE_NAMES];
export type CourseOfferingRoutePaths = typeof COURSE_OFFERING_ROUTE_PATHS;
