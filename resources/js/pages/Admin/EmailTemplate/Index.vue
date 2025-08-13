<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Email Templates</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage email templates for automated notifications and communications
        </p>
      </div>
      <div class="flex items-center space-x-3">
        <button
          @click="refreshTemplates"
          :disabled="isRefreshing"
          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
          <RefreshCwIcon :class="['h-4 w-4 mr-2', { 'animate-spin': isRefreshing }]" />
          Refresh
        </button>
        <button
          @click="showCreateModal = true"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          <PlusIcon class="h-4 w-4 mr-2" />
          Create Template
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-4">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div>
          <label for="type-filter" class="block text-sm font-medium text-gray-700">
            Template Type
          </label>
          <select
            id="type-filter"
            v-model="filters.type"
            @change="applyFilters"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          >
            <option value="">All Types</option>
            <option v-for="(label, value) in templateTypes" :key="value" :value="value">
              {{ label }}
            </option>
          </select>
        </div>

        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700">
            Status
          </label>
          <select
            id="status-filter"
            v-model="filters.is_active"
            @change="applyFilters"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          >
            <option value="">All Status</option>
            <option :value="true">Active</option>
            <option :value="false">Inactive</option>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label for="search" class="block text-sm font-medium text-gray-700">
            Search
          </label>
          <div class="mt-1 relative">
            <input
              id="search"
              v-model="filters.search"
              @input="debouncedSearch"
              type="text"
              placeholder="Search templates..."
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pl-10"
            />
            <SearchIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          </div>
        </div>
      </div>
    </div>

    <!-- Templates List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
      <div v-if="isLoading" class="p-6">
        <div class="animate-pulse space-y-4">
          <div v-for="i in 5" :key="i" class="h-20 bg-gray-200 rounded"></div>
        </div>
      </div>

      <ul v-else-if="templates.data && templates.data.length > 0" class="divide-y divide-gray-200">
        <li v-for="template in templates.data" :key="template.id" class="px-4 py-4 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div :class="[
                  'h-10 w-10 rounded-lg flex items-center justify-center',
                  getTypeColor(template.type)
                ]">
                  <component :is="getTypeIcon(template.type)" class="h-5 w-5 text-white" />
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center">
                  <p class="text-sm font-medium text-gray-900 truncate">
                    {{ template.name }}
                  </p>
                  <span v-if="template.is_active" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                  </span>
                  <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    v{{ template.version }}
                  </span>
                </div>
                <div class="mt-1">
                  <p class="text-sm text-gray-600 truncate">{{ template.subject }}</p>
                </div>
                <div class="mt-1 flex items-center text-xs text-gray-500">
                  <span class="capitalize">{{ templateTypes[template.type] || template.type }}</span>
                  <span class="mx-2">•</span>
                  <span>{{ template.variables?.length || 0 }} variables</span>
                  <span class="mx-2">•</span>
                  <span>Updated {{ formatDate(template.updated_at) }}</span>
                </div>
                <div v-if="template.description" class="mt-1">
                  <p class="text-xs text-gray-500 truncate">{{ template.description }}</p>
                </div>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <button
                @click="previewTemplate(template)"
                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <EyeIcon class="h-3 w-3 mr-1" />
                Preview
              </button>
              <button
                @click="editTemplate(template)"
                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <PencilIcon class="h-3 w-3 mr-1" />
                Edit
              </button>
              <button
                @click="createVersion(template)"
                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <CopyIcon class="h-3 w-3 mr-1" />
                New Version
              </button>
              <button
                @click="deleteTemplate(template)"
                class="inline-flex items-center px-2.5 py-1.5 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
              >
                <TrashIcon class="h-3 w-3 mr-1" />
                Delete
              </button>
            </div>
          </div>
        </li>
      </ul>

      <div v-else class="p-6 text-center">
        <MailIcon class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-medium text-gray-900">No templates</h3>
        <p class="mt-1 text-sm text-gray-500">
          Get started by creating your first email template.
        </p>
        <div class="mt-6">
          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            <PlusIcon class="h-4 w-4 mr-2" />
            Create Template
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="templates.data && templates.data.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="flex-1 flex justify-between sm:hidden">
            <button
              @click="previousPage"
              :disabled="!templates.prev_page_url"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              @click="nextPage"
              :disabled="!templates.next_page_url"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Showing
                <span class="font-medium">{{ templates.from || 0 }}</span>
                to
                <span class="font-medium">{{ templates.to || 0 }}</span>
                of
                <span class="font-medium">{{ templates.total || 0 }}</span>
                results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <button
                  @click="previousPage"
                  :disabled="!templates.prev_page_url"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <button
                  @click="nextPage"
                  :disabled="!templates.next_page_url"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronRightIcon class="h-5 w-5" />
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <EmailTemplateModal
      v-model:open="showCreateModal"
      :template="editingTemplate"
      :template-types="templateTypes"
      @saved="onTemplateSaved"
    />

    <!-- Preview Modal -->
    <TemplatePreviewModal
      v-model:open="showPreviewModal"
      :template="previewingTemplate"
      :preview-data="previewData"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-model:open="showDeleteModal"
      title="Delete Template"
      :message="`Are you sure you want to delete the template '${deletingTemplate?.name}'? This action cannot be undone.`"
      confirm-text="Delete"
      confirm-variant="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import {
  RefreshCwIcon,
  PlusIcon,
  SearchIcon,
  EyeIcon,
  PencilIcon,
  CopyIcon,
  TrashIcon,
  MailIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  BellIcon,
  GraduationCapIcon,
  AlertTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  MegaphoneIcon,
  FileTextIcon
} from 'lucide-vue-next'
import EmailTemplateModal from './components/EmailTemplateModal.vue'
import TemplatePreviewModal from './components/TemplatePreviewModal.vue'
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import { useEmailTemplate } from '@/composables/useEmailTemplate'

