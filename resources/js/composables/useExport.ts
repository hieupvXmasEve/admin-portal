import type { AssignmentFilters, ExportRequest } from '@/types/TeachingAssignment';
import { computed, ref } from 'vue';
import { useTeachingAssignments } from './useTeachingAssignments';

export interface ExportOptions {
    format: 'excel' | 'pdf';
    includeFilters: boolean;
    includeStatistics: boolean;
    includeConflicts: boolean;
    customFilename?: string;
}

export function useExport() {
    const { exportAssignments, loading } = useTeachingAssignments();

    // State
    const isExporting = ref(false);
    const exportProgress = ref(0);
    const exportError = ref<string | null>(null);
    const lastExportRequest = ref<ExportRequest | null>(null);

    // Computed
    const canExport = computed(() => !isExporting.value && !loading.value);

    // Methods
    const performExport = async (filters: AssignmentFilters = {}, options: ExportOptions): Promise<boolean> => {
        if (!canExport.value) {
            return false;
        }

        isExporting.value = true;
        exportProgress.value = 0;
        exportError.value = null;

        try {
            // Prepare export request
            const exportRequest: ExportRequest = {
                format: options.format,
                ...filters,
            };

            lastExportRequest.value = exportRequest;

            // Simulate progress for better UX
            const progressInterval = setInterval(() => {
                if (exportProgress.value < 90) {
                    exportProgress.value += 10;
                }
            }, 200);

            // Perform the actual export
            await exportAssignments(exportRequest);

            // Complete progress
            clearInterval(progressInterval);
            exportProgress.value = 100;

            // Reset after a short delay
            setTimeout(() => {
                exportProgress.value = 0;
            }, 1000);

            return true;
        } catch (error) {
            exportError.value = error instanceof Error ? error.message : 'Export failed';
            console.error('Export failed:', error);
            return false;
        } finally {
            isExporting.value = false;
        }
    };

    const exportToExcel = (filters: AssignmentFilters = {}, options: Partial<ExportOptions> = {}) => {
        return performExport(filters, {
            format: 'excel',
            includeFilters: true,
            includeStatistics: true,
            includeConflicts: false,
            ...options,
        });
    };

    const exportToPdf = (filters: AssignmentFilters = {}, options: Partial<ExportOptions> = {}) => {
        return performExport(filters, {
            format: 'pdf',
            includeFilters: true,
            includeStatistics: true,
            includeConflicts: true,
            ...options,
        });
    };

    const retryLastExport = () => {
        if (lastExportRequest.value) {
            return exportAssignments(lastExportRequest.value);
        }
        return Promise.resolve();
    };

    const clearExportError = () => {
        exportError.value = null;
    };

    // Export format helpers
    const getExportFormats = () => [
        {
            value: 'excel',
            label: 'Excel (.xlsx)',
            description: 'Spreadsheet format with detailed data and filtering capabilities',
            icon: 'file-spreadsheet',
            recommended: true,
        },
        {
            value: 'pdf',
            label: 'PDF (.pdf)',
            description: 'Formatted report suitable for printing and sharing',
            icon: 'file-text',
            recommended: false,
        },
    ];

    const getExportPresets = () => [
        {
            name: 'All Assignments',
            description: 'Export all teaching assignments with complete details',
            filters: {},
            options: {
                includeFilters: false,
                includeStatistics: true,
                includeConflicts: true,
            },
        },
        {
            name: 'Unassigned Courses',
            description: 'Export only courses that need lecturer assignment',
            filters: { assignment_status: 'unassigned' as const },
            options: {
                includeFilters: true,
                includeStatistics: true,
                includeConflicts: false,
            },
        },
        {
            name: 'Urgent Assignments',
            description: 'Export courses requiring immediate attention',
            filters: { assignment_status: 'urgent' as const },
            options: {
                includeFilters: true,
                includeStatistics: true,
                includeConflicts: true,
            },
        },
        {
            name: 'Current Semester',
            description: 'Export assignments for the active semester only',
            filters: {}, // Will be populated with current semester
            options: {
                includeFilters: true,
                includeStatistics: true,
                includeConflicts: false,
            },
        },
    ];

    const generateFilename = (format: string, filters: AssignmentFilters = {}) => {
        const timestamp = new Date().toISOString().split('T')[0];
        let filename = 'teaching-assignments';

        // Add filter-based suffixes
        if (filters.assignment_status) {
            filename += `-${filters.assignment_status}`;
        }

        if (filters.semester_id) {
            filename += `-semester-${filters.semester_id}`;
        }

        if (filters.campus_id) {
            filename += `-campus-${filters.campus_id}`;
        }

        if (filters.faculty) {
            filename += `-${filters.faculty.toLowerCase().replace(/\s+/g, '-')}`;
        }

        return `${filename}-${timestamp}.${format}`;
    };

    const validateExportRequest = (filters: AssignmentFilters, options: ExportOptions): string[] => {
        const errors: string[] = [];

        // Validate format
        if (!['excel', 'pdf'].includes(options.format)) {
            errors.push('Invalid export format selected');
        }

        // Validate filters
        if (filters.semester_id && (typeof filters.semester_id !== 'number' || filters.semester_id <= 0)) {
            errors.push('Invalid semester selected');
        }

        if (filters.campus_id && (typeof filters.campus_id !== 'number' || filters.campus_id <= 0)) {
            errors.push('Invalid campus selected');
        }

        // Validate filename if provided
        if (options.customFilename) {
            const invalidChars = /[<>:"/\\|?*]/;
            if (invalidChars.test(options.customFilename)) {
                errors.push('Filename contains invalid characters');
            }
        }

        return errors;
    };

    const getExportSummary = (filters: AssignmentFilters, options: ExportOptions) => {
        const summary = {
            format: options.format.toUpperCase(),
            filename: options.customFilename || generateFilename(options.format, filters),
            includesFilters: options.includeFilters,
            includesStatistics: options.includeStatistics,
            includesConflicts: options.includeConflicts,
            appliedFilters: Object.keys(filters).filter((key) => filters[key as keyof AssignmentFilters]).length,
        };

        return summary;
    };

    return {
        // State
        isExporting: isExporting,
        exportProgress: exportProgress,
        exportError: exportError,
        lastExportRequest: lastExportRequest,

        // Computed
        canExport,

        // Methods
        performExport,
        exportToExcel,
        exportToPdf,
        retryLastExport,
        clearExportError,

        // Helpers
        getExportFormats,
        getExportPresets,
        generateFilename,
        validateExportRequest,
        getExportSummary,
    };
}
