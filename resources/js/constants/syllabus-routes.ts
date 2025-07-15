/**
 * Syllabus Routes Constants
 * Centralized route management for Syllabus module
 */

// Route Names - these should match Laravel route names
export const SYLLABUS_ROUTE_NAMES = {
    INDEX: 'syllabus.index',
    CREATE: 'syllabus.create',
    STORE: 'syllabus.store',
    SHOW: 'syllabus.show',
    EDIT: 'syllabus.edit',
    UPDATE: 'syllabus.update',
    DESTROY: 'syllabus.destroy',
    TOGGLE_ACTIVE: 'syllabus.toggle-active',
    CLONE: 'syllabus.clone',
} as const;

// Route Paths - for frontend routing and navigation
export const SYLLABUS_ROUTE_PATHS = {
    INDEX: '/units/:unit/syllabus',
    CREATE: '/units/:unit/syllabus/create',
    SHOW: '/units/:unit/syllabus/:syllabus',
    EDIT: '/units/:unit/syllabus/:syllabus/edit',
} as const;

// Route Helper Functions
export const syllabusRoutes = {
    index: (unitId: number | string) => SYLLABUS_ROUTE_NAMES.INDEX.replace(':unit', unitId.toString()),
    create: (unitId: number | string) => SYLLABUS_ROUTE_NAMES.CREATE.replace(':unit', unitId.toString()),
    store: (unitId: number | string) => SYLLABUS_ROUTE_NAMES.STORE.replace(':unit', unitId.toString()),
    show: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.SHOW.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    edit: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.EDIT.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    update: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.UPDATE.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    destroy: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.DESTROY.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    toggleActive: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.TOGGLE_ACTIVE.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    clone: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_NAMES.CLONE.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),

    // Helper functions for frontend paths
    indexPath: (unitId: number | string) => SYLLABUS_ROUTE_PATHS.INDEX.replace(':unit', unitId.toString()),
    createPath: (unitId: number | string) => SYLLABUS_ROUTE_PATHS.CREATE.replace(':unit', unitId.toString()),
    showPath: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_PATHS.SHOW.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
    editPath: (unitId: number | string, syllabusId: number | string) =>
        SYLLABUS_ROUTE_PATHS.EDIT.replace(':unit', unitId.toString()).replace(':syllabus', syllabusId.toString()),
} as const;

// Type definitions for better TypeScript support
export type SyllabusRouteNames = (typeof SYLLABUS_ROUTE_NAMES)[keyof typeof SYLLABUS_ROUTE_NAMES];
export type SyllabusRoutePaths = typeof SYLLABUS_ROUTE_PATHS;
