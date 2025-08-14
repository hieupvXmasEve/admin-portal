<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription
} from 'reka-ui'
import { SendIcon } from 'lucide-vue-next'

interface Props {
    open: boolean
    batchId?: string | null
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'complete'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
})

const progress = ref<any>(null)
const recentActivity = ref<any[]>([])
let progressInterval: NodeJS.Timeout | null = null

const progressPercentage = computed(() => {
    if (!progress.value || progress.value.total === 0) return 0
    return Math.round((progress.value.completed / progress.value.total) * 100)
})

// Define functions before they're used in watchers
const stopProgressMonitoring = () => {
    if (progressInterval) {
        clearInterval(progressInterval)
        progressInterval = null
    }
}

const startProgressMonitoring = (batchId: string) => {
    // Initial load
    fetchProgress(batchId)

    // Set up polling
    progressInterval = setInterval(() => {
        fetchProgress(batchId)
    }, 2000) // Poll every 2 seconds
}

// Watch for batch ID changes to start monitoring
watch(() => props.batchId, (batchId) => {
    if (batchId && props.open) {
        startProgressMonitoring(batchId)
    } else {
        stopProgressMonitoring()
    }
}, { immediate: true })

// Watch for modal open/close
watch(() => props.open, (open) => {
    if (!open) {
        stopProgressMonitoring()
    } else if (props.batchId) {
        startProgressMonitoring(props.batchId)
    }
})

const fetchProgress = async (batchId: string) => {
    try {
        // This would be an actual API call to get batch progress
        // For now, simulate progress
        if (!progress.value) {
            progress.value = {
                status: 'processing',
                total: 100,
                completed: 0,
                sent: 0,
                failed: 0,
                remaining: 100,
                errors: []
            }
        } else {
            // Simulate progress
            if (progress.value.status === 'processing' && progress.value.completed < progress.value.total) {
                const increment = Math.floor(Math.random() * 10) + 1
                progress.value.completed = Math.min(progress.value.completed + increment, progress.value.total)
                progress.value.sent = progress.value.completed - progress.value.failed
                progress.value.remaining = progress.value.total - progress.value.completed

                // Add some activity
                recentActivity.value.unshift({
                    id: Date.now(),
                    timestamp: new Date().toLocaleTimeString(),
                    message: `Processed batch of ${increment} emails`
                })

                // Keep only last 10 activities
                if (recentActivity.value.length > 10) {
                    recentActivity.value = recentActivity.value.slice(0, 10)
                }

                // Check if completed
                if (progress.value.completed >= progress.value.total) {
                    progress.value.status = 'completed'
                    stopProgressMonitoring()

                    // Emit completion after a short delay
                    setTimeout(() => {
                        emit('complete')
                    }, 1000)
                }
            }
        }
    } catch (error) {
        console.error('Failed to fetch progress:', error)
        progress.value = {
            ...progress.value,
            status: 'failed'
        }
        stopProgressMonitoring()
    }
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed':
            return 'text-green-600'
        case 'failed':
            return 'text-red-600'
        case 'processing':
            return 'text-blue-600'
        default:
            return 'text-gray-600'
    }
}

const getStatusText = (status: string) => {
    switch (status) {
        case 'completed':
            return 'Completed'
        case 'failed':
            return 'Failed'
        case 'processing':
            return 'Processing'
        default:
            return 'Unknown'
    }
}

const closeModal = () => {
    isOpen.value = false
    if (progress.value?.status === 'completed' || progress.value?.status === 'failed') {
        emit('complete')
    }
}

onUnmounted(() => {
    stopProgressMonitoring()
})
</script>

<template>
    <DialogRoot v-model:open="isOpen">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" />
            <DialogContent class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                        <div>
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                                <SendIcon class="h-6 w-6 text-blue-600" />
                            </div>
                            <div class="mt-3 text-center sm:mt-5">
                                <DialogTitle class="text-lg font-semibold leading-6 text-gray-900">
                                    Sending Bulk Email
                                </DialogTitle>
                                <div class="mt-2">
                                    <DialogDescription class="text-sm text-gray-500">
                                        Your bulk email is being processed and sent in batches.
                                    </DialogDescription>
                                </div>
                            </div>
                        </div>

                        <div v-if="progress" class="mt-6 space-y-4">
                            <!-- Progress Bar -->
                            <div>
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span>{{ progress.completed }} / {{ progress.total }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: `${progressPercentage}%` }"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ progressPercentage }}% complete
                                </div>
                            </div>

                            <!-- Status Details -->
                            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Status:</span>
                                    <span :class="getStatusColor(progress.status)">{{ getStatusText(progress.status)
                                        }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Sent:</span>
                                    <span class="text-green-600">{{ progress.sent }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Failed:</span>
                                    <span class="text-red-600">{{ progress.failed }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Remaining:</span>
                                    <span class="text-gray-600">{{ progress.remaining }}</span>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div v-if="recentActivity.length > 0">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Recent Activity</h4>
                                <div class="bg-gray-50 rounded-lg p-3 max-h-32 overflow-y-auto">
                                    <div v-for="activity in recentActivity" :key="activity.id"
                                        class="text-xs text-gray-600 mb-1">
                                        <span class="font-medium">{{ activity.timestamp }}</span>: {{ activity.message
                                        }}
                                    </div>
                                </div>
                            </div>

                            <!-- Error Details -->
                            <div v-if="progress.errors && progress.errors.length > 0">
                                <h4 class="text-sm font-medium text-red-900 mb-2">Recent Errors</h4>
                                <div class="bg-red-50 rounded-lg p-3 max-h-32 overflow-y-auto">
                                    <div v-for="error in progress.errors.slice(0, 5)" :key="error.id"
                                        class="text-xs text-red-700 mb-1">
                                        <span class="font-medium">{{ error.recipient }}</span>: {{ error.message }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="mt-6 text-center">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="mt-2 text-sm text-gray-500">Initializing batch processing...</p>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button v-if="progress?.status === 'completed' || progress?.status === 'failed'"
                                @click="closeModal"
                                class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Close
                            </button>
                            <button v-else @click="closeModal"
                                class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Run in Background
                            </button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
