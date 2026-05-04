/**
 * Student Routes Constants
 * Centralized route management for Student module
 */

// Route Names - these should match Laravel route names from app/Constants/StudentRoutes.php
export const STUDENT_ROUTE_NAMES = {
    INDEX: 'students.index',
    CREATE: 'students.create',
    STORE: 'students.store',
    SHOW: 'students.academic-summary.overview',
    EDIT: 'students.edit',
    UPDATE: 'students.update',
    DESTROY: 'students.destroy',
} as const;

// Route Paths - for frontend routing and navigation
export const STUDENT_ROUTE_PATHS = {
    INDEX: '/students',
    CREATE: '/students/create',
    SHOW: '/students/:student',
    EDIT: '/students/:student/edit',
} as const;
