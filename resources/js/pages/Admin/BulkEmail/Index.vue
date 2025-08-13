<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Bulk Email Composer</h1>
        <p class="mt-1 text-sm text-gray-500">
          Send emails to multiple recipients with template support and progress tracking
        </p>
      </div>
      <div class="flex items-center space-x-3">
        <button
          @click="showHistoryModal = true"
          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          <ClockIcon class="h-4 w-4 mr-2" />
          Email History
        </button>
      </div>
    </div>

    <!-- Composer Form -->
    <div class="bg-white shadow rounded-lg">
      <div class="px-4 py-5 sm:p-6">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Recipients Section -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recipients</h3>

            <!-- Recipient Selection Tabs -->
            <div class="border-b border-gray-200">
              <nav class="-mb-px flex space-x-8">
                <button
                  type="button"
                  @click="recipientMode = 'manual'"
                  :class="[
                    'py-2 px-1 border-b-2 font-medium text-sm',
                    recipientMode === 'manual'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  ]"
                >
                  Manual Entry
                </button>
                <button
                  type="button"
                  @click="recipientMode = 'groups'"
                  :class="[
                    'py-2 px-1 border-b-2 font-medium text-sm',
                    recipientMode === 'groups'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  ]"
                >
                  User Groups
                </button>
                <button
                  type="button"
                  @click="recipientMode = 'upload'"
                  :class="[
                    'py-2 px-1 border-b-2 font-medium text-sm',
                    recipientMode === 'upload'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  ]"
                >
                  Upload CSV
                </button>
              </nav>
            </div>

            <div class="mt-4">
              <!-- Manual Entry -->
              <div v-show="recipientMode === 'manual'" class="space-y-4">
                <div>
                  <label for="manual-recipients" class="block text-sm font-medium text-gray-700">
                    Email Addresses
                  </label>
                  <textarea
                    id="manual-recipients"
                    v-model="form.manualRecipients"
                    rows="6"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.recipients }"
                    placeholder="Enter email addresses, one per line or separated by commas"
                  />
                  <p class="mt-1 text-xs text-gray-500">
                    Enter one email address per line or separate multiple addresses with commas
                  </p>
                  <p v-if="errors.recipients" class="mt-1 text-sm text-red-600">{{ errors.recipients[0] }}</p>
                </div>
              </div>

              <!-- User Groups -->
              <div v-show="recipientMode === 'groups'" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      User Roles
                    </label>
                    <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md p-3">
                      <div v-for="role in userRoles" :key="role.id" class="flex items-center">
                        <input
                          :id="`role-${role.id}`"
                          v-model="form.selectedRoles"
                          :value="role.id"
                          type="checkbox"
                          class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label :for="`role-${role.id}`" class="ml-2 text-sm text-gray-700">
                          {{ role.name }} ({{ role.users_count || 0 }} users)
                        </label>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Campuses
                    </label>
                    <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md p-3">
                      <div v-for="campus in campuses" :key="campus.id" class="flex items-center">
                        <input
                          :id="`campus-${campus.id}`"
                          v-model="form.selectedCampuses"
                          :value="campus.id"
                          type="checkbox"
                          class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label :for="`campus-${campus.id}`" class="ml-2 text-sm text-gray-700">
                          {{ campus.name }} ({{ campus.users_count || 0 }} users)
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="bg-blue-50 rounded-lg p-3">
                  <p class="text-sm text-blue-700">
                    Selected groups will include: {{ getSelectedGroupsCount() }} recipients
                  </p>
                </div>
              </div>

              <!-- Upload CSV -->
              <div v-show="recipientMode === 'upload'" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Upload Recipients CSV
                  </label>
                  <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                      <UploadIcon class="mx-auto h-12 w-12 text-gray-400" />
                      <div class="flex text-sm text-gray-600">
                        <label for="csv-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                          <span>Upload a file</span>
                          <input
                            id="csv-upload"
                            @change="handleCsvUpload"
                            type="file"
                            accept=".csv,.txt"
                            class="sr-only"
                          />
                        </label>
                        <p class="pl-1">or drag and drop</p>
                      </div>
                      <p class="text-xs text-gray-500">CSV or TXT up to 10MB</p>
                    </div>
                  </div>
                  <div v-if="uploadedFile" class="mt-2 text-sm text-green-600">
                    ✓ {{ uploadedFile.name }} ({{ uploadedRecipients.length }} recipients)
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Email Content Section -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Email Content</h3>

            <div class="space-y-4">
              <!-- Template Selection -->
              <div>
                <label for="template" class="block text-sm font-medium text-gray-700">
                  Email Template (Optional)
                </label>
                <select
                  id="template"
                  v-model="form.templateId"
                  @change="onTemplateChange"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                  <option value="">Select a template or compose manually</option>
                  <option v-for="template in emailTemplates" :key="template.id" :value="template.id">
                    {{ template.name }} ({{ template.type }})
                  </option>
                </select>
              </div>

              <!-- Subject -->
              <div>
                <label for="subject" class="block text-sm font-medium text-gray-700">
                  Subject *
                </label>
                <input
                  id="subject"
                  v-model="form.subject"
                  type="text"
                  required
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  :class="{ 'border-red-300': errors.subject }"
                  placeholder="Email subject line"
                />
                <p v-if="errors.subject" class="mt-1 text-sm text-red-600">{{ errors.subject[0] }}</p>
              </div>

              <!-- Content -->
              <div>
                <label for="content" class="block text-sm font-medium text-gray-700">
                  Email Content *
                </label>
                <textarea
                  id="content"
                  v-model="form.content"
                  rows="12"
                  required
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  :class="{ 'border-red-300': errors.content }"
                  placeholder="Enter your email content here..."
                />
                <p v-if="errors.content" class="mt-1 text-sm text-red-600">{{ errors.content[0] }}</p>
              </div>

              <!-- Attachments -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Attachments (Optional)
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                  <div class="space-y-1 text-center">
                    <PaperclipIcon class="mx-auto h-8 w-8 text-gray-400" />
                    <div class="flex text-sm text-gray-600">
                      <label for="attachments" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                        <span>Upload files</span>
                        <input
                          id="attachments"
                          @change="handleAttachments"
                          type="file"
                          multiple
                          class="sr-only"
                        />
                      </label>
                    </div>
                    <p class="text-xs text-gray-500">Up to 10MB per file</p>
                  </div>
                </div>
                <div v-if="form.attachments.length > 0" class="mt-2 space-y-1">
                  <div v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">{{ file.name }}</span>
                    <button
                      type="button"
                      @click="removeAttachment(index)"
                      class="text-red-600 hover:text-red-500"
                    >
                      <XIcon class="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sending Options -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sending Options</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label for="chunk-size" class="block text-sm font-medium text-gray-700">
                  Batch Size
                </label>
                <select
                  id="chunk-size"
                  v-model="form.chunkSize"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                  <option :value="50">50 emails per batch</option>
                  <option :value="100">100 emails per batch</option>
                  <option :value="200">200 emails per batch</option>
                  <option :value="500">500 emails per batch</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                  Smaller batches are processed more reliably but take longer
                </p>
              </div>

              <div class="flex items-center">
                <input
                  id="send-test"
                  v-model="form.sendTest"
                  type="checkbox"
                  class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="send-test" class="ml-2 block text-sm text-gray-900">
                  Send test email to myself first
                </label>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-900 mb-2">Sending Summary</h4>
            <div class="text-sm text-gray-600 space-y-1">
              <p>Recipients: {{ getTotalRecipients() }}</p>
              <p>Estimated batches: {{ Math.ceil(getTotalRecipients() / form.chunkSize) }}</p>
              <p>Attachments: {{ form.attachments.length }}</p>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
            <button
              type="button"
              @click="previewEmail"
              class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <EyeIcon class="h-4 w-4 mr-2" />
              Preview
            </button>
            <button
              type="submit"
              :disabled="isSubmitting || getTotalRecipients() === 0"
              class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isSubmitting ? 'Sending...' : 'Send Emails' }}
              <SendIcon class="h-4 w-4 ml-2" />
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Progress Modal -->
    <BulkEmailProgressModal
      v-model:open="showProgressModal"
      :batch-id="currentBatchId"
      @complete="onSendingComplete"
    />

    <!-- Preview Modal -->
    <EmailPreviewModal
      v-model:open="showPreviewModal"
      :subject="form.subject"
      :content="form.content"
    />

    <!-- History Modal -->
    <EmailHistoryModal
      v-model:open="showHistoryModal"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import {
  ClockIcon,
  UploadIcon,
  PaperclipIcon,
  XIcon,
  EyeIcon,
  SendIcon
} from 'lucide-vue-next'
import BulkEmailProgressModal from './components/BulkEmailProgressModal.vue'
import EmailPreviewModal from './components/EmailPreviewModal.vue'
import EmailHistoryModal from './components/EmailHistoryModal.vue'
import { useBulkEmail } from '@/composables/useBulkEmail'

