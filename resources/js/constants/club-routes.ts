/**
 * Club Routes Constants
 * Centralized route name management for Club module
 */
export const CLUB_ROUTE_NAMES = {
    // Main Club Routes
    INDEX: 'clubs.index',
    CREATE: 'clubs.create',
    STORE: 'clubs.store',
    SHOW: 'clubs.show',
    EDIT: 'clubs.edit',
    UPDATE: 'clubs.update',
    DESTROY: 'clubs.destroy',

    // Club Management Routes
    ASSIGN_PRESIDENT: 'clubs.assign-president',

    // Club API Routes
    API_STUDENTS_FOR_ASSIGNMENT: 'clubs.students-for-assignment',
    API_STUDENTS_FOR_CAMPUS: 'clubs.students-for-campus',
} as const;
