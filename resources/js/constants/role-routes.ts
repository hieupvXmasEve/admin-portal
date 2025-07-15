/**
 * Role Routes Constants
 * Centralized route management for Role module
 */

// Route Names - these should match Laravel route names from app/Constants/RoleRoutes.php
export const ROLE_ROUTE_NAMES = {
    INDEX: 'roles.index',
    CREATE: 'roles.create',
    STORE: 'roles.store',
    SHOW: 'roles.show',
    EDIT: 'roles.edit',
    UPDATE: 'roles.update',
    DESTROY: 'roles.destroy',
} as const;

// Route Paths - for frontend routing and navigation
export const ROLE_ROUTE_PATHS = {
    INDEX: '/roles',
    CREATE: '/roles/create',
    STORE: '/roles',
    SHOW: '/roles/:role',
    EDIT: '/roles/:role/edit',
    UPDATE: '/roles/:role',
    DESTROY: '/roles/:role',
} as const;

// Route Helper Functions
export const roleRoutes = {
    index: () => ROLE_ROUTE_PATHS.INDEX,
    create: () => ROLE_ROUTE_PATHS.CREATE,
    store: () => ROLE_ROUTE_PATHS.STORE,
    show: (roleId: number | string) => ROLE_ROUTE_PATHS.SHOW.replace(':role', roleId.toString()),
    edit: (roleId: number | string) => ROLE_ROUTE_PATHS.EDIT.replace(':role', roleId.toString()),
    update: (roleId: number | string) => ROLE_ROUTE_PATHS.UPDATE.replace(':role', roleId.toString()),
    destroy: (roleId: number | string) => ROLE_ROUTE_PATHS.DESTROY.replace(':role', roleId.toString()),

    // Helper functions for frontend paths
    indexPath: () => ROLE_ROUTE_PATHS.INDEX,
    createPath: () => ROLE_ROUTE_PATHS.CREATE,
    showPath: (roleId: number | string) => ROLE_ROUTE_PATHS.SHOW.replace(':role', roleId.toString()),
    editPath: (roleId: number | string) => ROLE_ROUTE_PATHS.EDIT.replace(':role', roleId.toString()),
} as const;

// Type definitions for better TypeScript support
export type RoleRouteNames = (typeof ROLE_ROUTE_NAMES)[keyof typeof ROLE_ROUTE_NAMES];
export type RoleRoutePaths = typeof ROLE_ROUTE_PATHS;
