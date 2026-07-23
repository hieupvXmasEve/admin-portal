<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useApi } from '@/composables/useApiRequest'
import { useSystemConfig } from '@/composables/useSystemConfig'
import type { SystemConfig } from '@/types/systemConfig'
import { Head } from '@inertiajs/vue3'
import { Save, Settings, Upload, Image } from 'lucide-vue-next'
import { computed, reactive, ref, watch } from 'vue'

interface Props {
    config: SystemConfig
    permissions: {
        can_manage: boolean
    }
}

const props = defineProps<Props>()

// System config composable for auto-refresh
const { loadConfig } = useSystemConfig()
const api = useApi()

// Form state
const form = reactive<SystemConfig>({
    app_name: '',
    logo_full: '',
    logo_text: '',
    copyright_text: '',
    country: ''
})

const isLoading = ref(false)
const isSaving = ref(false)
const saveMessage = ref('')
const saveMessageType = ref<'success' | 'error'>('success')

// File upload states
const isUploadingLogoFull = ref(false)
const isUploadingLogoText = ref(false)

// Cache busting for images
const imageCacheBuster = ref(Date.now())

// Initialize form with props
watch(() => props.config, (newConfig) => {
    Object.assign(form, newConfig)
}, { immediate: true })

// Check if form has changes
const hasChanges = computed(() => {
    return Object.keys(form).some(key => {
        const formKey = key as keyof SystemConfig
        return form[formKey] !== props.config[formKey]
    })
})

const handleSave = async () => {
    if (!hasChanges.value) {
        saveMessage.value = 'No changes to save'
        saveMessageType.value = 'error'
        setTimeout(() => {
            saveMessage.value = ''
        }, 3000)
        return
    }

    try {
        isSaving.value = true
        saveMessage.value = ''

        const response = await api.put<SystemConfig>('/api/system-config', form)
        console.log('%c response.data', 'color: red', response.data.value);
        if (response.data && response.data.value.success) {
            saveMessage.value = response.data.value.message || 'Configuration saved successfully'
            saveMessageType.value = 'success'

            // Refresh the system config globally
            await loadConfig()
        } else {
            throw new Error(response.data?.value.message || 'Failed to save configuration')
        }
    } catch (error) {
        console.error('Failed to save config:', error)
        saveMessage.value = error instanceof Error ? error.message : 'Failed to save configuration'
        saveMessageType.value = 'error'
    } finally {
        isSaving.value = false

        // Clear message after 5 seconds
        setTimeout(() => {
            saveMessage.value = ''
        }, 5000)
    }
}

const handleReset = () => {
    Object.assign(form, props.config)
    saveMessage.value = 'Form reset to original values'
    saveMessageType.value = 'success'
    setTimeout(() => {
        saveMessage.value = ''
    }, 3000)
}

// File upload handlers
const handleFileUpload = async (file: File, configKey: string) => {
    const isLogoFull = configKey === 'logo_full'
    const uploadingRef = isLogoFull ? isUploadingLogoFull : isUploadingLogoText

    try {
        uploadingRef.value = true
        saveMessage.value = ''

        const formData = new FormData()
        formData.append('file', file)
        formData.append('config_key', configKey)

        const response = await fetch('/api/system-config/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include'
        })

        const result = await response.json()

        if (result.success) {
            saveMessage.value = result.message || 'File uploaded successfully'
            saveMessageType.value = 'success'

            // Update cache buster to force image refresh
            imageCacheBuster.value = Date.now()

            // Refresh the system config globally to reflect changes
            await loadConfig()

            // Force refresh all images on the page by updating their src
            setTimeout(() => {
                const images = document.querySelectorAll('img[src*="/storage/branding/"]')
                images.forEach((img) => {
                    const htmlImg = img as HTMLImageElement
                    const src = htmlImg.src.split('?')[0] // Remove existing query params
                    htmlImg.src = `${src}?t=${imageCacheBuster.value}`
                })
            }, 100)
        } else {
            throw new Error(result.message || 'Failed to upload file')
        }
    } catch (error) {
        console.error('Failed to upload file:', error)
        saveMessage.value = error instanceof Error ? error.message : 'Failed to upload file'
        saveMessageType.value = 'error'
    } finally {
        uploadingRef.value = false

        setTimeout(() => {
            saveMessage.value = ''
        }, 5000)
    }
}

const handleLogoFullUpload = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (file) {
        handleFileUpload(file, 'logo_full')
    }
}

const handleLogoTextUpload = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (file) {
        handleFileUpload(file, 'logo_text')
    }
}
</script>

