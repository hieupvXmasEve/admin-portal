/**
 * Campus Routes Constants
 * Centralized route management for Campus module
 */

// Route Names - these should match Laravel route names
export const CAMPUS_ROUTE_NAMES = {
    // Campus Selection Routes
    SELECT_CAMPUS_INDEX: 'select-campus.index',
    SELECT_CAMPUS_SET_CURRENT: 'select-campus.set-current',
} as const;

// Route Paths - for frontend routing and navigation
export const CAMPUS_ROUTE_PATHS = {
    SELECT_CAMPUS: '/select-campus',
    SELECT_CAMPUS_SET_CURRENT: '/select-campus/set-current',
} as const;

// Route Helper Functions
export const campusRoutes = {
    // Campus routes
    selectCampusIndex: () => CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_INDEX,
    selectCampusSetCurrent: () => CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_SET_CURRENT,

    // Helper functions for frontend
    getSelectCampusPath: () => CAMPUS_ROUTE_PATHS.SELECT_CAMPUS,
    getSelectCampusSetCurrentPath: () => CAMPUS_ROUTE_PATHS.SELECT_CAMPUS_SET_CURRENT,
} as const;

// Type definitions for better TypeScript support
export type CampusRouteNames = (typeof CAMPUS_ROUTE_NAMES)[keyof typeof CAMPUS_ROUTE_NAMES];
export type CampusRoutePaths = typeof CAMPUS_ROUTE_PATHS;
