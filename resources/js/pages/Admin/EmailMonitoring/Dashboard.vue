<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  RefreshCwIcon,
  ActivityIcon,
  ClockIcon,
  ListIcon,
  XCircleIcon,
  AlertTriangleIcon,
  AlertCircleIcon,
  InfoIcon
} from 'lucide-vue-next'
import EmailTrendsChart from './components/EmailTrendsChart.vue'
import RecentFailuresList from './components/RecentFailuresList.vue'
import QueueStatusTable from './components/QueueStatusTable.vue'
import ActiveBatchesList from './components/ActiveBatchesList.vue'
import SystemHealthModal from './components/SystemHealthModal.vue'
import { useEmailMonitoring } from '@/composables/useEmailMonitoring'

interface Props {
  initialData: {
    overview: any
    queue_status: any
    recent_failures: any[]
    system_alerts: any[]
  }
}

const props = defineProps<Props>()

const {
  overview,
  queueStatus,
  recentFailures,
  systemAlerts,
  deliveryTrends,
  activeBatches,
  systemHealthStatus,
  isRefreshing,
  isLoadingTrends,
  refreshData,
  refreshQueueStatus,
  loadDeliveryTrends,
  testSystemHealth,
  cancelBatch,
  retryEmail
} = useEmailMonitoring(props.initialData)

const showSystemHealth = ref(false)

// Auto-refresh interval
let refreshInterval: NodeJS.Timeout | null = null

onMounted(() => {
  // Set up auto-refresh every 30 seconds
  refreshInterval = setInterval(() => {
    refreshData()
  }, 30000)

  // Load initial trends data
  loadDeliveryTrends('day')
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})

// Computed properties
const getTotalQueueSize = () => {
  if (!queueStatus.value?.queue_sizes) return 0

  return Object.values(queueStatus.value.queue_sizes)
    .filter(size => typeof size === 'number')
    .reduce((total: number, size: number) => total + size, 0)
}

const getQueueHealthColor = () => {
  const totalSize = getTotalQueueSize()
  if (totalSize > 5000) return 'text-red-600'
  if (totalSize > 1000) return 'text-yellow-600'
  return 'text-green-600'
}

const getQueueHealthText = () => {
  const totalSize = getTotalQueueSize()
  if (totalSize > 5000) return 'Critical backlog'
  if (totalSize > 1000) return 'High load'
  return 'Normal'
}

const getSystemHealthColor = () => {
  switch (systemHealthStatus.value) {
    case 'healthy': return 'text-green-600'
    case 'warning': return 'text-yellow-600'
    case 'error': return 'text-red-600'
    default: return 'text-gray-600'
  }
}

const getSystemHealthText = () => {
  switch (systemHealthStatus.value) {
    case 'healthy': return 'All systems operational'
    case 'warning': return 'Some issues detected'
    case 'error': return 'Critical issues found'
    default: return 'Unknown status'
  }
}

// Event handlers
const onTrendsPeriodChange = (period: string) => {
  loadDeliveryTrends(period)
}

const onRetryEmail = (emailId: number) => {
  retryEmail(emailId)
}

const onCancelBatch = (batchId: string) => {
  cancelBatch(batchId)
}

const onViewBatch = (batchId: string) => {
  // Navigate to batch details page
  // This would be implemented based on your routing setup
  console.log('View batch:', batchId)
}

const onTestSystemHealth = () => {
  testSystemHealth()
}

