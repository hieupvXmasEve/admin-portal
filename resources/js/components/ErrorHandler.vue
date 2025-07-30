<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { computed, ref } from 'vue';

// Error types
interface ErrorInfo {
    message: string;
    code?: string;
    type?: 'network' | 'validation' | 'permission' | 'conflict' | 'system' | 'unknown';
    severity?: 'low' | 'medium' | 'high' | 'critical';
    context?: Record<string, any>;
    timestamp?: Date;
    retryable?: boolean;
    dismissible?: boolean;
}

// Props
interface Props {
    error: ErrorInfo | null;
    showModal?: boolean;
    autoRetry?: boolean;
    maxRetries?: number;
    retryDelay?: number;
}

const props = withDefaults(defineProps<Props>(), {
    showModal: false,
    autoRetry: false,
    maxRetries: 3,
    retryDelay: 1000,
});

// Emits
const emit = defineEmits<{
    retry: [];
    dismiss: [];
    report: [error: ErrorInfo];
    reload: [];
}>();

// State
const retryCount = ref(0);
const isRetrying = ref(false);

// Computed
const showCriticalError = computed(() => props.error?.severity === 'critical' || props.showModal);

const canRetry = computed(() => props.error?.retryable !== false && retryCount.value < props.maxRetries);

const canDismiss = computed(() => props.error?.dismissible !== false && props.error?.severity !== 'critical');

const canReload = computed(() => props.error?.type === 'system' || props.error?.severity === 'critical');

const canReport = computed(() => props.error?.severity === 'high' || props.error?.severity === 'critical');

const hasActions = computed(() => canRetry.value || canReload.value || canReport.value);

// Methods
const getErrorBannerClass = () => {
    if (!props.error) return '';

    const baseClasses = 'rounded-lg p-4 border';

    switch (props.error.severity) {
        case 'critical':
            return `${baseClasses} bg-red-50 border-red-200 text-red-800`;
        case 'high':
            return `${baseClasses} bg-red-50 border-red-200 text-red-700`;
        case 'medium':
            return `${baseClasses} bg-yellow-50 border-yellow-200 text-yellow-800`;
        case 'low':
            return `${baseClasses} bg-blue-50 border-blue-200 text-blue-700`;
        default:
            return `${baseClasses} bg-gray-50 border-gray-200 text-gray-700`;
    }
};

const getErrorIcon = () => {
    if (!props.error) return 'alert-circle';

    switch (props.error.type) {
        case 'network':
            return 'wifi-off';
        case 'validation':
            return 'alert-circle';
        case 'permission':
            return 'lock';
        case 'conflict':
            return 'alert-triangle';
        case 'system':
            return 'server';
        default:
            return 'alert-circle';
    }
};

const getErrorTitle = () => {
    if (!props.error) return '';

    switch (props.error.type) {
        case 'network':
            return 'Connection Error';
        case 'validation':
            return 'Validation Error';
        case 'permission':
            return 'Permission Denied';
        case 'conflict':
            return 'Conflict Detected';
        case 'system':
            return 'System Error';
        default:
            return 'Error';
    }
};

const getErrorMessage = () => {
    if (!props.error) return '';

    // Provide user-friendly messages for common error codes
    switch (props.error.code) {
        case 'LECTURER_NOT_AVAILABLE':
            return 'The selected lecturer is not available for assignment. Please choose a different lecturer or check their availability status.';
        case 'SCHEDULE_CONFLICT':
            return 'A schedule conflict was detected. The lecturer already has another course scheduled at this time.';
        case 'ASSIGNMENT_CONFLICT':
            return 'This course offering is already assigned to another lecturer. Please unassign first or choose a different course.';
        case 'LECTURER_NOT_FOUND':
            return 'The selected lecturer could not be found. They may have been removed from the system.';
        case 'COURSE_OFFERING_NOT_FOUND':
            return 'The course offering could not be found. It may have been removed or archived.';
        case 'PERMISSION_DENIED':
            return 'You do not have permission to perform this action. Please contact your administrator.';
        case 'EXPORT_FAILED':
            return 'The export operation failed. Please try again or contact support if the problem persists.';
        default:
            return props.error.message || 'An unexpected error occurred. Please try again.';
    }
};

const handleRetry = async () => {
    if (!canRetry.value || isRetrying.value) return;

    isRetrying.value = true;
    retryCount.value++;

    try {
        // Add delay before retry
        if (props.retryDelay > 0) {
            await new Promise((resolve) => setTimeout(resolve, props.retryDelay));
        }

        emit('retry');
    } finally {
        isRetrying.value = false;
    }
};

const handleDismiss = () => {
    emit('dismiss');
    retryCount.value = 0;
};

const handleCloseCritical = () => {
    if (canDismiss.value) {
        handleDismiss();
    }
};

const handleReload = () => {
    emit('reload');
};

const handleReport = () => {
    if (props.error) {
        emit('report', props.error);
    }
};

// Auto-retry logic
if (props.autoRetry && props.error?.retryable !== false) {
    const autoRetryInterval = setInterval(() => {
        if (canRetry.value && !isRetrying.value) {
            handleRetry();
        } else {
            clearInterval(autoRetryInterval);
        }
    }, props.retryDelay);
}
</script>
<template>
    <div v-if="error" class="error-handler">
        <!-- Critical Error Modal -->
        <Dialog :open="showCriticalError" @update:open="handleCloseCritical">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center text-red-600">
                        <Icon name="alert-triangle" class="mr-2 h-5 w-5" />
                        Critical Error
                    </DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <p class="text-sm text-gray-700">
                        {{ error.message }}
                    </p>

                    <div v-if="error.context" class="rounded-lg bg-gray-50 p-3">
                        <h4 class="mb-2 text-xs font-medium text-gray-900">Error Details</h4>
                        <pre class="text-xs whitespace-pre-wrap text-gray-600">{{ JSON.stringify(error.context, null, 2) }}</pre>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="handleCloseCritical"> Close </Button>
                    <Button v-if="canRetry" @click="handleRetry">
                        <Icon name="refresh-cw" class="mr-2 h-4 w-4" />
                        Retry
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>

    <!-- Error Toast/Banner -->
    <div v-else class="error-banner" :class="getErrorBannerClass()">
        <div class="flex items-start">
            <Icon :name="getErrorIcon()" class="mt-0.5 mr-3 h-5 w-5 flex-shrink-0" />

            <div class="min-w-0 flex-1">
                <h4 class="text-sm font-medium">
                    {{ getErrorTitle() }}
                </h4>
                <p class="mt-1 text-sm">
                    {{ getErrorMessage() }}
                </p>

                <!-- Error Actions -->
                <div v-if="hasActions" class="mt-3 flex items-center space-x-3">
                    <button v-if="canRetry" type="button" class="text-sm font-medium underline hover:no-underline" @click="handleRetry">
                        Try Again
                    </button>

                    <button v-if="canReload" type="button" class="text-sm font-medium underline hover:no-underline" @click="handleReload">
                        Reload Page
                    </button>

                    <button v-if="canReport" type="button" class="text-sm font-medium underline hover:no-underline" @click="handleReport">
                        Report Issue
                    </button>
                </div>
            </div>

            <!-- Close Button -->
            <button v-if="canDismiss" type="button" class="ml-4 flex-shrink-0" @click="handleDismiss">
                <Icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.error-handler {
    /* Add any custom styles here */
}

.error-banner {
    /* Add any custom banner styles here */
}
</style>
