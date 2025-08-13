
<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogClose
} from 'reka-ui'
import {
  XIcon,
  EyeIcon
} from 'lucide-vue-next'
import { useEmailTemplate } from '@/composables/useEmailTemplate'

interface Props {
  open: boolean
  template?: any
  templateTypes: Record<string, string>
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { createTemplate, updateTemplate, previewTemplate } = useEmailTemplate()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const isEditing = computed(() => !!props.template && props.template.id > 0)

const form = reactive({
  name: '',
  type: '',
  subject: '',
  html_content: '',
  text_content: '',
  description: '',
  is_active: true
})

const errors = ref<Record<string, string[]>>({})
const isSubmitting = ref(false)
const isGeneratingPreview = ref(false)
const activeTab = ref('html')
const previewData = ref<any>(null)
const sampleVariables = ref<Record<string, string>>({})

const commonVariables = [
  { name: 'user_name', description: 'User full name' },
  { name: 'user_email', description: 'User email address' },
  { name: 'student_name', description: 'Student name' },
  { name: 'student_id', description: 'Student ID' },
  { name: 'course_name', description: 'Course name' },
  { name: 'course_code', description: 'Course code' },
  { name: 'semester', description: 'Current semester' },
  { name: 'grade', description: 'Grade/Score' },
  { name: 'deadline', description: 'Deadline date' },
  { name: 'system_name', description: 'System name' }
]

const extractedVariables = computed(() => {
  const content = form.subject + ' ' + form.html_content + ' ' + (form.text_content || '')
  const matches = content.match(/\{\{([^}]+)\}\}/g)
  if (!matches) return []

  return [...new Set(matches.map(match => match.slice(2, -2).trim()))]
})

// Watch for template changes
watch(() => props.template, (template) => {
  if (template) {
    Object.assign(form, {
      name: template.name || '',
      type: template.type || '',
      subject: template.subject || '',
      html_content: template.html_content || '',
      text_content: template.text_content || '',
      description: template.description || '',
      is_active: template.is_active ?? true
    })

    // Initialize sample variables
    if (template.variables) {
      const samples: Record<string, string> = {}
      template.variables.forEach((variable: string) => {
        samples[variable] = `[${variable}]`
      })
      sampleVariables.value = samples
    }
  } else {
    // Reset form for new template
    Object.assign(form, {
      name: '',
      type: '',
      subject: '',
      html_content: '',
      text_content: '',
      description: '',
      is_active: true
    })
    sampleVariables.value = {}
  }
  errors.value = {}
  previewData.value = null
  activeTab.value = 'html'
}, { immediate: true })

// Watch for variable changes to update sample variables
watch(extractedVariables, (newVariables) => {
  const samples: Record<string, string> = {}
  newVariables.forEach(variable => {
    samples[variable] = sampleVariables.value[variable] || `[${variable}]`
  })
  sampleVariables.value = samples
})

const generatePreview = async () => {
  if (!form.html_content) return

  isGeneratingPreview.value = true
  try {
    // Create a temporary template object for preview
    const tempTemplate = {
      subject: form.subject,
      html_content: form.html_content,
      text_content: form.text_content
    }

    // Use sample variables for preview
    const variables = { ...sampleVariables.value }

    // Simple client-side preview generation
    let subject = tempTemplate.subject
    let html = tempTemplate.html_content
    let text = tempTemplate.text_content || ''

    Object.entries(variables).forEach(([key, value]) => {
      const placeholder = `{{${key}}}`
      subject = subject.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value)
      html = html.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value)
      text = text.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value)
    })

    previewData.value = {
      subject,
      html,
      text: text || null
    }
  } catch (error) {
    console.error('Failed to generate preview:', error)
  } finally {
    isGeneratingPreview.value = false
  }
}

