<script setup lang="ts">
import { ref, computed, onUnmounted, watch, onMounted } from 'vue';
import { useImageUpload } from '@/composables/useImageUpload';
import { useUploadConfig } from '@/composables/useUploadConfig';
import type { ImageUploadOptions, UploadRecord } from '@/types/imageUpload';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import Icon from '@/components/Icon.vue';

// Props
interface Props {
    context: string;
    maxSize?: number;
    allowedTypes?: string[];
    multiple?: boolean;
    immediate?: boolean;
    disabled?: boolean;
    class?: string;
    showConfig?: boolean;
    autoLoadConfig?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    immediate: false,
    disabled: false,
    showConfig: true,
    autoLoadConfig: true,
});

// Emits
const emit = defineEmits<{
    'upload-start': [];
    'upload-progress': [percentage: number];
    'upload-success': [files: UploadRecord[]];
    'upload-error': [error: string];
    'files-selected': [count: number];
    'file-removed': [id: string];
    'config-loaded': [];
    'config-error': [error: string];
}>();

// Refs
const fileInput = ref<HTMLInputElement>();
const dropZone = ref<HTMLElement>();
const isDragOver = ref(false);

// Composables
const {
    config: uploadConfig,
    isLoading: configLoading,
    error: configError,
    fetchConfig,
    getContextConfig,
    getMaxSize,
    getAllowedTypes,
    isValidContext,
    getMaxSizeText,
    getValidationRules,
} = useUploadConfig();

// Computed configuration values
const effectiveMaxSize = computed(() => {
    if (props.maxSize) return props.maxSize;
    return getMaxSize(props.context);
});

const effectiveAllowedTypes = computed(() => {
    if (props.allowedTypes) return props.allowedTypes;
    return getAllowedTypes(props.context);
});

const uploadOptions = computed<ImageUploadOptions>(() => ({
    context: props.context,
    maxSize: effectiveMaxSize.value,
    allowedTypes: effectiveAllowedTypes.value,
    multiple: props.multiple,
    immediate: props.immediate,
}));

const {
    state,
    filePreviews,
    validationErrors,
    config,
    isValid,
    hasFiles,
    canUpload,
    uploadStats,
    selectFiles,
    uploadFiles,
    uploadSingleFileById,
    retryFailedUploads,
    removeFile,
    clearFiles,
    formatFileSize,
    cleanup
} = useImageUpload(uploadOptions.value);

// Computed
const acceptedFileTypes = computed(() => effectiveAllowedTypes.value.join(','));

const dragOverClass = computed(() => ({
    'border-blue-500 bg-blue-50': isDragOver.value && !props.disabled,
    'border-red-500 bg-red-50': isDragOver.value && !isValid.value && !props.disabled,
}));

const uploadButtonText = computed(() => {
    if (state.isUploading) return 'Uploading...';
    if (props.multiple) return 'Upload Images';
    return 'Upload Image';
});

const maxSizeText = computed(() => {
    if (uploadConfig.value) {
        return getMaxSizeText(props.context);
    }
    return formatFileSize(effectiveMaxSize.value);
});

const contextDisplayName = computed(() => {
    return props.context.charAt(0).toUpperCase() + props.context.slice(1);
});

const allowedTypesDisplay = computed(() => {
    return effectiveAllowedTypes.value
        .map(type => type.split('/')[1].toUpperCase())
        .join(', ');
});

const isContextValid = computed(() => {
    if (!uploadConfig.value) return true; // Allow if config not loaded
    return isValidContext(props.context);
});

const showConfigInfo = computed(() => {
    return props.showConfig && uploadConfig.value && !configLoading.value;
});

// Methods
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectFiles(target.files);
        emit('files-selected', target.files.length);
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = false;

    if (props.disabled) return;

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        selectFiles(files);
        emit('files-selected', files.length);
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (!props.disabled) {
        isDragOver.value = true;
    }
};

const handleDragLeave = (event: DragEvent) => {
    event.preventDefault();
    // Only set to false if we're leaving the drop zone entirely
    if (!dropZone.value?.contains(event.relatedTarget as Node)) {
        isDragOver.value = false;
    }
};

const triggerFileSelect = () => {
    if (!props.disabled) {
        fileInput.value?.click();
    }
};

const handleUpload = async () => {
    if (!canUpload.value) return;

    try {
        emit('upload-start');
        const uploadedFiles = await uploadFiles();
        emit('upload-success', uploadedFiles);
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Upload failed';
        emit('upload-error', errorMessage);
    }
};

const handleRemoveFile = (id: string) => {
    removeFile(id);
    emit('file-removed', id);
};

const handleClearAll = () => {
    clearFiles();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const copyUrlToClipboard = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
        // You could emit an event or show a toast here
    } catch (error) {
        console.error('Failed to copy URL:', error);
    }
};

// Load configuration on mount
onMounted(async () => {
    if (props.autoLoadConfig) {
        try {
            await fetchConfig();
            emit('config-loaded');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to load configuration';
            emit('config-error', errorMessage);
        }
    }
});