const {
  emailTemplates,
  userRoles,
  campuses,
  isSubmitting,
  loadEmailTemplates,
  loadUserGroups,
  sendBulkEmail
} = useBulkEmail()

const recipientMode = ref<'manual' | 'groups' | 'upload'>('manual')
const showProgressModal = ref(false)
const showPreviewModal = ref(false)
const showHistoryModal = ref(false)
const currentBatchId = ref<string | null>(null)
const uploadedFile = ref<File | null>(null)
const uploadedRecipients = ref<string[]>([])

const form = reactive({
  manualRecipients: '',
  selectedRoles: [] as number[],
  selectedCampuses: [] as number[],
  templateId: '',
  subject: '',
  content: '',
  attachments: [] as File[],
  chunkSize: 100,
  sendTest: false
})

const errors = ref<Record<string, string[]>>({})

onMounted(() => {
  loadEmailTemplates()
  loadUserGroups()
})

const getTotalRecipients = () => {
  switch (recipientMode.value) {
    case 'manual':
      return parseManualRecipients().length
    case 'groups':
      return getSelectedGroupsCount()
    case 'upload':
      return uploadedRecipients.value.length
    default:
      return 0
  }
}

const parseManualRecipients = () => {
  if (!form.manualRecipients.trim()) return []

  return form.manualRecipients
    .split(/[,\n]/)
    .map(email => email.trim())
    .filter(email => email && email.includes('@'))
}