interface EmailTemplate {
  id: number
  name: string
  type: string
  subject: string
  html_content: string
  text_content?: string
  variables?: string[]
  is_active: boolean
  version: number
  description?: string
  created_at: string
  updated_at: string
}

const {
  templates,
  templateTypes,
  isLoading,
  isRefreshing,
  loadTemplates,
  deleteTemplate: deleteTemplateAction,
  previewTemplate: previewTemplateAction
} = useEmailTemplate()

const filters = ref({
  type: '',
  is_active: '',
  search: ''
})

const showCreateModal = ref(false)
const showPreviewModal = ref(false)
const showDeleteModal = ref(false)
const editingTemplate = ref<EmailTemplate | null>(null)
const previewingTemplate = ref<EmailTemplate | null>(null)
const previewData = ref<any>(null)
const deletingTemplate = ref<EmailTemplate | null>(null)

onMounted(() => {
  loadTemplates()
})

const debouncedSearch = useDebounceFn(() => {
  applyFilters()
}, 300)

const applyFilters = () => {
  loadTemplates(filters.value)
}

const refreshTemplates = () => {
  loadTemplates(filters.value)
}

const editTemplate = (template: EmailTemplate) => {
  editingTemplate.value = template
  showCreateModal.value = true
}

const createVersion = (template: EmailTemplate) => {
  editingTemplate.value = { ...template, id: 0, version: template.version + 1 }
  showCreateModal.value = true
}

const previewTemplate = async (template: EmailTemplate) => {
  try {
    const preview = await previewTemplateAction(template.id)
    previewingTemplate.value = template
    previewData.value = preview
    showPreviewModal.value = true
  } catch (error) {
    console.error('Failed to preview template:', error)
  }
}

const deleteTemplate = (template: EmailTemplate) => {
  deletingTemplate.value = template
  showDeleteModal.value = true
}

const confirmDelete = async () => {
  if (deletingTemplate.value) {
    await deleteTemplateAction(deletingTemplate.value.id)
    deletingTemplate.value = null
    showDeleteModal.value = false
    loadTemplates(filters.value)
  }
}

const onTemplateSaved = () => {
  showCreateModal.value = false
  editingTemplate.value = null
  loadTemplates(filters.value)
}

const previousPage = () => {
  if (templates.value.prev_page_url) {
    loadTemplates(filters.value, templates.value.current_page - 1)
  }
}

const nextPage = () => {
  if (templates.value.next_page_url) {
    loadTemplates(filters.value, templates.value.current_page + 1)
  }
}

const getTypeColor = (type: string) => {
  const colors = {
    welcome: 'bg-blue-500',
    grade_notification: 'bg-green-500',
    course_registration: 'bg-purple-500',
    academic_hold: 'bg-red-500',
    enrollment_confirmation: 'bg-emerald-500',
    assessment_deadline: 'bg-orange-500',
    system_announcement: 'bg-indigo-500',
    reminder: 'bg-yellow-500',
    custom: 'bg-gray-500'
  }
  return colors[type as keyof typeof colors] || 'bg-gray-500'
}

const getTypeIcon = (type: string) => {
  const icons = {
    welcome: BellIcon,
    grade_notification: GraduationCapIcon,
    course_registration: FileTextIcon,
    academic_hold: AlertTriangleIcon,
    enrollment_confirmation: CheckCircleIcon,
    assessment_deadline: ClockIcon,
    system_announcement: MegaphoneIcon,
    reminder: ClockIcon,
    custom: FileTextIcon
  }
  return icons[type as keyof typeof icons] || FileTextIcon
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}
</script>
