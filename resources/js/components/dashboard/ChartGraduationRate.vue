<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApi } from '@/composables/useApiRequest'
import { toast } from 'vue-sonner'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { ChartTooltip } from '@/components/ui/chart'
import { VisLine, VisStackedBar, VisXYContainer } from '@unovis/vue'

interface YearlyRateData {
  year: number
  total_admitted: number
  total_graduated: number
  graduation_rate: number
}

interface ProgramRateData {
  program_id: number
  program_name: string
  program_code: string
  total_students: number
  graduated_students: number
  graduation_rate: number
  fill: string
}

interface GraduationRateData {
  yearly_rates: YearlyRateData[]
  program_rates: ProgramRateData[]
}

const data = ref<GraduationRateData | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const api = useApi()

const yearlyChartData = computed(() => {
  if (!data.value?.yearly_rates) return []
  return data.value.yearly_rates.map(item => ({
    ...item,
    year: item.year.toString()
  }))
})

const programChartData = computed(() => {
  if (!data.value?.program_rates) return []
  return data.value.program_rates.map(item => ({
    program: item.program_code,
    rate: item.graduation_rate,
    total_students: item.total_students,
    graduated_students: item.graduated_students,
    fill: item.fill
  }))
})

const overallStats = computed(() => {
  if (!data.value?.yearly_rates || !data.value?.program_rates) {
    return {
      totalAdmitted: 0,
      totalGraduated: 0,
      overallRate: 0,
      avgProgramRate: 0
    }
  }

  const totalAdmitted = data.value.yearly_rates.reduce((sum, item) => sum + item.total_admitted, 0)
  const totalGraduated = data.value.yearly_rates.reduce((sum, item) => sum + item.total_graduated, 0)
  const overallRate = totalAdmitted > 0 ? Math.round((totalGraduated / totalAdmitted) * 100) : 0
  const avgProgramRate = data.value.program_rates.length > 0 
    ? Math.round(data.value.program_rates.reduce((sum, item) => sum + item.graduation_rate, 0) / data.value.program_rates.length)
    : 0

  return {
    totalAdmitted,
    totalGraduated,
    overallRate,
    avgProgramRate
  }
})

onMounted(async () => {
  try {
    const response = await api.get('/api/dashboard/graduation-rate')
    data.value = response.data
  } catch (err) {
    console.error('Failed to load graduation rate data:', err)
    error.value = 'Failed to load graduation rate data'
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
        🎓 Graduation Rates
      </CardTitle>
      <div v-if="data" class="text-sm text-muted-foreground">
        Overall Rate: {{ overallStats.overallRate }}% | Programs Tracked: {{ data.program_rates.length }}
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
      
      <div v-else-if="data" class="space-y-8">
        <!-- Yearly Graduation Rates -->
        <div>
          <h4 class="text-sm font-medium text-muted-foreground mb-3">Graduation Rates by Admission Year</h4>
          <div class="h-[250px]">
            <VisXYContainer
              v-if="yearlyChartData.length > 0"
              :data="yearlyChartData"
              class="w-full h-full"
            >
              <VisLine
                :x="(d: any) => d.year"
                :y="(d: any) => d.graduation_rate"
                :curve-type="'curveMonotoneX'"
                :stroke-width="3"
                color="hsl(var(--chart-1))"
              />
            </VisXYContainer>
            <div v-else class="flex items-center justify-center h-full text-muted-foreground">
              No yearly graduation data available
            </div>
          </div>
        </div>

        <!-- Program-specific Graduation Rates -->
        <div>
          <h4 class="text-sm font-medium text-muted-foreground mb-3">Graduation Rates by Program</h4>
          <div class="h-[300px]">
            <VisXYContainer
              v-if="programChartData.length > 0"
              :data="programChartData"
              class="w-full h-full"
            >
              <VisStackedBar
                :x="(d: any) => d.program"
                :y="(d: any) => d.rate"
                :color="(d: any) => d.fill"
                orientation="horizontal"
              />
            </VisXYContainer>
            <div v-else class="flex items-center justify-center h-full text-muted-foreground">
              No program graduation data available
            </div>
          </div>
        </div>

        <!-- Program Details Table -->
        <div>
          <h4 class="text-sm font-medium text-muted-foreground mb-3">Program Performance Details</h4>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b">
                <tr class="text-left">
                  <th class="pb-2 font-medium text-muted-foreground">Program</th>
                  <th class="pb-2 font-medium text-muted-foreground text-center">Total Students</th>
                  <th class="pb-2 font-medium text-muted-foreground text-center">Graduated</th>
                  <th class="pb-2 font-medium text-muted-foreground text-center">Rate</th>
                  <th class="pb-2 font-medium text-muted-foreground">Performance</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr 
                  v-for="program in data.program_rates" 
                  :key="program.program_id"
                  class="hover:bg-muted/50 transition-colors"
                >
                  <td class="py-2">
                    <div class="flex items-center gap-2">
                      <div 
                        class="w-3 h-3 rounded-full" 
                        :style="{ backgroundColor: program.fill }"
                      ></div>
                      <div>
                        <div class="font-medium">{{ program.program_code }}</div>
                        <div class="text-xs text-muted-foreground">{{ program.program_name }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-2 text-center">{{ program.total_students }}</td>
                  <td class="py-2 text-center">{{ program.graduated_students }}</td>
                  <td class="py-2 text-center">
                    <span 
                      class="font-medium"
                      :class="{
                        'text-green-600': program.graduation_rate >= 80,
                        'text-yellow-600': program.graduation_rate >= 60 && program.graduation_rate < 80,
                        'text-red-600': program.graduation_rate < 60
                      }"
                    >
                      {{ program.graduation_rate }}%
                    </span>
                  </td>
                  <td class="py-2">
                    <div class="w-full bg-muted rounded-full h-2">
                      <div 
                        class="h-2 rounded-full transition-all duration-300"
                        :style="{ 
                          width: `${program.graduation_rate}%`,
                          backgroundColor: program.graduation_rate >= 80 ? '#16a34a' : 
                                          program.graduation_rate >= 60 ? '#ca8a04' : '#dc2626'
                        }"
                      ></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t">
          <div class="text-center">
            <div class="text-2xl font-bold text-primary">{{ overallStats.overallRate }}%</div>
            <div class="text-sm text-muted-foreground">Overall Rate</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold">{{ overallStats.totalGraduated }}</div>
            <div class="text-sm text-muted-foreground">Total Graduated</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold">{{ overallStats.totalAdmitted }}</div>
            <div class="text-sm text-muted-foreground">Total Admitted</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-chart-2">{{ overallStats.avgProgramRate }}%</div>
            <div class="text-sm text-muted-foreground">Avg Program Rate</div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
