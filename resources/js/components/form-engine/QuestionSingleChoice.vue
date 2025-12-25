<script setup lang="ts">
import { computed } from 'vue'
import type { FormAnswer, Question, SelectedOption } from './types'
import { normalizeToString, sortOptions } from './types'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
}>()

const sortedOptions = computed(() => sortOptions(props.question.options))

const selectedOptionId = computed(() => {
  const optionId = props.modelValue?.selected_options?.[0]?.option_id
  return optionId != null ? String(optionId) : ''
})

function handleSelect(value: string | number) {
  const optionId = typeof value === 'number' ? value : Number.parseInt(value, 10)
  const selectedOptions: SelectedOption[] = [{ option_id: optionId }]
  emit('update:modelValue', {
    question_id: props.question.id,
    selected_options: selectedOptions,
  })
}

function handleFreeText(optionId: number, freeText: string | number) {
  const selectedOptions: SelectedOption[] = [{
    option_id: optionId,
    free_text: normalizeToString(freeText),
  }]
  emit('update:modelValue', {
    question_id: props.question.id,
    selected_options: selectedOptions,
  })
}

function getOptionFreeText(optionId: number): string {
  return props.modelValue?.selected_options?.find((o: SelectedOption) => o.option_id === optionId)?.free_text ?? ''
}

function isOptionSelected(optionId: number): boolean {
  return props.modelValue?.selected_options?.[0]?.option_id === optionId
}
</script>

<template>
  <RadioGroup
    :model-value="selectedOptionId"
    :disabled="readonly"
    @update:model-value="handleSelect"
  >
    <div
      v-for="option in sortedOptions"
      :key="option.id"
      class="space-y-2"
    >
      <div class="flex items-center space-x-2">
        <RadioGroupItem
          :id="`${question.id}-${option.id}`"
          :value="option.id.toString()"
        />
        <Label
          :for="`${question.id}-${option.id}`"
          class="flex-1 cursor-pointer"
        >
          {{ option.label || option.text }}
        </Label>
      </div>
      <div v-if="option.allows_free_text && isOptionSelected(option.id)" class="ml-6">
        <Input
          type="text"
          :model-value="getOptionFreeText(option.id)"
          placeholder="Please specify..."
          class="max-w-md"
          :disabled="readonly"
          @update:model-value="(value: string | number) => handleFreeText(option.id, value)"
        />
      </div>
    </div>
  </RadioGroup>
</template>

