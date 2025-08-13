import { computed, ref } from 'vue'
import { format, startOfWeek, endOfWeek, addDays, isWithinInterval, parseISO } from 'date-fns'
import type { ScheduleSession, TimeSlot, WeeklyGridData } from '@/types/schedule'

export function useScheduleManagement() {
  const selectedWeek = ref(new Date())

  // Time slots for the grid (8:00 AM to 8:00 PM)
  const timeSlots = computed<TimeSlot[]>(() => {
    const slots: TimeSlot[] = []
    for (let hour = 7; hour <= 20; hour++) {
      slots.push({
        hour,
        displayTime: format(new Date().setHours(hour, 0, 0, 0), 'HH:mm'),
      })
    }
    return slots
  })

  // Get the current week's date range
  const currentWeekRange = computed(() => {
    const start = startOfWeek(selectedWeek.value, { weekStartsOn: 1 })
    const end = endOfWeek(selectedWeek.value, { weekStartsOn: 1 })

    return {
      start,
      end,
      formatted: {
        start: format(start, 'yyyy-MM-dd'),
        end: format(end, 'yyyy-MM-dd'),
      },
    }
  })

  // Get all days of the current week
  const weekDays = computed(() => {
    const startDate = currentWeekRange.value.start
    const days = []

    for (let i = 0; i < 7; i++) {
      const date = addDays(startDate, i)
      days.push({
        date,
        dateString: format(date, 'yyyy-MM-dd'),
        displayDate: format(date, 'EEE dd'),
        fullDate: format(date, 'EEEE, MMMM dd'),
        dayName: format(date, 'EEEE'),
        isToday: format(date, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd'),
      })
    }

    return days
  })

  // Filter sessions for the current week
  const filterSessionsForWeek = (sessions: ScheduleSession[]) => {
    const { start, end } = currentWeekRange.value

    return sessions.filter(session => {
      try {
        const sessionDate = parseISO(session.date)
        return isWithinInterval(sessionDate, { start, end })
      } catch {
        return false
      }
    })
  }

  // Group sessions by date
  const groupSessionsByDate = (sessions: ScheduleSession[]): WeeklyGridData => {
    const grouped: WeeklyGridData = {}

    sessions.forEach(session => {
      const date = session.date
      if (!grouped[date]) {
        grouped[date] = []
      }
      grouped[date].push(session)
    })

    return grouped
  }

  // Get sessions for a specific date and time slot
  const getSessionsForSlot = (
    sessionsByDate: WeeklyGridData,
    dateString: string,
    hour: number
  ): ScheduleSession[] => {
    const sessions = sessionsByDate[dateString] || []

    return sessions.filter(session => {
      try {
        const sessionStart = parseInt(session.startTime.split(':')[0])
        const sessionEnd = parseInt(session.endTime.split(':')[0])

        // Check if the time slot overlaps with the session
        return sessionStart <= hour && sessionEnd > hour
      } catch {
        return false
      }
    })
  }

  // Check if multiple sessions overlap in the same time slot
  const hasOverlappingSessions = (
    sessionsByDate: WeeklyGridData,
    dateString: string,
    hour: number
  ): boolean => {
    const count = getOverlappingSessionsCount(sessionsByDate, dateString, hour)
    return count > 1
  }

  // Get overlapping sessions count for a time slot
  const getOverlappingSessionsCount = (
    sessionsByDate: WeeklyGridData,
    dateString: string,
    hour: number
  ): number => {
    const sessions = getSessionsForSlot(sessionsByDate, dateString, hour)
    // Count unique sessions (by ID) to avoid counting spanning sessions multiple times
    const uniqueSessions = sessions.reduce((unique, session) => {
      if (!unique.find(s => s.id === session.id)) {
        unique.push(session)
      }
      return unique
    }, [] as typeof sessions)

    return uniqueSessions.length
  }

  // Check if we should show overlap indicator (more than 2 sessions)
  const shouldShowOverlapIndicator = (
    sessionsByDate: WeeklyGridData,
    dateString: string,
    hour: number
  ): boolean => {
    const count = getOverlappingSessionsCount(sessionsByDate, dateString, hour)
    return count > 2
  }

  // Calculate how many time slots a session spans
  const getSessionSpan = (session: ScheduleSession): number => {
    try {
      const [startHour, startMinute] = session.startTime.split(':').map(Number)
      const [endHour, endMinute] = session.endTime.split(':').map(Number)

      // Calculate span in hours (minimum 1 hour)
      let span = endHour - startHour
      if (endMinute > startMinute) span += 0.5
      if (endMinute < startMinute) span -= 0.5

      return Math.max(1, span)
    } catch {
      return 1
    }
  }

  // Check if a session starts at a specific hour
  const sessionStartsAtSlot = (session: ScheduleSession, hour: number): boolean => {
    try {
      const sessionStart = parseInt(session.startTime.split(':')[0])
      return sessionStart === hour
    } catch {
      return false
    }
  }

  // Navigation functions
  const navigateWeek = (direction: 'prev' | 'next') => {
    const newDate = new Date(selectedWeek.value)
    const daysToAdd = direction === 'next' ? 7 : -7
    newDate.setDate(newDate.getDate() + daysToAdd)
    selectedWeek.value = newDate
  }

  const goToToday = () => {
    selectedWeek.value = new Date()
  }

  const setWeek = (date: Date) => {
    selectedWeek.value = new Date(date)
  }

  // Format utilities
  const formatSessionTime = (startTime: string, endTime: string): string => {
    try {
      return `${startTime} - ${endTime}`
    } catch {
      return 'Invalid time'
    }
  }

  const formatSessionDuration = (startTime: string, endTime: string): string => {
    try {
      const [startHour, startMinute] = startTime.split(':').map(Number)
      const [endHour, endMinute] = endTime.split(':').map(Number)

      const durationMinutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute)
      const hours = Math.floor(durationMinutes / 60)
      const minutes = durationMinutes % 60

      if (hours > 0 && minutes > 0) {
        return `${hours}h ${minutes}m`
      } else if (hours > 0) {
        return `${hours}h`
      } else {
        return `${minutes}m`
      }
    } catch {
      return 'Unknown duration'
    }
  }

  // Session status utilities
  const getSessionStatusColor = (status: string) => {
    switch (status) {
      case 'scheduled':
        return 'blue'
      case 'in_progress':
        return 'yellow'
      case 'completed':
        return 'green'
      case 'cancelled':
        return 'red'
      default:
        return 'gray'
    }
  }

  const getSessionStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'scheduled':
        return 'default'
      case 'in_progress':
        return 'warning'
      case 'completed':
        return 'success'
      case 'cancelled':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  return {
    // State
    selectedWeek,

    // Computed
    timeSlots,
    currentWeekRange,
    weekDays,

    // Methods
    filterSessionsForWeek,
    groupSessionsByDate,
    getSessionsForSlot,
    hasOverlappingSessions,
    getOverlappingSessionsCount,
    shouldShowOverlapIndicator,
    getSessionSpan,
    sessionStartsAtSlot,
    navigateWeek,
    goToToday,
    setWeek,
    formatSessionTime,
    formatSessionDuration,
    getSessionStatusColor,
    getSessionStatusBadgeVariant,
  }
}
