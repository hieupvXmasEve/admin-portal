<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea'
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
  <Textarea
    :model-value="modelValue?.answer_text ?? ''"
    placeholder="Enter your detailed answer"
    :required="question.is_required"
    :disabled="readonly"
    rows="4"
    @update:model-value="handleUpdate"
  />
</template>

