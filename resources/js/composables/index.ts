// API composables
export { useApi } from './useApiRequest';

// Schedule management composables
export { useAdminSchedule } from './useAdminSchedule';
export { useScheduleManagement } from './useScheduleManagement';

// Global dialog composables
export { useGlobalConfirmDialog } from './useGlobalConfirmDialog';
export type { ConfirmDialogCallbacks, ConfirmDialogOptions } from './useGlobalConfirmDialog';

// Permission composables
export { usePermissions } from './usePermissions';

// Image upload composables
export { useImageUpload } from './useImageUpload';
export { useUploadConfig } from './useUploadConfig';

// Utility composables
export { useInitials } from './useInitials';

// Filter composables
export { useInertiaFilters, useTableFilters } from './useInertiaFilters';
export type { InertiaFilterOptions } from './useInertiaFilters';

// QR Scanner composables
export { useQRScanner } from './useQRScanner';

// Address data composables
export { useAddressData } from './useAddressData';

// Add other composables here as they are created
// export { useAuth } from './useAuth';
// export { useNotifications } from './useNotifications';
