<script setup lang="ts">
import { AlertTriangle, CheckCircle, FileText, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// Props
type PreviewRowData = {
    row_number: number;
    is_valid: boolean;
    data: Record<string, any>;
    errors?: string[];
    warnings?: string[];
};

type ValidationSummary = {
    valid_rows?: number;
    invalid_rows?: number;
    warnings?: string[];
};

type Props = {
    previewData?: PreviewRowData[];
    validationSummary?: ValidationSummary;
    totalRows?: number;
};

const props = withDefaults(defineProps<Props>(), {
    previewData: () => [],
    validationSummary: () => ({}),
    totalRows: 0,
});

// Emits
const emit = defineEmits(['options-changed']);

// State
const updateExisting = ref<boolean>(true);
const skipInvalid = ref<boolean>(false);

// Computed
type DisplayField = {
    key: string;
    label: string;
};

const displayFields = computed((): DisplayField[] => {
    if (props.previewData.length === 0) return [];

    const firstRow = props.previewData[0];
    const fields: DisplayField[] = [];

    // Always show required fields first
    const requiredFields = ['student_code', 'full_name'];
    for (const field of requiredFields) {
        if (firstRow.data && firstRow.data.hasOwnProperty(field)) {
            fields.push({
                key: field,
                label: getFieldLabel(field),
            });
        }
    }

    // Then show other fields
    if (firstRow.data) {
        for (const key of Object.keys(firstRow.data)) {
            if (!requiredFields.includes(key)) {
                fields.push({
                    key,
                    label: getFieldLabel(key),
                });
            }
        }
    }

    // Limit to first 6 fields to prevent table from being too wide
    return fields.slice(0, 6);
});

// Methods
const getFieldLabel = (fieldKey: string): string => {
    const labels: Record<string, string> = {
        full_name: 'Full Name',
        student_code: 'Student Code',
        email: 'Email',
        gender: 'Gender',
        ethnicity: 'Ethnicity',
        birth_day: 'Birth Day',
        birth_month: 'Birth Month',
        birth_year: 'Birth Year',
        national_id: 'National ID',
        phone: 'Phone',
        address: 'Address',
        parent_phone: 'Parent Phone',
        parent_email: 'Parent Email',
        campus_code: 'Campus',
        intended_program: 'Program',
        intended_specialization: 'Specialization',
        intake: 'Intake',
        english_test_type: 'Test Type',
        listening: 'Listening',
        reading: 'Reading',
        writing: 'Writing',
        speaking: 'Speaking',
        overall: 'Overall',
        is_international_applicant: 'International',
        status: 'Status',
    };

    return labels[fieldKey] || fieldKey.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatFieldValue = (value: any, fieldKey: string): string => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    // Format boolean values
    if (fieldKey === 'is_international_applicant') {
        return value ? 'Yes' : 'No';
    }

    // Format scores
    if (['listening', 'reading', 'writing', 'speaking', 'overall'].includes(fieldKey)) {
        return Number(value).toFixed(1);
    }

    // Truncate long text
    const stringValue = String(value);
    if (stringValue.length > 30) {
        return stringValue.substring(0, 30) + '...';
    }

    return stringValue;
};

// Watch for option changes

watch(
    [updateExisting, skipInvalid],
    () => {
        emit('options-changed', {
            update_existing: updateExisting.value,
            skip_invalid: skipInvalid.value,
        });
    },
    { immediate: true },
);
</script>
<template>
    <div class="space-y-4">
        <!-- Validation Summary -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-blue-50 p-4">
                <div class="flex items-center">
                    <FileText class="h-8 w-8 text-blue-600" />
                    <div class="ml-3">
                        <p class="text-sm font-medium text-blue-900">Total Rows</p>
                        <p class="text-2xl font-bold text-blue-600">{{ totalRows }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-green-50 p-4">
                <div class="flex items-center">
                    <CheckCircle class="h-8 w-8 text-green-600" />
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-900">Valid Rows</p>
                        <p class="text-2xl font-bold text-green-600">{{ validationSummary.valid_rows || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-red-50 p-4">
                <div class="flex items-center">
                    <XCircle class="h-8 w-8 text-red-600" />
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-900">Invalid Rows</p>
                        <p class="text-2xl font-bold text-red-600">{{ validationSummary.invalid_rows || 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warnings -->
        <div v-if="validationSummary.warnings && validationSummary.warnings.length > 0" class="rounded-md bg-yellow-50 p-4">
            <div class="flex">
                <AlertTriangle class="h-5 w-5 text-yellow-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Warnings</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-inside list-disc space-y-1">
                            <li v-for="warning in validationSummary.warnings" :key="warning">
                                {{ warning }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Table -->
        <div class="overflow-hidden rounded-md border border-gray-200">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                <h3 class="text-sm font-medium text-gray-900">Data Preview (First 10 rows)</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Row</th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Status</th>
                            <th v-for="field in displayFields" :key="field.key" class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                                {{ field.label }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Issues</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in previewData" :key="row.row_number" :class="['hover:bg-gray-50', row.is_valid ? '' : 'bg-red-50']">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ row.row_number }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-2 py-1 text-xs font-medium', row.is_valid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                    <CheckCircle v-if="row.is_valid" class="mr-1 h-3 w-3" />
                                    <XCircle v-else class="mr-1 h-3 w-3" />
                                    {{ row.is_valid ? 'Valid' : 'Invalid' }}
                                </span>
                            </td>
                            <td v-for="field in displayFields" :key="field.key" class="max-w-xs truncate px-4 py-3 text-sm text-gray-900" :title="row.data[field.key]">
                                {{ formatFieldValue(row.data[field.key], field.key) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div v-if="row.errors && row.errors.length > 0" class="space-y-1">
                                    <div v-for="error in row.errors" :key="error" class="text-xs text-red-600">
                                        {{ error }}
                                    </div>
                                </div>
                                <div v-if="row.warnings && row.warnings.length > 0" class="space-y-1">
                                    <div v-for="warning in row.warnings" :key="warning" class="text-xs text-yellow-600">
                                        {{ warning }}
                                    </div>
                                </div>
                                <span v-if="(!row.errors || row.errors.length === 0) && (!row.warnings || row.warnings.length === 0)" class="text-xs text-gray-400"> No issues </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="previewData.length === 0" class="py-12 text-center">
            <FileText class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900">No preview data</h3>
            <p class="mt-1 text-sm text-gray-500">Upload a file to see the data preview.</p>
        </div>

        <!-- Import Options -->
        <div class="rounded-lg bg-gray-50 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-900">Import Options</h4>
            <div class="space-y-3">
                <label class="flex items-center">
                    <input v-model="updateExisting" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span class="ml-2 text-sm text-gray-700">Update existing records (based on Student Code)</span>
                </label>
                <label class="flex items-center">
                    <input v-model="skipInvalid" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span class="ml-2 text-sm text-gray-700">Skip invalid records and continue import</span>
                </label>
            </div>
        </div>
    </div>
</template>
