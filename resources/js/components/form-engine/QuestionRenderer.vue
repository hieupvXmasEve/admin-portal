<script setup lang="ts">
import type { FormAnswer, Question } from './types'
import QuestionDate from './QuestionDate.vue'
import QuestionFile from './QuestionFile.vue'
import QuestionLongText from './QuestionLongText.vue'
import QuestionMultiChoice from './QuestionMultiChoice.vue'
import QuestionNumber from './QuestionNumber.vue'
import QuestionRating from './QuestionRating.vue'
import QuestionShortText from './QuestionShortText.vue'
import QuestionSingleChoice from './QuestionSingleChoice.vue'
import QuestionYesNo from './QuestionYesNo.vue'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
  (e: 'fileChange', questionId: number, file: File | null): void
}>()

function handleUpdate(value: FormAnswer) {
  emit('update:modelValue', value)
}

function handleFileChange(file: File | null) {
  emit('fileChange', props.question.id, file)
}
</script>

<template>
  <QuestionShortText
    v-if="question.type === 'short_text'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionLongText
    v-else-if="question.type === 'long_text' || question.type === 'matrix'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionNumber
    v-else-if="question.type === 'number'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionDate
    v-else-if="question.type === 'date'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionSingleChoice
    v-else-if="question.type === 'single_choice' || question.type === 'likert'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionMultiChoice
    v-else-if="question.type === 'multi_choice'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionYesNo
    v-else-if="question.type === 'yes_no'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionRating
    v-else-if="question.type === 'rating'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
  />

  <QuestionFile
    v-else-if="question.type === 'file'"
    :question="question"
    :model-value="modelValue"
    :readonly="readonly"
    @update:model-value="handleUpdate"
    @file-change="handleFileChange"
  />

  <div v-else class="text-muted-foreground text-sm">
    Unsupported question type: {{ question.type }}
  </div>
</template>