const handleSubmit = async () => {
  isSubmitting.value = true
  errors.value = {}

  try {
    if (isEditing.value) {
      await updateTemplate(props.template.id, form)
    } else {
      await createTemplate(form)
    }

    emit('saved')
  } catch (error: any) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      console.error('Failed to save template:', error)
    }
  } finally {
    isSubmitting.value = false
  }
}

const closeModal = () => {
  isOpen.value = false
}
</script>

<template>
  <DialogRoot v-model:open="isOpen">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" />
      <DialogContent class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
          <div>
            <div class="flex items-center justify-between">
              <DialogTitle class="text-lg font-semibold leading-6 text-gray-900">
                {{ isEditing ? 'Edit Email Template' : 'Create Email Template' }}
              </DialogTitle>
              <DialogClose class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <XIcon class="h-6 w-6" />
              </DialogClose>
            </div>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6">
              <!-- Basic Information -->
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700">
                    Template Name *
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.name }"
                    placeholder="e.g., Welcome Email, Grade Notification"
                  />
                  <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
                </div>

                <div>
                  <label for="type" class="block text-sm font-medium text-gray-700">
                    Template Type *
                  </label>
                  <select
                    id="type"
                    v-model="form.type"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.type }"
                  >
                    <option value="">Select type</option>
                    <option v-for="(label, value) in templateTypes" :key="value" :value="value">
                      {{ label }}
                    </option>
                  </select>
                  <p v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                  <label for="description" class="block text-sm font-medium text-gray-700">
                    Description
                  </label>
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="2"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.description }"
                    placeholder="Brief description of this template's purpose"
                  />
                  <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
                </div>
              </div>

              <!-- Subject Line -->
              <div>
                <label for="subject" class="block text-sm font-medium text-gray-700">
                  Subject Line *
                </label>
                <input
                  id="subject"
                  v-model="form.subject"
                  type="text"
                  required
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  :class="{ 'border-red-300': errors.subject }"
                  placeholder="Email subject line (use {{variable}} for dynamic content)"
                />
                <p v-if="errors.subject" class="mt-1 text-sm text-red-600">{{ errors.subject[0] }}</p>
              </div>

              <!-- Content Tabs -->
              <div>
                <div class="border-b border-gray-200">
                  <nav class="-mb-px flex space-x-8">
                    <button
                      type="button"
                      @click="activeTab = 'html'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'html'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]"
                    >
                      HTML Content
                    </button>
                    <button
                      type="button"
                      @click="activeTab = 'text'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'text'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]"
                    >
                      Plain Text
                    </button>
                    <button
                      type="button"
                      @click="activeTab = 'preview'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'preview'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]"
                    >
                      Preview
                    </button>
                    <button
                      type="button"
                      @click="activeTab = 'variables'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'variables'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]"
                    >
                      Variables ({{ extractedVariables.length }})
                    </button>
                  </nav>
                </div>

                <div class="mt-4">
                  <!-- HTML Content Tab -->
                  <div v-show="activeTab === 'html'" class="space-y-4">
                    <div>
                      <label for="html_content" class="block text-sm font-medium text-gray-700">
                        HTML Content *
                      </label>
                      <div class="mt-1">
                        <textarea
                          id="html_content"
                          v-model="form.html_content"
                          rows="12"
                          required
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                          :class="{ 'border-red-300': errors.html_content }"
                          placeholder="Enter HTML content here. Use {{variable_name}} for dynamic content."
                        />
                      </div>
                      <p class="mt-1 text-xs text-gray-500">
                        Use {{variable_name}} syntax for dynamic content. HTML tags are supported.
                      </p>
                      <p v-if="errors.html_content" class="mt-1 text-sm text-red-600">{{ errors.html_content[0] }}</p>
                    </div>
                  </div>

                  <!-- Plain Text Tab -->
                  <div v-show="activeTab === 'text'" class="space-y-4">
                    <div>
                      <label for="text_content" class="block text-sm font-medium text-gray-700">
                        Plain Text Content
                      </label>
                      <div class="mt-1">
                        <textarea
                          id="text_content"
                          v-model="form.text_content"
                          rows="12"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                          :class="{ 'border-red-300': errors.text_content }"
                          placeholder="Enter plain text version (optional). Use {{variable_name}} for dynamic content."
                        />
                      </div>
                      <p class="mt-1 text-xs text-gray-500">
                        Optional plain text version for email clients that don't support HTML.
                      </p>
                      <p v-if="errors.text_content" class="mt-1 text-sm text-red-600">{{ errors.text_content[0] }}</p>
                    </div>
                  </div>

                  <!-- Preview Tab -->
                  <div v-show="activeTab === 'preview'" class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                      <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-medium text-gray-900">Template Preview</h4>
                        <button
                          type="button"
                          @click="generatePreview"
                          :disabled="isGeneratingPreview"
                          class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                          <EyeIcon :class="['h-3 w-3 mr-1', { 'animate-spin': isGeneratingPreview }]" />
                          {{ isGeneratingPreview ? 'Generating...' : 'Generate Preview' }}
                        </button>
                      </div>

                      <div v-if="previewData" class="space-y-4">
                        <div>
                          <label class="block text-xs font-medium text-gray-700 mb-1">Subject:</label>
                          <div class="bg-white p-2 rounded border text-sm">{{ previewData.subject }}</div>
                        </div>
                        <div>
                          <label class="block text-xs font-medium text-gray-700 mb-1">HTML Content:</label>
                          <div class="bg-white p-4 rounded border max-h-64 overflow-y-auto" v-html="previewData.html"></div>
                        </div>
                        <div v-if="previewData.text">
                          <label class="block text-xs font-medium text-gray-700 mb-1">Plain Text:</label>
                          <div class="bg-white p-2 rounded border text-sm whitespace-pre-wrap font-mono">{{ previewData.text }}</div>
                        </div>
                      </div>

                      <div v-else class="text-center py-8 text-gray-500">
                        <EyeIcon class="mx-auto h-8 w-8 mb-2" />
                        <p class="text-sm">Click "Generate Preview" to see how your template will look</p>
                      </div>
                    </div>
                  </div>

                  <!-- Variables Tab -->
                  <div v-show="activeTab === 'variables'" class="space-y-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                      <h4 class="text-sm font-medium text-blue-900 mb-2">Detected Variables</h4>
                      <div v-if="extractedVariables.length > 0" class="space-y-2">
                        <div v-for="variable in extractedVariables" :key="variable" class="flex items-center justify-between bg-white p-2 rounded border">
                          <code class="text-sm text-blue-600">{{variable}}</code>
                          <input
                            v-model="sampleVariables[variable]"
                            type="text"
                            :placeholder="`Sample value for ${variable}`"
                            class="ml-2 flex-1 text-sm border-gray-300 rounded focus:border-indigo-500 focus:ring-indigo-500"
                          />
                        </div>
                      </div>
                      <div v-else class="text-sm text-blue-700">
                        No variables detected. Use {{variable_name}} syntax in your content to create dynamic placeholders.
                      </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                      <h4 class="text-sm font-medium text-gray-900 mb-2">Common Variables</h4>
                      <div class="grid grid-cols-2 gap-2 text-xs">
                        <div v-for="commonVar in commonVariables" :key="commonVar.name" class="flex items-center justify-between">
                          <code class="text-gray-600">{{ commonVar.name }}</code>
                          <span class="text-gray-500">{{ commonVar.description }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Active Status -->
              <div class="flex items-center">
                <input
                  id="is_active"
                  v-model="form.is_active"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                  Set as active template
                </label>
              </div>

              <!-- Form Actions -->
              <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <button
                  type="button"
                  @click="closeModal"
                  class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                >
                  {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update Template' : 'Create Template') }}
                </button>
              </div>
            </form>
          </div>
          </div>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
