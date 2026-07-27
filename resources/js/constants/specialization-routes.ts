/**
 * Specialization Routes Constants
 * Centralized route management for Specialization module
 */

// Route Names - these should match Laravel route names from app/Constants/SpecializationRoutes.php
export const SPECIALIZATION_ROUTE_NAMES = {
    INDEX: 'specializations.index',
    CREATE: 'specializations.create',
    STORE: 'specializations.store',
    SHOW: 'specializations.show',
    EDIT: 'specializations.edit',
    UPDATE: 'specializations.update',
    DESTROY: 'specializations.destroy',

    // API
    API_DESTROY: 'api.specializations.destroy',
    API_BULK_DELETE: 'api.specializations.bulk-delete',
    API_CURRICULUM_VERSION_UPDATE: 'api.curriculum_version.update',
} as const;

// Route Paths - for frontend routing and navigation
export const SPECIALIZATION_ROUTE_PATHS = {
    INDEX: '/specializations',
    CREATE: '/specializations/create',
    SHOW: '/specializations/:specialization',
    EDIT: '/specializations/:specialization/edit',
} as const;

// Route Helper Functions
export const specializationRoutes = {
    index: () => SPECIALIZATION_ROUTE_NAMES.INDEX,
    create: () => SPECIALIZATION_ROUTE_NAMES.CREATE,
    store: () => SPECIALIZATION_ROUTE_NAMES.STORE,
    show: (id: number | string) => SPECIALIZATION_ROUTE_NAMES.SHOW.replace(':specialization', id.toString()),
    edit: (id: number | string) => SPECIALIZATION_ROUTE_NAMES.EDIT.replace(':specialization', id.toString()),
    update: (id: number | string) => SPECIALIZATION_ROUTE_NAMES.UPDATE.replace(':specialization', id.toString()),
    destroy: (id: number | string) => SPECIALIZATION_ROUTE_NAMES.DESTROY.replace(':specialization', id.toString()),

    // API
    apiDestroy: (id: number | string) => SPECIALIZATION_ROUTE_NAMES.API_DESTROY.replace(':specialization', id.toString()),
    apiBulkDelete: () => SPECIALIZATION_ROUTE_NAMES.API_BULK_DELETE,
    apiCurriculumVersionUpdate: (id: number | string) =>
        SPECIALIZATION_ROUTE_NAMES.API_CURRICULUM_VERSION_UPDATE.replace(':curriculumVersion', id.toString()),

    // Helper functions for frontend paths
    indexPath: () => SPECIALIZATION_ROUTE_PATHS.INDEX,
    createPath: () => SPECIALIZATION_ROUTE_PATHS.CREATE,
    showPath: (id: number | string) => SPECIALIZATION_ROUTE_PATHS.SHOW.replace(':specialization', id.toString()),
    editPath: (id: number | string) => SPECIALIZATION_ROUTE_PATHS.EDIT.replace(':specialization', id.toString()),
} as const;

// Type definitions for better TypeScript support
export type SpecializationRouteNames = (typeof SPECIALIZATION_ROUTE_NAMES)[keyof typeof SPECIALIZATION_ROUTE_NAMES];
export type SpecializationRoutePaths = typeof SPECIALIZATION_ROUTE_PATHS;
