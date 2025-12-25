<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input';
import type { FormAnswer, Question } from './types'

const props = defineProps<{
  question: Question
  modelValue: FormAnswer | undefined
  accept?: string
  readonly?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FormAnswer): void
  (e: 'fileChange', file: File | null): void
}>()

const acceptTypes = computed(() => props.accept ?? '.pdf,.doc,.docx,.jpg,.png')

function handleChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null

  emit('fileChange', file)
  emit('update:modelValue', {
    question_id: props.question.id,
    answer_text: file?.name ?? undefined,
  })
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <Input type="file" :accept="acceptTypes" :required="question.is_required" :disabled="readonly"
      @change="handleChange" />
    <p class="text-xs text-muted-foreground">
      Allowed formats: PDF, Word, images. Maximum size: 10MB.
    </p>
  </div>
</template>
