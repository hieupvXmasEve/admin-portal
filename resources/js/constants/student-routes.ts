/**
 * Student Routes Constants
 * Centralized route management for Student module
 */

// Route Names - these should match Laravel route names from app/Constants/StudentRoutes.php
export const STUDENT_ROUTE_NAMES = {
    INDEX: 'students.index',
    CREATE: 'students.create',
    STORE: 'students.store',
    SHOW: 'students.show',
    EDIT: 'students.edit',
    UPDATE: 'students.update',
    DESTROY: 'students.destroy',

    // Actions
    ASSIGN_PROGRAM: 'students.assign-program',
    UPDATE_STATUS: 'students.update-status',

    // AJAX
    AJAX_SEARCH: 'ajax.students.search',
    AJAX_SHOW: 'ajax.students.show',
    AJAX_SPECIALIZATIONS: 'ajax.students.specializations',
    AJAX_CURRICULUM_VERSIONS: 'ajax.students.curriculum-versions',
    AJAX_BY_IDS: 'ajax.students.by-ids',
} as const;

// Route Paths - for frontend routing and navigation
export const STUDENT_ROUTE_PATHS = {
    INDEX: '/students',
    CREATE: '/students/create',
    SHOW: '/students/:student',
    EDIT: '/students/:student/edit',
} as const;
