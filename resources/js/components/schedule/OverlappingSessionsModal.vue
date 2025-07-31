<script setup lang="ts">
import { computed } from 'vue'
import { format, parseISO } from 'date-fns'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import {
  Calendar,
  Clock,
  MapPin,
  User,
  BookOpen,
  Edit3,
  Users,
} from 'lucide-vue-next'
import type { ScheduleSession } from '@/types/schedule'

// Props
interface Props {
  open: boolean
  sessions: ScheduleSession[]
  timeSlot: string
  date: string
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  'update:open': [value: boolean]
  'edit-session': [session: ScheduleSession]
}>()

// Computed
const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
})

const formattedDate = computed(() => {
  try {
    return format(parseISO(props.date), 'EEEE, MMMM dd, yyyy')
  } catch {
    return props.date
  }
})

const sortedSessions = computed(() => {
  return [...props.sessions].sort((a, b) => {
    // Sort by start time, then by unit code
    const timeComparison = a.startTime.localeCompare(b.startTime)
    if (timeComparison !== 0) return timeComparison
    return a.unitCode.localeCompare(b.unitCode)
  })
})

// Methods
const getSessionBadgeColor = (status: string) => {
  switch (status) {
    case 'scheduled':
      return 'default'
    case 'in_progress':
      return 'secondary'
    case 'completed':
      return 'outline'
    case 'cancelled':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const handleEditSession = (session: ScheduleSession) => {
  emit('edit-session', session)
  isOpen.value = false
}

const formatTimeRange = (startTime: string, endTime: string) => {
  return `${startTime} - ${endTime}`
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="isOpen = $event">
    <DialogContent class="max-w-2xl max-h-[80vh]">
      <DialogHeader>
        <DialogTitle class="flex items-center space-x-2">
          <Users class="h-5 w-5" />
          <span>Overlapping Sessions</span>
        </DialogTitle>
        <DialogDescription>
          Multiple sessions are scheduled for {{ formattedDate }} at {{ timeSlot }}.
          Click on any session to edit its details.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <!-- Session Count Summary -->
        <div class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
          <div class="flex items-center space-x-2">
            <Calendar class="h-4 w-4 text-muted-foreground" />
            <span class="font-medium">{{ formattedDate }}</span>
          </div>
          <div class="flex items-center space-x-2">
            <Clock class="h-4 w-4 text-muted-foreground" />
            <span class="font-medium">{{ timeSlot }}</span>
          </div>
          <Badge variant="secondary">
            {{ sessions.length }} session{{ sessions.length > 1 ? 's' : '' }}
          </Badge>
        </div>

        <!-- Sessions List -->
        <ScrollArea class="max-h-[400px]">
          <div class="space-y-3">
            <Card
              v-for="session in sortedSessions"
              :key="session.id"
              class="cursor-pointer transition-all hover:shadow-md hover:scale-[1.02] border-l-4"
              :class="{
                'border-l-blue-500 bg-blue-50 hover:bg-blue-100': session.status === 'scheduled',
                'border-l-yellow-500 bg-yellow-50 hover:bg-yellow-100': session.status === 'in_progress',
                'border-l-green-500 bg-green-50 hover:bg-green-100': session.status === 'completed',
                'border-l-red-500 bg-red-50 hover:bg-red-100': session.status === 'cancelled',
              }"
              @click="handleEditSession(session)"
            >
              <CardContent class="p-4">
                <div class="space-y-3">
                  <!-- Header with unit code and status -->
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                      <h3 class="font-semibold text-base">
                        {{ session.unitCode }}-{{ session.section }}
                      </h3>
                      <Badge
                        :variant="getSessionBadgeColor(session.status)"
                        class="text-xs"
                      >
                        {{ session.status }}
                      </Badge>
                    </div>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="opacity-0 group-hover:opacity-100 transition-opacity"
                      @click.stop="handleEditSession(session)"
                    >
                      <Edit3 class="h-4 w-4" />
                    </Button>
                  </div>

                  <!-- Session title -->
                  <div class="text-sm text-muted-foreground">
                    {{ session.title }}
                  </div>

                  <!-- Session details grid -->
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <!-- Time -->
                    <div class="flex items-center space-x-2 text-muted-foreground">
                      <Clock class="h-4 w-4" />
                      <span>{{ formatTimeRange(session.startTime, session.endTime) }}</span>
                    </div>

                    <!-- Room -->
                    <div class="flex items-center space-x-2 text-muted-foreground">
                      <MapPin class="h-4 w-4" />
                      <span>{{ session.room }}</span>
                    </div>

                    <!-- Lecturer -->
                    <div class="flex items-center space-x-2 text-muted-foreground">
                      <User class="h-4 w-4" />
                      <span>{{ session.lecturer }}</span>
                    </div>

                    <!-- Unit name -->
                    <div class="flex items-center space-x-2 text-muted-foreground">
                      <BookOpen class="h-4 w-4" />
                      <span>{{ session.unitName || session.unitCode }}</span>
                    </div>
                  </div>

                  <!-- Click to edit hint -->
                  <div class="flex items-center justify-end">
                    <span class="text-xs text-muted-foreground flex items-center space-x-1">
                      <Edit3 class="h-3 w-3" />
                      <span>Click to edit</span>
                    </span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </ScrollArea>
      </div>
    </DialogContent>
  </Dialog>
</template>