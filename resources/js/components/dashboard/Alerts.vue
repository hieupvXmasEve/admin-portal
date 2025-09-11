<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import CardContent from '@/components/ui/card/CardContent.vue'

interface AlertItem {
  id: number
  type: 'academic_hold' | 'attendance' | 'program_change'
  severity: 'high' | 'medium' | 'low'
  title: string
  message: string
  count?: number
  created_at: string
}

interface Props {
  alerts: AlertItem[]
}

const props = defineProps<Props>()

const getSeverityColor = (severity: string) => {
  switch (severity) {
    case 'high': return 'text-red-600 bg-red-50 border-red-200'
    case 'medium': return 'text-orange-600 bg-orange-50 border-orange-200'
    case 'low': return 'text-blue-600 bg-blue-50 border-blue-200'
    default: return 'text-gray-600 bg-gray-50 border-gray-200'
  }
}

const getSeverityIcon = (severity: string) => {
  switch (severity) {
    case 'high': return '🚨'
    case 'medium': return '⚠️'
    case 'low': return 'ℹ️'
    default: return '📝'
  }
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        🔔 Alerts & Notifications
        <span v-if="props.alerts.length > 0" class="ml-auto bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">
          {{ props.alerts.length }}
        </span>
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="props.alerts.length === 0" class="text-center py-8 text-muted-foreground">
        <div class="text-2xl mb-2">✅</div>
        <div>No alerts at this time</div>
        <div class="text-sm mt-1">All systems are running smoothly</div>
      </div>
      
      <div v-else class="space-y-3">
        <div 
          v-for="alert in props.alerts" 
          :key="alert.id"
          class="p-3 rounded-lg border"
          :class="getSeverityColor(alert.severity)"
        >
          <div class="flex items-start gap-3">
            <div class="text-lg">{{ getSeverityIcon(alert.severity) }}</div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm">{{ alert.title }}</div>
              <div class="text-sm mt-1 opacity-90">{{ alert.message }}</div>
              <div v-if="alert.count" class="text-xs mt-1 font-medium">
                {{ alert.count }} items affected
              </div>
              <div class="text-xs mt-2 opacity-75">{{ alert.created_at }}</div>
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
