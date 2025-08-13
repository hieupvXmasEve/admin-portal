<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { format, parseISO } from 'date-fns'
import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  Calendar,
  Clock,
  MapPin,
  User,
  BookOpen,
  AlertTriangle,
  Save,
  X
} from 'lucide-vue-next'
import { useAdminSchedule } from '@/composables/useAdminSchedule'
import type {
  ScheduleSession,
  DetailedScheduleSession,
  SessionUpdateData,
  ConflictError
} from '@/types/schedule'

// Props
interface Props {
  open: boolean
  session: ScheduleSession | null
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  'update:open': [value: boolean]
  'session-updated': [session: DetailedScheduleSession]
}>()

const scheduleApi = useAdminSchedule()

// Form validation schema
const sessionEditSchema = toTypedSchema(
  z.object({
    session_date: z.string().min(1, 'Date is required'),
    start_time: z.string().regex(/^\d{2}:\d{2}$/, 'Invalid time format (HH:MM)'),
    end_time: z.string().regex(/^\d{2}:\d{2}$/, 'Invalid time format (HH:MM)'),
    room_id: z.number().min(1, 'Room is required'),
  }).refine(
    (data) => {
      const startTime = parseInt(data.start_time.replace(':', ''))
      const endTime = parseInt(data.end_time.replace(':', ''))
      return endTime > startTime
    },
    {
      message: 'End time must be after start time',
      path: ['end_time'],
    }
  )
)

// Form setup
const { handleSubmit, resetForm, setFieldValue, values, errors } = useForm({
  validationSchema: sessionEditSchema,
})

// Local state
const isLoading = ref(false)
const conflictError = ref<string | null>(null)
const detailedSession = ref<DetailedScheduleSession | null>(null)

// Computed
const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
})

const selectedRoom = computed(() => {
  if (!values.room_id || !scheduleApi.filterOptions.value) return null
  return scheduleApi.filterOptions.value.rooms.find(r => r.id === values.room_id)
})

// Room options grouped by campus
const roomsByCapmus = computed(() => {
  if (!scheduleApi.filterOptions.value) return {}

  const grouped: Record<string, typeof scheduleApi.filterOptions.value.rooms> = {}

  scheduleApi.filterOptions.value.rooms.forEach(room => {
    const campusName = room.campus.name
    if (!grouped[campusName]) {
      grouped[campusName] = []
    }
    grouped[campusName].push(room)
  })

  return grouped
})

// Methods
const loadSessionDetails = async () => {
  if (!props.session) return

  isLoading.value = true
  try {
    await scheduleApi.fetchSessionDetails(props.session.id)
    detailedSession.value = scheduleApi.selectedSession.value as unknown as DetailedScheduleSession | null

    // Populate form with current session data
    if (detailedSession.value?.schedule && detailedSession.value?.room) {
      setFieldValue('session_date', detailedSession.value.schedule.date)
      setFieldValue('start_time', detailedSession.value.schedule.startTime)
      setFieldValue('end_time', detailedSession.value.schedule.endTime)
      setFieldValue('room_id', detailedSession.value.room.id || 0)
    }
  } catch (error) {
    console.error('Error loading session details:', error)
  } finally {
    isLoading.value = false
  }
}

const onSubmit = handleSubmit(async (formValues) => {
  if (!props.session) return

  isLoading.value = true
  conflictError.value = null

  try {
    const updateData: SessionUpdateData = {
      session_date: formValues.session_date,
      start_time: formValues.start_time,
      end_time: formValues.end_time,
      room_id: formValues.room_id,
    }

    const updatedSession = await scheduleApi.updateSession(props.session.id, updateData)

    if (updatedSession) {
      emit('session-updated', updatedSession as unknown as DetailedScheduleSession)
    }
    emit('update:open', false)
  } catch (error) {
    if (error instanceof Error) {
      conflictError.value = error.message
    }
  } finally {
    isLoading.value = false
  }
})

const handleClose = () => {
  conflictError.value = null
  resetForm()
  emit('update:open', false)
}

const formatDateTime = (date: string, time: string) => {
  try {
    const dateTime = parseISO(`${date}T${time}:00`)
    return format(dateTime, 'EEEE, MMMM dd, yyyy \'at\' h:mm a')
  } catch {
    return `${date} at ${time}`
  }
}

// Watch for session changes
watch(
  () => props.session,
  async (newSession) => {
    if (newSession && props.open) {
      await loadSessionDetails()
    }
  },
  { immediate: true }
)

// Watch for open state changes
watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen && props.session) {
      await loadSessionDetails()
    } else if (!isOpen) {
      resetForm()
      conflictError.value = null
      detailedSession.value = null
    }
  }
)

// Initialize filter options
onMounted(async () => {
  if (!scheduleApi.filterOptions.value) {
    await scheduleApi.fetchFilterOptions()
  }
})
</script>

