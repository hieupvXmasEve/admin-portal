
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogClose
} from 'reka-ui'
import {
    XIcon,
    EyeIcon,
    RefreshCwIcon,
    MailIcon,
    ChevronLeftIcon,
    ChevronRightIcon
} from 'lucide-vue-next'
import { useBulkEmail } from '@/composables/useBulkEmail'

interface Props {
    open: boolean
}

interface Emits {
    (e: 'update:open', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { emailLogs, isLoading, loadEmailLogs, retryEmail: retryEmailAction } = useBulkEmail()

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
})

const filters = ref({
    status: '',
    startDate: '',
    endDate: '',
    search: ''
})

onMounted(() => {
    if (props.open) {
        loadEmailLogs()
    }
})

const debouncedSearch = useDebounceFn(() => {
    applyFilters()
}, 300)

const applyFilters = () => {
    loadEmailLogs(filters.value)
}

const previousPage = () => {
    if (emailLogs.value.prev_page_url) {
        loadEmailLogs(filters.value, emailLogs.value.current_page - 1)
    }
}

const nextPage = () => {
    if (emailLogs.value.next_page_url) {
        loadEmailLogs(filters.value, emailLogs.value.current_page + 1)
    }
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'sent':
            return 'bg-green-100 text-green-800'
        case 'failed':
            return 'bg-red-100 text-red-800'
        case 'pending':
            return 'bg-yellow-100 text-yellow-800'
        default:
            return 'bg-gray-100 text-gray-800'
    }
}

const getStatusText = (status: string) => {
    switch (status) {
        case 'sent':
            return 'Sent'
        case 'failed':
            return 'Failed'
        case 'pending':
            return 'Pending'
        default:
            return status
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString()
}

const viewLog = (log: any) => {
    // This would open a detailed view of the email log
    console.log('View log:', log)
}

const retryEmail = async (log: any) => {
    try {
        await retryEmailAction(log.id)
        applyFilters() // Refresh the list
    } catch (error) {
        console.error('Failed to retry email:', error)
    }
}

const closeModal = () => {
  isOpen.value = false
}
</script>

<template>
    <DialogRoot v-model:open="isOpen">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" />
            <DialogContent class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                        <div>
                            <div class="flex items-center justify-between">
                                <DialogTitle class="text-lg font-semibold leading-6 text-gray-900">
                                    Email History
                                </DialogTitle>
                                <DialogClose
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <XIcon class="h-6 w-6" />
                                </DialogClose>
                            </div>

                            <!-- Filters -->
                            <div class="mt-6 bg-gray-50 rounded-lg p-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label for="status-filter" class="block text-sm font-medium text-gray-700">
                                            Status
                                        </label>
                                        <select id="status-filter" v-model="filters.status" @change="applyFilters"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="sent">Sent</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="date-from" class="block text-sm font-medium text-gray-700">
                                            From Date
                                        </label>
                                        <input id="date-from" v-model="filters.startDate" @change="applyFilters"
                                            type="date"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>

                                    <div>
                                        <label for="date-to" class="block text-sm font-medium text-gray-700">
                                            To Date
                                        </label>
                                        <input id="date-to" v-model="filters.endDate" @change="applyFilters" type="date"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>

                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700">
                                            Search
                                        </label>
                                        <input id="search" v-model="filters.search" @input="debouncedSearch" type="text"
                                            placeholder="Subject or recipient..."
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                </div>
                            </div>

                            <!-- Email Logs Table -->
                            <div class="mt-6">
                                <div v-if="isLoading" class="text-center py-8">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto">
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">Loading email history...</p>
                                </div>

                                <div v-else-if="emailLogs.data && emailLogs.data.length > 0"
                                    class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Recipient
                                                </th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Subject
                                                </th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Sent At
                                                </th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="log in emailLogs.data" :key="log.id">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ log.recipient }}
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    <div class="max-w-xs truncate">{{ log.subject }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          getStatusColor(log.status)
                        ]">
                                                        {{ getStatusText(log.status) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ formatDate(log.sent_at || log.created_at) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <button @click="viewLog(log)"
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                        <EyeIcon class="h-4 w-4" />
                                                    </button>
                                                    <button v-if="log.status === 'failed'" @click="retryEmail(log)"
                                                        class="text-green-600 hover:text-green-900">
                                                        <RefreshCwIcon class="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else class="text-center py-8">
                                    <MailIcon class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No email history</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        No emails have been sent yet.
                                    </p>
                                </div>

                                <!-- Pagination -->
                                <div v-if="emailLogs.data && emailLogs.data.length > 0"
                                    class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 flex justify-between sm:hidden">
                                            <button @click="previousPage" :disabled="!emailLogs.prev_page_url"
                                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Previous
                                            </button>
                                            <button @click="nextPage" :disabled="!emailLogs.next_page_url"
                                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Next
                                            </button>
                                        </div>
                                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm text-gray-700">
                                                    Showing
                                                    <span class="font-medium">{{ emailLogs.from || 0 }}</span>
                                                    to
                                                    <span class="font-medium">{{ emailLogs.to || 0 }}</span>
                                                    of
                                                    <span class="font-medium">{{ emailLogs.total || 0 }}</span>
                                                    results
                                                </p>
                                            </div>
                                            <div>
                                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                                    <button @click="previousPage" :disabled="!emailLogs.prev_page_url"
                                                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <ChevronLeftIcon class="h-5 w-5" />
                                                    </button>
                                                    <button @click="nextPage" :disabled="!emailLogs.next_page_url"
                                                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <ChevronRightIcon class="h-5 w-5" />
                                                    </button>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-6 flex justify-end">
                                <button @click="closeModal"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
