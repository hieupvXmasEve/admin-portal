<script setup lang="ts">
import { computed, onMounted, watch, ref } from 'vue'
import { format, startOfWeek, addDays, isToday, isSameDay, parseISO } from 'date-fns'
import { useDebounceFn } from '@vueuse/core'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight, Calendar, Clock, MapPin, User, Users } from 'lucide-vue-next'
import OverlappingSessionsModal from './OverlappingSessionsModal.vue'
import { useAdminSchedule } from '@/composables/useAdminSchedule'
import { useScheduleManagement } from '@/composables/useScheduleManagement'
import type { ScheduleSession, TimeSlot } from '@/types/schedule'

const scheduleApi = useAdminSchedule()
const scheduleUtils = useScheduleManagement()

// Props
interface Props {
  selectedWeek?: Date
}

const props = withDefaults(defineProps<Props>(), {
  selectedWeek: () => new Date(),
})

// Emits
const emit = defineEmits<{
  sessionClick: [session: ScheduleSession]
  weekChange: [date: Date]
  overlappingSessionsClick: [sessions: ScheduleSession[], date: string, timeSlot: string]
}>()

// Use schedule management utilities
const timeSlots = scheduleUtils.timeSlots
const weekDays = scheduleUtils.weekDays

// Set the selected week in schedule utils
watch(() => props.selectedWeek, (newWeek) => {
  if (newWeek) {
    scheduleUtils.setWeek(newWeek)
  }
}, { immediate: true })

// Use utility functions from schedule management composable
const getSessionsForSlot = (dateString: string, hour: number) => {
  return scheduleUtils.getSessionsForSlot(scheduleApi.sessionsByDate.value, dateString, hour)
}

// Get sessions that actually start at this time slot (for width calculation)
const getSessionsStartingAtSlot = (dateString: string, hour: number) => {
  const allSessions = getSessionsForSlot(dateString, hour)
  return allSessions.filter(session => scheduleUtils.sessionStartsAtSlot(session, hour))
}

const getSessionSpan = scheduleUtils.getSessionSpan
const sessionStartsAtSlot = scheduleUtils.sessionStartsAtSlot

// Local state for overlapping sessions modal
const overlappingModalOpen = ref(false)
const overlappingSessions = ref<ScheduleSession[]>([])
const overlappingTimeSlot = ref('')
const overlappingDate = ref('')

// Get session badge color based on status
const getSessionBadgeColor = (status: string) => {
  const variant = scheduleUtils.getSessionStatusBadgeVariant(status)
  // Map unsupported badge variants to supported ones
  if (variant === 'warning' || variant === 'success') {
    return variant === 'warning' ? 'secondary' : 'outline'
  }
  return variant
}

// Navigation functions
const navigatePrevWeek = () => {
  scheduleUtils.navigateWeek('prev')
  emit('weekChange', scheduleUtils.selectedWeek.value)
}

const navigateNextWeek = () => {
  scheduleUtils.navigateWeek('next')
  emit('weekChange', scheduleUtils.selectedWeek.value)
}

const goToToday = () => {
  scheduleUtils.goToToday()
  emit('weekChange', scheduleUtils.selectedWeek.value)
}

// Session click handler
const handleSessionClick = (session: ScheduleSession) => {
  emit('sessionClick', session)
}

// Handle overlapping sessions click
const handleOverlappingSessionsClick = (sessions: ScheduleSession[], date: string, timeSlot: string) => {
  overlappingSessions.value = sessions
  overlappingDate.value = date
  overlappingTimeSlot.value = timeSlot
  overlappingModalOpen.value = true
}

// Handle edit session from modal
const handleEditSessionFromModal = (session: ScheduleSession) => {
  emit('sessionClick', session)
}

// Current abort controller for canceling requests
let currentAbortController: AbortController | null = null

// Create debounced function for fetching sessions
const debouncedFetchSessions = useDebounceFn(async (newWeek: Date) => {
  // Cancel any pending API call
  if (currentAbortController) {
    currentAbortController.abort()
    currentAbortController = null
  }

  const startDate = startOfWeek(newWeek, { weekStartsOn: 1 })
  const endDate = addDays(startDate, 6)

  // Create new abort controller for this request
  currentAbortController = new AbortController()

  try {
    await scheduleApi.fetchSessions({
      date_range: {
        start: format(startDate, 'yyyy-MM-dd'),
        end: format(endDate, 'yyyy-MM-dd'),
      },
    }, { force: true, signal: currentAbortController.signal })
  } catch (error) {
    // Only log non-abort errors
    if (error instanceof Error && error.name !== 'AbortError') {
      console.error('Failed to fetch sessions:', error)
    }
  } finally {
    currentAbortController = null
  }
}, 300) // 300ms debounce delay

