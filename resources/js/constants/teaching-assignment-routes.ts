export const TEACHING_ASSIGNMENT_ROUTES = {
  // Main routes
  INDEX: 'teaching-assignments.index',
  
  // API routes
  API: {
    INDEX: 'api.teaching-assignments.index',
    ASSIGN: 'api.teaching-assignments.assign',
    UNASSIGN: 'api.teaching-assignments.unassign',
    AVAILABLE_LECTURERS: 'api.teaching-assignments.available-lecturers',
    CHECK_CONFLICTS: 'api.teaching-assignments.check-conflicts',
    EXPORT: 'api.teaching-assignments.export',
  }
} as const;

export type TeachingAssignmentRoute = typeof TEACHING_ASSIGNMENT_ROUTES[keyof typeof TEACHING_ASSIGNMENT_ROUTES];
export type TeachingAssignmentApiRoute = typeof TEACHING_ASSIGNMENT_ROUTES.API[keyof typeof TEACHING_ASSIGNMENT_ROUTES.API];
