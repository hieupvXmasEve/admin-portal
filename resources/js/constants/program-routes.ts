/**
 * Program Routes Constants
 * Centralized route management for Program module
 */

// Route Names - these should match Laravel route names from app/Constants/ProgramRoutes.php
export const PROGRAM_ROUTE_NAMES = {
    INDEX: 'programs.index',
    STORE: 'programs.store',
    SHOW: 'programs.show',
    UPDATE: 'programs.update',
    DESTROY: 'programs.destroy',

    // API
    API_SEARCH: 'api.programs.search',
    API_BULK_DELETE: 'api.programs.bulk-delete',
} as const;

// Route Paths - for frontend routing and navigation
export const PROGRAM_ROUTE_PATHS = {
    INDEX: '/programs',
    SHOW: '/programs/:program',
} as const;

// Route Helper Functions
export const programRoutes = {
    index: () => PROGRAM_ROUTE_NAMES.INDEX,
    store: () => PROGRAM_ROUTE_NAMES.STORE,
    show: (id: number | string) => PROGRAM_ROUTE_NAMES.SHOW.replace(':program', id.toString()),
    update: (id: number | string) => PROGRAM_ROUTE_NAMES.UPDATE.replace(':program', id.toString()),
    destroy: (id: number | string) => PROGRAM_ROUTE_NAMES.DESTROY.replace(':program', id.toString()),

    // API
    apiSearch: () => PROGRAM_ROUTE_NAMES.API_SEARCH,
    apiBulkDelete: () => PROGRAM_ROUTE_NAMES.API_BULK_DELETE,

    // Helper functions for frontend paths
    indexPath: () => PROGRAM_ROUTE_PATHS.INDEX,
    showPath: (id: number | string) => PROGRAM_ROUTE_PATHS.SHOW.replace(':program', id.toString()),
} as const;

// Type definitions for better TypeScript support
export type ProgramRouteNames = (typeof PROGRAM_ROUTE_NAMES)[keyof typeof PROGRAM_ROUTE_NAMES];
export type ProgramRoutePaths = typeof PROGRAM_ROUTE_PATHS;