// Watch for week changes and trigger debounced fetch
watch(
  () => scheduleUtils.selectedWeek.value,
  debouncedFetchSessions,
  { immediate: true }
)

// Initialize
onMounted(async () => {
  if (props.selectedWeek) {
    scheduleUtils.setWeek(props.selectedWeek)
  }

  // Load filter options if not already loaded
  if (!scheduleApi.filterOptions.value) {
    await scheduleApi.fetchFilterOptions()
  }
})
</script>

<template>
  <div class="w-full space-y-4">
    <!-- Week Navigation Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <h2 class="text-2xl font-bold min-w-48">
          {{ format(scheduleUtils.selectedWeek.value, 'MMMM yyyy') }}
        </h2>
        <div class="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            @click="navigatePrevWeek"
            :disabled="scheduleApi.isWeekTransitionLoading.value"
          >
            <ChevronLeft class="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="sm"
            @click="navigateNextWeek"
            :disabled="scheduleApi.isWeekTransitionLoading.value"
          >
            <ChevronRight class="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="sm"
            @click="goToToday"
            :disabled="scheduleApi.isWeekTransitionLoading.value"
          >
            <Calendar class="h-4 w-4 mr-2" />
            Today
          </Button>
        </div>
      </div>

      <div class="flex items-center space-x-2 text-sm text-muted-foreground">
        <span>{{ scheduleApi.sessionsCount.value }} sessions this week</span>
        <div v-if="scheduleApi.isWeekTransitionLoading.value" class="flex items-center space-x-1">
          <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary"></div>
          <span class="text-xs">Loading...</span>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="scheduleApi.isLoading.value" class="flex items-center justify-center py-8">
      <div class="flex items-center space-x-2">
        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
        <span class="text-muted-foreground">Loading schedule...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="scheduleApi.hasError.value" class="text-center py-8">
      <p class="text-destructive">{{ scheduleApi.error.value }}</p>
      <Button
        variant="outline"
        size="sm"
        class="mt-2"
        @click="scheduleApi.clearError"
      >
        Dismiss
      </Button>
    </div>

    <!-- Schedule Grid -->
    <div v-else class="border rounded-lg overflow-hidden transition-opacity duration-200"
         :class="{ 'opacity-75': scheduleApi.isWeekTransitionLoading.value }">
      <!-- Header with days -->
      <div class="grid grid-cols-8 bg-muted/50">
        <div class="p-3 border-r font-medium">Time</div>
        <div
          v-for="day in weekDays"
          :key="day.dateString"
          class="p-3 border-r last:border-r-0 text-center"
          :class="{ 'bg-primary/10 font-semibold': day.isToday }"
        >
          <div class="font-medium">{{ day.displayDate }}</div>
          <div class="text-xs text-muted-foreground mt-1">
            {{ day.fullDate }}
          </div>
        </div>
      </div>

      <!-- Time slots grid -->
      <div class="relative">
        <div
          v-for="(timeSlot, slotIndex) in timeSlots"
          :key="timeSlot.hour"
          class="grid grid-cols-8 border-b last:border-b-0"
          :class="{ 'bg-muted/20': slotIndex % 2 === 1 }"
        >
          <!-- Time column -->
          <div class="p-3 border-r font-mono text-sm text-muted-foreground">
            {{ timeSlot.displayTime }}
          </div>

          <!-- Day columns -->
          <div
            v-for="day in weekDays"
            :key="`${day.dateString}-${timeSlot.hour}`"
            class="relative border-r last:border-r-0 min-h-[60px]"
          >
            <!-- Check for overlapping sessions -->
            <template v-if="scheduleUtils.shouldShowOverlapIndicator(scheduleApi.sessionsByDate.value, day.dateString, timeSlot.hour)">
              <!-- Overlap indicator for multiple sessions -->
              <div
                class="absolute inset-1 flex items-center justify-center bg-primary/10 border-2 border-dashed border-primary/30 rounded-lg cursor-pointer hover:bg-primary/20 transition-colors z-20"
                @click="handleOverlappingSessionsClick(
                  getSessionsForSlot(day.dateString, timeSlot.hour),
                  day.dateString,
                  timeSlot.displayTime
                )"
              >
                <div class="text-center">
                  <Users class="h-6 w-6 mx-auto text-primary mb-1" />
                  <div class="text-sm font-semibold text-primary">
                    {{ scheduleUtils.getOverlappingSessionsCount(scheduleApi.sessionsByDate.value, day.dateString, timeSlot.hour) }} sessions
                  </div>
                  <div class="text-xs text-muted-foreground">
                    Click to view
                  </div>
                </div>
              </div>
            </template>

            <template v-else>
              <!-- Regular sessions display (1-2 sessions) -->
              <div
                v-for="(session, sessionIndex) in getSessionsStartingAtSlot(day.dateString, timeSlot.hour)"
                :key="session.id"
                class="absolute top-1 z-10"
                :class="{
                  'inset-x-1': getSessionsStartingAtSlot(day.dateString, timeSlot.hour).length === 1,
                  'left-1 right-1/2 mr-0.5': getSessionsStartingAtSlot(day.dateString, timeSlot.hour).length === 2 && sessionIndex === 0,
                  'left-1/2 right-1 ml-0.5': getSessionsStartingAtSlot(day.dateString, timeSlot.hour).length === 2 && sessionIndex === 1,
                }"
                :style="{
                  height: `${getSessionSpan(session) * 60 - 8}px`,
                  minHeight: '52px'
                }"
              >
                <Card
                  class="h-full cursor-pointer transition-all hover:shadow-md hover:scale-[1.02] border-l-4"
                  :class="{
                    'border-l-blue-500 bg-blue-50 hover:bg-blue-100': session.status === 'scheduled',
                    'border-l-yellow-500 bg-yellow-50 hover:bg-yellow-100': session.status === 'in_progress',
                    'border-l-green-500 bg-green-50 hover:bg-green-100': session.status === 'completed',
                    'border-l-red-500 bg-red-50 hover:bg-red-100': session.status === 'cancelled',
                  }"
                  @click="handleSessionClick(session)"
                >
                  <CardContent class="p-2 h-full">
                    <div class="space-y-1">
                      <!-- Unit code and section -->
                      <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm truncate">
                          {{ session.unitCode }}-{{ session.section }}
                        </div>
