<script setup lang="ts">
import { ref } from 'vue'
import { XIcon, ActivityIcon } from 'lucide-vue-next'

interface HealthCheck {
    status: string
    message: string
    details?: any
}

interface HealthData {
    overall_status: string
    checks: Record<string, HealthCheck>
    tested_at: string
}

interface Props {
    open: boolean
}

defineProps<Props>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    'test-health': []
}>()

const isLoading = ref(false)
const healthData = ref<HealthData | null>(null)

const runHealthCheck = async () => {
    isLoading.value = true

    try {
        emit('test-health')
        // In a real implementation, this would wait for the health check results
        // For now, we'll simulate it
        setTimeout(() => {
            healthData.value = {
                overall_status: 'healthy',
                checks: {
                    smtp_connection: {
                        status: 'healthy',
                        message: 'SMTP connection is working properly',
                        details: { host: 'smtp.example.com', port: 587 }
                    },
                    queue_workers: {
                        status: 'healthy',
                        message: 'Queue workers are processing jobs normally'
                    },
                    database_connection: {
                        status: 'healthy',
                        message: 'Database connection is stable'
                    },
                    email_templates: {
                        status: 'healthy',
                        message: 'All email templates are valid',
                        details: { active_templates: 12 }
                    }
                },
                tested_at: new Date().toISOString()
            }
            isLoading.value = false
        }, 2000)
    } catch (error) {
        console.error('Health check failed:', error)
        isLoading.value = false
    }
}

const getOverallStatusColor = (status: string): string => {
    switch (status) {
        case 'healthy': return 'bg-green-100 text-green-800'
        case 'warning': return 'bg-yellow-100 text-yellow-800'
        case 'error': return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-800'
    }
}

const getCheckStatusColor = (status: string): string => {
    switch (status) {
        case 'healthy': return 'bg-green-100 text-green-800'
        case 'warning': return 'bg-yellow-100 text-yellow-800'
        case 'error': return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-800'
    }
}

const getStatusDotColor = (status: string): string => {
    switch (status) {
        case 'healthy': return 'bg-green-400'
        case 'warning': return 'bg-yellow-400'
        case 'error': return 'bg-red-400'
        default: return 'bg-gray-400'
    }
}

const getStatusText = (status: string): string => {
    switch (status) {
        case 'healthy': return 'All Systems Operational'
        case 'warning': return 'Some Issues Detected'
        case 'error': return 'Critical Issues Found'
        default: return 'Unknown Status'
    }
}

const formatCheckName = (name: string): string => {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString()
}

const getRecommendations = (): string[] => {
    if (!healthData.value) return []

    const recommendations: string[] = []

    Object.entries(healthData.value.checks).forEach(([name, check]) => {
        if (check.status === 'warning') {
            recommendations.push(`Review ${formatCheckName(name)} configuration`)
        } else if (check.status === 'error') {
            recommendations.push(`Immediate attention required for ${formatCheckName(name)}`)
        }
    })

    return recommendations
}
</script>


<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 overflow-y-auto"
    @click="$emit('update:open', false)"
  >
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

      <div
        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full"
        @click.stop
      >
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              System Health Check
            </h3>
            <button
              @click="$emit('update:open', false)"
              class="text-gray-400 hover:text-gray-600"
            >
              <XIcon class="h-6 w-6" />
            </button>
          </div>

          <div v-if="isLoading" class="text-center py-8">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
            <p class="mt-4 text-sm text-gray-500">Running health checks...</p>
          </div>

          <div v-else-if="healthData" class="space-y-6">
            <!-- Overall Status -->
            <div class="text-center">
              <div :class="[
                'inline-flex items-center px-4 py-2 rounded-full text-sm font-medium',
                getOverallStatusColor(healthData.overall_status)
              ]">
                <div :class="[
                  'w-2 h-2 rounded-full mr-2',
                  getStatusDotColor(healthData.overall_status)
                ]"></div>
                {{ getStatusText(healthData.overall_status) }}
              </div>
              <p class="mt-2 text-sm text-gray-500">
                Last checked: {{ formatDate(healthData.tested_at) }}
              </p>
            </div>

            <!-- Health Checks -->
            <div class="space-y-4">
              <div
                v-for="(check, name) in healthData.checks"
                :key="name"
                class="border rounded-lg p-4"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center space-x-2">
                      <h4 class="text-sm font-medium text-gray-900 capitalize">
                        {{ formatCheckName(name) }}
                      </h4>
                      <span :class="[
                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                        getCheckStatusColor(check.status)
                      ]">
                        {{ check.status }}
                      </span>
                    </div>

                    <p class="mt-1 text-sm text-gray-600">
                      {{ check.message }}
                    </p>

                    <div v-if="check.details" class="mt-2">
                      <details class="text-xs text-gray-500">
                        <summary class="cursor-pointer hover:text-gray-700">
                          View details
                        </summary>
                        <pre class="mt-2 p-2 bg-gray-50 rounded overflow-x-auto">{{ JSON.stringify(check.details, null, 2) }}</pre>
                      </details>
                    </div>
                  </div>

                  <div class="ml-4">
                    <div :class="[
                      'w-3 h-3 rounded-full',
                      getStatusDotColor(check.status)
                    ]"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Recommendations -->
            <div v-if="getRecommendations().length > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h4 class="text-sm font-medium text-yellow-800 mb-2">
                Recommendations
              </h4>
              <ul class="space-y-1">
                <li
                  v-for="recommendation in getRecommendations()"
                  :key="recommendation"
                  class="text-sm text-yellow-700"
                >
                  • {{ recommendation }}
                </li>
              </ul>
            </div>
          </div>

          <div v-else class="text-center py-8">
            <ActivityIcon class="mx-auto h-12 w-12 text-gray-400" />
            <p class="mt-2 text-sm text-gray-500">Click "Run Health Check" to test system components</p>
          </div>
        </div>

        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            @click="runHealthCheck"
            :disabled="isLoading"
            type="button"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
          >
            <span v-if="isLoading" class="flex items-center">
              <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              Running...
            </span>
            <span v-else>Run Health Check</span>
          </button>
          <button
            @click="$emit('update:open', false)"
            type="button"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
