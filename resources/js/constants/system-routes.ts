export const SYSTEM_CONFIG_ROUTE_NAMES = {
    INDEX: 'system.config.index',
} as const;

export const SYSTEM_CONFIG_ROUTE_PATHS = {
    INDEX: '/systems/config',
} as const;

export const SYSTEM_ACTIVITY_LOG_ROUTE_NAMES = {
    INDEX: 'system.activity-logs.index',
} as const;

export const SYSTEM_ACTIVITY_LOG_ROUTE_PATHS = {
    INDEX: '/system/activity-logs',
} as const;

export const SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_NAMES = {
    INDEX: 'ai.provider-settings.index',
    UPDATE: 'ai.provider-settings.update',
    TEST: 'ai.provider-settings.test',
    KEY_DESTROY: 'ai.provider-settings.key.destroy',
} as const;

export const SYSTEM_AI_PROVIDER_SETTINGS_ROUTE_PATHS = {
    INDEX: '/ai/provider-settings',
    UPDATE: '/ai/provider-settings',
    TEST: '/ai/provider-settings/test',
    KEY_DESTROY: '/ai/provider-settings/key',
} as const;

export const SYSTEM_AI_COPILOT_ROUTE_NAMES = {
    INDEX: 'ai.copilot.index',
    MESSAGES_STORE: 'ai.copilot.messages.store',
} as const;

export const SYSTEM_AI_COPILOT_ROUTE_PATHS = {
    INDEX: '/ai/copilot',
    MESSAGES_STORE: '/ai/copilot/messages',
} as const;

export const SYSTEM_EMAIL_CONFIGURATION_ROUTE_NAMES = {
    INDEX: 'system.email-configuration.index',
    EMAIL_HISTORY: 'system.email-history.index',
} as const;

export const SYSTEM_EMAIL_CONFIGURATION_ROUTE_PATHS = {
    INDEX: '/system/email-configuration',
    EMAIL_HISTORY: '/system/email-history',
} as const;
