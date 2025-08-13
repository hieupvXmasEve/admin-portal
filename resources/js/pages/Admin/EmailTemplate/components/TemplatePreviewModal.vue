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
                Template Preview: {{ template?.name }}
              </DialogTitle>
              <DialogClose class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <XIcon class="h-6 w-6" />
              </DialogClose>
            </div>

            <div v-if="template" class="mt-6">
              <!-- Template Info -->
              <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <span class="font-medium text-gray-700">Type:</span>
                    <span class="ml-2 capitalize">{{ template.type.replace('_', ' ') }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Version:</span>
                    <span class="ml-2">{{ template.version }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Status:</span>
                    <span :class="[
                      'ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                      template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    ]">
                      {{ template.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Variables:</span>
                    <span class="ml-2">{{ template.variables?.length || 0 }}</span>
                  </div>
                </div>
                <div v-if="template.description" class="mt-3">
                  <span class="font-medium text-gray-700">Description:</span>
                  <p class="mt-1 text-sm text-gray-600">{{ template.description }}</p>
                </div>
              </div>

              <!-- Variable Input -->
              <div v-if="template.variables && template.variables.length > 0" class="mb-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Sample Variables</h4>
                <div class="bg-blue-50 rounded-lg p-4">
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="variable in template.variables" :key="variable" class="flex items-center space-x-2">
                      <label :for="`var-${variable}`" class="text-sm font-medium text-gray-700 w-24 flex-shrink-0">
                        {{ variable }}:
                      </label>
                      <input
                        :id="`var-${variable}`"
                        v-model="sampleVariables[variable]"
                        type="text"
                        :placeholder="`Sample ${variable}`"
                        class="flex-1 text-sm border-gray-300 rounded focus:border-indigo-500 focus:ring-indigo-500"
                        @input="updatePreview"
                      />
                    </div>
                  </div>
                  <div class="mt-3 flex justify-end">
                    <button
                      @click="resetToDefaults"
                      class="text-xs text-indigo-600 hover:text-indigo-500"
                    >
                      Reset to defaults
                    </button>
                  </div>
                </div>
              </div>

              <!-- Preview Tabs -->
              <div>
                <div class="border-b border-gray-200">
                  <nav class="-mb-px flex space-x-8">
                    <button
                      type="button"
                      @click="activeTab = 'rendered'"
                      :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'rendered'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]"
                    >
                      Rendered Preview
                    </button>
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
                      HTML Source
                    </button>
                    <button
                      v-if="renderedPreview?.text"
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
                  </nav>
                </div>

                <div class="mt-4">
                  <!-- Rendered Preview -->
                  <div v-show="activeTab === 'rendered'" class="space-y-4">
                    <div v-if="renderedPreview">
                      <!-- Subject -->
                      <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Subject Line</h4>
                        <div class="bg-white p-3 rounded border text-sm font-medium">
                          {{ renderedPreview.subject }}
                        </div>
                      </div>

                      <!-- HTML Content -->
                      <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Email Content</h4>
                        <div class="bg-white p-4 rounded border max-h-96 overflow-y-auto">
                          <div v-html="renderedPreview.html"></div>
                        </div>
                      </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                      <EyeIcon class="mx-auto h-8 w-8 mb-2" />
                      <p class="text-sm">Loading preview...</p>
                    </div>
                  </div>

                  <!-- HTML Source -->
                  <div v-show="activeTab === 'html'" class="space-y-4">
                    <div v-if="renderedPreview">
                      <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">HTML Source</h4>
                        <pre class="bg-white p-4 rounded border text-xs overflow-x-auto"><code>{{ renderedPreview.html }}</code></pre>
                      </div>
                    </div>
                  </div>

                  <!-- Plain Text -->
                  <div v-show="activeTab === 'text'" class="space-y-4">
                    <div v-if="renderedPreview?.text">
                      <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Plain Text Version</h4>
                        <pre class="bg-white p-4 rounded border text-sm whitespace-pre-wrap">{{ renderedPreview.text }}</pre>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end space-x-3">
              <button
                @click="closeModal"
                class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              >
                Close
              </button>
            </div>
          </div>
          </div>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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

interface Props {
  open: boolean
  template?: any
  previewData?: any
}

interface Emits {
  (e: 'update:open', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const activeTab = ref('rendered')
const sampleVariables = ref<Record<string, string>>({})
const renderedPreview = ref<any>(null)

const defaultVariableValues: Record<string, string> = {
  user_name: 'John Doe',
  user_email: 'john.doe@example.com',
  student_name: 'Jane Smith',
  student_id: 'STU001',
  course_name: 'Introduction to Computer Science',
  course_code: 'CS101',
  semester: 'Fall 2024',
  grade: 'A',
  deadline: '2024-12-15',
  system_name: 'Academic Management System'
}

// Watch for template changes
watch(() => props.template, (template) => {
  if (template && template.variables) {
    const samples: Record<string, string> = {}
    template.variables.forEach((variable: string) => {
      samples[variable] = defaultVariableValues[variable] || `[${variable}]`
    })
    sampleVariables.value = samples
    updatePreview()
  }
}, { immediate: true })

// Watch for preview data changes
watch(() => props.previewData, (data) => {
  if (data) {
    renderedPreview.value = data
  }
}, { immediate: true })

const updatePreview = () => {
  if (!props.template) return

  // Simple client-side rendering
  let subject = props.template.subject || ''
  let html = props.template.html_content || ''
  let text = props.template.text_content || ''

  Object.entries(sampleVariables.value).forEach(([key, value]) => {
    const placeholder = `{{${key}}}`
    const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')
    subject = subject.replace(regex, value)
    html = html.replace(regex, value)
    text = text.replace(regex, value)
  })

  renderedPreview.value = {
    subject,
    html,
    text: text || null
  }
}

const resetToDefaults = () => {
  if (props.template && props.template.variables) {
    const samples: Record<string, string> = {}
    props.template.variables.forEach((variable: string) => {
      samples[variable] = defaultVariableValues[variable] || `[${variable}]`
    })
    sampleVariables.value = samples
    updatePreview()
  }
}

const closeModal = () => {
  isOpen.value = false
}
</script>
