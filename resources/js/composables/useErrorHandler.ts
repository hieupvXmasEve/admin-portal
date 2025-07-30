import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export interface ErrorInfo {
    message: string;
    code?: string;
    type?: 'network' | 'validation' | 'permission' | 'conflict' | 'system' | 'unknown';
    severity?: 'low' | 'medium' | 'high' | 'critical';
    context?: Record<string, any>;
    timestamp?: Date;
    retryable?: boolean;
    dismissible?: boolean;
    source?: string;
}

export interface ErrorHandlerOptions {
    autoRetry?: boolean;
    maxRetries?: number;
    retryDelay?: number;
    showNotifications?: boolean;
    logErrors?: boolean;
}

export function useErrorHandler(options: ErrorHandlerOptions = {}) {
    const { autoRetry = false, maxRetries = 3, retryDelay = 1000, showNotifications = true, logErrors = true } = options;

    // State
    const currentError = ref<ErrorInfo | null>(null);
    const errorHistory = ref<ErrorInfo[]>([]);
    const retryCount = ref(0);
    const isRetrying = ref(false);

    // Computed
    const hasError = computed(() => currentError.value !== null);
    const canRetry = computed(() => currentError.value?.retryable !== false && retryCount.value < maxRetries);
    const errorCount = computed(() => errorHistory.value.length);

    // Methods
    const handleError = (error: any, context?: Record<string, any>) => {
        const errorInfo = normalizeError(error, context);

        // Log error if enabled
        if (logErrors) {
            logError(errorInfo);
        }

        // Add to history
        errorHistory.value.push(errorInfo);

        // Set as current error
        currentError.value = errorInfo;

        // Auto-retry if enabled and retryable
        if (autoRetry && errorInfo.retryable !== false && retryCount.value < maxRetries) {
            scheduleRetry();
        }

        return errorInfo;
    };

    const normalizeError = (error: any, context?: Record<string, any>): ErrorInfo => {
        // Handle different error types
        if (typeof error === 'string') {
            return {
                message: error,
                type: 'unknown',
                severity: 'medium',
                context,
                timestamp: new Date(),
                retryable: true,
                dismissible: true,
            };
        }

        // Handle API response errors
        if (error?.response) {
            const response = error.response;
            const data = response.data || {};

            return {
                message: data.error || data.message || 'An API error occurred',
                code: data.error_code || `HTTP_${response.status}`,
                type: determineErrorType(data.error_code, response.status),
                severity: determineErrorSeverity(data.error_code, response.status),
                context: { ...context, ...data.context, status: response.status },
                timestamp: new Date(),
                retryable: isRetryableError(data.error_code, response.status),
                dismissible: isDismissibleError(data.error_code, response.status),
            };
        }

        // Handle network errors
        if (error?.code === 'NETWORK_ERROR' || error?.message?.includes('Network Error')) {
            return {
                message: 'Network connection failed. Please check your internet connection.',
                code: 'NETWORK_ERROR',
                type: 'network',
                severity: 'high',
                context,
                timestamp: new Date(),
                retryable: true,
                dismissible: true,
            };
        }

        // Handle validation errors
        if (error?.errors) {
            const firstError = Object.values(error.errors)[0];
            const message = Array.isArray(firstError) ? firstError[0] : firstError;

            return {
                message: (message as string) || 'Validation failed',
                code: 'VALIDATION_ERROR',
                type: 'validation',
                severity: 'medium',
                context: { ...context, errors: error.errors },
                timestamp: new Date(),
                retryable: false,
                dismissible: true,
            };
        }

        // Handle generic Error objects
        if (error instanceof Error) {
            return {
                message: error.message,
                code: error.name,
                type: 'system',
                severity: 'medium',
                context: { ...context, stack: error.stack },
                timestamp: new Date(),
                retryable: true,
                dismissible: true,
            };
        }

        // Fallback for unknown error types
        return {
            message: 'An unexpected error occurred',
            type: 'unknown',
            severity: 'medium',
            context: { ...context, originalError: error },
            timestamp: new Date(),
            retryable: true,
            dismissible: true,
        };
    };

    const determineErrorType = (errorCode?: string, status?: number): ErrorInfo['type'] => {
        if (errorCode) {
            if (errorCode.includes('PERMISSION') || errorCode.includes('UNAUTHORIZED')) {
                return 'permission';
            }
            if (errorCode.includes('CONFLICT') || errorCode.includes('SCHEDULE')) {
                return 'conflict';
            }
            if (errorCode.includes('VALIDATION') || errorCode.includes('INVALID')) {
                return 'validation';
            }
            if (errorCode.includes('NETWORK') || errorCode.includes('CONNECTION')) {
                return 'network';
            }
        }

        if (status) {
            if (status === 401 || status === 403) return 'permission';
            if (status === 422) return 'validation';
            if (status === 409) return 'conflict';
            if (status >= 500) return 'system';
            if (status === 0) return 'network';
        }

        return 'unknown';
    };

    const determineErrorSeverity = (errorCode?: string, status?: number): ErrorInfo['severity'] => {
        if (errorCode) {
            if (errorCode.includes('CRITICAL') || errorCode.includes('FATAL')) {
                return 'critical';
            }
            if (errorCode.includes('PERMISSION') || errorCode.includes('UNAUTHORIZED')) {
                return 'high';
            }
            if (errorCode.includes('VALIDATION')) {
                return 'medium';
            }
        }

        if (status) {
            if (status >= 500) return 'high';
            if (status === 401 || status === 403) return 'high';
            if (status === 422) return 'medium';
            if (status === 404) return 'medium';
        }

        return 'medium';
    };

    const isRetryableError = (errorCode?: string, status?: number): boolean => {
        // Non-retryable error codes
        const nonRetryableCodes = [
            'PERMISSION_DENIED',
            'UNAUTHORIZED',
            'VALIDATION_ERROR',
            'INVALID_ASSIGNMENT_DATA',
            'LECTURER_NOT_FOUND',
            'COURSE_OFFERING_NOT_FOUND',
        ];

        if (errorCode && nonRetryableCodes.includes(errorCode)) {
            return false;
        }

        // Non-retryable status codes
        if (status && [400, 401, 403, 404, 422].includes(status)) {
            return false;
        }

        return true;
    };

    const isDismissibleError = (errorCode?: string, status?: number): boolean => {
        // Critical errors that shouldn't be dismissed
        const nonDismissibleCodes = ['CRITICAL_ERROR', 'FATAL_ERROR'];

        if (errorCode && nonDismissibleCodes.includes(errorCode)) {
            return false;
        }

        return true;
    };

    const logError = (errorInfo: ErrorInfo) => {
        const logData = {
            ...errorInfo,
            userAgent: navigator.userAgent,
            url: window.location.href,
            timestamp: errorInfo.timestamp?.toISOString(),
        };

        // Log to console in development
        if (import.meta.env.DEV) {
            console.error('Error Handler:', logData);
        }

        // TODO: Send to error reporting service in production
        // Example: Sentry, LogRocket, etc.
    };

    const scheduleRetry = () => {
        if (!canRetry.value || isRetrying.value) return;

        isRetrying.value = true;
        retryCount.value++;

        setTimeout(() => {
            isRetrying.value = false;
            // The actual retry logic should be handled by the calling component
        }, retryDelay);
    };

    const clearError = () => {
        currentError.value = null;
        retryCount.value = 0;
        isRetrying.value = false;
    };

    const clearHistory = () => {
        errorHistory.value = [];
    };

    const retry = () => {
        if (canRetry.value) {
            retryCount.value++;
            return true;
        }
        return false;
    };

    const reportError = (errorInfo: ErrorInfo) => {
        // TODO: Implement error reporting to external service
        console.log('Reporting error:', errorInfo);

        // Example: Send to error reporting API
        // fetch('/api/error-reports', {
        //   method: 'POST',
        //   headers: { 'Content-Type': 'application/json' },
        //   body: JSON.stringify(errorInfo)
        // });
    };

    const reloadPage = () => {
        router.reload();
    };

    const getErrorsByType = (type: ErrorInfo['type']) => {
        return errorHistory.value.filter((error) => error.type === type);
    };

    const getErrorsBySeverity = (severity: ErrorInfo['severity']) => {
        return errorHistory.value.filter((error) => error.severity === severity);
    };

    const getRecentErrors = (minutes: number = 5) => {
        const cutoff = new Date(Date.now() - minutes * 60 * 1000);
        return errorHistory.value.filter((error) => error.timestamp && error.timestamp > cutoff);
    };

    return {
        // State
        currentError: currentError,
        errorHistory: errorHistory,
        retryCount: retryCount,
        isRetrying: isRetrying,

        // Computed
        hasError,
        canRetry,
        errorCount,

        // Methods
        handleError,
        clearError,
        clearHistory,
        retry,
        reportError,
        reloadPage,

        // Utility methods
        getErrorsByType,
        getErrorsBySeverity,
        getRecentErrors,
    };
}
