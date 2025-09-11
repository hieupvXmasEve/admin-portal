<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApi } from '@/composables/useApiRequest'
import { toast } from 'vue-sonner'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { ChartTooltip } from '@/components/ui/chart'
import { VisStackedBar, VisXYContainer } from '@unovis/vue'

interface AcademicStandingItem {
  standing: string
  label: string
  count: number
  percentage: number
  avg_gpa: number
  avg_cumulative_gpa: number
  fill: string
}

interface AcademicStandingData {
  total_students: number
  standings: AcademicStandingItem[]
}

const data = ref<AcademicStandingData | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const api = useApi()

const chartData = computed(() => {
  if (!data.value?.standings) return []
  return data.value.standings.map(item => ({
    standing: item.label,
    count: item.count,
    percentage: item.percentage,
    fill: item.fill
  }))
})

const getStandingIcon = (standing: string): string => {
  switch (standing.toLowerCase()) {
    case 'good': return '✅'
    case 'probation': return '⚠️'
    case 'suspension': return '❌'
    case 'honors': return '🏆'
    default: return '📊'
  }
}

const getStandingDescription = (standing: string): string => {
  switch (standing.toLowerCase()) {
    case 'good': return 'Students in good academic standing'
    case 'probation': return 'Students on academic probation'
    case 'suspension': return 'Students under academic suspension'
    case 'honors': return 'Students with honors distinction'
    default: return 'Academic standing status'
  }
}

onMounted(async () => {
  try {
    const response = await api.get('/api/dashboard/academic-standing')
    data.value = response.data
  } catch (err) {
    console.error('Failed to load academic standing data:', err)
    error.value = 'Failed to load academic standing data'
    toast.error('Failed to load chart data')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        📈 Academic Standing Distribution
      </CardTitle>
      <div v-if="data" class="text-sm text-muted-foreground">
        Total Students with Standing: {{ data.total_students.toLocaleString() }}
      </div>
    </CardHeader>
    <CardContent>
      <div v-if="loading" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        <span class="ml-3 text-muted-foreground">Loading chart data...</span>
      </div>
      
      <div v-else-if="error" class="text-center py-8 text-red-600">
        <div class="text-xl mb-2">❌</div>
        <div>{{ error }}</div>
      </div>
      
      <div v-else-if="data" class="space-y-6">
        <!-- Bar Chart -->
        <div class="h-[300px]">
          <VisXYContainer
            v-if="chartData.length > 0"
            :data="chartData"
            class="w-full h-full"
          >
            <VisStackedBar
              :x="(d: any) => d.standing"
              :y="(d: any) => d.count"
              :color="(d: any) => d.fill"
            />
          </VisXYContainer>
          <div v-else class="flex items-center justify-center h-full text-muted-foreground">
            No academic standing data available
          </div>
        </div>

        <!-- Standing Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div 
            v-for="standing in data.standings" 
            :key="standing.standing"
            class="p-4 rounded-lg border bg-card"
          >
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="text-lg">{{ getStandingIcon(standing.standing) }}</span>
                <span class="font-medium">{{ standing.label }}</span>
              </div>
              <div class="text-right">
                <div class="text-lg font-bold">{{ standing.count }}</div>
                <div class="text-sm text-muted-foreground">{{ standing.percentage }}%</div>
              </div>
            </div>
            
            <div class="space-y-1 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Avg GPA:</span>
                <span class="font-medium">{{ standing.avg_gpa }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Avg Cumulative GPA:</span>
                <span class="font-medium">{{ standing.avg_cumulative_gpa }}</span>
              </div>
            </div>
            
            <!-- Progress bar -->
            <div class="mt-3">
              <div class="w-full bg-muted rounded-full h-2">
                <div 
                  class="h-2 rounded-full transition-all duration-300"
                  :style="{ 
                    width: `${standing.percentage}%`,
                    backgroundColor: standing.fill
                  }"
                ></div>
              </div>
            </div>
            
            <p class="text-xs text-muted-foreground mt-2">
              {{ getStandingDescription(standing.standing) }}
            </p>
          </div>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t">
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">
              {{ data.standings.find(s => s.standing === 'good')?.count || 0 }}
            </div>
            <div class="text-sm text-muted-foreground">Good Standing</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-yellow-600">
              {{ data.standings.find(s => s.standing === 'probation')?.count || 0 }}
            </div>
            <div class="text-sm text-muted-foreground">On Probation</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-red-600">
              {{ data.standings.find(s => s.standing === 'suspension')?.count || 0 }}
            </div>
            <div class="text-sm text-muted-foreground">Suspended</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">
              {{ data.standings.find(s => s.standing === 'honors')?.count || 0 }}
            </div>
            <div class="text-sm text-muted-foreground">Honors</div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