<template>
  <Sheet :open="isOpen" @update:open="isOpen = $event">
    <SheetContent class="w-full sm:max-w-lg overflow-y-auto py-4">
      <SheetHeader>
        <SheetTitle class="flex items-center space-x-2">
          <Calendar class="h-5 w-5" />
          <span>Edit Session Schedule</span>
        </SheetTitle>
        <SheetDescription>
          Modify the date, time, and room for this class session. Changes will be validated for conflicts.
        </SheetDescription>
      </SheetHeader>

      <!-- Loading State -->
      <div v-if="isLoading && !detailedSession" class="flex items-center justify-center py-8">
        <div class="flex items-center space-x-2">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
          <span class="text-muted-foreground">Loading session details...</span>
        </div>
      </div>

      <!-- Session Details & Edit Form -->
      <div v-else-if="detailedSession" class="space-y-6 px-4">
        <!-- Current Session Info -->
        <div class="space-y-4">
          <div class="space-y-2">
            <h3 class="font-semibold text-lg">{{ detailedSession.title }}</h3>
            <div class="flex items-center space-x-2">
              <Badge variant="outline">{{ detailedSession.unitCode }}</Badge>
              <Badge variant="outline">Section {{ detailedSession.section }}</Badge>
              <Badge :variant="
                detailedSession.details?.status === 'scheduled' ? 'default' :
                detailedSession.details?.status === 'in_progress' ? 'secondary' :
                detailedSession.details?.status === 'completed' ? 'outline' :
                'destructive'
              ">
                {{ detailedSession.details?.status }}
              </Badge>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 text-sm">
            <div class="flex items-center space-x-2 text-muted-foreground">
              <User class="h-4 w-4" />
              <span>{{ detailedSession.lecturer?.name }}</span>
            </div>
            <div class="flex items-center space-x-2 text-muted-foreground">
              <BookOpen class="h-4 w-4" />
              <span>{{ detailedSession.unitName }}</span>
            </div>
          </div>
        </div>

        <!-- Conflict Error Alert -->
        <Alert v-if="conflictError" variant="destructive">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>
            {{ conflictError }}
          </AlertDescription>
        </Alert>

        <!-- Edit Form -->
        <form @submit="onSubmit" class="space-y-4">
          <!-- Date Input -->
          <div class="space-y-2">
            <Label for="session_date">Session Date</Label>
            <Input
              id="session_date"
              type="date"
              v-model="values.session_date"
              :class="{ 'border-destructive': errors.session_date }"
            />
            <p v-if="errors.session_date" class="text-sm text-destructive">
              {{ errors.session_date }}
            </p>
          </div>

          <!-- Time Inputs -->
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="start_time">Start Time</Label>
              <Input
                id="start_time"
                type="time"
                v-model="values.start_time"
                :class="{ 'border-destructive': errors.start_time }"
              />
              <p v-if="errors.start_time" class="text-sm text-destructive">
                {{ errors.start_time }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="end_time">End Time</Label>
              <Input
                id="end_time"
                type="time"
                v-model="values.end_time"
                :class="{ 'border-destructive': errors.end_time }"
              />
              <p v-if="errors.end_time" class="text-sm text-destructive">
                {{ errors.end_time }}
              </p>
            </div>
          </div>

          <!-- Room Selection -->
          <div class="space-y-2">
            <Label for="room">Room</Label>
            <Select
              :value="values.room_id?.toString() || ''"
              onValueChange="(value) => setFieldValue('room_id', parseInt(value))"
            >
              <SelectTrigger
                id="room"
                :class="{ 'border-destructive': errors.room_id }"
              >
                <SelectValue placeholder="Select a room" />
              </SelectTrigger>
              <SelectContent>
                <template v-for="(rooms, campusName) in roomsByCapmus" :key="campusName">
                  <div class="px-2 py-1 text-sm font-semibold text-muted-foreground">
                    {{ campusName }}
                  </div>
                  <SelectItem
                    v-for="room in rooms"
                    :key="room.id"
                    :value="room.id.toString()"
                    class="pl-4"
                  >
                    <div class="flex items-center justify-between w-full">
                      <span>{{ room.name }}</span>
                      <span class="text-muted-foreground text-xs">{{ room.building }}</span>
                    </div>
                  </SelectItem>
                </template>
              </SelectContent>
            </Select>
            <p v-if="errors.room_id" class="text-sm text-destructive">
              {{ errors.room_id }}
            </p>
          </div>

          <!-- Preview of Changes -->
          <div
            v-if="values.session_date && values.start_time && values.end_time && selectedRoom"
            class="space-y-2 p-3 bg-muted/50 rounded-lg"
          >
            <div class="font-medium text-sm">Preview of Changes:</div>
            <div class="space-y-1 text-sm text-muted-foreground">
              <div class="flex items-center space-x-2">
                <Calendar class="h-3 w-3" />
                <span>{{ formatDateTime(values.session_date, values.start_time) }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <Clock class="h-3 w-3" />
                <span>{{ values.start_time }} - {{ values.end_time }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <MapPin class="h-3 w-3" />
                <span>{{ selectedRoom.name }} ({{ selectedRoom.building }})</span>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex space-x-2 pt-4">
            <Button
              type="submit"
              class="flex-1"
              :disabled="isLoading"
            >
              <Save class="h-4 w-4 mr-2" />
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
            </Button>
            <Button
              type="button"
              variant="outline"
              @click="handleClose"
              :disabled="isLoading"
            >
              <X class="h-4 w-4 mr-2" />
              Cancel
            </Button>
          </div>
        </form>

        <!-- Session Metadata (Read-only) -->
        <div class="space-y-3 pt-4 border-t">
          <h4 class="font-medium">Session Information</h4>
          <div class="grid grid-cols-1 gap-2 text-sm text-muted-foreground">
            <div class="flex justify-between">
              <span>Delivery Mode:</span>
              <span class="capitalize">{{ detailedSession.details?.deliveryMode }}</span>
            </div>
            <div class="flex justify-between">
              <span>Session Type:</span>
              <span class="capitalize">{{ detailedSession.details?.sessionType }}</span>
            </div>
            <div class="flex justify-between">
              <span>Attendance Required:</span>
              <span>{{ detailedSession.details?.attendanceRequired ? 'Yes' : 'No' }}</span>
            </div>
            <div v-if="detailedSession.details?.isAssessment" class="flex justify-between">
              <span>Assessment Weight:</span>
              <span>{{ detailedSession.details?.assessmentWeight }}%</span>
            </div>
          </div>
        </div>
      </div>
    </SheetContent>
  </Sheet>
</template>
