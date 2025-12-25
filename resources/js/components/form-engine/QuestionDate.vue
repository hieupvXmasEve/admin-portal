<script setup lang="ts">
import { Input } from '@/components/ui/input';
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
  const dateValue = normalizeToString(value)
  emit('update:modelValue', {
    question_id: props.question.id,
    answer_date: dateValue || undefined,
  })
}
</script>

<template>
  <Input type="date" :model-value="modelValue?.answer_date ?? ''" :required="question.is_required" :disabled="readonly"
    @update:model-value="handleUpdate" />
</template>
