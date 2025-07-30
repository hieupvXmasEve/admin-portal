<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { ExportOptions } from '@/composables/useExport';
import { useExport } from '@/composables/useExport';
import type { AssignmentFilters } from '@/types/TeachingAssignment';
import { computed, ref, watch } from 'vue';

// Props
interface Props {
    isOpen: boolean;
    currentFilters?: AssignmentFilters;
}

const props = withDefaults(defineProps<Props>(), {
    currentFilters: () => ({}),
});

// Emits
const emit = defineEmits<{
    close: [];
    'export-started': [];
    'export-completed': [];
}>();

// Composables
const {
    isExporting,
    exportProgress,
    exportError,
    performExport,
    retryLastExport,
    clearExportError,
    getExportFormats,
    getExportPresets,
    generateFilename,
    getExportSummary,
} = useExport();

// State
const selectedFormat = ref<'excel' | 'pdf'>('excel');
const selectedPreset = ref<string>('');
const exportOptions = ref<ExportOptions>({
    format: 'excel',
    includeFilters: true,
    includeStatistics: true,
    includeConflicts: false,
    customFilename: '',
});

// Computed
const exportFormats = computed(() => getExportFormats());
const exportPresets = computed(() => getExportPresets());

const appliedFiltersCount = computed(
    () => Object.keys(props.currentFilters).filter((key) => props.currentFilters[key as keyof AssignmentFilters]).length,
);

const previewFilename = computed(() => {
    if (exportOptions.value.customFilename) {
        return `${exportOptions.value.customFilename}.${selectedFormat.value}`;
    }
    return generateFilename(selectedFormat.value, props.currentFilters);
});

// Methods
const handleClose = () => {
    if (!isExporting.value) {
        emit('close');
        resetState();
    }
};

const resetState = () => {
    selectedFormat.value = 'excel';
    selectedPreset.value = '';
    exportOptions.value = {
        format: 'excel',
        includeFilters: true,
        includeStatistics: true,
        includeConflicts: false,
        customFilename: '',
    };
    clearExportError();
};

const selectPreset = (preset: any) => {
    selectedPreset.value = preset.name;
    exportOptions.value = {
        ...exportOptions.value,
        ...preset.options,
    };
};

const handleExport = async () => {
    if (!selectedFormat.value) return;

    emit('export-started');

    const success = await performExport(props.currentFilters, {
        ...exportOptions.value,
        format: selectedFormat.value,
    });

    if (success) {
        emit('export-completed');
        handleClose();
    }
};

const retryExport = () => {
    clearExportError();
    retryLastExport();
};

// Watch for format changes
watch(selectedFormat, (newFormat) => {
    exportOptions.value.format = newFormat;
    selectedPreset.value = ''; // Clear preset when format changes
});

