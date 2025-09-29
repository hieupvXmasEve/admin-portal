<script setup lang="ts">
import { ref } from 'vue'
import { ListIcon, EyeIcon, XIcon, AlertTriangleIcon } from 'lucide-vue-next'

interface EmailBatch {
  id: string
  name: string
  total_jobs: number
  pending_jobs: number
  failed_jobs: number
  progress_percentage: number
  created_at: string
}

interface Props {
  batches: EmailBatch[]
  loading?: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  'cancel-batch': [batchId: string]
  'view-batch': [batchId: string]
}>()

const batchToCancel = ref<EmailBatch | null>(null)
const isCancelling = ref(false)

const confirmCancel = (batch: EmailBatch) => {
  batchToCancel.value = batch
}

const handleCancelBatch = async () => {
  if (!batchToCancel.value) return

  isCancelling.value = true

  try {
    emit('cancel-batch', batchToCancel.value.id)
    batchToCancel.value = null
  } catch (error) {
    console.error('Failed to cancel batch:', error)
  } finally {
    isCancelling.value = false
  }
}

const getBatchStatusColor = (progress: number): string => {
  if (progress >= 100) return 'bg-green-100 text-green-800'
  if (progress >= 50) return 'bg-blue-100 text-blue-800'
  return 'bg-yellow-100 text-yellow-800'
}

const getBatchStatusText = (progress: number): string => {
  if (progress >= 100) return 'Completed'
  if (progress >= 50) return 'In Progress'
  return 'Starting'
}

const getProgressBarColor = (progress: number): string => {
  if (progress >= 100) return 'bg-green-500'
  if (progress >= 50) return 'bg-blue-500'
  return 'bg-yellow-500'
}

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat().format(num)
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString()
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="animate-pulse">
      <div v-for="i in 3" :key="i" class="h-20 bg-gray-200 rounded mb-3"></div>
    </div>

    <div v-else-if="batches.length === 0" class="text-center py-8">
      <ListIcon class="mx-auto h-12 w-12 text-gray-400" />
      <p class="mt-2 text-sm text-gray-500">No active batches</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="batch in batches"
        :key="batch.id"
        class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-3">
              <h4 class="text-sm font-medium text-gray-900 truncate">
                {{ batch.name }}
              </h4>
              <span :class="[
                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                getBatchStatusColor(batch.progress_percentage)
              ]">
                {{ getBatchStatusText(batch.progress_percentage) }}
              </span>
            </div>

            <div class="mt-2">
              <!-- Progress Bar -->
              <div class="flex items-center space-x-3">
                <div class="flex-1 bg-gray-200 rounded-full h-2">
                  <div
                    :class="[
                      'h-2 rounded-full transition-all duration-300',
                      getProgressBarColor(batch.progress_percentage)
                    ]"
                    :style="{ width: `${batch.progress_percentage}%` }"
                  ></div>
                </div>
                <span class="text-sm font-medium text-gray-700">
                  {{ batch.progress_percentage }}%
                </span>
              </div>
            </div>

            <div class="mt-2 grid grid-cols-3 gap-4 text-sm text-gray-500">
              <div>
                <span class="font-medium">Total:</span> {{ formatNumber(batch.total_jobs) }}
              </div>
              <div>
                <span class="font-medium">Pending:</span> {{ formatNumber(batch.pending_jobs) }}
              </div>
              <div>
                <span class="font-medium">Failed:</span>
                <span :class="batch.failed_jobs > 0 ? 'text-red-600' : ''">
                  {{ formatNumber(batch.failed_jobs) }}
                </span>
              </div>
            </div>

            <div class="mt-1 text-xs text-gray-400">
              Started: {{ formatDate(batch.created_at) }}
            </div>
          </div>

          <div class="flex items-center space-x-2 ml-4">
            <button
              @click="$emit('view-batch', batch.id)"
              class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <EyeIcon class="h-4 w-4 mr-1" />
              View
            </button>

            <button
              @click="confirmCancel(batch)"
              :disabled="batch.progress_percentage >= 100"
              class="inline-flex items-center px-3 py-1 border border-red-300 shadow-sm text-sm font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <XIcon class="h-4 w-4 mr-1" />
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div
      v-if="batchToCancel"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click="batchToCancel = null"
    >
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div
          class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
          @click.stop
        >
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <AlertTriangleIcon class="h-6 w-6 text-red-600" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Cancel Email Batch
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    Are you sure you want to cancel the batch "{{ batchToCancel.name }}"?
                    This will stop processing the remaining {{ formatNumber(batchToCancel.pending_jobs) }} emails.
                  </p>
                  <div class="mt-3 p-3 bg-yellow-50 rounded-md">
                    <div class="flex">
                      <AlertTriangleIcon class="h-5 w-5 text-yellow-400" />
                      <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                          Emails that are already being processed will complete, but no new emails from this batch will be sent.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              @click="handleCancelBatch"
              :disabled="isCancelling"
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              <span v-if="isCancelling" class="flex items-center">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                Cancelling...
              </span>
              <span v-else>Cancel Batch</span>
            </button>
            <button
              @click="batchToCancel = null"
              :disabled="isCancelling"
              type="button"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              Keep Running
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
