import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

export interface ImportState {
    step: 'upload' | 'preview' | 'mapping' | 'processing' | 'results';
    file: File | null;
    detectedColumns: string[];
    columnMapping: Record<string, string>;
    previewData: StudentApplicationPreview[];
    validationErrors: ValidationError[];
    importResults: ImportResults | null;
    isProcessing: boolean;
    progress: number;
}

export interface StudentApplicationPreview {
    row_number: number;
    data: Record<string, any>;
    errors: string[];
    warnings: string[];
    is_valid: boolean;
}

export interface ValidationError {
    row: number;
    student_code: string;
    errors: string[];
}

export interface ImportResults {
    total_processed: number;
    created: number;
    updated: number;
    skipped: number;
    errors: ImportError[];
    execution_time: string;
}

export interface ImportError {
    row: number;
    student_code: string;
    errors: string[];
}

export interface PreviewResponse {
    success: boolean;
    data?: {
        file_info: {
            name: string;
            size: number;
            type: string;
            temp_path: string;
        };
        detected_columns: string[];
        column_mapping: Record<string, string>;
        unmapped_columns: string[];
        preview_data: StudentApplicationPreview[];
        total_rows: number;
        validation_summary: {
            valid_rows: number;
            invalid_rows: number;
            warnings: string[];
        };
        execution_time: string;
    };
    error?: string;
}

export interface ProcessResponse {
    success: boolean;
    data?: ImportResults;
    error?: string;
}

