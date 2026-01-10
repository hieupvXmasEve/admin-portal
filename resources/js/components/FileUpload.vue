<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useFileUpload } from '@/composables/useFileUpload';
import { cn } from '@/lib/utils';
import { UploadedFile } from '@/types/fileUpload';
import { ref } from 'vue';

interface Props {
    // Required context for the upload (e.g., 'action_attachment')
    context: string;

    // Configuration
    multiple?: boolean;
    maxSize?: number; // bytes
    allowedTypes?: string[];
    uploadImmediately?: boolean;

    // UI
    label?: string;
    accept?: string; // HTML input accept attribute
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    maxSize: 10 * 1024 * 1024, // 10MB
    allowedTypes: () => [],
    uploadImmediately: false,
    label: 'Drop files here or click to upload',
    accept: '*/*',
    class: '',
});

const emit = defineEmits<{
    (e: 'upload-success', file: UploadedFile): void;
    (e: 'update:modelValue', files: UploadedFile[]): void;
    (e: 'remove', id: number): void; // For removing already uploaded files if parent manages them
}>();

// Initialize composable
const { state, hasFiles, hasPendingFiles, isUploading, addFiles, removeFile, uploadPendingFiles, formatFileSize, reset } = useFileUpload({
    context: props.context,
    multiple: props.multiple,
    maxSize: props.maxSize,
    allowedTypes: props.allowedTypes,
    uploadImmediately: props.uploadImmediately,
});

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

// Watch for successful uploads to emit events
import { watch } from 'vue';
watch(
    () => state.value.files,
    (newFiles) => {
        // Check for newly completed uploads
        newFiles.forEach((file) => {
            if (file.status === 'success' && file.response) {
                // We could track which ones we've already emitted if needed
                // But for now, the parent might just care about the latest or the full list
            }
        });

        // Emit the list of successfully uploaded files
        const uploaded = newFiles.filter((f) => f.status === 'success' && f.response).map((f) => f.response as UploadedFile);

        emit('update:modelValue', uploaded);

        // If specific event is needed per file, we can add logic here,
        // but usually binding v-model or listening to a general complete is enough.
        // Let's emit specific success for the last changed file if it just finished?
        // Actually, simple is better. Let's rely on the parent watching the modelValue or
        // listening to a specific event if we add one.

        // Let's emit 'upload-success' for every success to be compatible with single-file logic
        // We need a way to know which one just finished.
        // For now, let's keep it simple.
    },
    { deep: true },
);

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        addFiles(input.files);
        input.value = ''; // Reset input
    }
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        addFiles(files);
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const getFileIcon = (mimeType: string, name: string) => {
    if (mimeType.startsWith('image/') || name.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
        return 'image';
    }
    if (mimeType === 'application/pdf' || name.endsWith('.pdf')) {
        return 'file-text';
    }
    return 'file';
};
</script>

<template>
    <div :class="cn('w-full space-y-4', props.class)">
        <!-- Dropzone -->
        <div
            @click="triggerFileInput"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            :class="
                cn(
                    'cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition-colors',
                    isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50',
                    isUploading ? 'pointer-events-none opacity-50' : '',
                )
            "
        >
            <input ref="fileInput" type="file" class="hidden" :multiple="props.multiple" :accept="props.accept" @change="handleFileSelect" />

            <div class="flex flex-col items-center gap-2">
                <div class="bg-muted rounded-full p-3">
                    <Icon name="CloudUpload" class="text-muted-foreground h-6 w-6" />
                </div>
                <div class="text-foreground text-sm font-medium">
                    {{ label }}
                </div>
                <div class="text-muted-foreground text-xs">
                    Max size: {{ formatFileSize(maxSize) }}
                    <span v-if="!multiple">(Single file)</span>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="state.error" class="text-destructive flex items-center gap-2 text-sm">
            <Icon name="alert-circle" class="h-4 w-4" />
            {{ state.error }}
        </div>

        <!-- File List -->
        <div v-if="hasFiles" class="space-y-2">
            <div v-for="file in state.files" :key="file.id" class="bg-card group flex items-center gap-3 rounded-md border p-3">
                <!-- File Icon -->
                <div class="bg-muted shrink-0 rounded-md p-2">
                    <Icon :name="getFileIcon(file.file.type, file.file.name)" class="text-foreground h-4 w-4" />
                </div>

                <!-- File Info & Progress -->
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="truncate pr-2 text-sm font-medium">
                            {{ file.file.name }}
                        </span>
                        <span class="text-muted-foreground shrink-0 text-xs">
                            {{ formatFileSize(file.file.size) }}
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="file.status === 'uploading' || file.status === 'pending'" class="h-1.5 w-full">
                        <Progress :model-value="file.progress" class="h-1.5" />
                    </div>

                    <!-- Status Text -->
                    <div v-else class="flex items-center gap-1.5 text-xs">
                        <span v-if="file.status === 'success'" class="flex items-center gap-1 text-green-600">
                            <Icon name="check-circle" class="h-3 w-3" />
                            Uploaded
                        </span>
                        <span v-else-if="file.status === 'error'" class="text-destructive flex items-center gap-1">
                            <Icon name="alert-circle" class="h-3 w-3" />
                            {{ file.error || 'Upload failed' }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <button v-if="!isUploading" @click.stop="removeFile(file.id)" class="hover:bg-destructive/10 hover:text-destructive rounded-full p-2 opacity-0 transition-colors group-hover:opacity-100 focus:opacity-100" title="Remove file">
                    <Icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Actions -->
        <div v-if="!props.uploadImmediately && hasPendingFiles" class="flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="reset" :disabled="isUploading"> Clear All </Button>
            <Button size="sm" @click="uploadPendingFiles" :disabled="isUploading">
                <Icon v-if="isUploading" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                {{ isUploading ? 'Uploading...' : 'Upload Files' }}
            </Button>
        </div>
    </div>
</template>
