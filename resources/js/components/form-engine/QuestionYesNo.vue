<script setup lang="ts">
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Label } from '@/components/ui/label'
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
  <RadioGroup
    :model-value="modelValue?.answer_text ?? ''"
    :disabled="readonly"
    @update:model-value="handleUpdate"
  >
    <div class="flex items-center space-x-2">
      <RadioGroupItem :id="`${question.id}-yes`" value="yes" />
      <Label :for="`${question.id}-yes`" class="cursor-pointer">
        Yes
      </Label>
    </div>
    <div class="flex items-center space-x-2">
      <RadioGroupItem :id="`${question.id}-no`" value="no" />
      <Label :for="`${question.id}-no`" class="cursor-pointer">
        No
      </Label>
    </div>
  </RadioGroup>
</template>

