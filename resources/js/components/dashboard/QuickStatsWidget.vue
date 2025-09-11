<script setup lang="ts">
import { computed } from 'vue'
import StatsCard from '@/components/StatsCard.vue'

interface QuickStats {
  students: { total: number; by_status: Record<string, number> }
  lecturers: { total: number; by_employment_type: Record<string, number> }
  academics: { programs: number; specializations: number; active_curriculum_versions: number }
  rooms: { total: number; available: number; occupied: number; maintenance: number }
}

const props = defineProps<{ stats: QuickStats }>()

// Computed properties for better readability
const studentStatusItems = computed(() => [
  { label: 'Active', value: props.stats.students.by_status.active || 0 },
  { label: 'Inactive', value: props.stats.students.by_status.inactive || 0 },
  { label: 'Suspended', value: props.stats.students.by_status.suspended || 0 },
  { label: 'Graduated', value: props.stats.students.by_status.graduated || 0 },
])

const lecturerEmploymentItems = computed(() => [
  { label: 'Full time', value: props.stats.lecturers.by_employment_type.full_time || 0 },
  { label: 'Part time', value: props.stats.lecturers.by_employment_type.part_time || 0 },
  { label: 'Visiting', value: props.stats.lecturers.by_employment_type.visiting || 0 },
  { label: 'Contract', value: props.stats.lecturers.by_employment_type.contract || 0 },
])

const academicItems = computed(() => [
  { label: 'Active Curriculums', value: props.stats.academics.active_curriculum_versions || 0 },
])

const roomStatusItems = computed(() => [
  { label: 'Available', value: props.stats.rooms.available || 0 },
  { label: 'Occupied', value: props.stats.rooms.occupied || 0 },
  { label: 'Maintenance', value: props.stats.rooms.maintenance || 0 },
])

const programsSpecializationsDisplay = computed(() => 
  `${props.stats.academics.programs} / ${props.stats.academics.specializations}`
)
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-semibold text-foreground">Quick Stats</h2>
    
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard
        title="Students"
        :value="props.stats.students.total"
        :sub-items="studentStatusItems"
      />

      <StatsCard
        title="Lecturers"
        :value="props.stats.lecturers.total"
        :sub-items="lecturerEmploymentItems"
      />

      <StatsCard
        title="Programs / Specializations"
        :value="programsSpecializationsDisplay"
        :sub-items="academicItems"
      />

      <StatsCard
        title="Rooms"
        :value="props.stats.rooms.total"
        :sub-items="roomStatusItems"
      />
    </div>
  </div>
</template>
