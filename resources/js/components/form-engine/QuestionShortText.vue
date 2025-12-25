<script setup lang="ts">
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
  emit('update:modelValue', {
    question_id: props.question.id,
    answer_text: normalizeToString(value),
  })
}
</script>

<template>
  <Input
    type="text"
    :model-value="modelValue?.answer_text ?? ''"
    placeholder="Enter your answer"
    :required="question.is_required"
    :disabled="readonly"
    @update:model-value="handleUpdate"
  />
</template>

