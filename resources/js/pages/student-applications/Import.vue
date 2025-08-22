<script setup lang="ts">
import { ArrowLeft, Check, Download, Info, Play, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

import ColumnMapping from '@/components/imports/ColumnMapping.vue';
import FileUpload from '@/components/imports/FileUpload.vue';
import ImportPreview from '@/components/imports/ImportPreview.vue';
import ImportProgress from '@/components/imports/ImportProgress.vue';
import ImportResults from '@/components/imports/ImportResults.vue';
import { useStudentApplicationImport } from '@/composables/useStudentApplicationImport';

// Props
type Props = {
    maxFileSize?: string;
    allowedExtensions?: string[];
};

defineProps<Props>();

// Composable
const {
    // State
    currentStep,
    detectedColumns,
    columnMapping,
    previewData,
    validationSummary,
    importResults,
    isLoading,
    isProcessing,
    progress,
    error,

    // Computed
    canStartImport,

    // Methods
    previewImport,
    processImport,
    downloadTemplate,
    updateColumnMapping,
    resetImport,
    goToApplicationsList,
    startNewImport,
} = useStudentApplicationImport();

// Computed for template
const importStatus = computed(() => {
    if (isProcessing.value) {
        if (progress.value < 30) return 'Validating data...';
        if (progress.value < 70) return 'Processing records...';
        if (progress.value < 100) return 'Finalizing import...';
    }
    return 'Import completed!';
});

// Methods
const handleFileSelected = async (file: File): Promise<void> => {
    await previewImport(file, { preview_rows: 10 });
};

const handleMappingChanged = (newMapping: Record<string, string>): void => {
    updateColumnMapping(newMapping);
};

const startImport = async (): Promise<void> => {
    await processImport({
        update_existing: true,
        skip_invalid: false,
    });
};
</script>
<template>
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Import Student Applications</h1>
                        <p class="mt-2 text-sm text-gray-600">Upload an Excel file to import student application data. Only matching columns will be imported.</p>
                    </div>
                    <div class="flex space-x-4">
                        <a @click="downloadTemplate" href="#" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50">
                            <Download class="mr-2 h-4 w-4" />
                            Download Template
                        </a>
                        <button @click="goToApplicationsList" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Applications
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progress Steps -->
            <div class="mb-8">
                <nav aria-label="Progress">
                    <ol class="divide-y divide-gray-300 rounded-md border border-gray-300 md:flex md:divide-y-0">
                        <li class="relative md:flex md:flex-1">
                            <div :class="['group flex w-full items-center', currentStep === 'upload' ? 'bg-blue-50' : ['preview', 'mapping', 'processing', 'results'].includes(currentStep) ? 'bg-green-50' : 'bg-white']">
                                <span class="flex items-center px-6 py-4 text-sm font-medium">
                                    <span
                                        :class="[
                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full',
                                            currentStep === 'upload' ? 'bg-blue-600 text-white' : ['preview', 'mapping', 'processing', 'results'].includes(currentStep) ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-500',
                                        ]"
                                    >
                                        <span v-if="currentStep === 'upload'">1</span>
                                        <Check v-else-if="['preview', 'mapping', 'processing', 'results'].includes(currentStep)" class="h-6 w-6" />
                                        <span v-else>1</span>
                                    </span>
                                    <span class="ml-4 text-sm font-medium text-gray-900">Upload File</span>
                                </span>
                            </div>
                        </li>

                        <li class="relative md:flex md:flex-1">
                            <div :class="['group flex w-full items-center', currentStep === 'preview' ? 'bg-blue-50' : ['mapping', 'processing', 'results'].includes(currentStep) ? 'bg-green-50' : 'bg-white']">
                                <span class="flex items-center px-6 py-4 text-sm font-medium">
                                    <span
                                        :class="[
                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full',
                                            currentStep === 'preview' ? 'bg-blue-600 text-white' : ['mapping', 'processing', 'results'].includes(currentStep) ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-500',
                                        ]"
                                    >
                                        <span v-if="currentStep === 'preview'">2</span>
                                        <Check v-else-if="['mapping', 'processing', 'results'].includes(currentStep)" class="h-6 w-6" />
                                        <span v-else>2</span>
                                    </span>
                                    <span class="ml-4 text-sm font-medium text-gray-900">Preview & Map</span>
                                </span>
                            </div>
                        </li>

                        <li class="relative md:flex md:flex-1">
                            <div :class="['group flex w-full items-center', currentStep === 'processing' ? 'bg-blue-50' : currentStep === 'results' ? 'bg-green-50' : 'bg-white']">
                                <span class="flex items-center px-6 py-4 text-sm font-medium">
                                    <span
                                        :class="[
                                            'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full',
                                            currentStep === 'processing' ? 'bg-blue-600 text-white' : currentStep === 'results' ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-500',
                                        ]"
                                    >
                                        <span v-if="currentStep === 'processing'">3</span>
                                        <Check v-else-if="currentStep === 'results'" class="h-6 w-6" />
                                        <span v-else>3</span>
                                    </span>
                                    <span class="ml-4 text-sm font-medium text-gray-900">Import</span>
                                </span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="space-y-6">
                <!-- Upload Step -->
                <div v-if="currentStep === 'upload'" class="rounded-lg bg-white p-6 shadow">
                    <FileUpload @file-selected="handleFileSelected" :loading="isLoading" :max-file-size="maxFileSize" :allowed-extensions="allowedExtensions" />

                    <!-- Upload Instructions -->
                    <div class="mt-6 rounded-md bg-blue-50 p-4">
                        <div class="flex">
                            <Info class="h-5 w-5 text-blue-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Import Requirements</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc space-y-1 pl-5">
                                        <li><strong>Email</strong> is required and used to identify existing records</li>
                                        <li><strong>Student Code</strong> is required</li>
                                        <li><strong>Full Name</strong> is required</li>
                                        <li>If an email already exists in the database, the record will be updated</li>
                                        <li>Records without email addresses will be skipped</li>
                                        <li>Only matching columns will be imported</li>
                                        <li>Maximum file size: {{ maxFileSize }}</li>
                                        <li>Supported formats: {{ allowedExtensions.join(', ').toUpperCase() }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview & Mapping Step -->
                <div v-if="currentStep === 'preview'" class="space-y-6">
                    <!-- Column Mapping -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Column Mapping</h3>
                        <ColumnMapping :detected-columns="detectedColumns || []" :initial-mapping="columnMapping || {}" @mapping-changed="handleMappingChanged" />
                    </div>

                    <!-- Preview Data -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Data Preview</h3>
                        <ImportPreview :preview-data="previewData || []" :validation-summary="validationSummary || {}" :total-rows="previewData?.length || 0" />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <button @click="resetImport" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Upload
                        </button>
                        <button
                            @click="startImport"
                            :disabled="!canStartImport || isLoading"
                            class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Play class="mr-2 h-4 w-4" />
                            Start Import
                        </button>
                    </div>
                </div>

                <!-- Processing Step -->
                <div v-if="currentStep === 'processing'" class="rounded-lg bg-white p-6 shadow">
                    <ImportProgress :is-processing="isProcessing" :progress="progress" :status="importStatus" />
                </div>

                <!-- Results Step -->
                <div v-if="currentStep === 'results'" class="space-y-6">
                    <ImportResults :results="importResults" @import-another="startNewImport" @view-applications="goToApplicationsList" />
                </div>
            </div>

            <!-- Error Alert -->
            <div v-if="error" class="mt-6 rounded-md bg-red-50 p-4">
                <div class="flex">
                    <XCircle class="h-5 w-5 text-red-400" />
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            {{ error }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
