<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { FormAnswer, Question, SelectedOption } from './types'
import { normalizeToString, sortOptions } from './types'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
}>()

const sortedOptions = computed(() => sortOptions(props.question.options))

function isOptionChecked(optionId: number): boolean {
  return (props.modelValue?.selected_options ?? []).some((o: SelectedOption) => o.option_id === optionId)
}

function getOptionFreeText(optionId: number): string {
  return props.modelValue?.selected_options?.find((o: SelectedOption) => o.option_id === optionId)?.free_text ?? ''
}

function handleToggle(optionId: number, checked: boolean, freeText?: string) {
  const currentOptions = props.modelValue?.selected_options ?? []

  let nextOptions: SelectedOption[]
  if (checked) {
    const existing = currentOptions.find((o: SelectedOption) => o.option_id === optionId)
    if (existing) {
      nextOptions = currentOptions.map((o: SelectedOption) =>
        o.option_id === optionId ? { ...o, free_text: freeText } : o,
      )
    }
    else {
      nextOptions = [...currentOptions, { option_id: optionId, free_text: freeText }]
    }
  }
  else {
    nextOptions = currentOptions.filter((o: SelectedOption) => o.option_id !== optionId)
  }

  emit('update:modelValue', {
    question_id: props.question.id,
    selected_options: nextOptions,
  })
}

function handleFreeTextUpdate(optionId: number, value: string | number) {
  handleToggle(optionId, true, normalizeToString(value))
}
</script>

<template>
  <div class="space-y-3">
    <div v-for="option in sortedOptions" :key="option.id" class="space-y-2">
      <div class="flex items-center space-x-2">
        <Checkbox :id="`${question.id}-${option.id}`" :model-value="isOptionChecked(option.id)" :disabled="readonly"
          @update:model-value="(value: boolean | 'indeterminate') => handleToggle(option.id, value === true)" />
        <Label :for="`${question.id}-${option.id}`" class="flex-1 cursor-pointer">
          {{ option.label || option.text }}
        </Label>
      </div>
      <div v-if="option.allows_free_text && isOptionChecked(option.id)" class="ml-6">
        <Input type="text" :model-value="getOptionFreeText(option.id)" placeholder="Please specify..." class="max-w-md"
          :disabled="readonly"
          @update:model-value="(value: string | number) => handleFreeTextUpdate(option.id, value)" />
      </div>
    </div>
  </div>
</template>
