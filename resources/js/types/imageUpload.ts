export interface UploadRecord {
    id: string;
    filename: string;
    original_name: string;
    mime_type: string;
    size: number;
    context: string;
    path: string;
    url: string;
    user_id?: number;
    created_at: string;
    updated_at: string;
}

export interface UploadConfig {
    context: string;
    maxSize: number;
    allowedTypes: string[];
    directory: string;
    generateThumbnails: boolean;
    publicAccess: boolean;
}

export interface UploadProgress {
    loaded: number;
    total: number;
    percentage: number;
}

export interface UploadState {
    isUploading: boolean;
    progress: UploadProgress;
    error: string | null;
    success: boolean;
    uploadedFile: UploadRecord | null;
    uploadedFiles: UploadRecord[];
}

export interface FileUploadState {
    id: string;
    file: File;
    isUploading: boolean;
    progress: UploadProgress;
    error: string | null;
    success: boolean;
    uploadedFile: UploadRecord | null;
}

export interface FileValidationError {
    field: string;
    message: string;
}

export interface UploadResponse {
    success: boolean;
    message: string;
    data?: {
        upload: UploadRecord;
    };
    errors?: FileValidationError[];
}

export interface ImageUploadOptions {
    context: string;
    maxSize?: number;
    allowedTypes?: string[];
    multiple?: boolean;
    immediate?: boolean;
}

export interface FilePreview {
    file: File;
    url: string;
    id: string;
    uploadState?: FileUploadState;
}
