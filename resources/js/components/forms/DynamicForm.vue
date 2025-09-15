<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Form Sections -->
    <div v-for="section in sections" :key="section.id" class="space-y-4">
      <div v-if="section.title" class="border-b pb-2">
        <h3 class="text-lg font-semibold">{{ section.title }}</h3>
        <p v-if="section.description" class="text-sm text-muted-foreground mt-1">
          {{ section.description }}
        </p>
      </div>

      <!-- Questions in Section -->
      <div v-for="question in getQuestionsForSection(section.id)" :key="question.id" class="space-y-2">
        <DynamicQuestion
          :question="question"
          :model-value="formData[question.id]"
          :errors="errors[`answers.${question.id}`]"
          @update:model-value="updateAnswer(question.id, $event)"
        />
      </div>
    </div>

    <!-- Questions without sections -->
    <div v-if="questionsWithoutSection.length > 0" class="space-y-4">
      <div v-for="question in questionsWithoutSection" :key="question.id" class="space-y-2">
        <DynamicQuestion
          :question="question"
          :model-value="formData[question.id]"
          :errors="errors[`answers.${question.id}`]"
          @update:model-value="updateAnswer(question.id, $event)"
        />
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-between pt-6 border-t">
      <div class="flex items-center gap-4">
        <Checkbox
          v-if="allowAnonymous"
          v-model:checked="isAnonymous"
          id="anonymous"
        />
        <Label v-if="allowAnonymous" for="anonymous" class="text-sm font-medium">
          Submit anonymously
        </Label>
      </div>

      <div class="flex gap-3">
        <Button type="button" variant="outline" @click="handleCancel">
          Cancel
        </Button>
        <Button type="submit" :disabled="isSubmitting">
          <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
          {{ isSubmitting ? 'Submitting...' : 'Submit' }}
        </Button>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Loader2 } from 'lucide-vue-next'
import DynamicQuestion from './DynamicQuestion.vue'
import type { Form, FormSection, Question } from '@/types/forms'

interface Props {
  form: Form
  sections?: FormSection[]
  questions: Question[]
  allowAnonymous?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  allowAnonymous: false,
  sections: () => []
})

const emit = defineEmits<{
  submit: [data: any]
  cancel: []
}>()

const formData = ref<Record<string, any>>({})
const errors = ref<Record<string, string>>({})
const isSubmitting = ref(false)
const isAnonymous = ref(false)

// Initialize form data with default values
props.questions.forEach(question => {
  if (question.type === 'multi_choice') {
    formData.value[question.id] = []
  } else if (question.type === 'yes_no') {
    formData.value[question.id] = null
  } else {
    formData.value[question.id] = ''
  }
})

const questionsWithoutSection = computed(() => {
  return props.questions.filter(q => !q.section_id)
})

const getQuestionsForSection = (sectionId: number) => {
  return props.questions.filter(q => q.section_id === sectionId)
}

const updateAnswer = (questionId: number, value: any) => {
  formData.value[questionId] = value
  // Clear error for this field when user starts typing
  if (errors.value[`answers.${questionId}`]) {
    delete errors.value[`answers.${questionId}`]
  }
}

const validateForm = (): boolean => {
  errors.value = {}
  let isValid = true

  props.questions.forEach(question => {
    if (question.is_required) {
      const answer = formData.value[question.id]
      
      if (answer === null || answer === undefined || answer === '' || 
          (Array.isArray(answer) && answer.length === 0)) {
        errors.value[`answers.${question.id}`] = `This field is required`
        isValid = false
      }
    }

    // Additional validation based on question type
    const answer = formData.value[question.id]
    if (answer && question.validation_rules) {
      // Validate based on validation rules
      if (question.type === 'short_text' || question.type === 'long_text') {
        if (question.validation_rules.maxLength && answer.length > question.validation_rules.maxLength) {
          errors.value[`answers.${question.id}`] = `Maximum length is ${question.validation_rules.maxLength} characters`
          isValid = false
        }
        if (question.validation_rules.minLength && answer.length < question.validation_rules.minLength) {
          errors.value[`answers.${question.id}`] = `Minimum length is ${question.validation_rules.minLength} characters`
          isValid = false
        }
      }

      if (question.type === 'number' || question.type === 'rating') {
        if (question.validation_rules.min !== undefined && answer < question.validation_rules.min) {
          errors.value[`answers.${question.id}`] = `Minimum value is ${question.validation_rules.min}`
          isValid = false
        }
        if (question.validation_rules.max !== undefined && answer > question.validation_rules.max) {
          errors.value[`answers.${question.id}`] = `Maximum value is ${question.validation_rules.max}`
          isValid = false
        }
      }
    }
  })

  return isValid
}

const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }

  isSubmitting.value = true

  const submitData = {
    answers: formData.value,
    anonymized: isAnonymous.value,
    target_scope_type: 'global', // This could be dynamic based on context
    origin: 'web'
  }

  emit('submit', submitData)
  
  // The parent component will handle the actual submission
  // and reset isSubmitting when done
}

const handleCancel = () => {
  emit('cancel')
}

// Allow parent to control submission state
defineExpose({
  setSubmitting: (value: boolean) => {
    isSubmitting.value = value
  },
  setErrors: (newErrors: Record<string, string>) => {
    errors.value = newErrors
  }
})
</script>