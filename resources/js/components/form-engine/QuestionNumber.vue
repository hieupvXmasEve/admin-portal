<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input'
import type { FormAnswer, Question } from './types'
import { normalizeToString } from './types'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
}>()

function handleUpdate(value: string | number) {
  const numericValue = normalizeToString(value)
  emit('update:modelValue', {
    question_id: props.question.id,
    answer_number: numericValue ? Number.parseFloat(numericValue) : undefined,
  })
}

const displayValue = computed(() => {
  const val = props.modelValue?.answer_number
  return val != null ? String(val) : ''
})
</script>

<template>
  <Input
    type="number"
    :model-value="displayValue"
    placeholder="Enter a number"
    :required="question.is_required"
    :disabled="readonly"
    @update:model-value="handleUpdate"
  />
</template>

