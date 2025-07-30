<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { useExport } from '@/composables/useExport';
import type { AssignmentFilters } from '@/types/TeachingAssignment';
import { computed, onMounted, onUnmounted, ref } from 'vue';

// Props
interface Props {
    variant?: 'default' | 'outline' | 'ghost' | 'destructive' | 'secondary';
    size?: 'sm' | 'default' | 'lg';
    disabled?: boolean;
    buttonText?: string;
    exportingText?: string;
    icon?: string;
    showQuickExport?: boolean;
    showProgress?: boolean;
    currentFilters?: AssignmentFilters;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'outline',
    size: 'default',
    disabled: false,
    buttonText: 'Export',
    exportingText: 'Exporting...',
    icon: 'download',
    showQuickExport: true,
    showProgress: false,
    currentFilters: () => ({}),
});

// Emits
const emit = defineEmits<{
    'export-modal-requested': [];
    'export-started': [format: string];
    'export-completed': [format: string];
    'export-failed': [error: string];
}>();

// Composables
const { isExporting, exportProgress, exportError, exportToExcel, exportToPdf, clearExportError } = useExport();

// State
const showDropdown = ref(false);

// Computed
const quickExportOptions = computed(() => [
    {
        format: 'excel' as const,
        label: 'Export to Excel',
        icon: 'file-spreadsheet',
    },
    {
        format: 'pdf' as const,
        label: 'Export to PDF',
        icon: 'file-text',
    },
]);

// Methods
const handleClick = () => {
    if (props.showQuickExport) {
        toggleDropdown();
    } else {
        openExportModal();
    }
};

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
    showDropdown.value = false;
};

const handleQuickExport = async (format: 'excel' | 'pdf') => {
    closeDropdown();
    clearExportError();

    emit('export-started', format);

    try {
        let success = false;

        if (format === 'excel') {
            success = await exportToExcel(props.currentFilters);
        } else if (format === 'pdf') {
            success = await exportToPdf(props.currentFilters);
        }

        if (success) {
            emit('export-completed', format);
        } else {
            emit('export-failed', exportError.value || 'Export failed');
        }
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Unknown error occurred';
        emit('export-failed', errorMessage);
    }
};

const openExportModal = () => {
    closeDropdown();
    emit('export-modal-requested');
};

// Click outside handler
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    const dropdown = document.querySelector('.export-dropdown');
    const button = document.querySelector('.export-button');

    if (dropdown && !dropdown.contains(target) && !button?.contains(target)) {
        closeDropdown();
    }
};

// Lifecycle
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative">
        <!-- Main Export Button -->
        <Button :variant="variant" :size="size" :disabled="disabled || isExporting" @click="handleClick">
            <Icon v-if="isExporting" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
            <Icon v-else :name="icon" class="mr-2 h-4 w-4" />
            {{ isExporting ? exportingText : buttonText }}
        </Button>

        <!-- Quick Export Dropdown -->
        <div
            v-if="showQuickExport && !isExporting"
            class="absolute top-full right-0 z-50 mt-1 w-48 rounded-md border border-gray-200 bg-white shadow-lg"
            v-show="showDropdown"
        >
            <div class="py-1">
                <button
                    v-for="option in quickExportOptions"
                    :key="option.format"
                    type="button"
                    class="flex w-full items-center px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                    @click="handleQuickExport(option.format)"
                >
                    <Icon :name="option.icon" class="mr-2 h-4 w-4 text-gray-400" />
                    {{ option.label }}
                </button>
                <div class="my-1 border-t border-gray-100"></div>
                <button
                    type="button"
                    class="flex w-full items-center px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                    @click="openExportModal"
                >
                    <Icon name="settings" class="mr-2 h-4 w-4 text-gray-400" />
                    More Options...
                </button>
            </div>
        </div>

        <!-- Export Progress Overlay -->
        <div v-if="isExporting && showProgress" class="bg-opacity-90 absolute inset-0 flex items-center justify-center rounded-md bg-white">
            <div class="text-center">
                <div class="mx-auto mb-2 h-8 w-8 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"></div>
                <div class="text-xs text-gray-600">{{ exportProgress }}%</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for the export button */
.export-dropdown {
    /* Add any custom dropdown styles here */
}

.export-button {
    /* Add any custom button styles here */
}
</style>
