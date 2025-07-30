/**
 * Room Management Route Constants
 */
export const ROOM_ROUTE_NAMES = {
    INDEX: 'rooms.index',
    CREATE: 'rooms.create',
    STORE: 'rooms.store',
    SHOW: 'rooms.show', 
    EDIT: 'rooms.edit',
    UPDATE: 'rooms.update',
    DESTROY: 'rooms.destroy',
    // API routes
    API_INDEX: 'api.rooms.index',
    API_SHOW: 'api.rooms.show',
    API_STORE: 'api.rooms.store',
    API_UPDATE: 'api.rooms.update',
    API_DESTROY: 'api.rooms.destroy',
} as const;