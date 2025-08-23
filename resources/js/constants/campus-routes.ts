/**
 * Campus Routes Constants
 * Centralized route management for Campus module
 */

// Route Names - these should match Laravel route names
export const CAMPUS_ROUTE_NAMES = {
    // Campus Selection Routes
    SELECT_CAMPUS_INDEX: 'select-campus.index',
    SELECT_CAMPUS_SET_CURRENT: 'select-campus.set-current',
    SELECT_CAMPUS_CHANGE: 'select-campus.change',

    // Main Campus Routes
    INDEX: 'campuses.index',
    CREATE: 'campuses.create',
    STORE: 'campuses.store',
    SHOW: 'campuses.show',
    EDIT: 'campuses.edit',
    UPDATE: 'campuses.update',
    DESTROY: 'campuses.destroy',

    // Campus Building Routes
    BUILDINGS_CREATE: 'campuses.buildings.create',
    BUILDINGS_STORE: 'campuses.buildings.store',
    BUILDINGS_EDIT: 'campuses.buildings.edit',
    BUILDINGS_UPDATE: 'campuses.buildings.update',
    BUILDINGS_DESTROY: 'campuses.buildings.destroy',

    // Campus API Routes
    API_CAMPUSES: 'api.campuses',
} as const;

// Route Paths - for frontend routing and navigation
export const CAMPUS_ROUTE_PATHS = {
    SELECT_CAMPUS: '/select-campus',
    SELECT_CAMPUS_SET_CURRENT: '/select-campus/set-current',
    INDEX: '/campuses',
    CREATE: '/campuses/create',
    SHOW: '/campuses/:campus',
    EDIT: '/campuses/:campus/edit',
    BUILDINGS_CREATE: '/campuses/:campus/buildings/create',
    BUILDINGS_EDIT: '/campuses/:campus/buildings/:building/edit',
} as const;

// Route Helper Functions
export const campusRoutes = {
    // Campus routes
    selectCampusIndex: () => CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_INDEX,
    selectCampusSetCurrent: () => CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_SET_CURRENT,

    index: () => CAMPUS_ROUTE_NAMES.INDEX,
    create: () => CAMPUS_ROUTE_NAMES.CREATE,
    store: () => CAMPUS_ROUTE_NAMES.STORE,
    show: (id: number | string) => CAMPUS_ROUTE_NAMES.SHOW.replace(':campus', id.toString()),
    edit: (id: number | string) => CAMPUS_ROUTE_NAMES.EDIT.replace(':campus', id.toString()),
    update: (id: number | string) => CAMPUS_ROUTE_NAMES.UPDATE.replace(':campus', id.toString()),
    destroy: (id: number | string) => CAMPUS_ROUTE_NAMES.DESTROY.replace(':campus', id.toString()),

    // Buildings
    buildingsCreate: (campusId: number | string) => CAMPUS_ROUTE_NAMES.BUILDINGS_CREATE.replace(':campus', campusId.toString()),
    buildingsStore: (campusId: number | string) => CAMPUS_ROUTE_NAMES.BUILDINGS_STORE.replace(':campus', campusId.toString()),
    buildingsEdit: (campusId: number | string, buildingId: number | string) =>
        CAMPUS_ROUTE_NAMES.BUILDINGS_EDIT.replace(':campus', campusId.toString()).replace(':building', buildingId.toString()),
    buildingsUpdate: (campusId: number | string, buildingId: number | string) =>
        CAMPUS_ROUTE_NAMES.BUILDINGS_UPDATE.replace(':campus', campusId.toString()).replace(':building', buildingId.toString()),
    buildingsDestroy: (campusId: number | string, buildingId: number | string) =>
        CAMPUS_ROUTE_NAMES.BUILDINGS_DESTROY.replace(':campus', campusId.toString()).replace(':building', buildingId.toString()),

    // API
    apiCampuses: () => CAMPUS_ROUTE_NAMES.API_CAMPUSES,

    // Helper functions for frontend
    getSelectCampusPath: () => CAMPUS_ROUTE_PATHS.SELECT_CAMPUS,
    getSelectCampusSetCurrentPath: () => CAMPUS_ROUTE_PATHS.SELECT_CAMPUS_SET_CURRENT,
    indexPath: () => CAMPUS_ROUTE_PATHS.INDEX,
    createPath: () => CAMPUS_ROUTE_PATHS.CREATE,
    showPath: (id: number | string) => CAMPUS_ROUTE_PATHS.SHOW.replace(':campus', id.toString()),
    editPath: (id: number | string) => CAMPUS_ROUTE_PATHS.EDIT.replace(':campus', id.toString()),
    buildingsCreatePath: (campusId: number | string) => CAMPUS_ROUTE_PATHS.BUILDINGS_CREATE.replace(':campus', campusId.toString()),
    buildingsEditPath: (campusId: number | string, buildingId: number | string) =>
        CAMPUS_ROUTE_PATHS.BUILDINGS_EDIT.replace(':campus', campusId.toString()).replace(':building', buildingId.toString()),
} as const;

// Type definitions for better TypeScript support
export type CampusRouteNames = (typeof CAMPUS_ROUTE_NAMES)[keyof typeof CAMPUS_ROUTE_NAMES];
export type CampusRoutePaths = typeof CAMPUS_ROUTE_PATHS;
