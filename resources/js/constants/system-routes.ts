export const SYSTEM_ACTIVITY_LOG_ROUTE_NAMES = {
    INDEX: 'system.activity-logs.index',
} as const;

export const SYSTEM_ACTIVITY_LOG_ROUTE_PATHS = {
    INDEX: '/system/activity-logs',
} as const;

export const SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES = {
    INDEX: 'system.email-configuration.index',
    TEMPLATES: 'system.email-templates.index',
    BULK_EMAIL: 'system.bulk-email.index',
} as const;

export const SYSTEM_EMAIL_CONFIGURATION_ROUTE_PATHS = {
    INDEX: '/system/email-configuration',
    TEMPLATES: '/system/email-templates',
    BULK_EMAIL: '/system/bulk-email',
} as const;
