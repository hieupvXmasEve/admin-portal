import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

interface EmailStatistics {
  total: number
  sent: number
  failed: number
  pending: number
  success_rate: number
}

interface QueueStatus {
  queue_sizes: Record<string, number | string>
  failed_jobs_count: number
  recent_statistics: any
  active_batches: any[]
  system_health: {
    status: string
    issues: string[]
  }
  last_updated: string
}

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

interface SystemAlert {
  type: 'error' | 'warning' | 'info'
  title: string
  message: string
  action: string
}

interface DeliveryTrend {
  period: string
  total: number
  sent: number
  delivered: number
  failed: number
  bounced: number
  success_rate: number
}

export function useEmailMonitoring(initialData?: any) {
  // Reactive state
  const overview = ref<{
    last_24_hours: EmailStatistics
    last_week: EmailStatistics
  }>(initialData?.overview || {
    last_24_hours: { total: 0, sent: 0, failed: 0, pending: 0, success_rate: 0 },
    last_week: { total: 0, sent: 0, failed: 0, pending: 0, success_rate: 0 }
  })

  const queueStatus = ref<QueueStatus>(initialData?.queue_status || {
    queue_sizes: {},
    failed_jobs_count: 0,
    recent_statistics: {},
    active_batches: [],
    system_health: { status: 'unknown', issues: [] },
    last_updated: new Date().toISOString()
  })

  const recentFailures = ref<EmailFailure[]>(initialData?.recent_failures || [])
  const systemAlerts = ref<SystemAlert[]>(initialData?.system_alerts || [])
  const deliveryTrends = ref<DeliveryTrend[]>([])
  const activeBatches = ref<any[]>([])
  const systemHealthStatus = ref<string>('unknown')

  // Loading states
  const isRefreshing = ref(false)
  const isLoadingTrends = ref(false)
  const isLoadingHealth = ref(false)

  // Error state
  const error = ref<string | null>(null)

  /**
   * Refresh all dashboard data
   */
  const refreshData = async () => {
    if (isRefreshing.value) return

    isRefreshing.value = true
    error.value = null

    try {
      const [statisticsResponse, queueResponse] = await Promise.all([
        fetch('/admin/email-monitoring/statistics'),
        fetch('/admin/email-monitoring/queue-status')
      ])

      if (!statisticsResponse.ok || !queueResponse.ok) {
        throw new Error('Failed to fetch monitoring data')
      }

      const statisticsData = await statisticsResponse.json()
      const queueData = await queueResponse.json()

      // Update overview statistics
      if (statisticsData.success) {
        overview.value = {
          last_24_hours: statisticsData.data,
          last_week: overview.value.last_week // Keep existing week data or fetch separately
        }
      }

      // Update queue status
      if (queueData.success) {
        queueStatus.value = queueData.data
        activeBatches.value = queueData.data.active_batches || []
        systemHealthStatus.value = queueData.data.system_health?.status || 'unknown'
      }

      // Refresh recent failures
      await refreshRecentFailures()

      // Update system alerts based on current data
      updateSystemAlerts()

    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Failed to refresh monitoring data:', err)
    } finally {
      isRefreshing.value = false
    }
  }

  /**
   * Refresh queue status only
   */
  const refreshQueueStatus = async () => {
    try {
      const response = await fetch('/admin/email-monitoring/queue-status')

      if (!response.ok) {
        throw new Error('Failed to fetch queue status')
      }

      const data = await response.json()

      if (data.success) {
        queueStatus.value = data.data
        activeBatches.value = data.data.active_batches || []
        systemHealthStatus.value = data.data.system_health?.status || 'unknown'
      }
    } catch (err) {
      console.error('Failed to refresh queue status:', err)
    }
  }

  /**
   * Load delivery trends for charting
   */
  const loadDeliveryTrends = async (period: string = 'day', startDate?: string, endDate?: string) => {
    isLoadingTrends.value = true

    try {
      const params = new URLSearchParams({ period })
      if (startDate) params.append('start_date', startDate)
      if (endDate) params.append('end_date', endDate)

      const response = await fetch(`/admin/email-monitoring/delivery-trends?${params}`)

      if (!response.ok) {
        throw new Error('Failed to fetch delivery trends')
      }

      const data = await response.json()

      if (data.success) {
        deliveryTrends.value = data.data
      }
    } catch (err) {
      console.error('Failed to load delivery trends:', err)
    } finally {
      isLoadingTrends.value = false
    }
  }

  /**
   * Test system health
   */
  const testSystemHealth = async () => {
    isLoadingHealth.value = true

    try {
      const response = await fetch('/admin/email-monitoring/test-health', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to test system health')
      }

      const data = await response.json()

      if (data.success) {
        systemHealthStatus.value = data.data.overall_status
        // You could also update a detailed health report here
        return data.data
      }
    } catch (err) {
      console.error('Failed to test system health:', err)
      throw err
    } finally {
      isLoadingHealth.value = false
    }
  }

  /**
   * Cancel a bulk email batch
   */
  const cancelBatch = async (batchId: string) => {
    try {
      const response = await fetch(`/admin/email-monitoring/batches/${batchId}/cancel`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to cancel batch')
      }

      const data = await response.json()

      if (data.success) {
        // Refresh active batches
        await refreshQueueStatus()
        return data.data
      } else {
        throw new Error(data.message || 'Failed to cancel batch')
      }
    } catch (err) {
      console.error('Failed to cancel batch:', err)
      throw err
    }
  }

  /**
   * Retry a failed email
   */
  const retryEmail = async (emailId: number) => {
    try {
      const response = await fetch(`/admin/email-monitoring/emails/${emailId}/retry`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to retry email')
      }

      const data = await response.json()

      if (data.success) {
        // Refresh recent failures
        await refreshRecentFailures()
        return data.data
      } else {
        throw new Error(data.message || 'Failed to retry email')
      }
    } catch (err) {
      console.error('Failed to retry email:', err)
      throw err
    }
  }

  /**
   * Get email logs with filtering
   */
  const getEmailLogs = async (filters: Record<string, any> = {}) => {
    try {
      const params = new URLSearchParams()

      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params.append(key, String(value))
        }
      })

      const response = await fetch(`/admin/email-monitoring/logs?${params}`)

      if (!response.ok) {
        throw new Error('Failed to fetch email logs')
      }

      const data = await response.json()

      if (data.success) {
        return {
          logs: data.data,
          meta: data.meta
        }
      } else {
        throw new Error(data.message || 'Failed to fetch email logs')
      }
    } catch (err) {
      console.error('Failed to get email logs:', err)
      throw err
    }
  }

  /**
   * Get batch progress
   */
  const getBatchProgress = async (batchId: string) => {
    try {
      const response = await fetch(`/admin/email-monitoring/batches/${batchId}/progress`)

      if (!response.ok) {
        throw new Error('Failed to fetch batch progress')
      }

      const data = await response.json()

      if (data.success) {
        return data.data
      } else {
        throw new Error(data.message || 'Failed to fetch batch progress')
      }
    } catch (err) {
      console.error('Failed to get batch progress:', err)
      throw err
    }
  }

  /**
   * Export statistics
   */
  const exportStatistics = async (format: string, filters: Record<string, any> = {}) => {
    try {
      const params = new URLSearchParams({ format })

      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params.append(key, String(value))
        }
      })

      const response = await fetch(`/admin/email-monitoring/export?${params}`)

      if (!response.ok) {
        throw new Error('Failed to export statistics')
      }

      // Handle file download
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `email-statistics-${new Date().toISOString().split('T')[0]}.${format}`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)

    } catch (err) {
      console.error('Failed to export statistics:', err)
      throw err
    }
  }

  /**
   * Refresh recent failures
   */
  const refreshRecentFailures = async () => {
    try {
      const response = await fetch('/admin/email-monitoring/logs?status=failed&per_page=10')

      if (!response.ok) {
        throw new Error('Failed to fetch recent failures')
      }

      const data = await response.json()

      if (data.success) {
        recentFailures.value = data.data
      }
    } catch (err) {
      console.error('Failed to refresh recent failures:', err)
    }
  }

  /**
   * Update system alerts based on current data
   */
  const updateSystemAlerts = () => {
    const alerts: SystemAlert[] = []

    // Check success rate
    const successRate = overview.value.last_24_hours?.success_rate || 0
    if (successRate < 90 && overview.value.last_24_hours?.total > 10) {
      alerts.push({
        type: 'warning',
        title: 'Low Success Rate',
        message: `Email success rate in the last 24 hours is ${successRate}%`,
        action: 'Check SMTP configuration and recent error logs'
      })
    }

    // Check queue backlog
    const queueSizes = queueStatus.value.queue_sizes || {}
    Object.entries(queueSizes).forEach(([queue, size]) => {
      if (typeof size === 'number' && size > 1000) {
        alerts.push({
          type: 'warning',
          title: 'Queue Backlog',
          message: `Queue '${queue}' has ${size} pending jobs`,
          action: 'Consider scaling queue workers or investigating processing delays'
        })
      }
    })

    // Check failed jobs
    const failedJobsCount = queueStatus.value.failed_jobs_count || 0
    if (failedJobsCount > 100) {
      alerts.push({
        type: 'error',
        title: 'High Failed Jobs Count',
        message: `There are ${failedJobsCount} failed jobs`,
        action: 'Review failed jobs and retry or clear them'
      })
    }

    systemAlerts.value = alerts
  }

  return {
    // State
    overview,
    queueStatus,
    recentFailures,
    systemAlerts,
    deliveryTrends,
    activeBatches,
    systemHealthStatus,
    isRefreshing,
    isLoadingTrends,
    isLoadingHealth,
    error,

    // Methods
    refreshData,
    refreshQueueStatus,
    loadDeliveryTrends,
    testSystemHealth,
    cancelBatch,
    retryEmail,
    getEmailLogs,
    getBatchProgress,
    exportStatistics
  }
}