const getSelectedGroupsCount = () => {
  let count = 0

  userRoles.value.forEach(role => {
    if (form.selectedRoles.includes(role.id)) {
      count += role.users_count || 0
    }
  })

  campuses.value.forEach(campus => {
    if (form.selectedCampuses.includes(campus.id)) {
      count += campus.users_count || 0
    }
  })

  return count
}

const handleCsvUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  uploadedFile.value = file

  const reader = new FileReader()
  reader.onload = (e) => {
    const text = e.target?.result as string
    const emails = text
      .split(/[,\n\r]/)
      .map(email => email.trim())
      .filter(email => email && email.includes('@'))

    uploadedRecipients.value = emails
  }
  reader.readAsText(file)
}

const handleAttachments = (event: Event) => {
  const files = Array.from((event.target as HTMLInputElement).files || [])
  form.attachments.push(...files)
}

const removeAttachment = (index: number) => {
  form.attachments.splice(index, 1)
}

const onTemplateChange = () => {
  if (form.templateId) {
    const template = emailTemplates.value.find(t => t.id === parseInt(form.templateId))
    if (template) {
      form.subject = template.subject
      form.content = template.html_content
    }
  }
}

const previewEmail = () => {
  showPreviewModal.value = true
}

const handleSubmit = async () => {
  errors.value = {}

  // Validate recipients
  const recipients = getRecipients()
  if (recipients.length === 0) {
    errors.value.recipients = ['Please select at least one recipient']
    return
  }

  try {
    const result = await sendBulkEmail({
      recipients,
      subject: form.subject,
      content: form.content,
      template_id: form.templateId || undefined,
      attachments: form.attachments,
      chunk_size: form.chunkSize
    })

    currentBatchId.value = result.batch_id
    showProgressModal.value = true
  } catch (error: any) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      console.error('Failed to send bulk email:', error)
    }
  }
}

const getRecipients = (): string[] => {
  switch (recipientMode.value) {
    case 'manual':
      return parseManualRecipients()
    case 'groups':
      // This would need to be implemented to fetch actual email addresses from selected groups
      return []
    case 'upload':
      return uploadedRecipients.value
    default:
      return []
  }
}

const onSendingComplete = () => {
  showProgressModal.value = false
  currentBatchId.value = null

  // Reset form
  Object.assign(form, {
    manualRecipients: '',
    selectedRoles: [],
    selectedCampuses: [],
    templateId: '',
    subject: '',
    content: '',
    attachments: [],
    chunkSize: 100,
    sendTest: false
  })

  uploadedFile.value = null
  uploadedRecipients.value = []
}
</script>
