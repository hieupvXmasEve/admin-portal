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
    API_SEARCH: 'units.search',
    API_VALIDATE_CODE: 'units.validate-code',
    API_VALIDATE_PREREQUISITE_EXPRESSION: 'units.validate-prerequisite-expression',
    API_BULK_DELETE: 'units.bulk-delete',
} as const;

// Type definitions for better TypeScript support
export type UnitRouteNames = (typeof UNIT_ROUTE_NAMES)[keyof typeof UNIT_ROUTE_NAMES];
