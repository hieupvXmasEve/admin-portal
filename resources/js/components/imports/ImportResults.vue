<script setup lang="ts">
import { AlertCircle, AlertTriangle, CheckCircle, Download, Eye, FileText, Plus, RotateCcw, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Props
type ImportError = {
    row: number;
    student_code?: string;
    errors: string[];
};

type ImportResults = {
    total_processed?: number;
    created?: number;
    updated?: number;
    skipped?: number;
    execution_time?: string;
    errors?: ImportError[];
};

type Props = {
    results?: ImportResults | null;
};

const props = withDefaults(defineProps<Props>(), {
    results: null,
});

// Emits
defineEmits(['import-another', 'view-applications']);

// State
const showAllErrors = ref<boolean>(false);

// Computed
const displayedErrors = computed((): ImportError[] => {
    if (!props.results?.errors) return [];

    if (showAllErrors.value) {
        return props.results.errors;
    }

    return props.results.errors.slice(0, 5);
});

// Methods
const downloadErrorReport = (): void => {
    if (!props.results?.errors || props.results.errors.length === 0) return;

    const errorData = props.results.errors.map((error) => ({
        Row: error.row,
        'Student Code': error.student_code || 'N/A',
        Errors: error.errors.join('; '),
    }));

    const csvContent = [
        Object.keys(errorData[0]).join(','),
        ...errorData.map((row) =>
            Object.values(row)
                .map((val) => `"${val}"`)
                .join(','),
        ),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');

    a.href = url;
    a.download = `import_errors_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
};
</script>
<template>
    <div class="space-y-6">
        <!-- Success Header -->
        <div class="py-6 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <CheckCircle class="h-10 w-10 text-green-600" />
            </div>
            <h2 class="mt-4 text-2xl font-bold text-gray-900">Import Completed!</h2>
            <p class="mt-2 text-sm text-gray-600">Your student application data has been successfully imported.</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-lg bg-blue-50 p-4 text-center">
                <FileText class="mx-auto mb-2 h-8 w-8 text-blue-600" />
                <p class="text-sm font-medium text-blue-900">Total Processed</p>
                <p class="text-2xl font-bold text-blue-600">{{ results?.total_processed || 0 }}</p>
            </div>

            <div class="rounded-lg bg-green-50 p-4 text-center">
                <Plus class="mx-auto mb-2 h-8 w-8 text-green-600" />
                <p class="text-sm font-medium text-green-900">Created</p>
                <p class="text-2xl font-bold text-green-600">{{ results?.created || 0 }}</p>
            </div>

            <div class="rounded-lg bg-yellow-50 p-4 text-center">
                <RotateCcw class="mx-auto mb-2 h-8 w-8 text-yellow-600" />
                <p class="text-sm font-medium text-yellow-900">Updated</p>
                <p class="text-2xl font-bold text-yellow-600">{{ results?.updated || 0 }}</p>
            </div>

            <div class="rounded-lg bg-red-50 p-4 text-center">
                <AlertTriangle class="mx-auto mb-2 h-8 w-8 text-red-600" />
                <p class="text-sm font-medium text-red-900">Skipped</p>
                <p class="text-2xl font-bold text-red-600">{{ results?.skipped || 0 }}</p>
            </div>
        </div>

        <!-- Processing Time -->
        <div v-if="results?.execution_time" class="text-center text-sm text-gray-500">Completed in {{ results.execution_time }}</div>

        <!-- Errors Section -->
        <div v-if="results?.errors && results.errors.length > 0" class="rounded-lg bg-red-50 p-4">
            <div class="mb-3 flex items-center">
                <AlertCircle class="mr-2 h-5 w-5 text-red-400" />
                <h3 class="text-sm font-medium text-red-800">Import Errors ({{ results.errors.length }})</h3>
                <button @click="showAllErrors = !showAllErrors" class="ml-auto text-sm text-red-700 hover:text-red-900">
                    {{ showAllErrors ? 'Show Less' : 'Show All' }}
                </button>
            </div>

            <div class="space-y-2">
                <div v-for="(error, index) in displayedErrors" :key="index" class="rounded-md border border-red-200 bg-white p-3">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-red-900">
                                Row {{ error.row }}
                                <span v-if="error.student_code" class="text-red-700"> ({{ error.student_code }}) </span>
                            </p>
                            <ul class="mt-1 list-inside list-disc text-sm text-red-700">
                                <li v-for="errorMsg in error.errors" :key="errorMsg">
                                    {{ errorMsg }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="results.errors.length > 5 && !showAllErrors" class="mt-3 text-center">
                <button @click="showAllErrors = true" class="text-sm text-red-700 hover:text-red-900">Show {{ results.errors.length - 5 }} more errors</button>
            </div>
        </div>

        <!-- Success Message -->
        <div v-if="results?.errors && results.errors.length === 0" class="rounded-lg bg-green-50 p-4">
            <div class="flex">
                <CheckCircle class="h-5 w-5 text-green-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">Perfect Import!</h3>
                    <p class="mt-1 text-sm text-green-700">All records were processed successfully without any errors.</p>
                </div>
            </div>
        </div>

        <!-- Import Summary -->
        <div class="rounded-lg bg-gray-50 p-4">
            <h3 class="mb-3 text-sm font-medium text-gray-900">Import Summary</h3>
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                <div>
                    <h4 class="mb-2 font-medium text-gray-700">Records Processed</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>✅ {{ results?.created || 0 }} new applications created</li>
                        <li>🔄 {{ results?.updated || 0 }} existing applications updated</li>
                        <li>⚠️ {{ results?.skipped || 0 }} records skipped due to errors</li>
                    </ul>
                </div>
                <div>
                    <h4 class="mb-2 font-medium text-gray-700">Data Quality</h4>
                    <ul class="space-y-1 text-gray-600">
                        <li>
                            Success Rate:
                            <span class="font-medium"> {{ results ? Math.round(((results.created + results.updated) / results.total_processed) * 100) : 0 }}% </span>
                        </li>
                        <li>
                            Error Rate:
                            <span class="font-medium"> {{ results ? Math.round((results.skipped / results.total_processed) * 100) : 0 }}% </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col justify-center gap-4 sm:flex-row">
            <button @click="$emit('view-applications')" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                <Eye class="mr-2 h-4 w-4" />
                View Applications
            </button>

            <button @click="$emit('import-another')" class="inline-flex items-center justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50">
                <Upload class="mr-2 h-4 w-4" />
                Import Another File
            </button>

            <button @click="downloadErrorReport" v-if="results?.errors && results.errors.length > 0" class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                <Download class="mr-2 h-4 w-4" />
                Download Error Report
            </button>
        </div>
    </div>
</template>
