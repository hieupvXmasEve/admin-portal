<script setup lang="ts">
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { CheckCircle, CloudUpload, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Props
type Props = {
    loading?: boolean;
    maxFileSize?: string;
    allowedExtensions?: string[];
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    maxFileSize: '10MB',
    allowedExtensions: () => ['xlsx', 'xls', 'csv'],
});

// Emits
const emit = defineEmits(['file-selected', 'file-cleared']);

// State
const isDragOver = ref(false);
const selectedFile = ref<File | null>(null);
const error = ref<string | null>(null);

// Computed
const acceptedFileTypes = computed(() => {
    const mimeTypes = {
        xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        xls: 'application/vnd.ms-excel',
        csv: 'text/csv',
    };

    return props.allowedExtensions
        .map((ext) => mimeTypes[ext])
        .filter(Boolean)
        .join(',');
});

// Methods
const validateFile = (file: File): void => {
    // Check file extension
    const fileExt = file.name.split('.').pop()?.toLowerCase() || '';
    if (!props.allowedExtensions.includes(fileExt)) {
        throw new Error(`File type not supported. Please upload ${props.allowedExtensions.join(', ').toUpperCase()} files only.`);
    }

    // Check file size (convert maxFileSize to bytes)
    const maxSizeInBytes = parseFileSize(props.maxFileSize);
    if (file.size > maxSizeInBytes) {
        throw new Error(`File size exceeds maximum limit of ${props.maxFileSize}.`);
    }

    // Check if file is empty
    if (file.size === 0) {
        throw new Error('File is empty. Please select a valid file.');
    }
};

const parseFileSize = (sizeString: string): number => {
    const units: Record<string, number> = {
        B: 1,
        KB: 1024,
        MB: 1024 * 1024,
        GB: 1024 * 1024 * 1024,
    };

    const match = sizeString.match(/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB)$/i);
    if (!match) return 0;

    const value = parseFloat(match[1]);
    const unit = match[2].toUpperCase();

    return value * (units[unit] || 1);
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B';

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
};

const handleFileSelect = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        processFile(file);
    }
};

const handleDrop = (event: DragEvent): void => {
    event.preventDefault();
    isDragOver.value = false;

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        processFile(files[0]);
    }
};

const processFile = (file: File): void => {
    error.value = null;

    try {
        validateFile(file);
        selectedFile.value = file;
        emit('file-selected', file);
    } catch (err) {
        error.value = (err as Error).message;
        selectedFile.value = null;
    }
};

const clearFile = (): void => {
    selectedFile.value = null;
    error.value = null;
    emit('file-cleared');

    // Clear the file input
    const fileInput = document.getElementById('file-upload') as HTMLInputElement;
    if (fileInput) {
        fileInput.value = '';
    }
};
</script>
<template>
    <div class="space-y-4">
        <!-- Drag and Drop Area -->
        <div
            @drop="handleDrop"
            @dragover.prevent
            @dragenter.prevent
            :class="['relative rounded-lg border-2 border-dashed p-6 transition-colors', isDragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300', loading ? 'pointer-events-none opacity-50' : 'hover:border-gray-400']"
            @dragover="isDragOver = true"
            @dragleave="isDragOver = false"
        >
            <div class="text-center">
                <div class="mx-auto h-12 w-12 text-gray-400">
                    <CloudUpload class="h-full w-full" />
                </div>
                <div class="mt-4">
                    <label for="file-upload" class="cursor-pointer">
                        <span class="mt-2 block text-sm font-medium text-gray-900">
                            Drop your file here, or
                            <span class="text-blue-600 hover:text-blue-500">browse</span>
                        </span>
                        <input id="file-upload" name="file-upload" type="file" class="sr-only" :accept="acceptedFileTypes" @change="handleFileSelect" :disabled="loading" />
                    </label>
                    <p class="mt-1 text-xs text-gray-500">{{ allowedExtensions.map((ext) => ext.toUpperCase()).join(', ') }} up to {{ maxFileSize }}</p>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div v-if="loading" class="bg-opacity-75 absolute inset-0 flex items-center justify-center rounded-lg bg-white">
                <div class="flex items-center space-x-2">
                    <LoadingSpinner class="h-5 w-5" />
                    <span class="text-sm text-gray-600">Processing file...</span>
                </div>
            </div>
        </div>

        <!-- Selected File Info -->
        <div v-if="selectedFile && !loading" class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <CheckCircle class="h-5 w-5 text-green-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">File Selected</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p><strong>Name:</strong> {{ selectedFile.name }}</p>
                        <p><strong>Size:</strong> {{ formatFileSize(selectedFile.size) }}</p>
                        <p><strong>Type:</strong> {{ selectedFile.type || 'Unknown' }}</p>
                    </div>
                </div>
                <div class="ml-auto">
                    <button @click="clearFile" class="inline-flex items-center rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-800 hover:bg-green-200">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <XCircle class="h-5 w-5 text-red-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Upload Error</h3>
                    <div class="mt-2 text-sm text-red-700">
                        {{ error }}
                    </div>
                </div>
            </div>
        </div>

        <!-- File Requirements -->
        <div class="text-xs text-gray-500">
            <p><strong>Requirements:</strong></p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                <li>File must contain student application data</li>
                <li>First row should contain column headers</li>
                <li>Student Code column is required</li>
                <li>Full Name column is required</li>
            </ul>
        </div>
    </div>
</template>
