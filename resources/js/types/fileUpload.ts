export interface FileUploadOptions {
    context: string;
    multiple?: boolean;
    maxSize?: number; // bytes
    allowedTypes?: string[]; // mime types
    uploadImmediately?: boolean;
}

export interface UploadedFile {
    id: number;
    url: string;
    filename: string;
    original_name: string;
    mime_type: string;
    size: number;
    context: string;
    metadata?: Record<string, any>;
    created_at: string;
}

export interface FileItem {
    id: string; // temp id
    file: File;
    status: 'pending' | 'uploading' | 'success' | 'error';
    progress: number;
    error?: string;
    response?: UploadedFile;
}

export interface FileUploadState {
    files: FileItem[];
    isUploading: boolean;
    error: string | null;
}
