
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
  EyeIcon,
  EyeOffIcon,
  TestTubeIcon,
  CheckCircleIcon,
  XCircleIcon
} from 'lucide-vue-next'
import { useSmtpConfiguration } from '@/composables/useSmtpConfiguration'

interface Props {
  open: boolean
  configuration?: any
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { createConfiguration, updateConfiguration, testConnectionWithData } = useSmtpConfiguration()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const isEditing = computed(() => !!props.configuration)

const form = reactive({
  name: '',
  host: '',
  port: 587,
  username: '',
  password: '',
  encryption: 'tls',
  from_address: '',
  from_name: '',
  daily_limit: 1000,
  rate_limit: 50,
  is_active: false
})

const errors = ref<Record<string, string[]>>({})
const isSubmitting = ref(false)
const isTesting = ref(false)
const testResult = ref<any>(null)
const showPassword = ref(false)

const canTest = computed(() => {
  return form.host && form.port && form.from_address
})

// Watch for configuration changes
watch(() => props.configuration, (config) => {
  if (config) {
    Object.assign(form, {
      name: config.name || '',
      host: config.host || '',
      port: config.port || 587,
      username: config.username || '',
      password: '', // Don't populate password for security
      encryption: config.encryption || 'tls',
      from_address: config.from_address || '',
      from_name: config.from_name || '',
      daily_limit: config.daily_limit || 1000,
      rate_limit: config.rate_limit || 50,
      is_active: config.is_active || false
    })
  } else {
    // Reset form for new configuration
    Object.assign(form, {
      name: '',
      host: '',
      port: 587,
      username: '',
      password: '',
      encryption: 'tls',
      from_address: '',
      from_name: '',
      daily_limit: 1000,
      rate_limit: 50,
      is_active: false
    })
  }
  errors.value = {}
  testResult.value = null
}, { immediate: true })

const testConnection = async () => {
  if (!canTest.value) return

  isTesting.value = true
  testResult.value = null

  try {
    const result = await testConnectionWithData(form)
    testResult.value = result
  } catch (error) {
    testResult.value = {
      success: false,
      message: 'Failed to test connection',
      technical_details: error instanceof Error ? error.message : 'Unknown error'
    }
  } finally {
    isTesting.value = false
  }
}

const handleSubmit = async () => {
  isSubmitting.value = true
  errors.value = {}

  try {
    if (isEditing.value) {
      await updateConfiguration(props.configuration.id, form)
    } else {
      await createConfiguration(form)
    }

    emit('saved')
  } catch (error: any) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      // Handle general error
      console.error('Failed to save configuration:', error)
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
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">
          <div>
            <div class="flex items-center justify-between">
              <DialogTitle class="text-lg font-semibold leading-6 text-gray-900">
                {{ isEditing ? 'Edit SMTP Configuration' : 'Add SMTP Configuration' }}
              </DialogTitle>
              <DialogClose class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <XIcon class="h-6 w-6" />
              </DialogClose>
            </div>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6">
              <!-- Basic Information -->
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                  <label for="name" class="block text-sm font-medium text-gray-700">
                    Configuration Name *
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.name }"
                    placeholder="e.g., Production SMTP, Gmail SMTP"
                  />
                  <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
                </div>

