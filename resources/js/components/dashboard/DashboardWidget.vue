<script setup lang="ts">
interface Props {
  title?: string
  loading?: boolean
  error?: string | null
}

defineProps<Props>()
</script>

<template>
  <div class="dashboard-widget">
    <div v-if="title" class="mb-4">
      <h2 class="text-lg font-semibold text-foreground">{{ title }}</h2>
    </div>
    
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      <span class="ml-3 text-muted-foreground">Loading...</span>
    </div>
    
    <div v-else-if="error" class="rounded-lg border border-destructive/20 bg-destructive/10 p-4">
      <div class="text-sm text-destructive">{{ error }}</div>
    </div>
    
    <div v-else>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.dashboard-widget {
  @apply space-y-4;
}
</style>
