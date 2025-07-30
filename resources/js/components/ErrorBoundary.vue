<script setup lang="ts">
import ErrorHandler from '@/components/ErrorHandler.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import type { ErrorInfo } from '@/composables/useErrorHandler';
import { useErrorHandler } from '@/composables/useErrorHandler';
import { router } from '@inertiajs/vue3';
import { computed, onErrorCaptured, onMounted, ref } from 'vue';

// Props
interface Props {
    fallbackMessage?: string;
    showErrorDetails?: boolean;
    enableRetry?: boolean;
    maxRetries?: number;
}

const props = withDefaults(defineProps<Props>(), {
    fallbackMessage: 'An unexpected error occurred. Please try again.',
    showErrorDetails: false, // Only show in development
    enableRetry: true,
    maxRetries: 3,
});

// Emits
const emit = defineEmits<{
    error: [error: Error];
    retry: [];
    recover: [];
}>();

// Error handling
const { currentError, hasError: hasNonCriticalError, handleError, clearError, reportError, reloadPage } = useErrorHandler();

// State
const hasError = ref(false);
const error = ref<Error | null>(null);
const retryCount = ref(0);
const isRetrying = ref(false);

// Computed
const errorMessage = computed(() => {
    if (error.value?.message) {
        return error.value.message;
    }
    return props.fallbackMessage;
});

const errorDetails = computed(() => {
    if (!error.value) return '';

    return {
        message: error.value.message,
        stack: error.value.stack,
        name: error.value.name,
        timestamp: new Date().toISOString(),
    };
});

const canRetry = computed(() => props.enableRetry && retryCount.value < props.maxRetries);

// Error capture
onErrorCaptured((err: Error, instance, info) => {
    console.error('Error captured by boundary:', err, info);

    // Set error state
    hasError.value = true;
    error.value = err;

    // Emit error event
    emit('error', err);

    // Handle error through error handler
    handleError(err, {
        component: instance?.$?.type?.name || 'Unknown',
        info,
        severity: 'critical',
    });

    // Prevent the error from propagating
    return false;
});

// Global error handlers
onMounted(() => {
    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
        console.error('Unhandled promise rejection:', event.reason);

        const errorInfo: ErrorInfo = {
            message: event.reason?.message || 'Unhandled promise rejection',
            type: 'system',
            severity: 'high',
            context: { reason: event.reason },
            timestamp: new Date(),
            retryable: true,
            dismissible: true,
        };

        handleError(errorInfo);

        // Prevent default browser error handling
        event.preventDefault();
    });

    // Handle global JavaScript errors
    window.addEventListener('error', (event) => {
        console.error('Global error:', event.error);

        const errorInfo: ErrorInfo = {
            message: event.error?.message || event.message || 'JavaScript error',
            type: 'system',
            severity: 'high',
            context: {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error,
            },
            timestamp: new Date(),
            retryable: true,
            dismissible: true,
        };

        handleError(errorInfo);
    });
});

// Methods
const handleRetry = async () => {
    if (!canRetry.value || isRetrying.value) return;

    isRetrying.value = true;
    retryCount.value++;

    try {
        // Clear error state
        hasError.value = false;
        error.value = null;

        // Emit retry event
        emit('retry');

        // Wait a bit for the component to re-render
        await new Promise((resolve) => setTimeout(resolve, 100));

        // If we get here without error, consider it recovered
        emit('recover');
    } catch (err) {
        // If retry fails, restore error state
        hasError.value = true;
        error.value = err instanceof Error ? err : new Error('Retry failed');
    } finally {
        isRetrying.value = false;
    }
};

const handleReload = () => {
    router.visit('/');
};

const handleReport = () => {
    if (error.value) {
        const errorInfo: ErrorInfo = {
            message: error.value.message,
            type: 'system',
            severity: 'critical',
            context: {
                stack: error.value.stack,
                name: error.value.name,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString(),
            },
            timestamp: new Date(),
            retryable: false,
            dismissible: false,
        };

        reportError(errorInfo);

        // Show confirmation to user
        alert('Error report sent. Thank you for helping us improve the application.');
    }
};

// Non-critical error handlers
const handleErrorRetry = () => {
    // This would be handled by the specific component that triggered the error
    emit('retry');
};

const handleErrorDismiss = () => {
    clearError();
};

const handleErrorReport = (errorInfo: ErrorInfo) => {
    reportError(errorInfo);
};

const handleErrorReload = () => {
    reloadPage();
};

// Recovery method for external use
const recover = () => {
    hasError.value = false;
    error.value = null;
    retryCount.value = 0;
    isRetrying.value = false;
    clearError();
    emit('recover');
};

// Expose recovery method
defineExpose({
    recover,
    hasError: computed(() => hasError.value),
    error: computed(() => error.value),
});
</script>

<template>
    <div class="error-boundary">
        <!-- Error State -->
        <div v-if="hasError" class="error-boundary-content">
            <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
                <div class="w-full max-w-md space-y-8">
                    <div class="text-center">
                        <Icon name="alert-triangle" class="mx-auto h-16 w-16 text-red-500" />
                        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Something went wrong</h2>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ errorMessage }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <!-- Error Details (Development Only) -->
                        <div v-if="showErrorDetails" class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <h3 class="mb-2 text-sm font-medium text-red-800">Error Details</h3>
                            <div class="max-h-32 overflow-auto rounded bg-red-100 p-2 font-mono text-xs text-red-700">
                                {{ errorDetails }}
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col space-y-2">
                            <Button @click="handleRetry" :disabled="isRetrying" class="w-full">
                                <Icon v-if="isRetrying" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                                <Icon v-else name="refresh-cw" class="mr-2 h-4 w-4" />
                                {{ isRetrying ? 'Retrying...' : 'Try Again' }}
                            </Button>

                            <Button variant="outline" @click="handleReload" class="w-full">
                                <Icon name="home" class="mr-2 h-4 w-4" />
                                Go to Home
                            </Button>

                            <Button variant="ghost" @click="handleReport" class="w-full text-sm">
                                <Icon name="bug" class="mr-2 h-4 w-4" />
                                Report this issue
                            </Button>
                        </div>

                        <!-- Additional Help -->
                        <div class="text-center">
                            <p class="text-xs text-gray-500">If this problem persists, please contact support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Normal Content -->
        <div v-else>
            <slot />
        </div>

        <!-- Error Handler for Non-Critical Errors -->
        <ErrorHandler
            v-if="!hasError && currentError"
            :error="currentError"
            @retry="handleErrorRetry"
            @dismiss="handleErrorDismiss"
            @report="handleErrorReport"
            @reload="handleErrorReload"
        />
    </div>
</template>

<style scoped>
.error-boundary {
    /* Add any custom styles here */
}

.error-boundary-content {
    /* Add any custom error content styles here */
}
</style>
