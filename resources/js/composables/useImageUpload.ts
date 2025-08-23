import { ref, reactive, computed, readonly } from 'vue';
// Note: useApi import removed as it's not currently used in this implementation
import type {
    UploadState,
    UploadRecord,
    ImageUploadOptions,
    FilePreview,
    UploadResponse,
    FileValidationError
} from '@/types/imageUpload';

/**
 * Composable for handling image uploads with progress tracking and validation
 * Provides reactive state management and file validation for image upload functionality
 */
export function useImageUpload(options: ImageUploadOptions) {

    // Reactive state for upload process
    const state = reactive<UploadState>({
        isUploading: false,
        progress: {
            loaded: 0,
            total: 0,
            percentage: 0
        },
        error: null,
        success: false,
        uploadedFile: null,
        uploadedFiles: []
    });

    // File previews for selected files
    const filePreviews = ref<FilePreview[]>([]);

    // Validation errors
    const validationErrors = ref<FileValidationError[]>([]);

    // Default configuration
    const config = reactive({
        context: options.context,
        maxSize: options.maxSize || 10 * 1024 * 1024, // 10MB default
        allowedTypes: options.allowedTypes || ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        multiple: options.multiple || false,
        immediate: options.immediate || false
    });

    // Computed properties
    const isValid = computed(() => validationErrors.value.length === 0);
    const hasFiles = computed(() => filePreviews.value.length > 0);
    const canUpload = computed(() => hasFiles.value && isValid.value && !state.isUploading);

    /**
     * Validate a single file against the configuration
     */
    function validateFile(file: File): FileValidationError[] {
        const errors: FileValidationError[] = [];

        // Check file size
        if (file.size > config.maxSize) {
            errors.push({
                field: 'size',
                message: `File size must be less than ${formatFileSize(config.maxSize)}`
            });
        }

        // Check file type
        if (!config.allowedTypes.includes(file.type)) {
            errors.push({
                field: 'type',
                message: `File type must be one of: ${config.allowedTypes.join(', ')}`
            });
        }

        // Check if file is actually an image
        if (!file.type.startsWith('image/')) {
            errors.push({
                field: 'type',
                message: 'File must be an image'
            });
        }

        return errors;
    }

    /**
     * Validate all selected files
     */
    function validateFiles(files: File[]): FileValidationError[] {
        const allErrors: FileValidationError[] = [];

        files.forEach((file, index) => {
            const fileErrors = validateFile(file);
            fileErrors.forEach(error => {
                allErrors.push({
                    field: `file_${index}_${error.field}`,
                    message: `${file.name}: ${error.message}`
                });
            });
        });

        return allErrors;
    }

    /**
     * Create preview URLs for selected files
     */
    function createFilePreviews(files: File[]): FilePreview[] {
        return files.map(file => {
            const id = `${file.name}_${file.size}_${Date.now()}_${Math.random()}`;
            return {
                file,
                url: URL.createObjectURL(file),
                id,
                uploadState: {
                    id,
                    file,
                    isUploading: false,
                    progress: { loaded: 0, total: 0, percentage: 0 },
                    error: null,
                    success: false,
                    uploadedFile: null
                }
            };
        });
    }

    /**
     * Clean up preview URLs to prevent memory leaks
     */
    function cleanupPreviews() {
        filePreviews.value.forEach(preview => {
            URL.revokeObjectURL(preview.url);
        });
        filePreviews.value = [];
    }

    /**
     * Select files for upload
     */
    function selectFiles(files: FileList | File[]) {
        // Clean up existing previews
        cleanupPreviews();

        // Reset state
        resetState();

        const fileArray = Array.from(files);

        // Validate files
        const errors = validateFiles(fileArray);
        validationErrors.value = errors;

        if (errors.length === 0) {
            // Create previews for valid files
            filePreviews.value = createFilePreviews(fileArray);

            // Auto-upload if immediate mode is enabled
            if (config.immediate && fileArray.length === 1) {
                uploadFiles();
            }
        }
    }

    /**
     * Upload files to the server
     */
    async function uploadFiles(): Promise<UploadRecord[]> {
        if (!canUpload.value) {
            throw new Error('Cannot upload: files are invalid or upload is in progress');
        }

        state.isUploading = true;
        state.error = null;
        state.success = false;
        state.uploadedFiles = [];

        const uploadedFiles: UploadRecord[] = [];
        const totalFiles = filePreviews.value.length;

        try {
            // Upload files in parallel for better performance
            const uploadPromises = filePreviews.value.map((preview, index) =>
                uploadSingleFileWithState(preview, index, totalFiles)
            );

            const results = await Promise.allSettled(uploadPromises);

            // Process results
            results.forEach((result, index) => {
                if (result.status === 'fulfilled') {
                    uploadedFiles.push(result.value);
                } else {
                    // Mark individual file as failed
                    const preview = filePreviews.value[index];
                    if (preview.uploadState) {
                        preview.uploadState.error = result.reason instanceof Error ? result.reason.message : 'Upload failed';
                        preview.uploadState.success = false;
                        preview.uploadState.isUploading = false;
                    }
                }
            });

            state.uploadedFiles = uploadedFiles;
            state.success = uploadedFiles.length > 0;

            // Store the first uploaded file for single uploads
            if (uploadedFiles.length === 1) {
                state.uploadedFile = uploadedFiles[0];
            }

            // Set overall error if no files were uploaded
            if (uploadedFiles.length === 0) {
                state.error = 'All uploads failed';
            } else if (uploadedFiles.length < totalFiles) {
                state.error = `${totalFiles - uploadedFiles.length} of ${totalFiles} uploads failed`;
            }

            return uploadedFiles;
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Upload failed';
            throw error;
        } finally {
            state.isUploading = false;
        }
    }

    /**
     * Upload a single file with individual state tracking
     */
    async function uploadSingleFileWithState(preview: FilePreview, index: number, total: number): Promise<UploadRecord> {
        if (!preview.uploadState) {
            throw new Error('Upload state not initialized');
        }

        const uploadState = preview.uploadState;
        uploadState.isUploading = true;
        uploadState.error = null;
        uploadState.success = false;

        try {
            const uploadedFile = await uploadSingleFile(preview.file, index, total, uploadState);
            uploadState.success = true;
            uploadState.uploadedFile = uploadedFile;
            return uploadedFile;
        } catch (error) {
            uploadState.error = error instanceof Error ? error.message : 'Upload failed';
            uploadState.success = false;
            throw error;
        } finally {
            uploadState.isUploading = false;
        }
    }

    /**
     * Upload a single file with progress tracking
     */
    async function uploadSingleFile(file: File, index: number, total: number, uploadState?: FileUploadState): Promise<UploadRecord> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('context', config.context);

        // Create XMLHttpRequest for progress tracking
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Track upload progress
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const fileProgress = (event.loaded / event.total) * 100;

                    // Update individual file progress
                    if (uploadState) {
                        uploadState.progress = {
                            loaded: event.loaded,
                            total: event.total,
                            percentage: Math.round(fileProgress)
                        };
                    }

                    // Update overall progress
                    const overallProgress = ((index * 100) + fileProgress) / total;
                    state.progress = {
                        loaded: event.loaded,
                        total: event.total,
                        percentage: Math.round(overallProgress)
                    };
                }
            });

            // Handle completion
            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response: UploadResponse = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            resolve(response.data.upload);
                        } else {
                            reject(new Error(response.message || 'Upload failed'));
                        }
                    } catch (error) {
                        reject(new Error('Invalid response format'));
                    }
                } else {
                    try {
                        const response: UploadResponse = JSON.parse(xhr.responseText);
                        reject(new Error(response.message || `HTTP ${xhr.status}: ${xhr.statusText}`));
                    } catch {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                }
            });

            // Handle errors
            xhr.addEventListener('error', () => {
                reject(new Error('Network error occurred'));
            });

            // Handle timeout
            xhr.addEventListener('timeout', () => {
                reject(new Error('Upload timeout'));
            });

            // Get CSRF token
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Configure request
            xhr.open('POST', '/api/images/upload');
            xhr.setRequestHeader('X-CSRF-TOKEN', token || '');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 60000; // 60 second timeout

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Remove a file from the preview list
     */
    function removeFile(id: string) {
        const index = filePreviews.value.findIndex(preview => preview.id === id);
        if (index !== -1) {
            // Clean up the preview URL
            URL.revokeObjectURL(filePreviews.value[index].url);
            filePreviews.value.splice(index, 1);
        }

        // Re-validate remaining files
        if (filePreviews.value.length > 0) {
            const files = filePreviews.value.map(preview => preview.file);
            validationErrors.value = validateFiles(files);
        } else {
            validationErrors.value = [];
            resetState();
        }
    }

    /**
     * Upload a specific file by ID
     */
    async function uploadSingleFileById(id: string): Promise<UploadRecord | null> {
        const preview = filePreviews.value.find(p => p.id === id);
        if (!preview || !preview.uploadState) {
            throw new Error('File not found');
        }

        if (preview.uploadState.isUploading || preview.uploadState.success) {
            return preview.uploadState.uploadedFile;
        }

        try {
            return await uploadSingleFileWithState(preview, 0, 1);
        } catch (error) {
            throw error;
        }
    }

    /**
     * Retry failed uploads
     */
    async function retryFailedUploads(): Promise<UploadRecord[]> {
        const failedPreviews = filePreviews.value.filter(p =>
            p.uploadState && p.uploadState.error && !p.uploadState.success
        );

        if (failedPreviews.length === 0) {
            return [];
        }

        const uploadPromises = failedPreviews.map((preview, index) =>
            uploadSingleFileWithState(preview, index, failedPreviews.length)
        );

        const results = await Promise.allSettled(uploadPromises);
        const successfulUploads: UploadRecord[] = [];

        results.forEach((result) => {
            if (result.status === 'fulfilled') {
                successfulUploads.push(result.value);
            }
        });

        return successfulUploads;
    }

    /**
     * Get upload statistics
     */
    const uploadStats = computed(() => {
        const total = filePreviews.value.length;
        const uploading = filePreviews.value.filter(p => p.uploadState?.isUploading).length;
        const success = filePreviews.value.filter(p => p.uploadState?.success).length;
        const failed = filePreviews.value.filter(p => p.uploadState?.error && !p.uploadState?.success).length;
        const pending = total - uploading - success - failed;

        return {
            total,
            uploading,
            success,
            failed,
            pending,
            isComplete: success + failed === total && total > 0,
            hasFailures: failed > 0
        };
    });

    /**
     * Reset the upload state
     */
    function resetState() {
        state.isUploading = false;
        state.progress = { loaded: 0, total: 0, percentage: 0 };
        state.error = null;
        state.success = false;
        state.uploadedFile = null;
        state.uploadedFiles = [];
        validationErrors.value = [];

        // Reset individual file states
        filePreviews.value.forEach(preview => {
            if (preview.uploadState) {
                preview.uploadState.isUploading = false;
                preview.uploadState.progress = { loaded: 0, total: 0, percentage: 0 };
                preview.uploadState.error = null;
                preview.uploadState.success = false;
                preview.uploadState.uploadedFile = null;
            }
        });
    }

    /**
     * Clear all files and reset state
     */
    function clearFiles() {
        cleanupPreviews();
        resetState();
    }

    /**
     * Format file size for display
     */
    function formatFileSize(bytes: number): string {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Get file extension from filename
     */
    function getFileExtension(filename: string): string {
        return filename.split('.').pop()?.toLowerCase() || '';
    }

    // Cleanup on unmount
    function cleanup() {
        cleanupPreviews();
    }

    return {
        // State
        state: readonly(state),
        filePreviews: readonly(filePreviews),
        validationErrors: readonly(validationErrors),
        config: readonly(config),

        // Computed
        isValid,
        hasFiles,
        canUpload,
        uploadStats,

        // Methods
        selectFiles,
        uploadFiles,
        uploadSingleFileById,
        retryFailedUploads,
        removeFile,
        clearFiles,
        resetState,
        cleanup,
        formatFileSize,
        getFileExtension
    };
}
