<script setup lang="ts">
import { ref, computed, onUnmounted, watch, onMounted } from 'vue';
import { useImageUpload } from '@/composables/useImageUpload';
import { useUploadConfig } from '@/composables/useUploadConfig';
import type { ImageUploadOptions, UploadRecord, FilePreview } from '@/types/imageUpload';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import Icon from '@/components/Icon.vue';
import ImagePreview from '@/components/ImagePreview.vue';

// Props
interface Props {
    context: string;
    maxSize?: number;
    allowedTypes?: string[];
    disabled?: boolean;
    class?: string;
    showConfig?: boolean;
    autoLoadConfig?: boolean;
    maxFiles?: number;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    showConfig: true,
    autoLoadConfig: true,
    maxFiles: 10,
});

// Emits
const emit = defineEmits<{
    'upload-start': [];
    'upload-progress': [percentage: number];
    'upload-success': [files: UploadRecord[]];
    'upload-error': [error: string];
    'files-selected': [count: number];
    'file-removed': [id: string];
    'file-uploaded': [file: UploadRecord];
    'batch-complete': [stats: any];
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
    getMaxSize,
    getAllowedTypes,
    isValidContext,
    getMaxSizeText,
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
    multiple: true,
    immediate: false,
}));

const {
    state,
    filePreviews,
    validationErrors,
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
    if (!uploadConfig.value) return true;
    return isValidContext(props.context);
});

const showConfigInfo = computed(() => {
    return props.showConfig && uploadConfig.value && !configLoading.value;
});

const canAddMoreFiles = computed(() => {
    return filePreviews.value.length < props.maxFiles;
});

const pendingFiles = computed(() => {
    return filePreviews.value.filter(p =>
        !p.uploadState?.success && !p.uploadState?.error && !p.uploadState?.isUploading
    );
});

const uploadingFiles = computed(() => {
    return filePreviews.value.filter(p => p.uploadState?.isUploading);
});

const successfulFiles = computed(() => {
    return filePreviews.value.filter(p => p.uploadState?.success);
});

const failedFiles = computed(() => {
    return filePreviews.value.filter(p => p.uploadState?.error && !p.uploadState?.success);
});

// Methods
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const newFilesCount = Math.min(target.files.length, props.maxFiles - filePreviews.value.length);
        const filesToAdd = Array.from(target.files).slice(0, newFilesCount);

        selectFiles(filesToAdd);
        emit('files-selected', filesToAdd.length);

        // Reset input
        target.value = '';
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = false;

    if (props.disabled) return;

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        const newFilesCount = Math.min(files.length, props.maxFiles - filePreviews.value.length);
        const filesToAdd = Array.from(files).slice(0, newFilesCount);

        selectFiles(filesToAdd);
        emit('files-selected', filesToAdd.length);
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
    if (!dropZone.value?.contains(event.relatedTarget as Node)) {
        isDragOver.value = false;
    }
};

const triggerFileSelect = () => {
    if (!props.disabled && canAddMoreFiles.value) {
        fileInput.value?.click();
    }
};

const handleBatchUpload = async () => {
    if (!canUpload.value) return;

    try {
        emit('upload-start');
        const uploadedFiles = await uploadFiles();
        emit('upload-success', uploadedFiles);
        emit('batch-complete', uploadStats.value);
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Upload failed';
        emit('upload-error', errorMessage);
    }
};

const handleSingleUpload = async (id: string) => {
    try {
        const uploadedFile = await uploadSingleFileById(id);
        if (uploadedFile) {
            emit('file-uploaded', uploadedFile);
        }
    } catch (error) {
        console.error('Single file upload failed:', error);
    }
};

