<script setup lang="ts">
import { computed } from 'vue';
import QuestionCard from './QuestionCard.vue';
import type { FormAnswer, FormSection } from './types';
import { sortQuestions } from './types';

const props = defineProps<{
  section: FormSection;
  answers: Record<number, FormAnswer>;
  errorQuestionIds?: number[];
  readonly?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:answer', questionId: number, value: FormAnswer): void;
  (e: 'file-change', questionId: number, file: File | null): void;
}>();

const sortedQuestions = computed(() => sortQuestions(props.section.questions));

function getAnswer(questionId: number): FormAnswer | undefined {
  return props.answers[questionId];
}

function isQuestionError(questionId: number): boolean {
  return props.errorQuestionIds?.includes(questionId) ?? false;
}

function handleUpdate(value: FormAnswer) {
  emit('update:answer', value.question_id, value);
}

function handleFileChange(questionId: number, file: File | null) {
  emit('file-change', questionId, file);
}
</script>

<template>
  <div class="space-y-4">
    <div class="border-b pb-2">
      <h3 class="text-foreground text-lg font-semibold">
        {{ section.title }}
      </h3>
      <p v-if="section.description" class="text-muted-foreground text-sm">
        {{ section.description }}
      </p>
    </div>
    <div class="space-y-4">
      <QuestionCard v-for="question in sortedQuestions" :key="question.id" :question="question"
        :model-value="getAnswer(question.id)" :is-error="isQuestionError(question.id)" :readonly="readonly"
        @update:model-value="handleUpdate" @file-change="handleFileChange" />
    </div>
  </div>
</template>
