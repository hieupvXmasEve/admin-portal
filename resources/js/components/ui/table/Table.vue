<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
  /**
   * Extra classes for the scrolling container div (not the <table> itself).
   * Use this — not an ancestor wrapper — to set max-height/overflow: a
   * caller-side overflow-auto div nested around <Table> creates a second
   * scroll context, and `sticky` headers stick to the wrong (non-scrolling)
   * one instead of this container.
   */
  containerClass?: HTMLAttributes['class']
}>()
</script>

<template>
  <div data-slot="table-container" :class="cn('relative w-full overflow-auto', props.containerClass)">
    <table data-slot="table" :class="cn('w-full caption-bottom text-sm', props.class)">
      <slot />
    </table>
  </div>
</template>
