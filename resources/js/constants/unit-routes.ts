/**
 * Unit Routes Constants
 * Centralized route management for Unit module
 */

// Route Names - these should match Laravel route names from app/Constants/UnitRoutes.php
export const UNIT_ROUTE_NAMES = {
    INDEX: 'units.index',
    CREATE: 'units.create',
    STORE: 'units.store',
    SHOW: 'units.show',
    EDIT: 'units.edit',
    UPDATE: 'units.update',
    DESTROY: 'units.destroy',

    // Export
    EXPORT_EXCEL: 'units.export.excel',
    EXPORT_EXCEL_FILTERED: 'units.export.excel.filtered',

    // Import
    IMPORT: 'units.import',
    IMPORT_UPLOAD: 'units.import.upload',
    IMPORT_PREVIEW: 'units.import.preview',
    IMPORT_PROCESS: 'units.import.process',
    IMPORT_TEMPLATE: 'units.import.template',
    IMPORT_HISTORY: 'units.import.history',

    // API
    API_SEARCH: 'api.units.search',
    API_VALIDATE_CODE: 'api.units.validate-code',
    API_VALIDATE_PREREQUISITE_EXPRESSION: 'api.units.validate-prerequisite-expression',
    API_BULK_DELETE: 'api.units.bulk-delete',
} as const;

// Route Paths - for frontend routing and navigation
export const UNIT_ROUTE_PATHS = {
    INDEX: '/units',
    CREATE: '/units/create',
    SHOW: '/units/:unit',
    EDIT: '/units/:unit/edit',
    IMPORT: '/units/import',
    IMPORT_TEMPLATE: '/units/import/template/:format',
} as const;

// Route Helper Functions
export const unitRoutes = {
    index: () => UNIT_ROUTE_NAMES.INDEX,
    create: () => UNIT_ROUTE_NAMES.CREATE,
    store: () => UNIT_ROUTE_NAMES.STORE,
    show: (id: number | string) => UNIT_ROUTE_NAMES.SHOW.replace(':unit', id.toString()),
    edit: (id: number | string) => UNIT_ROUTE_NAMES.EDIT.replace(':unit', id.toString()),
    update: (id: number | string) => UNIT_ROUTE_NAMES.UPDATE.replace(':unit', id.toString()),
    destroy: (id: number | string) => UNIT_ROUTE_NAMES.DESTROY.replace(':unit', id.toString()),

    // Export
    exportExcel: () => UNIT_ROUTE_NAMES.EXPORT_EXCEL,
    exportExcelFiltered: () => UNIT_ROUTE_NAMES.EXPORT_EXCEL_FILTERED,

    // Import
    import: () => UNIT_ROUTE_NAMES.IMPORT,
    importUpload: () => UNIT_ROUTE_NAMES.IMPORT_UPLOAD,
    importPreview: () => UNIT_ROUTE_NAMES.IMPORT_PREVIEW,
    importProcess: () => UNIT_ROUTE_NAMES.IMPORT_PROCESS,
    importTemplate: (format: 'simple' | 'detailed') => UNIT_ROUTE_NAMES.IMPORT_TEMPLATE.replace(':format', format),
    importHistory: () => UNIT_ROUTE_NAMES.IMPORT_HISTORY,

    // API
    apiSearch: () => UNIT_ROUTE_NAMES.API_SEARCH,
    apiValidateCode: () => UNIT_ROUTE_NAMES.API_VALIDATE_CODE,
    apiValidatePrerequisiteExpression: () => UNIT_ROUTE_NAMES.API_VALIDATE_PREREQUISITE_EXPRESSION,
    apiBulkDelete: () => UNIT_ROUTE_NAMES.API_BULK_DELETE,

    // Helper functions for frontend paths
    indexPath: () => UNIT_ROUTE_PATHS.INDEX,
    createPath: () => UNIT_ROUTE_PATHS.CREATE,
    showPath: (id: number | string) => UNIT_ROUTE_PATHS.SHOW.replace(':unit', id.toString()),
    editPath: (id: number | string) => UNIT_ROUTE_PATHS.EDIT.replace(':unit', id.toString()),
    importPath: () => UNIT_ROUTE_PATHS.IMPORT,
    importTemplatePath: (format: 'simple' | 'detailed') => UNIT_ROUTE_PATHS.IMPORT_TEMPLATE.replace(':format', format),
} as const;

// Type definitions for better TypeScript support
export type UnitRouteNames = (typeof UNIT_ROUTE_NAMES)[keyof typeof UNIT_ROUTE_NAMES];
export type UnitRoutePaths = typeof UNIT_ROUTE_PATHS;
