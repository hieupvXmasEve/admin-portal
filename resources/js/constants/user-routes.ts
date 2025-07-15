/**
 * User Routes Constants
 * Centralized route management for User module
 */

// Route Names - these should match Laravel route names from app/Constants/UserRoutes.php
export const USER_ROUTE_NAMES = {
    INDEX: 'users.index',
    CREATE: 'users.create',
    STORE: 'users.store',
    SHOW: 'users.show',
    EDIT: 'users.edit',
    UPDATE: 'users.update',
    DESTROY: 'users.destroy',

    // Import
    IMPORT_FORM: 'users.import.form',
    IMPORT_UPLOAD: 'users.import.upload',
    IMPORT_PREVIEW: 'users.import.preview',
    IMPORT_PROCESS: 'users.import.process',
    IMPORT_HISTORY: 'users.import.history',
    IMPORT_DEBUG: 'users.import.debug',

    // Template
    TEMPLATE_DOWNLOAD: 'users.templates.download',

    // Export
    EXPORT_EXCEL: 'users.export.excel',
    EXPORT_EXCEL_FILTERED: 'users.export.excel.filtered',
} as const;

// Route Paths - for frontend routing and navigation
export const USER_ROUTE_PATHS = {
    INDEX: '/users',
    CREATE: '/users/create',
    SHOW: '/users/:user',
    EDIT: '/users/:user/edit',

    // Import
    IMPORT_FORM: '/users/import',
    IMPORT_UPLOAD: '/users/import/upload',
    IMPORT_PREVIEW: '/users/import/preview',
    IMPORT_PROCESS: '/users/import/process',
    IMPORT_HISTORY: '/users/import/history',
    IMPORT_DEBUG: '/users/import/debug',

    // Template
    TEMPLATE_DOWNLOAD: '/users/templates/:format',

    // Export
    EXPORT_EXCEL: '/users/export/excel',
    EXPORT_EXCEL_FILTERED: '/users/export/excel/filtered',
} as const;

// Route Helper Functions
export const userRoutes = {
    index: () => USER_ROUTE_NAMES.INDEX,
    create: () => USER_ROUTE_NAMES.CREATE,
    store: () => USER_ROUTE_NAMES.STORE,
    show: (userId: number | string) => USER_ROUTE_NAMES.SHOW.replace(':user', userId.toString()),
    edit: (userId: number | string) => USER_ROUTE_NAMES.EDIT.replace(':user', userId.toString()),
    update: (userId: number | string) => USER_ROUTE_NAMES.UPDATE.replace(':user', userId.toString()),
    destroy: (userId: number | string) => USER_ROUTE_NAMES.DESTROY.replace(':user', userId.toString()),

    // Import
    importForm: () => USER_ROUTE_NAMES.IMPORT_FORM,
    importUpload: () => USER_ROUTE_NAMES.IMPORT_UPLOAD,
    importPreview: () => USER_ROUTE_NAMES.IMPORT_PREVIEW,
    importProcess: () => USER_ROUTE_NAMES.IMPORT_PROCESS,
    importHistory: () => USER_ROUTE_NAMES.IMPORT_HISTORY,
    importDebug: () => USER_ROUTE_NAMES.IMPORT_DEBUG,

    // Template
    templateDownload: (format: 'simple' | 'detailed' | 'relationship') => USER_ROUTE_NAMES.TEMPLATE_DOWNLOAD.replace(':format', format),

    // Export
    exportExcel: () => USER_ROUTE_NAMES.EXPORT_EXCEL,
    exportExcelFiltered: () => USER_ROUTE_NAMES.EXPORT_EXCEL_FILTERED,

    // Helper functions for frontend paths
    indexPath: () => USER_ROUTE_PATHS.INDEX,
    createPath: () => USER_ROUTE_PATHS.CREATE,
    showPath: (userId: number | string) => USER_ROUTE_PATHS.SHOW.replace(':user', userId.toString()),
    editPath: (userId: number | string) => USER_ROUTE_PATHS.EDIT.replace(':user', userId.toString()),

    importFormPath: () => USER_ROUTE_PATHS.IMPORT_FORM,
    templateDownloadPath: (format: 'simple' | 'detailed' | 'relationship') => USER_ROUTE_PATHS.TEMPLATE_DOWNLOAD.replace(':format', format),
} as const;

// Type definitions for better TypeScript support
export type UserRouteNames = (typeof USER_ROUTE_NAMES)[keyof typeof USER_ROUTE_NAMES];
export type UserRoutePaths = typeof USER_ROUTE_PATHS;