<template>
    <Head title="System Configuration" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <Settings class="h-6 w-6" />
                    System Configuration
                </h1>
                <p class="text-muted-foreground">
                    Manage global application settings and branding
                </p>
            </div>
        </div>

        <!-- Save Message -->
        <div
            v-if="saveMessage"
            :class="[
                'rounded-md border p-4',
                saveMessageType === 'success'
                    ? 'border-green-200 bg-green-50 text-green-800'
                    : 'border-red-200 bg-red-50 text-red-800'
            ]"
        >
            {{ saveMessage }}
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Application Settings</CardTitle>
                <CardDescription>
                    Configure basic application information and branding
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <form @submit.prevent="handleSave" class="space-y-6">
                    <!-- App Name -->
                    <div class="space-y-2">
                        <Label for="app_name">Application Name</Label>
                        <Input
                            id="app_name"
                            v-model="form.app_name"
                            placeholder="Enter application name"
                            :disabled="isSaving || !props.permissions.can_manage"
                        />
                        <p class="text-sm text-muted-foreground">
                            The main application name displayed throughout the system
                        </p>
                    </div>

                    <!-- Logo Full Upload -->
                    <div class="space-y-2">
                        <Label for="logo_full_file">Full Logo Upload</Label>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <input
                                    id="logo_full_file"
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml"
                                    @change="handleLogoFullUpload"
                                    :disabled="isUploadingLogoFull || isSaving || !props.permissions.can_manage"
                                    class="block w-full text-sm text-slate-500
                                           file:mr-4 file:py-2 file:px-4
                                           file:rounded-full file:border-0
                                           file:text-sm file:font-semibold
                                           file:bg-violet-50 file:text-violet-700
                                           hover:file:bg-violet-100
                                           disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="isUploadingLogoFull || isSaving || !props.permissions.can_manage"
                                class="min-w-24"
                            >
                                <Upload v-if="!isUploadingLogoFull" class="mr-2 h-4 w-4" />
                                <div v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                {{ isUploadingLogoFull ? 'Uploading...' : 'Upload' }}
                            </Button>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-muted-foreground">Current:</span>
                            <img
                                v-if="form.logo_full"
                                :src="`${form.logo_full}?v=${imageCacheBuster}`"
                                alt="Full Logo"
                                class="h-28 w-auto object-contain"
                                @error="() => {}"
                            />
                            <span v-else class="text-sm text-muted-foreground">No logo set</span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Upload a new full logo image. Supported formats: PNG, JPG, GIF, SVG (max 2MB)
                        </p>
                    </div>

                    <!-- Logo Text Upload -->
                    <div class="space-y-2">
                        <Label for="logo_text_file">Logo SVG Upload</Label>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <input
                                    id="logo_text_file"
                                    type="file"
                                    accept="image/svg+xml"
                                    @change="handleLogoTextUpload"
                                    :disabled="isUploadingLogoText || isSaving || !props.permissions.can_manage"
                                    class="block w-full text-sm text-slate-500
                                           file:mr-4 file:py-2 file:px-4
                                           file:rounded-full file:border-0
                                           file:text-sm file:font-semibold
                                           file:bg-violet-50 file:text-violet-700
                                           hover:file:bg-violet-100
                                           disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="isUploadingLogoText || isSaving || !props.permissions.can_manage"
                                class="min-w-24"
                            >
                                <Upload v-if="!isUploadingLogoText" class="mr-2 h-4 w-4" />
                                <div v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                {{ isUploadingLogoText ? 'Uploading...' : 'Upload' }}
                            </Button>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-muted-foreground">Current:</span>
                            <img
                                v-if="form.logo_text"
                                :src="`${form.logo_text}?v=${imageCacheBuster}`"
                                alt="Text Logo"
                                class="h-28 w-auto object-contain"
                                @error="() => {}"
                            />
                            <span v-else class="text-sm text-muted-foreground">No logo set</span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Upload a new text logo image. Supported formats: SVG (max 2MB)
                        </p>
                    </div>

                    <!-- Copyright Text -->
                    <div class="space-y-2">
                        <Label for="copyright_text">Copyright Text</Label>
                        <Textarea
                            id="copyright_text"
                            v-model="form.copyright_text"
                            placeholder="© 2025 Your Organization. All rights reserved."
                            :disabled="isSaving || !props.permissions.can_manage"
                            rows="3"
                        />
                        <p class="text-sm text-muted-foreground">
                            Copyright notice displayed in footers and legal pages
                        </p>
                    </div>

                    <!-- Country -->
                    <div class="space-y-2">
                        <Label for="country">Country</Label>
                        <Input
                            id="country"
                            v-model="form.country"
                            placeholder="Vietnam"
                            :disabled="isSaving || !props.permissions.can_manage"
                        />
                        <p class="text-sm text-muted-foreground">
                            Country name for localization and display purposes
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 pt-6 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            @click="handleReset"
                            :disabled="isSaving || !hasChanges || !props.permissions.can_manage"
                        >
                            Reset
                        </Button>

                        <Button
                            type="submit"
                            :disabled="isSaving || !hasChanges || !props.permissions.can_manage"
                            class="min-w-24"
                        >
                            <Save v-if="!isSaving" class="mr-2 h-4 w-4" />
                            <div v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Current Values Preview -->
        <Card>
            <CardHeader>
                <CardTitle>Current Configuration Preview</CardTitle>
                <CardDescription>
                    Preview of how the current settings appear in the application
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div>
                        <Label class="text-sm font-medium">App Name Display:</Label>
                        <p class="text-2xl font-bold">{{ form.app_name || 'Not Set' }}</p>
                    </div>

                    <div>
                        <Label class="text-sm font-medium">Logo Paths:</Label>
                        <div class="mt-2 space-y-1">
                            <p class="text-sm"><span class="font-medium">Full:</span> {{ form.logo_full || 'Not Set' }}</p>
                            <p class="text-sm"><span class="font-medium">Text:</span> {{ form.logo_text || 'Not Set' }}</p>
                        </div>
                    </div>

                    <div>
                        <Label class="text-sm font-medium">Copyright Notice:</Label>
                        <p class="text-sm text-muted-foreground">{{ form.copyright_text || 'Not Set' }}</p>
                    </div>

                    <div>
                        <Label class="text-sm font-medium">Country:</Label>
                        <p class="text-sm">🇻🇳 {{ form.country || 'Not Set' }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
