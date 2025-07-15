/**
 * Settings Routes Constants
 * Centralized route management for Settings module
 */

// Route Names - these should match Laravel route names
export const SETTINGS_ROUTE_NAMES = {
    PROFILE_EDIT: 'profile.edit',
    PROFILE_UPDATE: 'profile.update',
    PROFILE_DESTROY: 'profile.destroy',
    PASSWORD_EDIT: 'password.edit',
    PASSWORD_UPDATE: 'password.update',
    APPEARANCE: 'appearance',
} as const;

// Route Paths - for frontend routing and navigation
export const SETTINGS_ROUTE_PATHS = {
    PROFILE: '/settings/profile',
    PASSWORD: '/settings/password',
    APPEARANCE: '/settings/appearance',
} as const;

// Route Helper Functions
export const settingsRoutes = {
    profileEdit: () => SETTINGS_ROUTE_NAMES.PROFILE_EDIT,
    profileUpdate: () => SETTINGS_ROUTE_NAMES.PROFILE_UPDATE,
    profileDestroy: () => SETTINGS_ROUTE_NAMES.PROFILE_DESTROY,
    passwordEdit: () => SETTINGS_ROUTE_NAMES.PASSWORD_EDIT,
    passwordUpdate: () => SETTINGS_ROUTE_NAMES.PASSWORD_UPDATE,
    appearance: () => SETTINGS_ROUTE_NAMES.APPEARANCE,

    // Helper functions for frontend paths
    profilePath: () => SETTINGS_ROUTE_PATHS.PROFILE,
    passwordPath: () => SETTINGS_ROUTE_PATHS.PASSWORD,
    appearancePath: () => SETTINGS_ROUTE_PATHS.APPEARANCE,
} as const;

// Type definitions for better TypeScript support
export type SettingsRouteNames = (typeof SETTINGS_ROUTE_NAMES)[keyof typeof SETTINGS_ROUTE_NAMES];
export type SettingsRoutePaths = typeof SETTINGS_ROUTE_PATHS;