// Watch for progress changes
const unwatchProgress = watch(
    () => state.progress.percentage,
    (percentage) => {
        emit('upload-progress', percentage);
    }
);

// Watch for configuration errors
watch(configError, (error) => {
    if (error) {
        emit('config-error', error);
    }
});

// Cleanup on unmount
onUnmounted(() => {
    cleanup();
    unwatchProgress();
});
</script>

<template>
    <div :class="props.class">
        <!-- Configuration loading state -->
        <div v-if="configLoading" class="flex items-center justify-center p-6 text-sm text-gray-600">
            <Icon name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
            Loading upload configuration...
        </div>

        <!-- Configuration error -->
        <div v-else-if="configError" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 mb-4">
            <div class="flex items-center">
                <Icon name="alert-triangle" class="mr-2 h-4 w-4 text-yellow-600" />
                <p class="text-sm text-yellow-800">
                    Configuration warning: {{ configError }}
                </p>
            </div>
            <p class="mt-1 text-xs text-yellow-700">
                Using default settings. Upload functionality may be limited.
            </p>
        </div>

        <!-- Invalid context warning -->
        <div v-else-if="!isContextValid" class="rounded-lg border border-red-200 bg-red-50 p-4 mb-4">
            <div class="flex items-center">
                <Icon name="alert-circle" class="mr-2 h-4 w-4 text-red-600" />
                <p class="text-sm text-red-800">
                    Invalid upload context: "{{ props.context }}"
                </p>
            </div>
            <p class="mt-1 text-xs text-red-700">
                Please use a valid context or configure this context in the upload settings.
            </p>
        </div>

        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            :accept="acceptedFileTypes"
            :multiple="multiple"
            :disabled="disabled || configLoading || !isContextValid"
            class="hidden"
            @change="handleFileSelect"
        />

        <!-- Drop zone -->
        <div
            v-if="!configLoading && isContextValid"
            ref="dropZone"
            class="relative rounded-lg border-2 border-dashed border-gray-300 p-6 transition-colors"
            :class="[dragOverClass, { 'opacity-50 cursor-not-allowed': disabled }]"
            @drop="handleDrop"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
        >
            <!-- Upload area -->
            <div v-if="!hasFiles" class="text-center">
                <Icon
                    name="image"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-900">
                        {{ multiple ? 'Drop images here or click to select' : 'Drop an image here or click to select' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-600">
                        {{ allowedTypesDisplay }} up to {{ maxSizeText }}
                    </p>
                    <p v-if="showConfigInfo" class="mt-1 text-xs text-gray-500">
                        Context: {{ contextDisplayName }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="mt-4"
                    :disabled="disabled"
                    @click="triggerFileSelect"
                >
                    <Icon name="upload" class="mr-2 h-4 w-4" />
                    Select {{ multiple ? 'Images' : 'Image' }}
                </Button>
            </div>

            <!-- File previews -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <h3 class="text-sm font-medium text-gray-900">
                            Selected {{ multiple ? 'Images' : 'Image' }} ({{ filePreviews.length }})
                        </h3>

                        <!-- Upload statistics for multiple files -->
                        <div v-if="multiple && uploadStats.total > 0" class="flex items-center space-x-2">
                            <Badge v-if="uploadStats.success > 0" variant="default" class="bg-green-100 text-green-800 text-xs">
                                {{ uploadStats.success }} uploaded
                            </Badge>
                            <Badge v-if="uploadStats.failed > 0" variant="destructive" class="text-xs">
                                {{ uploadStats.failed }} failed
                            </Badge>
                            <Badge v-if="uploadStats.uploading > 0" variant="secondary" class="text-xs">
                                {{ uploadStats.uploading }} uploading
                            </Badge>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Retry failed uploads button -->
                        <Button
                            v-if="multiple && uploadStats.failed > 0"
                            variant="outline"
                            size="sm"
                            :disabled="disabled"
                            @click="retryFailedUploads"
                        >
                            <Icon name="refresh-cw" class="h-3 w-3 mr-1" />
                            Retry
                        </Button>

                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="disabled || state.isUploading"
                            @click="handleClearAll"
                        >
                            <Icon name="x" class="h-4 w-4" />
                            Clear All
                        </Button>
                    </div>
                </div>

                <!-- Preview grid -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="preview in filePreviews"
                        :key="preview.id"
                        class="relative overflow-hidden p-0"
                    >
                        <!-- Image preview -->
                        <div class="aspect-square overflow-hidden relative">
                            <img
                                :src="preview.url"
                                :alt="preview.file.name"
                                class="h-full w-full object-cover"
                            />

                            <!-- Individual upload status overlay -->
                            <div
                                v-if="multiple && preview.uploadState"
                                class="absolute inset-0 bg-black/50 flex items-center justify-center"
                                :class="{
                                    'opacity-0': !preview.uploadState.isUploading && !preview.uploadState.error && !preview.uploadState.success,
                                    'opacity-100': preview.uploadState.isUploading || preview.uploadState.error || preview.uploadState.success
                                }"
                            >
                                <div class="text-center text-white">
                                    <Icon
                                        v-if="preview.uploadState.isUploading"
                                        name="loader-2"
                                        class="h-6 w-6 animate-spin mx-auto mb-1"
                                    />
                                    <Icon
                                        v-else-if="preview.uploadState.success"
                                        name="check-circle"
                                        class="h-6 w-6 mx-auto mb-1 text-green-400"
                                    />
                                    <Icon
                                        v-else-if="preview.uploadState.error"
                                        name="alert-circle"
                                        class="h-6 w-6 mx-auto mb-1 text-red-400"
                                    />

                                    <div v-if="preview.uploadState.isUploading" class="text-xs">
                                        {{ preview.uploadState.progress.percentage }}%
                                    </div>
                                    <div v-else-if="preview.uploadState.success" class="text-xs">
                                        Uploaded
                                    </div>
                                    <div v-else-if="preview.uploadState.error" class="text-xs">
                                        Failed
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- File info -->
                        <div class="p-3">
                            <p class="truncate text-sm font-medium text-gray-900">
                                {{ preview.file.name }}
                            </p>
                            <p class="text-xs text-gray-600">
                                {{ formatFileSize(preview.file.size) }}
                            </p>

                            <!-- Individual progress bar for multiple uploads -->
                            <div v-if="multiple && preview.uploadState?.isUploading" class="mt-2">
                                <Progress :model-value="preview.uploadState.progress.percentage" class="h-1" />
                            </div>

                            <!-- Individual error message -->
                            <div v-if="multiple && preview.uploadState?.error" class="mt-1">
                                <p class="text-xs text-red-600">{{ preview.uploadState.error }}</p>
                            </div>
                        </div>

                        <!-- Remove button -->
                        <Button
                            variant="ghost"
                            size="sm"
                            class="absolute right-2 top-2 h-6 w-6 rounded-full bg-white/80 p-0 shadow-sm hover:bg-white"
                            :disabled="disabled || (preview.uploadState?.isUploading ?? state.isUploading)"
                            @click="handleRemoveFile(preview.id)"
                        >
                            <Icon name="x" class="h-3 w-3" />
                        </Button>
                    </Card>
                </div>

                <!-- Add more button for multiple uploads -->
                <Button
                    v-if="multiple"
                    variant="outline"
                    class="w-full"
                    :disabled="disabled || state.isUploading"
                    @click="triggerFileSelect"
                >
                    <Icon name="plus" class="mr-2 h-4 w-4" />
                    Add More Images
                </Button>
            </div>
        </div>

        <!-- Validation errors -->
        <div v-if="validationErrors.length > 0" class="mt-3 space-y-1">
            <div
                v-for="error in validationErrors"
                :key="error.field"
                class="flex items-center text-sm text-red-600"
            >
                <Icon name="alert-circle" class="mr-1 h-4 w-4" />
                {{ error.message }}
            </div>
        </div>

        <!-- Upload progress -->
        <div v-if="state.isUploading" class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">Uploading...</span>
                <span class="font-medium">{{ state.progress.percentage }}%</span>
            </div>
            <Progress :model-value="state.progress.percentage" class="h-2" />
        </div>

        <!-- Upload error -->
        <div v-if="state.error" class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3">
            <div class="flex items-center">
                <Icon name="alert-circle" class="mr-2 h-4 w-4 text-red-500" />
                <p class="text-sm text-red-700">{{ state.error }}</p>
            </div>
        </div>

        <!-- Success message with uploaded file info -->
        <div v-if="state.success && state.uploadedFile" class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3">
            <div class="flex items-start justify-between">
                <div class="flex items-center">
                    <Icon name="check-circle" class="mr-2 h-4 w-4 text-green-500" />
                    <div>
                        <p class="text-sm font-medium text-green-800">Upload successful!</p>
                        <p class="text-xs text-green-700">{{ state.uploadedFile.original_name }}</p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-green-700 hover:text-green-800"
                    @click="copyUrlToClipboard(state.uploadedFile!.url)"
                >
                    <Icon name="copy" class="h-3 w-3" />
                </Button>
            </div>
        </div>

        <!-- Upload button -->
        <div v-if="hasFiles && !immediate" class="mt-4 flex justify-end space-x-2">
            <Button
                variant="outline"
                :disabled="disabled || state.isUploading"
                @click="handleClearAll"
            >
                Cancel
            </Button>
            <Button
                :disabled="!canUpload || disabled"
                @click="handleUpload"
            >
                <Icon
                    v-if="state.isUploading"
                    name="loader-2"
                    class="mr-2 h-4 w-4 animate-spin"
                />
                <Icon
                    v-else
                    name="upload"
                    class="mr-2 h-4 w-4"
                />
                {{ uploadButtonText }}
            </Button>
        </div>
    </div>
</template>
