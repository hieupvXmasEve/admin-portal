import { ref, computed } from 'vue';
import type { FileUploadOptions, FileItem, UploadedFile, FileUploadState } from '@/types/fileUpload';

export function useFileUpload(options: FileUploadOptions) {
    // State
    const state = ref<FileUploadState>({
        files: [],
        isUploading: false,
        error: null,
    });

    // Configuration
    const config = {
        context: options.context,
        multiple: options.multiple ?? false,
        maxSize: options.maxSize ?? 10 * 1024 * 1024, // Default 10MB
        allowedTypes: options.allowedTypes || [],
        uploadImmediately: options.uploadImmediately ?? false,
    };

    // Computed
    const hasFiles = computed(() => state.value.files.length > 0);
    const hasPendingFiles = computed(() => state.value.files.some(f => f.status === 'pending' || f.status === 'error'));
    const isUploading = computed(() => state.value.isUploading);
    const uploadedFiles = computed(() =>
        state.value.files
            .filter(f => f.status === 'success' && f.response)
            .map(f => f.response as UploadedFile)
    );

    // Helpers
    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const validateFile = (file: File): string | null => {
        // Size check
        if (file.size > config.maxSize) {
            return `File size exceeds ${formatFileSize(config.maxSize)}`;
        }

        // Type check
        if (config.allowedTypes.length > 0 && !config.allowedTypes.includes(file.type)) {
            return `File type ${file.type} is not allowed`;
        }

        return null;
    };

    // Actions
    const addFiles = (fileList: FileList | File[]) => {
        const newFiles = Array.from(fileList);

        // If not multiple, replace existing files
        if (!config.multiple && newFiles.length > 0) {
            state.value.files = [];
        }

        for (const file of newFiles) {
            const error = validateFile(file);

            const fileItem: FileItem = {
                id: `${file.name}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                file,
                status: error ? 'error' : 'pending',
                progress: 0,
                error: error || undefined,
            };

            state.value.files.push(fileItem);
        }

        if (config.uploadImmediately) {
            uploadPendingFiles();
        }
    };

    const removeFile = (id: string) => {
        const index = state.value.files.findIndex(f => f.id === id);
        if (index !== -1) {
            state.value.files.splice(index, 1);
        }
    };

    const clearFiles = () => {
        state.value.files = [];
        state.value.error = null;
    };

    const uploadSingleFile = (fileItem: FileItem): Promise<UploadedFile> => {
        return new Promise((resolve, reject) => {
            fileItem.status = 'uploading';
            fileItem.progress = 0;
            fileItem.error = undefined;

            const formData = new FormData();
            formData.append('file', fileItem.file);
            formData.append('context', config.context);

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    fileItem.progress = Math.round((event.loaded / event.total) * 100);
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            // Handle case where controller returns data directly or nested
                            // Based on ImageUploadController, it returns 'data' which contains the file record
                            const uploadRecord = response.data.upload || response.data;

                            fileItem.status = 'success';
                            fileItem.progress = 100;
                            fileItem.response = uploadRecord;
                            resolve(uploadRecord);
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    } catch (e: any) {
                        const msg = e.message || 'Invalid response';
                        fileItem.status = 'error';
                        fileItem.error = msg;
                        reject(new Error(msg));
                    }
                } else {
                    let msg = `HTTP ${xhr.status}`;
                    try {
                        const response = JSON.parse(xhr.responseText);
                        msg = response.message || msg;
                    } catch (e) {}

                    fileItem.status = 'error';
                    fileItem.error = msg;
                    reject(new Error(msg));
                }
            });

            xhr.addEventListener('error', () => {
                fileItem.status = 'error';
                fileItem.error = 'Network error';
                reject(new Error('Network error'));
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            xhr.open('POST', '/api/uploads');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    };

    const uploadPendingFiles = async () => {
        const pending = state.value.files.filter(f => f.status === 'pending');
        if (pending.length === 0) return;

        state.value.isUploading = true;
        state.value.error = null;

        // Process sequentially to avoid overwhelming connection, or map for parallel
        // Using parallel for better UX with small files
        const promises = pending.map(item => uploadSingleFile(item).catch(() => {}));

        await Promise.all(promises);

        state.value.isUploading = false;

        // Check overall status
        const errors = state.value.files.filter(f => f.status === 'error');
        if (errors.length > 0 && errors.length === pending.length) {
            state.value.error = 'All uploads failed';
        } else if (errors.length > 0) {
            state.value.error = 'Some uploads failed';
        }
    };

    const reset = () => {
        clearFiles();
        state.value.isUploading = false;
        state.value.error = null;
    };

    return {
        state,
        hasFiles,
        hasPendingFiles,
        isUploading,
        uploadedFiles,
        addFiles,
        removeFile,
        clearFiles,
        uploadPendingFiles,
        formatFileSize,
        reset
    };
}
