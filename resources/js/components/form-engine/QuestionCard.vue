<script setup lang="ts">
import type { FormAnswer, Question } from './types'
import QuestionRenderer from './QuestionRenderer.vue'
import { Badge } from '../ui/badge';

defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  showBorder?: boolean
  isError?: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
  (e: 'fileChange', questionId: number, file: File | null): void
}>()

function handleUpdate(value: FormAnswer) {
  emit('update:modelValue', value)
}

function handleFileChange(questionId: number, file: File | null) {
  emit('fileChange', questionId, file)
}
</script>

<template>
  <div
    class="space-y-4 rounded-xl p-4 transition"
    :class="[
      showBorder !== false ? 'border' : '',
      isError ? 'border-destructive bg-destructive/5' : 'hover:bg-muted/40',
    ]"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="space-y-1 flex-1">
        <p class="text-base font-medium text-foreground">
          {{ question.text }}
          <span v-if="question.is_required" class="text-destructive ml-1">*</span>
        </p>
        <p v-if="question.help_text" class="text-xs text-muted-foreground">
          {{ question.help_text }}
        </p>
      </div>
      <Badge
        v-if="showBorder !== false"
        variant="outline"
        class="whitespace-nowrap text-xs shrink-0"
        :class="isError ? 'border-destructive text-destructive' : question.is_required ? 'border-primary text-primary' : 'text-muted-foreground'"
      >
        {{ question.is_required ? 'Required' : 'Optional' }}
      </Badge>
    </div>

    <div class="mt-4">
      <QuestionRenderer
        :question="question"
        :model-value="modelValue"
        :readonly="readonly"
        @update:model-value="handleUpdate"
        @file-change="(questionId: number, file: File | null) => handleFileChange(questionId, file)"
      />
    </div>
  </div>
</template>
