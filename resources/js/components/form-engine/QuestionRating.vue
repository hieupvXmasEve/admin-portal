<script setup lang="ts">
import { computed } from 'vue'
import type { FormAnswer, Question } from './types'
import { Star } from 'lucide-vue-next'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  maxRating?: number
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
}>()

const max = computed(() => props.maxRating ?? 5)
const rating = computed(() => props.modelValue?.answer_number ?? 0)

function handleUpdate(value: number) {
  if (props.readonly) return
  emit('update:modelValue', {
    question_id: props.question.id,
    answer_number: value,
  })
}
</script>

<template>
  <div class="flex items-center gap-1">
    <button
      v-for="i in max"
      :key="i"
      type="button"
      :disabled="readonly"
      class="transition-colors focus:outline-none"
      :class="[readonly ? 'cursor-default' : 'cursor-pointer hover:scale-110']"
      @click="handleUpdate(i)"
    >
      <Star
        class="h-6 w-6"
        :class="[
          i <= rating
            ? 'fill-yellow-400 text-yellow-400'
            : 'text-muted-foreground/30'
        ]"
      />
    </button>
    <span v-if="rating > 0" class="ml-2 text-sm text-muted-foreground">
      ({{ rating }}/{{ max }})
    </span>
  </div>
</template>
