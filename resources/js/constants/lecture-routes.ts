/**
 * Lecture Routes Constants
 * Centralized route name management for Lecturers module
 * Following the established pattern from other route constants
 */

export const LECTURE_ROUTE_NAMES = {
    // Main Lecture Routes
    INDEX: 'lectures.index',
    CREATE: 'lectures.create',
    STORE: 'lectures.store',
    SHOW: 'lectures.show',
    EDIT: 'lectures.edit',
    UPDATE: 'lectures.update',
    DESTROY: 'lectures.destroy',

    // Import Routes
    IMPORT_FORM: 'lectures.import.form',
    IMPORT_UPLOAD: 'lectures.import.upload',
    IMPORT_PREVIEW: 'lectures.import.preview',
    IMPORT_PROCESS: 'lectures.import.process',
    TEMPLATE_DOWNLOAD: 'lectures.templates.download',

    // Export Routes
    EXPORT_EXCEL: 'lectures.export.excel',
    EXPORT_EXCEL_FILTERED: 'lectures.export.excel.filtered',

    // API Routes
    API_SEARCH: 'api.lectures.search',
    API_STATISTICS: 'api.lectures.statistics',
} as const;

export type LectureRouteNames = (typeof LECTURE_ROUTE_NAMES)[keyof typeof LECTURE_ROUTE_NAMES];
