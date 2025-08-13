<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">SMTP Configuration</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage email server settings and test connections
        </p>
      </div>
      <div class="flex items-center space-x-3">
        <button
          @click="refreshConfigurations"
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
          Add Configuration
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <ServerIcon class="h-6 w-6 text-gray-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Total Configurations
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ statistics.total_configurations || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <CheckCircleIcon class="h-6 w-6 text-green-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Active Configurations
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ statistics.active_configurations || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <TestTubeIcon class="h-6 w-6 text-blue-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Tested Configurations
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ statistics.tested_configurations || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <TrendingUpIcon class="h-6 w-6 text-green-400" />
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">
                  Success Rate
                </dt>
                <dd class="text-lg font-medium text-gray-900">
                  {{ statistics.test_success_rate || 0 }}%
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Configurations List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
      <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
          Email Configurations
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
          Manage your SMTP server configurations and test connections.
        </p>
      </div>

      <div v-if="isLoading" class="p-6">
        <div class="animate-pulse space-y-4">
          <div v-for="i in 3" :key="i" class="h-16 bg-gray-200 rounded"></div>
        </div>
      </div>

      <ul v-else-if="configurations.length > 0" class="divide-y divide-gray-200">
        <li v-for="config in configurations" :key="config.id" class="px-4 py-4 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div :class="[
                  'h-3 w-3 rounded-full',
                  config.is_active ? 'bg-green-400' : 'bg-gray-300'
                ]"></div>
              </div>
              <div class="ml-4">
                <div class="flex items-center">
                  <p class="text-sm font-medium text-gray-900">
                    {{ config.name }}
                  </p>
                  <span v-if="config.is_active" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                  </span>
                </div>
                <div class="mt-1 flex items-center text-sm text-gray-500">
                  <span>{{ config.host }}:{{ config.port }}</span>
                  <span class="mx-2">•</span>
                  <span>{{ config.from_address }}</span>
                  <span class="mx-2">•</span>
                  <span class="capitalize">{{ config.encryption }}</span>
                </div>
                <div v-if="config.last_tested_at" class="mt-1 flex items-center text-xs text-gray-400">
                  <ClockIcon class="h-3 w-3 mr-1" />
                  Last tested: {{ formatDate(config.last_tested_at) }}
                  <span v-if="config.test_result" class="ml-2">
                    <span :class="[
                      config.test_result === 'success' ? 'text-green-600' : 'text-red-600'
                    ]">
                      {{ config.test_result === 'success' ? '✓ Success' : '✗ Failed' }}
                    </span>
                  </span>
                </div>
              </div>
            </div>
            <div class="flex items-center space-x-2">
              <button
                @click="testConnection(config)"
                :disabled="testingConfigs.has(config.id)"
                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
              >
                <TestTubeIcon :class="['h-3 w-3 mr-1', { 'animate-spin': testingConfigs.has(config.id) }]" />
                Test
              </button>
              <button
                v-if="!config.is_active"
                @click="setActive(config)"
                :disabled="activatingConfigs.has(config.id)"
                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
              >
                <CheckCircleIcon :class="['h-3 w-3 mr-1', { 'animate-spin': activatingConfigs.has(config.id) }]" />
                Activate
              </button>
              <button
                @click="editConfiguration(config)"
                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <PencilIcon class="h-3 w-3 mr-1" />
                Edit
              </button>
              <button
                @click="deleteConfiguration(config)"
                :disabled="config.is_active"
                class="inline-flex items-center px-2.5 py-1.5 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <TrashIcon class="h-3 w-3 mr-1" />
                Delete
              </button>
            </div>
          </div>
        </li>
      </ul>

      <div v-else class="p-6 text-center">
        <ServerIcon class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-medium text-gray-900">No configurations</h3>
        <p class="mt-1 text-sm text-gray-500">
          Get started by creating your first SMTP configuration.
        </p>
        <div class="mt-6">
          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            <PlusIcon class="h-4 w-4 mr-2" />
            Add Configuration
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <SmtpConfigurationModal
      v-model:open="showCreateModal"
      :configuration="editingConfiguration"
      @saved="onConfigurationSaved"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-model:open="showDeleteModal"
      title="Delete Configuration"
      :message="`Are you sure you want to delete the configuration '${deletingConfiguration?.name}'? This action cannot be undone.`"
      confirm-text="Delete"
      confirm-variant="danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  RefreshCwIcon,
  PlusIcon,
  ServerIcon,
  CheckCircleIcon,
  TestTubeIcon,
  TrendingUpIcon,
  ClockIcon,
  PencilIcon,
  TrashIcon
} from 'lucide-vue-next'
import SmtpConfigurationModal from './components/SmtpConfigurationModal.vue'
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import { useSmtpConfiguration } from '@/composables/useSmtpConfiguration'

interface EmailConfiguration {
  id: number
  name: string
  host: string
  port: number
  username?: string
  encryption: string
  from_address: string
  from_name: string
  is_active: boolean
  daily_limit: number
  rate_limit: number
  last_tested_at?: string
  test_result?: string
}

interface Statistics {
  total_configurations: number
  active_configurations: number
  tested_configurations: number
  successful_tests: number
  test_success_rate: number
}

const {
  configurations,
  statistics,
  isLoading,
  isRefreshing,
  testingConfigs,
  activatingConfigs,
  loadConfigurations,
  testConnection,
  setActiveConfiguration,
  deleteConfiguration: deleteConfig
} = useSmtpConfiguration()

const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const editingConfiguration = ref<EmailConfiguration | null>(null)
const deletingConfiguration = ref<EmailConfiguration | null>(null)

onMounted(() => {
  loadConfigurations()
})

const refreshConfigurations = () => {
  loadConfigurations()
}

const editConfiguration = (config: EmailConfiguration) => {
  editingConfiguration.value = config
  showCreateModal.value = true
}

const deleteConfiguration = (config: EmailConfiguration) => {
  deletingConfiguration.value = config
  showDeleteModal.value = true
}

const confirmDelete = async () => {
  if (deletingConfiguration.value) {
    await deleteConfig(deletingConfiguration.value.id)
    deletingConfiguration.value = null
    showDeleteModal.value = false
  }
}

const setActive = (config: EmailConfiguration) => {
  setActiveConfiguration(config.id)
}

const onConfigurationSaved = () => {
  showCreateModal.value = false
  editingConfiguration.value = null
  loadConfigurations()
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}
</script>
