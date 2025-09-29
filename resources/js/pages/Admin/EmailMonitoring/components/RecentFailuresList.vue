<script setup lang="ts">
import { ref } from 'vue'
import { XCircleIcon, RefreshCwIcon, EyeIcon } from 'lucide-vue-next'

interface EmailFailure {
  id: number
  recipient: string
  subject: string
  error_message: string
  failed_at: string
  retry_count: number
  template_name?: string
  sender_name?: string
}

interface Props {
  failures: EmailFailure[]
  loading?: boolean
}

defineProps<Props>()

defineEmits<{
  'retry-email': [emailId: number]
}>()

const selectedFailure = ref<EmailFailure | null>(null)

const showDetails = (failure: EmailFailure) => {
  selectedFailure.value = failure
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString()
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="animate-pulse">
      <div v-for="i in 5" :key="i" class="h-16 bg-gray-200 rounded mb-2"></div>
    </div>

    <div v-else-if="failures.length === 0" class="text-center py-8">
      <XCircleIcon class="mx-auto h-12 w-12 text-gray-400" />
      <p class="mt-2 text-sm text-gray-500">No recent failures</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="failure in failures"
        :key="failure.id"
        class="bg-red-50 border border-red-200 rounded-lg p-4"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2">
              <h4 class="text-sm font-medium text-red-800 truncate">
                {{ failure.recipient }}
              </h4>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                Retry {{ failure.retry_count }}
              </span>
            </div>

            <p class="mt-1 text-sm text-red-700 truncate" :title="failure.subject">
              {{ failure.subject }}
            </p>

            <p class="mt-1 text-xs text-red-600 truncate" :title="failure.error_message">
              {{ failure.error_message }}
            </p>

            <div class="mt-2 flex items-center space-x-4 text-xs text-red-600">
              <span v-if="failure.template_name">
                Template: {{ failure.template_name }}
              </span>
              <span v-if="failure.sender_name">
                Sender: {{ failure.sender_name }}
              </span>
              <span>
                {{ formatDate(failure.failed_at) }}
              </span>
            </div>
          </div>

          <div class="flex items-center space-x-2 ml-4">
            <button
              @click="$emit('retry-email', failure.id)"
              class="inline-flex items-center px-2 py-1 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <RefreshCwIcon class="h-3 w-3 mr-1" />
              Retry
            </button>

            <button
              @click="showDetails(failure)"
              class="inline-flex items-center px-2 py-1 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <EyeIcon class="h-3 w-3 mr-1" />
              Details
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Failure Details Modal -->
    <div
      v-if="selectedFailure"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click="selectedFailure = null"
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
                <XCircleIcon class="h-6 w-6 text-red-600" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Email Failure Details
                </h3>
                <div class="mt-4 space-y-3">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Recipient</label>
                    <p class="mt-1 text-sm text-gray-900">{{ selectedFailure.recipient }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <p class="mt-1 text-sm text-gray-900">{{ selectedFailure.subject }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Error Message</label>
                    <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-2 rounded">
                      {{ selectedFailure.error_message }}
                    </p>
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Retry Count</label>
                      <p class="mt-1 text-sm text-gray-900">{{ selectedFailure.retry_count }}</p>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700">Failed At</label>
                      <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedFailure.failed_at) }}</p>
                    </div>
                  </div>

                  <div v-if="selectedFailure.template_name">
                    <label class="block text-sm font-medium text-gray-700">Template</label>
                    <p class="mt-1 text-sm text-gray-900">{{ selectedFailure.template_name }}</p>
                  </div>

                  <div v-if="selectedFailure.sender_name">
                    <label class="block text-sm font-medium text-gray-700">Sender</label>
                    <p class="mt-1 text-sm text-gray-900">{{ selectedFailure.sender_name }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              @click="$emit('retry-email', selectedFailure.id); selectedFailure = null"
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Retry Email
            </button>
            <button
              @click="selectedFailure = null"
              type="button"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