// Reset state when modal opens
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            resetState();
        }
    },
);
</script>
<template>
    <Dialog :open="isOpen" @update:open="handleClose">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Export Teaching Assignments</DialogTitle>
                <DialogDescription> Choose your export format and options to generate a report of teaching assignments. </DialogDescription>
            </DialogHeader>

            <div class="space-y-6">
                <!-- Export Format Selection -->
                <div>
                    <h3 class="mb-3 text-sm font-medium text-gray-900">Export Format</h3>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div
                            v-for="format in exportFormats"
                            :key="format.value"
                            class="relative cursor-pointer rounded-lg border p-4 transition-colors"
                            :class="{
                                'border-blue-500 bg-blue-50': selectedFormat === format.value,
                                'border-gray-200 hover:border-gray-300': selectedFormat !== format.value,
                            }"
                            @click="selectedFormat = format.value"
                        >
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <Icon :name="format.icon" class="h-6 w-6 text-gray-400" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <h4 class="text-sm font-medium text-gray-900">{{ format.label }}</h4>
                                        <Badge v-if="format.recommended" variant="default" class="ml-2 text-xs"> Recommended </Badge>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">{{ format.description }}</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <div
                                        class="h-4 w-4 rounded-full border-2"
                                        :class="{
                                            'border-blue-500 bg-blue-500': selectedFormat === format.value,
                                            'border-gray-300': selectedFormat !== format.value,
                                        }"
                                    >
                                        <Icon v-if="selectedFormat === format.value" name="check" class="m-0.5 h-2 w-2 text-white" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Presets -->
                <div>
                    <h3 class="mb-3 text-sm font-medium text-gray-900">Quick Export Options</h3>
                    <div class="space-y-2">
                        <button
                            v-for="preset in exportPresets"
                            :key="preset.name"
                            type="button"
                            class="w-full rounded-lg border p-3 text-left transition-colors hover:bg-gray-50"
                            :class="{
                                'border-blue-500 bg-blue-50': selectedPreset === preset.name,
                                'border-gray-200': selectedPreset !== preset.name,
                            }"
                            @click="selectPreset(preset)"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ preset.name }}</h4>
                                    <p class="mt-1 text-xs text-gray-600">{{ preset.description }}</p>
                                </div>
                                <Icon v-if="selectedPreset === preset.name" name="check-circle" class="h-5 w-5 text-blue-500" />
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Export Options -->
                <div>
                    <h3 class="mb-3 text-sm font-medium text-gray-900">Export Options</h3>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input
                                v-model="exportOptions.includeFilters"
                                type="checkbox"
                                class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                            />
                            <span class="ml-2 text-sm text-gray-700">Include applied filters in export</span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="exportOptions.includeStatistics"
                                type="checkbox"
                                class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                            />
                            <span class="ml-2 text-sm text-gray-700">Include summary statistics</span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="exportOptions.includeConflicts"
                                type="checkbox"
                                class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                            />
                            <span class="ml-2 text-sm text-gray-700">Include conflict information</span>
                        </label>
                    </div>
                </div>

                <!-- Custom Filename -->
                <div>
                    <label for="custom-filename" class="mb-1 block text-sm font-medium text-gray-700"> Custom Filename (Optional) </label>
                    <div class="flex items-center space-x-2">
                        <input
                            id="custom-filename"
                            v-model="exportOptions.customFilename"
                            type="text"
                            placeholder="Leave empty for auto-generated name"
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        />
                        <span class="text-sm text-gray-500">.{{ selectedFormat }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-600">Preview: {{ previewFilename }}</p>
                </div>

                <!-- Export Summary -->
                <div class="rounded-lg bg-gray-50 p-4">
                    <h4 class="mb-2 text-sm font-medium text-gray-900">Export Summary</h4>
                    <div class="space-y-1 text-sm text-gray-600">
                        <div>
                            Format: <span class="font-medium">{{ selectedFormat.toUpperCase() }}</span>
                        </div>
                        <div>
                            Applied Filters: <span class="font-medium">{{ appliedFiltersCount }}</span>
                        </div>
                        <div>
                            Include Statistics: <span class="font-medium">{{ exportOptions.includeStatistics ? 'Yes' : 'No' }}</span>
                        </div>
                        <div>
                            Include Conflicts: <span class="font-medium">{{ exportOptions.includeConflicts ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Export Progress -->
                <div v-if="isExporting" class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Generating export...</span>
                        <span class="font-medium">{{ exportProgress }}%</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-200">
                        <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: `${exportProgress}%` }" />
                    </div>
                </div>

                <!-- Export Error -->
                <div v-if="exportError" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="flex items-center">
                        <Icon name="alert-circle" class="mr-2 h-5 w-5 text-red-500" />
                        <div>
                            <h4 class="text-sm font-medium text-red-800">Export Failed</h4>
                            <p class="mt-1 text-sm text-red-700">{{ exportError }}</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <Button variant="outline" size="sm" @click="retryExport">
                            <Icon name="refresh-cw" class="mr-1 h-4 w-4" />
                            Retry
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <Button variant="outline" @click="handleClose" :disabled="isExporting"> Cancel </Button>
                <Button @click="handleExport" :disabled="isExporting || !selectedFormat">
                    <Icon v-if="isExporting" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                    <Icon v-else name="download" class="mr-2 h-4 w-4" />
                    {{ isExporting ? 'Exporting...' : 'Export' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
