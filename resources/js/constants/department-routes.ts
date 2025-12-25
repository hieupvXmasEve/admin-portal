export const DEPARTMENT_ROUTE_NAMES = {
    INDEX: 'admin.departments.index',
    MEMBERS_INDEX: 'admin.departments.members.index',
    MEMBERS_STORE: 'admin.departments.members.store',
    MEMBERS_UPDATE: 'admin.departments.members.update',
    MEMBERS_DESTROY: 'admin.departments.members.destroy',
} as const;

export const DEPARTMENT_ROUTE_PATHS = {
    INDEX: '/admin/departments',
} as const;