                <div>
                  <label for="host" class="block text-sm font-medium text-gray-700">
                    SMTP Host *
                  </label>
                  <input
                    id="host"
                    v-model="form.host"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.host }"
                    placeholder="smtp.gmail.com"
                  />
                  <p v-if="errors.host" class="mt-1 text-sm text-red-600">{{ errors.host[0] }}</p>
                </div>

                <div>
                  <label for="port" class="block text-sm font-medium text-gray-700">
                    Port *
                  </label>
                  <input
                    id="port"
                    v-model.number="form.port"
                    type="number"
                    required
                    min="1"
                    max="65535"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.port }"
                    placeholder="587"
                  />
                  <p v-if="errors.port" class="mt-1 text-sm text-red-600">{{ errors.port[0] }}</p>
                </div>

                <div>
                  <label for="encryption" class="block text-sm font-medium text-gray-700">
                    Encryption *
                  </label>
                  <select
                    id="encryption"
                    v-model="form.encryption"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.encryption }"
                  >
                    <option value="">Select encryption</option>
                    <option value="tls">TLS (recommended)</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                  </select>
                  <p v-if="errors.encryption" class="mt-1 text-sm text-red-600">{{ errors.encryption[0] }}</p>
                </div>

                <div>
                  <label for="username" class="block text-sm font-medium text-gray-700">
                    Username
                  </label>
                  <input
                    id="username"
                    v-model="form.username"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.username }"
                    placeholder="your-email@domain.com"
                  />
                  <p v-if="errors.username" class="mt-1 text-sm text-red-600">{{ errors.username[0] }}</p>
                </div>
              </div>

              <!-- Authentication -->
              <div>
                <label for="password" class="block text-sm font-medium text-gray-700">
                  Password {{ isEditing ? '(leave blank to keep current)' : '*' }}
                </label>
                <div class="mt-1 relative">
                  <input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    :required="!isEditing"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-10"
                    :class="{ 'border-red-300': errors.password }"
                    placeholder="Enter SMTP password"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    <EyeIcon v-if="!showPassword" class="h-4 w-4 text-gray-400" />
                    <EyeOffIcon v-else class="h-4 w-4 text-gray-400" />
                  </button>
                </div>
                <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
              </div>

              <!-- From Information -->
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="from_address" class="block text-sm font-medium text-gray-700">
                    From Email Address *
                  </label>
                  <input
                    id="from_address"
                    v-model="form.from_address"
                    type="email"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.from_address }"
                    placeholder="noreply@yourdomain.com"
                  />
                  <p v-if="errors.from_address" class="mt-1 text-sm text-red-600">{{ errors.from_address[0] }}</p>
                </div>

                <div>
                  <label for="from_name" class="block text-sm font-medium text-gray-700">
                    From Name *
                  </label>
                  <input
                    id="from_name"
                    v-model="form.from_name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.from_name }"
                    placeholder="Your Organization"
                  />
                  <p v-if="errors.from_name" class="mt-1 text-sm text-red-600">{{ errors.from_name[0] }}</p>
                </div>
              </div>

              <!-- Limits -->
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="daily_limit" class="block text-sm font-medium text-gray-700">
                    Daily Limit *
                  </label>
                  <input
                    id="daily_limit"
                    v-model.number="form.daily_limit"
                    type="number"
                    required
                    min="1"
                    max="10000"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.daily_limit }"
                    placeholder="1000"
                  />
                  <p class="mt-1 text-xs text-gray-500">Maximum emails per day</p>
                  <p v-if="errors.daily_limit" class="mt-1 text-sm text-red-600">{{ errors.daily_limit[0] }}</p>
                </div>

                <div>
                  <label for="rate_limit" class="block text-sm font-medium text-gray-700">
                    Rate Limit *
                  </label>
                  <input
                    id="rate_limit"
                    v-model.number="form.rate_limit"
                    type="number"
                    required
                    min="1"
                    max="1000"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :class="{ 'border-red-300': errors.rate_limit }"
                    placeholder="50"
                  />
                  <p class="mt-1 text-xs text-gray-500">Emails per hour</p>
                  <p v-if="errors.rate_limit" class="mt-1 text-sm text-red-600">{{ errors.rate_limit[0] }}</p>
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
                  Set as active configuration
                </label>
              </div>

              <!-- Test Connection -->
              <div v-if="!isEditing || form.password" class="bg-gray-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="text-sm font-medium text-gray-900">Test Connection</h4>
                    <p class="text-sm text-gray-500">
                      Test the SMTP connection before saving
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="testConnection"
                    :disabled="isTesting || !canTest"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                  >
                    <TestTubeIcon :class="['h-4 w-4 mr-2', { 'animate-spin': isTesting }]" />
                    {{ isTesting ? 'Testing...' : 'Test Connection' }}
                  </button>
                </div>

                <div v-if="testResult" class="mt-3">
                  <div :class="[
                    'rounded-md p-3',
                    testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
                  ]">
                    <div class="flex">
                      <div class="flex-shrink-0">
                        <CheckCircleIcon v-if="testResult.success" class="h-5 w-5 text-green-400" />
                        <XCircleIcon v-else class="h-5 w-5 text-red-400" />
                      </div>
                      <div class="ml-3">
                        <p :class="[
                          'text-sm font-medium',
                          testResult.success ? 'text-green-800' : 'text-red-800'
                        ]">
                          {{ testResult.message }}
                        </p>
                        <p v-if="testResult.technical_details && !testResult.success" class="mt-1 text-xs text-red-700">
                          Technical details: {{ testResult.technical_details }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
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
                  {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update Configuration' : 'Create Configuration') }}
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