export function useStudentApplicationImport() {
    // State
    const currentStep = ref<ImportState['step']>('upload');
    const selectedFile = ref<File | null>(null);
    const previewData = ref<StudentApplicationPreview[]>([]);
    const detectedColumns = ref<string[]>([]);
    const columnMapping = ref<Record<string, string>>({});
    const validationSummary = ref<any>({});
    const importResults = ref<ImportResults | null>(null);
    const isLoading = ref(false);
    const isProcessing = ref(false);
    const progress = ref(0);
    const error = ref<string | null>(null);

    // Computed
    const canStartImport = computed(() => {
        return selectedFile.value && Object.keys(columnMapping.value).length > 0 && Object.values(columnMapping.value).includes('email') && Object.values(columnMapping.value).includes('student_code') && Object.values(columnMapping.value).includes('full_name');
    });

    const hasValidData = computed(() => {
        return previewData.value.length > 0 && validationSummary.value.valid_rows > 0;
    });

    // API Methods
    const previewImport = async (file: File, options: Record<string, any> = {}): Promise<void> => {
        isLoading.value = true;
        error.value = null;

        try {
            const formData = new FormData();
            formData.append('file', file);

            // Add options
            Object.keys(options).forEach((key) => {
                if (options[key] !== undefined) {
                    formData.append(`options[${key}]`, String(options[key]));
                }
            });

            const response = await fetch(route('student-applications.import.preview'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            // Check if response is ok
            if (!response.ok) {
                let errorMessage = `HTTP error ${response.status}: ${response.statusText}`;

                try {
                    // Try to parse error response as JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        errorMessage = errorData.error || errorData.message || errorMessage;
                    } else {
                        // If not JSON, try to get text response
                        const textResponse = await response.text();
                        if (textResponse && textResponse.length < 500) {
                            errorMessage = textResponse;
                        }
                    }
                } catch (parseError) {
                    // Use the default error message if parsing fails
                    console.warn('Failed to parse error response:', parseError);
                }

                if (response.status === 403) {
                    throw new Error('You do not have permission to import student applications');
                }
                if (response.status === 422) {
                    throw new Error('Validation failed. Please check your file format and try again');
                }
                if (response.status === 500) {
                    throw new Error(`Server error: ${errorMessage}`);
                }
                throw new Error(errorMessage);
            }

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const responseText = await response.text();
                console.error('Non-JSON response received:', {
                    status: response.status,
                    statusText: response.statusText,
                    contentType,
                    responsePreview: responseText.substring(0, 200),
                });
                throw new Error('Server returned invalid response format. Expected JSON but received: ' + (contentType || 'unknown'));
            }

            const data: PreviewResponse = await response.json();

            if (data.success && data.data) {
                selectedFile.value = file;
                detectedColumns.value = data.data.detected_columns;
                columnMapping.value = data.data.column_mapping;
                previewData.value = data.data.preview_data;
                validationSummary.value = data.data.validation_summary;
                currentStep.value = 'preview';
            } else {
                throw new Error(data.error || 'Preview failed');
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'An unexpected error occurred';
            console.error('Preview import failed:', err);
        } finally {
            isLoading.value = false;
        }
    };

    const processImport = async (options: Record<string, any> = {}): Promise<void> => {
        if (!selectedFile.value || !canStartImport.value) {
            const missingItems = [];
            if (!selectedFile.value) missingItems.push('file');
            if (!Object.keys(columnMapping.value).length) missingItems.push('column mapping');
            if (!Object.values(columnMapping.value).includes('email')) missingItems.push('email mapping');
            if (!Object.values(columnMapping.value).includes('student_code')) missingItems.push('student_code mapping');
            if (!Object.values(columnMapping.value).includes('full_name')) missingItems.push('full_name mapping');

            throw new Error(`Cannot start import: missing ${missingItems.join(', ')}`);
        }

        console.log('Starting import with:', {
            fileName: selectedFile.value.name,
            fileSize: selectedFile.value.size,
            columnMapping: columnMapping.value,
            options,
            canStartImport: canStartImport.value,
        });

        currentStep.value = 'processing';
        isProcessing.value = true;
        progress.value = 0;
        error.value = null;

        try {
            const formData = new FormData();
            formData.append('file', selectedFile.value);
            formData.append('column_mapping', JSON.stringify(columnMapping.value));

            // Add options
            const defaultOptions: Record<string, any> = {
                update_existing: true,
                skip_invalid: false,
                ...options,
            };

            Object.keys(defaultOptions).forEach((key) => {
                if (defaultOptions[key] !== undefined) {
                    formData.append(`options[${key}]`, String(defaultOptions[key]));
                }
            });

            console.log('Form data prepared:', {
                formDataEntries: Array.from(formData.entries()),
                url: route('student-applications.import.process'),
            });

            // Simulate progress updates
            const progressInterval = setInterval(() => {
                if (progress.value < 90) {
                    progress.value += Math.random() * 10;
                }
            }, 500);

            const response = await fetch(route('student-applications.import.process'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            clearInterval(progressInterval);

            // Check if response is ok
            if (!response.ok) {
                let errorMessage = `HTTP error ${response.status}: ${response.statusText}`;

                try {
                    // Try to parse error response as JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        errorMessage = errorData.error || errorData.message || errorMessage;
                    } else {
                        // If not JSON, try to get text response
                        const textResponse = await response.text();
                        if (textResponse && textResponse.length < 500) {
                            errorMessage = textResponse;
                        }
                    }
                } catch (parseError) {
                    // Use the default error message if parsing fails
                    console.warn('Failed to parse error response:', parseError);
                }

                if (response.status === 403) {
                    throw new Error('You do not have permission to import student applications');
                }
                if (response.status === 422) {
                    throw new Error('Validation failed. Please check your file format and mapping');
                }
                if (response.status === 500) {
                    throw new Error(`Server error: ${errorMessage}`);
                }
                throw new Error(errorMessage);
            }

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const responseText = await response.text();
                console.error('Non-JSON response received:', {
                    status: response.status,
                    statusText: response.statusText,
                    contentType,
                    responsePreview: responseText.substring(0, 200),
                });
                throw new Error('Server returned invalid response format. Expected JSON but received: ' + (contentType || 'unknown'));
            }

            const data: ProcessResponse = await response.json();

            if (data.success && data.data) {
                progress.value = 100;
                importResults.value = data.data;
                currentStep.value = 'results';
            } else {
                throw new Error(data.error || 'Import failed');
            }
        } catch (err) {
            console.error('Process import failed:', {
                error: err,
                errorMessage: err instanceof Error ? err.message : 'Unknown error',
                selectedFile: selectedFile.value?.name,
                columnMapping: columnMapping.value,
                options,
            });

            error.value = err instanceof Error ? err.message : 'An unexpected error occurred';
            currentStep.value = 'preview';
        } finally {
            isProcessing.value = false;
        }
    };

    const downloadTemplate = (): void => {
        window.location.href = route('student-applications.import.template');
    };

    // Helper methods
    const updateColumnMapping = (newMapping: Record<string, string>): void => {
        columnMapping.value = { ...newMapping };
    };

    const resetImport = (): void => {
        currentStep.value = 'upload';
        selectedFile.value = null;
        detectedColumns.value = [];
        columnMapping.value = {};
        previewData.value = [];
        validationSummary.value = {};
        importResults.value = null;
        progress.value = 0;
        error.value = null;
        isLoading.value = false;
        isProcessing.value = false;
    };

    const goToStep = (step: ImportState['step']): void => {
        currentStep.value = step;
        error.value = null;
    };

    const validateRequiredMapping = (): { isValid: boolean; missing: string[] } => {
        const requiredFields = ['student_code', 'full_name'];
        const mappedFields = Object.values(columnMapping.value);
        const missing = requiredFields.filter((field) => !mappedFields.includes(field));

        return {
            isValid: missing.length === 0,
            missing,
        };
    };

    // Export helper for error reports
    const downloadErrorReport = (): void => {
        if (!importResults.value?.errors || importResults.value.errors.length === 0) return;

        const errorData = importResults.value.errors.map((error) => ({
            Row: error.row,
            'Student Code': error.student_code || 'N/A',
            Errors: error.errors.join('; '),
        }));

        const csvContent = [
            Object.keys(errorData[0]).join(','),
            ...errorData.map((row) =>
                Object.values(row)
                    .map((val) => `"${String(val).replace(/"/g, '""')}"`)
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

    // Navigation helpers
    const goToApplicationsList = (): void => {
        router.visit(route('student-applications.index'));
    };

    const startNewImport = (): void => {
        resetImport();
    };

    // Watch for step changes to clear errors
    watch(currentStep, () => {
        error.value = null;
    });

    return {
        // State
        currentStep: computed(() => currentStep.value),
        selectedFile: computed(() => selectedFile.value),
        detectedColumns: computed(() => detectedColumns.value),
        columnMapping: computed(() => columnMapping.value),
        previewData: computed(() => previewData.value),
        validationSummary: computed(() => validationSummary.value),
        importResults: computed(() => importResults.value),
        isLoading: computed(() => isLoading.value),
        isProcessing: computed(() => isProcessing.value),
        progress: computed(() => progress.value),
        error: computed(() => error.value),

        // Computed
        canStartImport,
        hasValidData,

        // Methods
        previewImport,
        processImport,
        downloadTemplate,
        updateColumnMapping,
        resetImport,
        goToStep,
        validateRequiredMapping,
        downloadErrorReport,
        goToApplicationsList,
        startNewImport,
    };
}
