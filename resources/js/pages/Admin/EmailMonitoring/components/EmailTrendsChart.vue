<template>
  <div class="space-y-4">
    <!-- Period Selector -->
    <div class="flex items-center justify-between">
      <div class="flex space-x-2">
        <button
          v-for="period in periods"
          :key="period.value"
          @click="$emit('period-change', period.value)"
          :class="[
            'px-3 py-1 text-sm font-medium rounded-md',
            selectedPeriod === period.value
              ? 'bg-indigo-100 text-indigo-700'
              : 'text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ period.label }}
        </button>
      </div>
      <div v-if="!loading && data.length > 0" class="text-sm text-gray-500">
        {{ data.length }} data points
      </div>
    </div>

    <!-- Chart Container -->
    <div class="relative h-64">
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      </div>

      <div v-else-if="data.length === 0" class="absolute inset-0 flex items-center justify-center">
        <div class="text-center">
          <BarChart3Icon class="mx-auto h-12 w-12 text-gray-400" />
          <p class="mt-2 text-sm text-gray-500">No data available</p>
        </div>
      </div>

      <div v-else class="h-full">
        <!-- Simple SVG Chart -->
        <svg class="w-full h-full" viewBox="0 0 800 200">
          <!-- Grid lines -->
          <defs>
            <pattern id="grid" width="40" height="20" patternUnits="userSpaceOnUse">
              <path d="M 40 0 L 0 0 0 20" fill="none" stroke="#f3f4f6" stroke-width="1"/>
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#grid)" />

          <!-- Success rate line -->
          <polyline
            :points="getSuccessRatePoints()"
            fill="none"
            stroke="#10b981"
            stroke-width="2"
          />

          <!-- Total emails bars -->
          <g v-for="(point, index) in data" :key="index">
            <rect
              :x="getXPosition(index)"
              :y="getYPosition(point.total, maxTotal)"
              :width="barWidth"
              :height="getBarHeight(point.total, maxTotal)"
              fill="#e5e7eb"
              opacity="0.7"
            />
            <rect
              :x="getXPosition(index)"
              :y="getYPosition(point.sent + point.delivered, maxTotal)"
              :width="barWidth"
              :height="getBarHeight(point.sent + point.delivered, maxTotal)"
              fill="#10b981"
              opacity="0.8"
            />
            <rect
              :x="getXPosition(index)"
              :y="getYPosition(point.failed, maxTotal)"
              :width="barWidth"
              :height="getBarHeight(point.failed, maxTotal)"
              fill="#ef4444"
              opacity="0.8"
            />
          </g>

          <!-- Data points for success rate -->
          <g v-for="(point, index) in data" :key="`point-${index}`">
            <circle
              :cx="getXPosition(index) + barWidth / 2"
              :cy="getSuccessRateY(point.success_rate)"
              r="3"
              fill="#10b981"
            />
          </g>
        </svg>

        <!-- Legend -->
        <div class="mt-4 flex items-center justify-center space-x-6 text-sm">
          <div class="flex items-center">
            <div class="w-3 h-3 bg-gray-300 rounded mr-2"></div>
            <span>Total Emails</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-green-500 rounded mr-2"></div>
            <span>Successful</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-red-500 rounded mr-2"></div>
            <span>Failed</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
            <span>Success Rate</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Stats -->
    <div v-if="!loading && data.length > 0" class="grid grid-cols-4 gap-4 pt-4 border-t">
      <div class="text-center">
        <div class="text-lg font-semibold text-gray-900">{{ formatNumber(totalEmails) }}</div>
        <div class="text-sm text-gray-500">Total Emails</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-green-600">{{ formatNumber(successfulEmails) }}</div>
        <div class="text-sm text-gray-500">Successful</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-red-600">{{ formatNumber(failedEmails) }}</div>
        <div class="text-sm text-gray-500">Failed</div>
      </div>
      <div class="text-center">
        <div class="text-lg font-semibold text-indigo-600">{{ averageSuccessRate }}%</div>
        <div class="text-sm text-gray-500">Avg Success Rate</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { BarChart3Icon } from 'lucide-vue-next'

interface TrendData {
  period: string
  total: number
  sent: number
  delivered: number
  failed: number
  bounced: number
  success_rate: number
}

interface Props {
  data: TrendData[]
  loading?: boolean
  selectedPeriod?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  selectedPeriod: 'day'
})

defineEmits<{
  'period-change': [period: string]
}>()

const periods = [
  { value: 'hour', label: 'Hourly' },
  { value: 'day', label: 'Daily' },
  { value: 'week', label: 'Weekly' },
  { value: 'month', label: 'Monthly' }
]

// Chart dimensions
const chartWidth = 800
const chartHeight = 200
const padding = 40

// Computed properties for chart calculations
const maxTotal = computed(() => {
  return Math.max(...props.data.map(d => d.total), 1)
})

const barWidth = computed(() => {
  if (props.data.length === 0) return 0
  return (chartWidth - padding * 2) / props.data.length * 0.8
})

const totalEmails = computed(() => {
  return props.data.reduce((sum, d) => sum + d.total, 0)
})

const successfulEmails = computed(() => {
  return props.data.reduce((sum, d) => sum + d.sent + d.delivered, 0)
})

const failedEmails = computed(() => {
  return props.data.reduce((sum, d) => sum + d.failed, 0)
})

const averageSuccessRate = computed(() => {
  if (props.data.length === 0) return 0
  const sum = props.data.reduce((sum, d) => sum + d.success_rate, 0)
  return Math.round(sum / props.data.length)
})

// Chart calculation functions
const getXPosition = (index: number): number => {
  return padding + (index * (chartWidth - padding * 2) / props.data.length)
}

const getYPosition = (value: number, max: number): number => {
  const ratio = value / max
  return chartHeight - padding - (ratio * (chartHeight - padding * 2))
}

const getBarHeight = (value: number, max: number): number => {
  const ratio = value / max
  return ratio * (chartHeight - padding * 2)
}

const getSuccessRateY = (successRate: number): number => {
  const ratio = successRate / 100
  return chartHeight - padding - (ratio * (chartHeight - padding * 2))
}

const getSuccessRatePoints = (): string => {
  return props.data.map((point, index) => {
    const x = getXPosition(index) + barWidth.value / 2
    const y = getSuccessRateY(point.success_rate)
    return `${x},${y}`
  }).join(' ')
}

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat().format(num)
}
</script>
