<script setup lang="ts">
import { AlertTriangle, Info } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// Props
type Props = {
    detectedColumns?: string[];
    initialMapping?: Record<string, string>;
};

const props = withDefaults(defineProps<Props>(), {
    detectedColumns: () => [],
    initialMapping: () => ({}),
});

// Emits
const emit = defineEmits(['mapping-changed']);

// State
const columnMapping = ref<Record<string, string>>({ ...props.initialMapping });

// Available database fields
type DatabaseField = {
    value: string;
    label: string;
    required: boolean;
};

const databaseFields: DatabaseField[] = [
    { value: 'full_name', label: 'Full Name', required: true },
    { value: 'student_code', label: 'Student Code', required: true },
    { value: 'email', label: 'Email', required: false },
    { value: 'gender', label: 'Gender', required: false },
    { value: 'ethnicity', label: 'Ethnicity', required: false },
    { value: 'birth_day', label: 'Birth Day', required: false },
    { value: 'birth_month', label: 'Birth Month', required: false },
    { value: 'birth_year', label: 'Birth Year', required: false },
    { value: 'national_id', label: 'National ID', required: false },
    { value: 'phone', label: 'Phone', required: false },
    { value: 'address', label: 'Address', required: false },
    { value: 'parent_phone', label: 'Parent Phone', required: false },
    { value: 'parent_email', label: 'Parent Email', required: false },
    { value: 'campus_code', label: 'Campus Code', required: false },
    { value: 'intended_program', label: 'Intended Program', required: false },
    { value: 'intended_specialization', label: 'Intended Specialization', required: false },
    { value: 'intake', label: 'Intake', required: false },
    { value: 'english_test_type', label: 'English Test Type', required: false },
    { value: 'listening', label: 'Listening Score', required: false },
    { value: 'reading', label: 'Reading Score', required: false },
    { value: 'writing', label: 'Writing Score', required: false },
    { value: 'speaking', label: 'Speaking Score', required: false },
    { value: 'overall', label: 'Overall Score', required: false },
    { value: 'is_international_applicant', label: 'International Applicant', required: false },
    { value: 'status', label: 'Status', required: false },
    { value: 'health_information', label: 'Health Information', required: false },
    { value: 'exam_date', label: 'Exam Date', required: false },
    { value: 'english_qualifications', label: 'English Qualifications', required: false },
    { value: 'sut_id', label: 'SUT ID', required: false },
    { value: 'exception_units', label: 'Exception Units', required: false },
    { value: 'study_link_status', label: 'Study Link Status', required: false },
];

// Computed
const requiredFields = computed(() => databaseFields.filter((field) => field.required));
const optionalFields = computed(() => databaseFields.filter((field) => !field.required));

const mappedFields = computed(() => Object.values(columnMapping.value));
const mappedCount = computed(() => Object.keys(columnMapping.value).filter((key) => columnMapping.value[key]).length);

const unmappedColumns = computed(() => props.detectedColumns.filter((column) => !columnMapping.value[column]));

const hasRequiredFields = computed(() => {
    const required = ['student_code', 'full_name'];
    return required.every((field) => mappedFields.value.includes(field));
});

// Methods
const handleMappingChange = (column: string, field: string): void => {
    if (field === '') {
        delete columnMapping.value[column];
    } else {
        columnMapping.value[column] = field;
    }
    emit('mapping-changed', { ...columnMapping.value });
};

const clearMapping = (column: string): void => {
    delete columnMapping.value[column];
    emit('mapping-changed', { ...columnMapping.value });
};

const isFieldMapped = (field: string): boolean => {
    return mappedFields.value.includes(field);
};

const isRequiredField = (field: string): boolean => {
    return requiredFields.value.some((reqField) => reqField.value === field);
};

const getFieldLabel = (fieldValue: string): string => {
    const field = databaseFields.find((f) => f.value === fieldValue);
    return field ? field.label : fieldValue;
};

// Watch for prop changes
watch(
    () => props.initialMapping,
    (newMapping) => {
        columnMapping.value = { ...newMapping };
    },
    { immediate: true },
);

// Emit initial mapping
emit('mapping-changed', { ...columnMapping.value });
</script>
<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-medium text-gray-900">Map Excel columns to database fields</h4>
            <div class="text-sm text-gray-500">{{ mappedCount }}/{{ detectedColumns.length }} columns mapped</div>
        </div>

        <!-- Required Fields Warning -->
        <div v-if="!hasRequiredFields" class="rounded-md bg-yellow-50 p-3">
            <div class="flex">
                <AlertTriangle class="h-5 w-5 text-yellow-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Required Fields Missing</h3>
                    <div class="mt-1 text-sm text-yellow-700">
                        <p>The following required fields must be mapped:</p>
                        <ul class="mt-1 list-inside list-disc">
                            <li v-if="!mappedFields.includes('student_code')">Student Code</li>
                            <li v-if="!mappedFields.includes('full_name')">Full Name</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapping Table -->
        <div class="overflow-hidden rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Excel Column</th>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Database Field</th>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Required</th>
                        <th class="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="column in detectedColumns" :key="column" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            {{ column }}
                        </td>
                        <td class="px-4 py-3">
                            <select :value="columnMapping[column] || ''" @change="handleMappingChange(column, $event.target.value)" class="block w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Select Field --</option>
                                <optgroup label="Required Fields">
                                    <option v-for="field in requiredFields" :key="field.value" :value="field.value" :disabled="isFieldMapped(field.value) && columnMapping[column] !== field.value">
                                        {{ field.label }}
                                    </option>
                                </optgroup>
                                <optgroup label="Optional Fields">
                                    <option v-for="field in optionalFields" :key="field.value" :value="field.value" :disabled="isFieldMapped(field.value) && columnMapping[column] !== field.value">
                                        {{ field.label }}
                                    </option>
                                </optgroup>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            <span v-if="isRequiredField(columnMapping[column])" class="inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800"> Required </span>
                            <span v-else class="text-gray-400">Optional</span>
                        </td>
                        <td class="px-4 py-3">
                            <button v-if="columnMapping[column]" @click="clearMapping(column)" class="text-sm text-red-600 hover:text-red-800">Clear</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Auto-mapping suggestion -->
        <div v-if="unmappedColumns.length > 0" class="rounded-md bg-blue-50 p-3">
            <div class="flex">
                <Info class="h-5 w-5 text-blue-400" />
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Unmapped Columns</h3>
                    <div class="mt-1 text-sm text-blue-700">
                        <p>The following columns are not mapped and will be ignored during import:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="column in unmappedColumns" :key="column" class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                                {{ column }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapping Summary -->
        <div class="rounded-md bg-gray-50 p-3">
            <h4 class="mb-2 text-sm font-medium text-gray-900">Mapping Summary</h4>
            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                <div>
                    <p class="font-medium text-green-700">Mapped Fields ({{ mappedCount }})</p>
                    <ul class="mt-1 space-y-1">
                        <li v-for="[column, field] in Object.entries(columnMapping)" :key="column" class="text-gray-600">
                            <span class="font-medium">{{ column }}</span> → {{ getFieldLabel(field) }}
                        </li>
                    </ul>
                </div>
                <div v-if="unmappedColumns.length > 0">
                    <p class="font-medium text-gray-700">Unmapped Columns ({{ unmappedColumns.length }})</p>
                    <ul class="mt-1 space-y-1">
                        <li v-for="column in unmappedColumns" :key="column" class="text-gray-600">
                            {{ column }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
