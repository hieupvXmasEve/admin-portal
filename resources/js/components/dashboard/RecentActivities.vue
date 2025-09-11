<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import CardContent from '@/components/ui/card/CardContent.vue'

interface ActivityItem {
  id: number
  type: 'course_registration' | 'class_session' | 'room_booking' | 'student_enrollment'
  title: string
  description: string
  user_name?: string
  status: 'pending' | 'approved' | 'completed' | 'cancelled'
  created_at: string
  metadata?: Record<string, any>
}

interface RecentActivitiesData {
  activities: ActivityItem[]
  total_count: number
  has_more: boolean
}

const data = ref<RecentActivitiesData | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const getActivityIcon = (type: string) => {
  switch (type) {
    case 'course_registration': return '📚'
    case 'class_session': return '🏫'
    case 'room_booking': return '🏠'
    case 'student_enrollment': return '👨‍🎓'
    default: return '📝'
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'completed': return 'text-green-600'
    case 'approved': return 'text-blue-600'
    case 'pending': return 'text-orange-600'
    case 'cancelled': return 'text-red-600'
    default: return 'text-gray-600'
  }
}

onMounted(async () => {
  try {
    // This will be implemented later
    // const res = await axios.get('/api/admin/dashboard/recent-activities')
    // data.value = res.data
    
    // For now, show placeholder
    setTimeout(() => {
      loading.value = false
    }, 1000)
  } catch (err) {
    error.value = 'Failed to load recent activities'
    loading.value = false
  }
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        🕒 Recent Activities
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        <span class="ml-3 text-muted-foreground">Loading activities...</span>
      </div>
      
      <div v-else-if="error" class="text-center py-8 text-red-600">
        <div class="text-xl mb-2">❌</div>
        <div>{{ error }}</div>
      </div>
      
      <div v-else class="text-center py-12 text-muted-foreground border-2 border-dashed border-muted-foreground/25 rounded-lg">
        <div class="text-4xl mb-4">📋</div>
        <div class="text-lg font-medium">Recent Activities Feed</div>
        <div class="text-sm mt-2">Latest course registrations, class sessions, and bookings</div>
        <div class="text-xs mt-1 text-muted-foreground/70">
          Data endpoint: /api/admin/dashboard/recent-activities
        </div>
      </div>
    </CardContent>
  </Card>
</template>