const handleRetryFailed = async () => {
    try {
        const retriedFiles = await retryFailedUploads();
        if (retriedFiles.length > 0) {
            emit('upload-success', retriedFiles);
        }
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Retry failed';
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

const getStatusIcon = (preview: FilePreview) => {
    if (!preview.uploadState) return 'file';

    if (preview.uploadState.isUploading) return 'loader-2';
    if (preview.uploadState.success) return 'check-circle';
    if (preview.uploadState.error) return 'alert-circle';
    return 'clock';
};

const getStatusColor = (preview: FilePreview) => {
    if (!preview.uploadState) return 'text-gray-500';

    if (preview.uploadState.isUploading) return 'text-blue-500';
    if (preview.uploadState.success) return 'text-green-500';
    if (preview.uploadState.error) return 'text-red-500';
    return 'text-gray-500';
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
            multiple
            :disabled="disabled || configLoading || !isContextValid || !canAddMoreFiles"
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
                <Icon name="images" class="mx-auto h-12 w-12 text-gray-400" />
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-900">
                        Drop images here or click to select multiple files
                    </p>
                    <p class="mt-1 text-xs text-gray-600">
                        {{ allowedTypesDisplay }} up to {{ maxSizeText }} each
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        Maximum {{ maxFiles }} files
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
                    Select Images
                </Button>
            </div>

            <!-- File management area -->
            <div v-else class="space-y-6">
                <!-- Header with stats -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <h3 class="text-sm font-medium text-gray-900">
                            Selected Images ({{ filePreviews.length }}/{{ maxFiles }})
                        </h3>
                        <div class="flex items-center space-x-2">
                            <Badge v-if="uploadStats.success > 0" variant="default" class="bg-green-100 text-green-800">
                                {{ uploadStats.success }} uploaded
                            </Badge>
                            <Badge v-if="uploadStats.failed > 0" variant="destructive">
                                {{ uploadStats.failed }} failed
                            </Badge>
                            <Badge v-if="uploadStats.uploading > 0" variant="secondary">
                                {{ uploadStats.uploading }} uploading
                            </Badge>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Button
                            v-if="canAddMoreFiles"
                            variant="outline"
                            size="sm"
                            :disabled="disabled || state.isUploading"
                            @click="triggerFileSelect"
                        >
                            <Icon name="plus" class="h-4 w-4 mr-1" />
                            Add More
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

                <!-- Overall progress -->
                <div v-if="state.isUploading" class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Overall Progress</span>
                        <span class="font-medium">{{ state.progress.percentage }}%</span>
                    </div>
                    <Progress :model-value="state.progress.percentage" class="h-2" />
                </div>

                <!-- File list -->
                <div class="space-y-3">
                    <Card
                        v-for="preview in filePreviews"
                        :key="preview.id"
                        class="p-4"
                    >
                        <div class="flex items-center space-x-4">
                            <!-- File preview -->
                            <div class="flex-shrink-0">
                                <div class="relative h-16 w-16 overflow-hidden rounded-lg">
                                    <img
                                        :src="preview.url"
                                        :alt="preview.file.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <!-- Status overlay -->
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                                        <Icon
                                            :name="getStatusIcon(preview)"
                                            :class="[
                                                'h-4 w-4 text-white',
                                                { 'animate-spin': preview.uploadState?.isUploading }
                                            ]"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- File info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ preview.file.name }}
                                    </p>
                                    <div class="flex items-center space-x-2">
                                        <Icon
                                            :name="getStatusIcon(preview)"
                                            :class="[
                                                'h-4 w-4',
                                                getStatusColor(preview),
                                                { 'animate-spin': preview.uploadState?.isUploading }
                                            ]"
                                        />
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600">
                                    {{ formatFileSize(preview.file.size) }}
                                </p>

                                <!-- Individual progress -->
                                <div v-if="preview.uploadState?.isUploading" class="mt-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-500">Uploading...</span>
                                        <span class="font-medium">{{ preview.uploadState.progress.percentage }}%</span>
                                    </div>
                                    <Progress :model-value="preview.uploadState.progress.percentage" class="h-1 mt-1" />
                                </div>

                                <!-- Error message -->
                                <div v-if="preview.uploadState?.error" class="mt-2">
                                    <p class="text-xs text-red-600">{{ preview.uploadState.error }}</p>
                                </div>

                                <!-- Success message -->
                                <div v-if="preview.uploadState?.success && preview.uploadState.uploadedFile" class="mt-2">
                                    <p class="text-xs text-green-600">Upload successful</p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                <!-- Individual upload button -->
                                <Button
                                    v-if="!preview.uploadState?.success && !preview.uploadState?.isUploading"
                                    variant="outline"
                                    size="sm"
                                    :disabled="disabled"
                                    @click="handleSingleUpload(preview.id)"
                                >
                                    <Icon name="upload" class="h-3 w-3" />
                                </Button>

                                <!-- Retry button -->
                                <Button
                                    v-if="preview.uploadState?.error"
                                    variant="outline"
                                    size="sm"
                                    :disabled="disabled"
                                    @click="handleSingleUpload(preview.id)"
                                >
                                    <Icon name="refresh-cw" class="h-3 w-3" />
                                </Button>

                                <!-- Preview button -->
                                <ImagePreview
                                    v-if="preview.uploadState?.success && preview.uploadState.uploadedFile"
                                    :upload="preview.uploadState.uploadedFile"
                                    :show-metadata="false"
                                    :show-copy-button="false"
                                    thumbnail-size="sm"
                                    class="cursor-pointer"
                                />

                                <!-- Remove button -->
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="disabled || preview.uploadState?.isUploading"
                                    @click="handleRemoveFile(preview.id)"
                                >
                                    <Icon name="x" class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>

                <Separator />

                <!-- Batch actions -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <Button
                            v-if="failedFiles.length > 0"
                            variant="outline"
                            size="sm"
                            :disabled="disabled"
                            @click="handleRetryFailed"
                        >
                            <Icon name="refresh-cw" class="h-4 w-4 mr-2" />
                            Retry Failed ({{ failedFiles.length }})
                        </Button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Button
                            v-if="pendingFiles.length > 0"
                            :disabled="!canUpload || disabled"
                            @click="handleBatchUpload"
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
                            Upload All ({{ pendingFiles.length }})
                        </Button>
                    </div>
                </div>
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

        <!-- Upload error -->
        <div v-if="state.error" class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3">
            <div class="flex items-center">
                <Icon name="alert-circle" class="mr-2 h-4 w-4 text-red-500" />
                <p class="text-sm text-red-700">{{ state.error }}</p>
            </div>
        </div>

        <!-- Success summary -->
        <div v-if="uploadStats.isComplete && uploadStats.success > 0" class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3">
            <div class="flex items-center">
                <Icon name="check-circle" class="mr-2 h-4 w-4 text-green-500" />
                <div>
                    <p class="text-sm font-medium text-green-800">
                        {{ uploadStats.success }} of {{ uploadStats.total }} files uploaded successfully
                    </p>
                    <p v-if="uploadStats.hasFailures" class="text-xs text-green-700">
                        {{ uploadStats.failed }} files failed to upload
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
