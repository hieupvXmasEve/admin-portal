import { computed, ref, watch } from 'vue'
import { useUrlSearchParams } from '@vueuse/core'
import { useApi } from '@/composables/useApiRequest'
import { route } from 'ziggy-js'
import type { ScheduleSession, DetailedScheduleSession, ScheduleFilters, FilterOptions } from '@/types/schedule'

/**
 * Admin Schedule Management Composable
 * Provides a clean API interface for schedule management
 * with business logic, caching, and error handling without using Pinia
 */
// Singleton store builder
function createScheduleStore() {
  const api = useApi()

  // URL Params sync
  const params = useUrlSearchParams('history')

  // State management
  const sessions = ref<ScheduleSession[]>([])
  const selectedSession = ref<DetailedScheduleSession | null>(null)
  const filterOptions = ref<FilterOptions | null>(null)

  const filters = ref<ScheduleFilters>({
    semester_id: params.semester_id ? Number(params.semester_id) : undefined,
    lecturer_id: params.lecturer_id ? Number(params.lecturer_id) : undefined,
    room_id: params.room_id ? Number(params.room_id) : undefined,
    unit_type: params.unit_type ? String(params.unit_type) : undefined,
    date_range: undefined,
  })

  // Watch for filter changes to update URL
  watch(filters, (newVal) => {
    // Sync to URL params
    if (newVal.semester_id) params.semester_id = String(newVal.semester_id)
    else delete params.semester_id

    if (newVal.lecturer_id) params.lecturer_id = String(newVal.lecturer_id)
    else delete params.lecturer_id

    if (newVal.room_id) params.room_id = String(newVal.room_id)
    else delete params.room_id

    if (newVal.unit_type) params.unit_type = newVal.unit_type
    else delete params.unit_type
  }, { deep: true })

  // Loading states
  const loading = ref(false)
  const sessionLoading = ref(false)
  const filterOptionsLoading = ref(false)
  const updateLoading = ref(false)
  const weekTransitionLoading = ref(false)

  // Error states
  const error = ref<string | null>(null)
  const sessionError = ref<string | null>(null)
  const filterOptionsError = ref<string | null>(null)
  const updateError = ref<string | null>(null)

  // Cache management
  const lastFetchTime = ref<number>(0)
  const cacheTimeout = 5 * 60 * 1000 // 5 minutes

  // Computed properties
  const isLoading = computed(() => loading.value)
  const hasError = computed(() => error.value !== null)
  const sessionsCount = computed(() => sessions.value.length)
  const isSessionLoading = computed(() => sessionLoading.value)
  const isUpdateLoading = computed(() => updateLoading.value)
  const isFilterOptionsLoading = computed(() => filterOptionsLoading.value)
  const isWeekTransitionLoading = computed(() => weekTransitionLoading.value)

  const hasErrors = computed(() =>
    !!(error.value || sessionError.value || filterOptionsError.value || updateError.value)
  )

  const errors = computed(() => ({
    sessions: error.value,
    session: sessionError.value,
    filterOptions: filterOptionsError.value,
    update: updateError.value,
  }))

  // Group sessions by date for grid display
  const sessionsByDate = computed(() => {
    const grouped: Record<string, ScheduleSession[]> = {}

    sessions.value.forEach(session => {
      const date = session.date
      if (!grouped[date]) {
        grouped[date] = []
      }
      grouped[date].push(session)
    })

    return grouped
  })

  // Get sessions for a specific date
  const getSessionsForDate = computed(() => {
    return (date: string) => sessionsByDate.value[date] || []
  })

  // Utility methods for cache and state management
  const isCacheValid = () => {
    return Date.now() - lastFetchTime.value < cacheTimeout
  }

  // Smart merge function to update sessions without full re-render
  const mergeSessions = (newSessions: ScheduleSession[], dateRange?: { start: string; end: string }) => {
    // Always replace sessions with new ones for the selected week/date range
    sessions.value = newSessions
  }

  const clearErrors = () => {
    error.value = null
    sessionError.value = null
    filterOptionsError.value = null
    updateError.value = null
  }

  const clearError = () => {
    error.value = null
  }

  // Core API methods
  const fetchSessions = async (customFilters?: Partial<ScheduleFilters>, options: {
    force?: boolean
    signal?: AbortSignal
  } = {}) => {
    const { force = false, signal } = options

    // Skip if already loading and not forced
    if (loading.value && !force) {
      return sessions.value
    }

    // Use cached data if available and not forced
    if (!force && sessions.value.length > 0 && isCacheValid()) {
      return sessions.value
    }

    // Set appropriate loading state
    if (customFilters?.date_range) {
      weekTransitionLoading.value = true
    } else {
      loading.value = true
    }
    error.value = null

    try {
      const queryFilters = { ...filters.value, ...customFilters }
      
      // Build query parameters
      const queryParams: Record<string, string> = {}
      Object.entries(queryFilters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          if (key === 'date_range' && typeof value === 'object' && value !== null) {
            if (value.start) queryParams[`${key}[start]`] = value.start
            if (value.end) queryParams[`${key}[end]`] = value.end
          } else {
            queryParams[key] = String(value)
          }
        }
      })

      const response = await api.get<ScheduleSession[]>(route('api.admin.schedules.index'), queryParams, { signal })

      if (response.data?.value?.success && response.data?.value?.data) {
        // Extract date range from filters for smart merging
        const dateRange = queryFilters.date_range && typeof queryFilters.date_range === 'object' 
          ? queryFilters.date_range as { start: string; end: string }
          : undefined
        
        // Use smart merge to avoid full re-render
        mergeSessions(response.data.value.data, dateRange)
        lastFetchTime.value = Date.now()
      } else {
        error.value = response.data?.value?.error || 'Failed to fetch schedule data'
        // Only clear sessions if no date range (full reload)
        if (!queryFilters.date_range) {
          sessions.value = []
        }
      }

      return sessions.value
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      sessions.value = []
      console.error('Failed to fetch sessions:', err)
      throw err
    } finally {
      loading.value = false
      weekTransitionLoading.value = false
    }
  }

  const fetchFilterOptions = async (options: { force?: boolean } = {}) => {
    const { force = false } = options

    if (filterOptionsLoading.value && !force) {
      return filterOptions.value
    }

    // Use cached data if available and not forced
    if (!force && filterOptions.value && isCacheValid()) {
      return filterOptions.value
    }

    filterOptionsLoading.value = true
    filterOptionsError.value = null

    try {
      const response = await api.get<FilterOptions>(route('api.admin.schedules.filter-options'))

      if (response.data?.value?.success && response.data?.value?.data) {
        filterOptions.value = response.data.value.data
      } else {
        filterOptionsError.value = response.data?.value?.error || 'Failed to fetch filter options'
      }

      return filterOptions.value
    } catch (err) {
      filterOptionsError.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching filter options:', err)
      throw err
    } finally {
      filterOptionsLoading.value = false
    }
  }

  const fetchSessionDetails = async (sessionId: number, options: { force?: boolean } = {}) => {
    const { force = false } = options

    if (sessionLoading.value && !force) {
      return selectedSession.value
    }

    sessionLoading.value = true
    sessionError.value = null

    try {
      const response = await api.get<DetailedScheduleSession>(route('api.admin.schedules.show', { session: sessionId }))

      if (response.data?.value) {
        // Handle both direct response and API response wrapper
        if ('data' in response.data.value) {
          // API response with data wrapper: {"data": DetailedScheduleSession}
          selectedSession.value = (response.data.value as any).data
        } else if ('success' in response.data.value && response.data.value.success !== false) {
          selectedSession.value = (response.data.value as any).data || response.data.value
        } else if (!('success' in response.data.value)) {
          // Direct session data without wrapper
          selectedSession.value = response.data.value
        } else {
          sessionError.value = (response.data.value as any).error || 'Failed to fetch session details'
        }
      } else {
        sessionError.value = 'Failed to fetch session details'
      }

      return selectedSession.value
    } catch (err) {
      sessionError.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Failed to fetch session details:', err)
      throw err
    } finally {
      sessionLoading.value = false
    }
  }

  const updateSession = async (sessionId: number, updateData: {
    session_date: string
    start_time: string
    end_time: string
    room_id: number
  }) => {
    if (updateLoading.value) {
      throw new Error('Update operation already in progress')
    }

    updateLoading.value = true
    updateError.value = null

    try {
      const response = await api.put<ScheduleSession>(route('api.admin.schedules.update', { session: sessionId }), updateData)

      if (response.data?.value?.success) {
        const updatedSession = (response.data.value as any).data
        // Keep selected session for detail views; let caller decide to refetch list
        selectedSession.value = updatedSession as any
        return updatedSession as any
      } else {
        const errorMessage = response.data?.value?.message || response.data?.value?.error || 'Failed to update session'
        updateError.value = errorMessage
        throw new Error(errorMessage)
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred'
      updateError.value = errorMessage
      console.error('Failed to update session:', err)
      throw err
    } finally {
      updateLoading.value = false
    }
  }

  // Filter management
  const setFilters = (newFilters: Partial<ScheduleFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const clearFilters = () => {
    filters.value = {
      semester_id: undefined,
      lecturer_id: undefined,
      room_id: undefined,
      unit_type: undefined,
      date_range: undefined,
    }
  }

  const applyFilters = async (newFilters: Partial<ScheduleFilters>) => {
    setFilters(newFilters)
    return fetchSessions(newFilters, { force: true })
  }

  // Selection management
  const setSelectedSession = (session: ScheduleSession | null) => {
    selectedSession.value = session
    sessionError.value = null
  }

  // Utility methods
  const refreshData = async (options: { clearCache?: boolean } = {}) => {
    const { clearCache = true } = options

    if (clearCache) {
      lastFetchTime.value = 0
    }

    return fetchSessions(filters.value, { force: true })
  }

  const clearAllErrors = () => {
    clearErrors()
  }

  const clearAllData = () => {
    sessions.value = []
    selectedSession.value = null
    filterOptions.value = null
    lastFetchTime.value = 0
  }

  // Validation helpers
  const getSessionById = (id: number): ScheduleSession | undefined => {
    return sessions.value.find(s => s.id === id)
  }

  const hasSessionConflicts = (newSession: Partial<ScheduleSession>): boolean => {
    // Basic conflict detection - check if same room, date, and overlapping times
    return sessions.value.some(session => {
      if (session.id === newSession.id) return false // Don't conflict with self
      
      return (
        session.room?.id === newSession.room?.id &&
        session.date === newSession.date &&
        newSession.startTime &&
        newSession.endTime &&
        // Simple time overlap check
        ((newSession.startTime >= session.startTime && newSession.startTime < session.endTime) ||
         (newSession.endTime > session.startTime && newSession.endTime <= session.endTime) ||
         (newSession.startTime <= session.startTime && newSession.endTime >= session.endTime))
      )
    })
  }

  return {
    // State (reactive refs)
    sessions,
    selectedSession,
    filterOptions,
    filters,

    // Loading states
    isLoading,
    isSessionLoading,
    isUpdateLoading,
    isFilterOptionsLoading,
    isWeekTransitionLoading,
    loading, // Direct access to loading states
    sessionLoading,
    updateLoading,
    filterOptionsLoading,
    weekTransitionLoading,

    // Error states
    hasError,
    hasErrors,
    errors,
    error, // Direct access to error states
    sessionError,
    updateError,
    filterOptionsError,

    // Computed properties
    sessionsCount,
    sessionsByDate,
    getSessionsForDate,

    // Core API methods
    fetchSessions,
    fetchFilterOptions,
    fetchSessionDetails,
    updateSession,

    // Filter management
    setFilters,
    clearFilters,
    applyFilters,

    // Selection management
    setSelectedSession,

    // Utility methods
    refreshData,
    clearAllErrors,
    clearAllData,
    clearError,
    isCacheValid,

    // Validation helpers
    getSessionById,
    hasSessionConflicts,
  }
}

// Singleton instance holder
let _scheduleStore: ReturnType<typeof createScheduleStore> | null = null

export function useAdminSchedule() {
  if (!_scheduleStore) {
    _scheduleStore = createScheduleStore()
  }
  return _scheduleStore
}
