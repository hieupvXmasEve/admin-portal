import { ref, computed, readonly } from 'vue';
import { useApi } from './useApiRequest';
import type { UploadConfig } from '@/types/imageUpload';

interface UploadContextConfig {
    max_size: number; // in KB
    allowed_types: string[];
    allowed_extensions: string[];
    directory: string;
    generate_thumbnails: boolean;
    public: boolean;
    disk: string;
}

interface UploadConfigResponse {
    contexts: Record<string, UploadContextConfig>;
    defaults: UploadContextConfig;
    security: {
        validate_mime_type: boolean;
        validate_file_signature: boolean;
        sanitize_filename: boolean;
        generate_unique_names: boolean;
        scan_for_malware: boolean;
    };
    performance: {
        chunk_size: number;
        memory_limit: string;
        timeout: number;
        cleanup_failed_uploads: boolean;
        cleanup_interval: number;
    };
}

/**
 * Composable for managing upload configuration
 * Provides access to context-specific upload settings and validation rules
 */
export function useUploadConfig() {
    const api = useApi();

    // State
    const config = ref<UploadConfigResponse | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Computed
    const availableContexts = computed(() => {
        if (!config.value) return [];
        return Object.keys(config.value.contexts);
    });

    /**
     * Fetch upload configuration from the server
     */
    async function fetchConfig(): Promise<void> {
        if (config.value) return; // Already loaded

        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.get<UploadConfigResponse>('/api/upload/config');

            if (response.data.value?.success) {
                config.value = response.data.value.data;
            } else {
                throw new Error(response.data.value?.message || 'Failed to fetch upload configuration');
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to fetch configuration';
            console.error('Upload config fetch error:', err);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Get configuration for a specific context
     */
    function getContextConfig(context: string): UploadConfig | null {
        if (!config.value) return null;

        const contextConfig = config.value.contexts[context];
        if (!contextConfig) return null;

        return {
            context,
            maxSize: contextConfig.max_size * 1024, // Convert KB to bytes
            allowedTypes: contextConfig.allowed_types,
            directory: contextConfig.directory,
            generateThumbnails: contextConfig.generate_thumbnails,
            publicAccess: contextConfig.public,
        };
    }

    /**
     * Get the maximum file size for a context in bytes
     */
    function getMaxSize(context: string): number {
        const contextConfig = getContextConfig(context);
        return contextConfig?.maxSize || (config.value?.defaults.max_size || 10240) * 1024;
    }

    /**
     * Get allowed file types for a context
     */
    function getAllowedTypes(context: string): string[] {
        const contextConfig = getContextConfig(context);
        return contextConfig?.allowedTypes || config.value?.defaults.allowed_types || [];
    }

    /**
     * Get allowed file extensions for a context
     */
    function getAllowedExtensions(context: string): string[] {
        if (!config.value) return [];

        const contextConfig = config.value.contexts[context];
        return contextConfig?.allowed_extensions || config.value.defaults.allowed_extensions || [];
    }

    /**
     * Check if a context exists
     */
    function isValidContext(context: string): boolean {
        return availableContexts.value.includes(context);
    }

    /**
     * Get formatted file size limit text
     */
    function getMaxSizeText(context: string): string {
        const maxSizeBytes = getMaxSize(context);
        return formatFileSize(maxSizeBytes);
    }

    /**
     * Format file size for display
     */
    function formatFileSize(bytes: number): string {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Get security settings
     */
    function getSecuritySettings() {
        return config.value?.security || {
            validate_mime_type: true,
            validate_file_signature: true,
            sanitize_filename: true,
            generate_unique_names: true,
            scan_for_malware: false,
        };
    }

    /**
     * Get performance settings
     */
    function getPerformanceSettings() {
        return config.value?.performance || {
            chunk_size: 1024,
            memory_limit: '256M',
            timeout: 300,
            cleanup_failed_uploads: true,
            cleanup_interval: 3600,
        };
    }

    /**
     * Validate if a file type is allowed for a context
     */
    function isFileTypeAllowed(context: string, mimeType: string): boolean {
        const allowedTypes = getAllowedTypes(context);
        return allowedTypes.includes(mimeType);
    }

    /**
     * Validate if a file extension is allowed for a context
     */
    function isFileExtensionAllowed(context: string, extension: string): boolean {
        const allowedExtensions = getAllowedExtensions(context);
        return allowedExtensions.includes(extension.toLowerCase());
    }

    /**
     * Validate if a file size is within limits for a context
     */
    function isFileSizeAllowed(context: string, size: number): boolean {
        const maxSize = getMaxSize(context);
        return size <= maxSize;
    }

    /**
     * Get comprehensive validation rules for a context
     */
    function getValidationRules(context: string) {
        return {
            maxSize: getMaxSize(context),
            allowedTypes: getAllowedTypes(context),
            allowedExtensions: getAllowedExtensions(context),
            maxSizeText: getMaxSizeText(context),
            security: getSecuritySettings(),
        };
    }

    return {
        // State
        config: readonly(config),
        isLoading: readonly(isLoading),
        error: readonly(error),

        // Computed
        availableContexts,

        // Methods
        fetchConfig,
        getContextConfig,
        getMaxSize,
        getAllowedTypes,
        getAllowedExtensions,
        isValidContext,
        getMaxSizeText,
        formatFileSize,
        getSecuritySettings,
        getPerformanceSettings,
        isFileTypeAllowed,
        isFileExtensionAllowed,
        isFileSizeAllowed,
        getValidationRules,
    };
}
