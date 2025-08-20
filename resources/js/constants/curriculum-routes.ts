/**
 * Curriculum Routes Constants
 * Centralized route management for Curriculum module
 */

// Route Names - these should match Laravel route names from app/Constants/CurriculumRoutes.php
export const CURRICULUM_ROUTE_NAMES = {
    // Version
    VERSION_INDEX: 'curriculum_versions.index',
    VERSION_CREATE: 'curriculum_versions.create',
    VERSION_STORE: 'curriculum_versions.store',
    VERSION_SHOW: 'curriculum_versions.show',
    VERSION_EDIT: 'curriculum_versions.edit',
    VERSION_UPDATE: 'curriculum_versions.update',
    VERSION_DESTROY: 'curriculum_versions.destroy',
    VERSION_EXPORT_FILTERED: 'curriculum_versions.export.filtered',

    // Version Summary Tab Routes
    VERSION_SUMMARY_OVERVIEW: 'curriculum_versions.summary.overview',
    VERSION_SUMMARY_UNITS: 'curriculum_versions.summary.units',
    VERSION_SUMMARY_STUDENTS: 'curriculum_versions.summary.students',
    VERSION_SUMMARY_DEPLOYMENTS: 'curriculum_versions.summary.deployments',

    // Unit
    UNIT_INDEX: 'curriculum_unit.index',
    UNIT_CREATE: 'curriculum_unit.create',
    UNIT_STORE: 'curriculum_unit.store',
    UNIT_SHOW: 'curriculum_unit.show',
    UNIT_EDIT: 'curriculum_unit.edit',
    UNIT_UPDATE: 'curriculum_unit.update',
    UNIT_DESTROY: 'curriculum_unit.destroy',

    // Syllabus Template
    SYLLABUS_TEMPLATE_INDEX: 'syllabus_templates.index',
    SYLLABUS_TEMPLATE_CREATE: 'syllabus_templates.create',
    SYLLABUS_TEMPLATE_STORE: 'syllabus_templates.store',
    SYLLABUS_TEMPLATE_SHOW: 'syllabus_templates.show',
    SYLLABUS_TEMPLATE_EDIT: 'syllabus_templates.edit',
    SYLLABUS_TEMPLATE_UPDATE: 'syllabus_templates.update',
    SYLLABUS_TEMPLATE_DESTROY: 'syllabus_templates.destroy',

    // API Version
    API_VERSION_STORE: 'api.curriculum_versions.store',
    API_VERSION_DESTROY: 'api.curriculum_versions.destroy',
    API_VERSION_SPECIALIZATIONS_BY_PROGRAM: 'api.curriculum_versions.specializations-by-program',
    API_VERSION_BY_PROGRAM_SPECIALIZATION: 'api.curriculum_versions.by-program-specialization',
    API_VERSION_BULK_DELETE: 'api.curriculum_versions.bulk-delete',
    API_VERSION_BULK_OPERATIONS: 'api.curriculum_versions.bulk-operations',

    // API Unit
    API_UNIT_STORE: 'api.curriculum-units.store',
    API_UNIT_UPDATE: 'api.curriculum-units.update',
    API_UNIT_DESTROY: 'api.curriculum-units.destroy',
    API_UNIT_BY_CURRICULUM_VERSION: 'api.curriculum-units.by-curriculum-version',
    API_UNIT_BULK_DELETE: 'api.curriculum-units.bulk-delete',
} as const;

// Route Paths - for frontend routing and navigation
export const CURRICULUM_ROUTE_PATHS = {
    VERSION_INDEX: '/curriculum-versions',
    VERSION_CREATE: '/curriculum-versions/create',
    VERSION_SHOW: '/curriculum-versions/:curriculum_version',
    VERSION_EDIT: '/curriculum-versions/:curriculum_version/edit',

    UNIT_INDEX: '/curriculum-units',
    UNIT_CREATE: '/curriculum-units/create',
    UNIT_SHOW: '/curriculum-units/:curriculum_unit',
    UNIT_EDIT: '/curriculum-units/:curriculum_unit/edit',
} as const;

// // Route Helper Functions
// export const curriculumRoutes = {
//     // Version
//     versionIndex: () => CURRICULUM_ROUTE_NAMES.VERSION_INDEX,
//     versionCreate: () => CURRICULUM_ROUTE_NAMES.VERSION_CREATE,
//     versionStore: () => CURRICULUM_ROUTE_NAMES.VERSION_STORE,
//     versionShow: (id: number | string) => CURRICULUM_ROUTE_NAMES.VERSION_SHOW.replace(':curriculum_version', id.toString()),
//     versionEdit: (id: number | string) => CURRICULUM_ROUTE_NAMES.VERSION_EDIT.replace(':curriculum_version', id.toString()),
//     versionUpdate: (id: number | string) => CURRICULUM_ROUTE_NAMES.VERSION_UPDATE.replace(':curriculum_version', id.toString()),
//     versionDestroy: (id: number | string) => CURRICULUM_ROUTE_NAMES.VERSION_DESTROY.replace(':curriculum_version', id.toString()),
//     versionExportFiltered: () => CURRICULUM_ROUTE_NAMES.VERSION_EXPORT_FILTERED,

