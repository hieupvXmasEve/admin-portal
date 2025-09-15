<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowLeft, Building, FileText, AlertCircle } from 'lucide-vue-next'
import DynamicForm from '@/components/forms/DynamicForm.vue'
import QueryTopicSelector from '../components/QueryTopicSelector.vue'
import { toast } from 'vue-sonner'
import type { Form, Question } from '@/types/forms'

interface Props {
    form: Form
    campus: any
}

const props = defineProps<Props>()

const formRef = ref()
const selectedTopic = ref<number | null>(null)
const customTopicText = ref('')

// Sample query topics - in real app these would come from backend
const queryTopics = ref([
    { id: 1, title: 'Academic', description: 'Course enrollment, grades, transcripts' },
    { id: 2, title: 'Financial', description: 'Tuition, fees, scholarships' },
    { id: 3, title: 'Technical', description: 'IT support, system access' },
    { id: 4, title: 'Administrative', description: 'Registration, documents' },
    { id: 0, title: 'Other', description: 'Other queries' }
])

const questions = computed(() => {
    return props.form.current_version?.questions || []
})

const questionCount = computed(() => {
    return questions.value.length
})

const requiredCount = computed(() => {
    return questions.value.filter(q => q.is_required).length
})

const getTypeBadgeVariant = (type: string) => {
    switch (type) {
        case 'feedback':
            return 'default'
        case 'survey':
            return 'secondary'
        case 'query':
            return 'outline'
        default:
            return 'default'
    }
}

const goBack = () => {
    router.visit(route('student.forms.index'))
}

const handleSubmit = async (data: any) => {
    // Add query-specific data if it's a query form
    if (props.form.type === 'query') {
        if (selectedTopic.value === 0) {
            data.custom_topic_text = customTopicText.value
        } else {
            data.topic_id = selectedTopic.value
        }
    }

    const form = useForm(data)

    form.post(route('student.forms.submit', props.form.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Form submitted successfully!')
        },
        onError: (errors) => {
            // Pass errors back to form component
            if (formRef.value) {
                formRef.value.setSubmitting(false)
                formRef.value.setErrors(errors)
            }
            toast.error('Please fix the errors and try again')
        },
        onFinish: () => {
            if (formRef.value) {
                formRef.value.setSubmitting(false)
            }
        }
    })
}
</script>
<template>
  <AppLayout :title="form.title">
    <div class="container mx-auto py-6 max-w-4xl">
      <!-- Page Header -->
      <div class="mb-6">
        <Button variant="ghost" size="sm" @click="goBack" class="mb-4">
          <ArrowLeft class="mr-2 h-4 w-4" />
          Back to Forms
        </Button>

        <div class="bg-card rounded-lg p-6 border">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h1 class="text-2xl font-bold">{{ form.title }}</h1>
              <p v-if="form.description" class="text-muted-foreground mt-2">
                {{ form.description }}
              </p>
            </div>
            <Badge :variant="getTypeBadgeVariant(form.type)">
              {{ form.type }}
            </Badge>
          </div>

          <!-- Form Info -->
          <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
            <div class="flex items-center gap-2">
              <Building class="h-4 w-4" />
              <span>{{ campus.name }}</span>
            </div>
            <div v-if="questionCount > 0" class="flex items-center gap-2">
              <FileText class="h-4 w-4" />
              <span>{{ questionCount }} questions</span>
            </div>
            <div v-if="requiredCount > 0" class="flex items-center gap-2">
              <AlertCircle class="h-4 w-4" />
              <span>{{ requiredCount }} required</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Query Topic Selection (for query forms) -->
      <div v-if="form.type === 'query'" class="mb-6">
        <Card>
          <CardHeader>
            <CardTitle>Query Topic</CardTitle>
            <CardDescription>
              Select the topic that best describes your query
            </CardDescription>
          </CardHeader>
          <CardContent>
            <QueryTopicSelector
              v-model="selectedTopic"
              :topics="queryTopics"
              @update:custom-topic="customTopicText = $event"
            />
          </CardContent>
        </Card>
      </div>

      <!-- Dynamic Form -->
      <div class="bg-card rounded-lg p-6 border">
        <DynamicForm
          ref="formRef"
          :form="form"
          :sections="form.current_version?.sections || []"
          :questions="questions"
          :allow-anonymous="form.type === 'feedback'"
          @submit="handleSubmit"
          @cancel="goBack"
        />
      </div>
    </div>
  </AppLayout>
</template>

