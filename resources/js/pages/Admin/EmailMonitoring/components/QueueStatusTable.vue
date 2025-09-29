<script setup lang="ts">
import { RefreshCwIcon } from 'lucide-vue-next'

interface QueueStatus {
  queue_sizes: Record<string, number | string>
  failed_jobs_count: number
  recent_statistics?: {
    last_hour?: { total: number; success_rate: number }
    last_24_hours?: { total: number; success_rate: number }
    today?: { total: number; success_rate: number }
  }
  active_batches: any[]
  system_health?: {
    status: string
    issues?: string[]
  }
  last_updated: string
}

interface Props {
  queueStatus: QueueStatus
  loading?: boolean
}

defineProps<Props>()

defineEmits<{
  'refresh-queue': []
}>()

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat().format(num)
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString()
}

const getHealthText = (size: number | string): string => {
  if (typeof size !== 'number') return 'Unavailable'

  if (size === 0) return 'Healthy'
  if (size < 100) return 'Normal'
  if (size < 1000) return 'High Load'
  return 'Critical'
}

const getSystemHealthColor = (): string => {
  // This would be passed from props in a real implementation
  return 'text-green-600' // Default to healthy
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-medium text-gray-900">Queue Status</h4>
      <button
        @click="$emit('refresh-queue')"
        :disabled="loading"
        class="inline-flex items-center px-2 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
      >
        <RefreshCwIcon :class="['h-3 w-3 mr-1', { 'animate-spin': loading }]" />
        Refresh
      </button>
    </div>

    <div v-if="loading" class="animate-pulse">
      <div class="h-32 bg-gray-200 rounded"></div>
    </div>

    <div v-else class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
      <table class="min-w-full divide-y divide-gray-300">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Queue Name
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Pending Jobs
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Status
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Health
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="(size, queueName) in queueStatus.queue_sizes" :key="queueName">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              {{ queueName }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <span v-if="typeof size === 'number'">
                {{ formatNumber(size) }}
              </span>
              <span v-else class="text-red-500">
                {{ size }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span v-if="typeof size === 'number'" :class="[
                'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                size === 0 ? 'bg-green-100 text-green-800' :
                size < 100 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              ]">
                {{ size === 0 ? 'Empty' : size < 100 ? 'Processing' : 'Backlog' }}
              </span>
              <span v-else class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                Error
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div v-if="typeof size === 'number'" :class="[
                  'flex-shrink-0 h-2.5 w-2.5 rounded-full',
                  size === 0 ? 'bg-green-400' :
                  size < 100 ? 'bg-yellow-400' :
                  size < 1000 ? 'bg-orange-400' :
                  'bg-red-400'
                ]"></div>
                <div v-else class="flex-shrink-0 h-2.5 w-2.5 rounded-full bg-red-400"></div>
                <span class="ml-2 text-sm text-gray-500">
                  {{ getHealthText(size) }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- System Health Summary -->
    <div class="bg-gray-50 rounded-lg p-4">
      <div class="flex items-center justify-between">
        <div>
          <h5 class="text-sm font-medium text-gray-900">System Health</h5>
          <p class="text-sm text-gray-500">
            Overall status:
            <span :class="getSystemHealthColor()">
              {{ queueStatus.system_health?.status || 'Unknown' }}
            </span>
          </p>
        </div>
        <div class="text-right">
          <p class="text-sm text-gray-500">Failed Jobs</p>
          <p :class="[
            'text-lg font-semibold',
            (queueStatus.failed_jobs_count || 0) > 100 ? 'text-red-600' : 'text-green-600'
          ]">
            {{ formatNumber(queueStatus.failed_jobs_count || 0) }}
          </p>
        </div>
      </div>

      <!-- Issues List -->
      <div v-if="queueStatus.system_health?.issues?.length > 0" class="mt-3">
        <h6 class="text-xs font-medium text-gray-700 uppercase tracking-wider">Issues</h6>
        <ul class="mt-1 space-y-1">
          <li
            v-for="issue in queueStatus.system_health.issues"
            :key="issue"
            class="text-sm text-red-600"
          >
            • {{ issue }}
          </li>
        </ul>
      </div>

      <!-- Last Updated -->
      <div class="mt-3 text-xs text-gray-500">
        Last updated: {{ formatDate(queueStatus.last_updated) }}
      </div>
    </div>

    <!-- Recent Statistics -->
    <div v-if="queueStatus.recent_statistics" class="grid grid-cols-3 gap-4">
      <div class="bg-white p-3 rounded-lg border">
        <h6 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Last Hour</h6>
        <div class="mt-1">
          <p class="text-lg font-semibold text-gray-900">
            {{ queueStatus.recent_statistics.last_hour?.total || 0 }}
          </p>
          <p class="text-sm text-gray-500">
            {{ queueStatus.recent_statistics.last_hour?.success_rate || 0 }}% success
          </p>
        </div>
      </div>

      <div class="bg-white p-3 rounded-lg border">
        <h6 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Last 24 Hours</h6>
        <div class="mt-1">
          <p class="text-lg font-semibold text-gray-900">
            {{ queueStatus.recent_statistics.last_24_hours?.total || 0 }}
          </p>
          <p class="text-sm text-gray-500">
            {{ queueStatus.recent_statistics.last_24_hours?.success_rate || 0 }}% success
          </p>
        </div>
      </div>

      <div class="bg-white p-3 rounded-lg border">
        <h6 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Today</h6>
        <div class="mt-1">
          <p class="text-lg font-semibold text-gray-900">
            {{ queueStatus.recent_statistics.today?.total || 0 }}
          </p>
          <p class="text-sm text-gray-500">
            {{ queueStatus.recent_statistics.today?.success_rate || 0 }}% success
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