//     // Unit
//     unitIndex: () => CURRICULUM_ROUTE_NAMES.UNIT_INDEX,
//     unitCreate: () => CURRICULUM_ROUTE_NAMES.UNIT_CREATE,
//     unitStore: () => CURRICULUM_ROUTE_NAMES.UNIT_STORE,
//     unitShow: (id: number | string) => CURRICULUM_ROUTE_NAMES.UNIT_SHOW.replace(':curriculum_unit', id.toString()),
//     unitEdit: (id: number | string) => CURRICULUM_ROUTE_NAMES.UNIT_EDIT.replace(':curriculum_unit', id.toString()),
//     unitUpdate: (id: number | string) => CURRICULUM_ROUTE_NAMES.UNIT_UPDATE.replace(':curriculum_unit', id.toString()),
//     unitDestroy: (id: number | string) => CURRICULUM_ROUTE_NAMES.UNIT_DESTROY.replace(':curriculum_unit', id.toString()),

//     // API Version
//     apiVersionStore: () => CURRICULUM_ROUTE_NAMES.API_VERSION_STORE,
//     apiVersionDestroy: (id: number | string) => CURRICULUM_ROUTE_NAMES.API_VERSION_DESTROY.replace(':curriculumVersion', id.toString()),
//     apiVersionSpecializationsByProgram: () => CURRICULUM_ROUTE_NAMES.API_VERSION_SPECIALIZATIONS_BY_PROGRAM,
//     apiVersionByProgramSpecialization: () => CURRICULUM_ROUTE_NAMES.API_VERSION_BY_PROGRAM_SPECIALIZATION,
//     apiVersionBulkDelete: () => CURRICULUM_ROUTE_NAMES.API_VERSION_BULK_DELETE,
//     apiVersionBulkOperations: () => CURRICULUM_ROUTE_NAMES.API_VERSION_BULK_OPERATIONS,

//     // API Unit
//     apiUnitStore: () => CURRICULUM_ROUTE_NAMES.API_UNIT_STORE,
//     apiUnitUpdate: (id: number | string) => CURRICULUM_ROUTE_NAMES.API_UNIT_UPDATE.replace(':curriculumUnit', id.toString()),
//     apiUnitDestroy: (id: number | string) => CURRICULUM_ROUTE_NAMES.API_UNIT_DESTROY.replace(':curriculumUnit', id.toString()),
//     apiUnitByCurriculumVersion: () => CURRICULUM_ROUTE_NAMES.API_UNIT_BY_CURRICULUM_VERSION,
//     apiUnitBulkDelete: () => CURRICULUM_ROUTE_NAMES.API_UNIT_BULK_DELETE,

//     // Helper functions for frontend paths
//     versionIndexPath: () => CURRICULUM_ROUTE_PATHS.VERSION_INDEX,
//     versionCreatePath: () => CURRICULUM_ROUTE_PATHS.VERSION_CREATE,
//     versionShowPath: (id: number | string) => CURRICULUM_ROUTE_PATHS.VERSION_SHOW.replace(':curriculum_version', id.toString()),
//     versionEditPath: (id: number | string) => CURRICULUM_ROUTE_PATHS.VERSION_EDIT.replace(':curriculum_version', id.toString()),

//     unitIndexPath: () => CURRICULUM_ROUTE_PATHS.UNIT_INDEX,
//     unitCreatePath: () => CURRICULUM_ROUTE_PATHS.UNIT_CREATE,
//     unitShowPath: (id: number | string) => CURRICULUM_ROUTE_PATHS.UNIT_SHOW.replace(':curriculum_unit', id.toString()),
//     unitEditPath: (id: number | string) => CURRICULUM_ROUTE_PATHS.UNIT_EDIT.replace(':curriculum_unit', id.toString()),
// } as const;

// // Type definitions for better TypeScript support
// export type CurriculumRouteNames = (typeof CURRICULUM_ROUTE_NAMES)[keyof typeof CURRICULUM_ROUTE_NAMES];
// export type CurriculumRoutePaths = typeof CURRICULUM_ROUTE_PATHS;