// Utility functions
const formatNumber = (num: number): string => {
  return new Intl.NumberFormat().format(num)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Email Monitoring Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">
          Monitor email delivery, queue status, and system health
        </p>
      </div>
      <div class="flex items-center space-x-3">
        <button
          @click="refreshData"
          :disabled="isRefreshing"
          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
          <RefreshCwIcon :class="['h-4 w-4 mr-2', { 'animate-spin': isRefreshing }]" />
          Refresh
        </button>
        <button
          @click="showSystemHealth = true"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          <ActivityIcon class="h-4 w-4 mr-2" />
          System Health
        </button>
      </div>
    </div>

    <!-- System Alerts -->
    <div v-if="systemAlerts.length > 0" class="space-y-3">
      <div
        v-for="alert in systemAlerts"
        :key="alert.title"
        :class="[
          'rounded-md p-4',
          {
            'bg-red-50 border border-red-200': alert.type === 'error',
            'bg-yellow-50 border border-yellow-200': alert.type === 'warning',
            'bg-blue-50 border border-blue-200': alert.type === 'info',
          }
        ]"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <AlertTriangleIcon
              v-if="alert.type === 'error'"
              class="h-5 w-5 text-red-400"
            />
            <AlertCircleIcon
              v-else-if="alert.type === 'warning'"
              class="h-5 w-5 text-yellow-400"
            />
            <InfoIcon v-else class="h-5 w-5 text-blue-400" />
          </div>
          <div class="ml-3">
            <h3 :class="[
              'text-sm font-medium',
              {
                'text-red-800': alert.type === 'error',
                'text-yellow-800': alert.type === 'warning',
                'text-blue-800': alert.type === 'info',
              }
            ]">
              {{ alert.title }}
            </h3>
            <div :class="[
              'mt-2 text-sm',
              {
                'text-red-700': alert.type === 'error',
                'text-yellow-700': alert.type === 'warning',
                'text-blue-700': alert.type === 'info',
              }
            ]">
              <p>{{ alert.message }}</p>
              <p class="mt-1 font-medium">Action: {{ alert.action }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Last 24 Hours -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <ClockIcon class="h-6 w-6 text-gray-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Last 24 Hours
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ formatNumber(overview.last_24_hours?.total || 0) }} emails
                </dd>
                <dd class="text-sm text-gray-500">
                  {{ overview.last_24_hours?.success_rate || 0 }}% success rate
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Queue Status -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <ListIcon class="h-6 w-6 text-gray-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Queue Status
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ getTotalQueueSize() }} pending
                </dd>
                <dd :class="[
                  'text-sm',
                  getQueueHealthColor()
                ]">
                  {{ getQueueHealthText() }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Failed Jobs -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <XCircleIcon class="h-6 w-6 text-gray-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Failed Jobs
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ formatNumber(queueStatus.failed_jobs_count || 0) }}
                </dd>
                <dd :class="[
                  'text-sm',
                  (queueStatus.failed_jobs_count || 0) > 100 ? 'text-red-600' : 'text-green-600'
                ]">
                  {{ (queueStatus.failed_jobs_count || 0) > 100 ? 'High' : 'Normal' }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- System Health -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <ActivityIcon class="h-6 w-6 text-gray-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  System Health
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ systemHealthStatus }}
                </dd>
                <dd :class="[
                  'text-sm',
                  getSystemHealthColor()
                ]">
                  {{ getSystemHealthText() }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts and Detailed Views -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Email Delivery Trends -->
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            Email Delivery Trends
          </h3>
          <EmailTrendsChart
            :data="deliveryTrends"
            :loading="isLoadingTrends"
            @period-change="onTrendsPeriodChange"
          />
        </div>
      </div>

      <!-- Recent Failures -->
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            Recent Failures
          </h3>
          <RecentFailuresList
            :failures="recentFailures"
            :loading="isRefreshing"
            @retry-email="onRetryEmail"
          />
        </div>
      </div>
    </div>

    <!-- Queue Details -->
    <div class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
          Queue Details
        </h3>
        <QueueStatusTable
          :queue-status="queueStatus"
          :loading="isRefreshing"
          @refresh-queue="refreshQueueStatus"
        />
      </div>
    </div>

    <!-- Active Batches -->
    <div v-if="activeBatches.length > 0" class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
          Active Email Batches
        </h3>
        <ActiveBatchesList
          :batches="activeBatches"
          :loading="isRefreshing"
          @cancel-batch="onCancelBatch"
          @view-batch="onViewBatch"
        />
      </div>
    </div>

    <!-- System Health Modal -->
    <SystemHealthModal
      v-model:open="showSystemHealth"
      @test-health="onTestSystemHealth"
    />
  </div>
</template>