<!--                        <Badge-->
<!--                          :variant="getSessionBadgeColor(session.status)"-->
<!--                          class="text-xs"-->
<!--                        >-->
<!--                          {{ session.status }}-->
<!--                        </Badge>-->
                      </div>

                      <!-- Session title -->
                      <div class="text-xs text-muted-foreground truncate" :title="session.title">
                        {{ session.title }}
                      </div>

                      <!-- Time -->
<!--                      <div class="flex items-center text-xs text-muted-foreground">-->
<!--                        <Clock class="h-3 w-3 mr-1" />-->
<!--                        {{ session.startTime }}-{{ session.endTime }}-->
<!--                      </div>-->

                      <!-- Lecturer -->
                      <div class="flex items-center text-xs text-muted-foreground truncate">
                        <User class="h-3 w-3 mr-1" />
                        <span :title="session.lecturer">{{ session.lecturer }}</span>
                      </div>

                      <!-- Room -->
<!--                      <div class="flex items-center text-xs text-muted-foreground truncate">-->
<!--                        <MapPin class="h-3 w-3 mr-1" />-->
<!--                        <span :title="session.room">{{ session.room }}</span>-->
<!--                      </div>-->
                    </div>
                  </CardContent>
                </Card>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="!scheduleApi.isLoading.value && !scheduleApi.hasError.value && scheduleApi.sessionsCount.value === 0"
      class="text-center py-12 text-muted-foreground"
    >
      <Calendar class="h-12 w-12 mx-auto mb-4 opacity-50" />
      <p class="text-lg">No sessions scheduled for this week</p>
      <p class="text-sm">Sessions will appear here when they are scheduled</p>
    </div>

    <!-- Overlapping Sessions Modal -->
    <OverlappingSessionsModal
      v-model:open="overlappingModalOpen"
      :sessions="overlappingSessions"
      :time-slot="overlappingTimeSlot"
      :date="overlappingDate"
      @edit-session="handleEditSessionFromModal"
    />
  </div>
</template>

<style scoped>
/* Ensure proper grid layout */
.grid-cols-8 {
  grid-template-columns: 120px repeat(7, 1fr);
}

/* Custom scrollbar for the grid container */
.overflow-hidden::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.overflow-hidden::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.overflow-hidden::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.overflow-hidden::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .grid-cols-8 {
    grid-template-columns: 80px repeat(7, 1fr);
  }
}

@media (max-width: 768px) {
  .grid-cols-8 {
    grid-template-columns: 60px repeat(7, minmax(100px, 1fr));
  }
}
</style>
