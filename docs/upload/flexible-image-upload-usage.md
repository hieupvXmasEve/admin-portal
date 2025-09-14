# Flexible Image Upload System - Usage Documentation

## Overview

The flexible image upload system provides a comprehensive, reusable solution for handling image uploads across the Laravel application. It supports multiple storage backends, configurable contexts, and provides both single and multiple file upload capabilities with real-time progress tracking.

## Table of Contents

- [Quick Start](#quick-start)
- [Components](#components)
- [Composables](#composables)
- [Configuration](#configuration)
- [Examples](#examples)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)

## Quick Start

### Basic Single Image Upload

```vue
<template>
  <ImageUpload
    context="avatar"
    @upload-success="handleUploadSuccess"
    @upload-error="handleUploadError"
  />
</template>

<script setup>
import ImageUpload from '@/components/ImageUpload.vue'

const handleUploadSuccess = (files) => {
  console.log('Upload successful:', files)
}

const handleUploadError = (error) => {
  console.error('Upload failed:', error)
}
</script>
```

### Basic Multiple Image Upload

```vue
<template>
  <MultipleImageUpload
    context="assignment"
    :max-files="5"
    @upload-success="handleBatchUpload"
  />
</template>

<script setup>
import MultipleImageUpload from '@/components/MultipleImageUpload.vue'

const handleBatchUpload = (files) => {
  console.log('Batch upload completed:', files)
}
</script>
```

## Components

### ImageUpload.vue

A versatile single/multiple image upload component with drag-and-drop support.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `context` | `string` | **required** | Upload context (avatar, assignment, general) |
| `maxSize` | `number` | `undefined` | Maximum file size in bytes (overrides context config) |
| `allowedTypes` | `string[]` | `undefined` | Allowed MIME types (overrides context config) |
| `multiple` | `boolean` | `false` | Enable multiple file selection |
| `immediate` | `boolean` | `false` | Auto-upload on file selection |
| `disabled` | `boolean` | `false` | Disable the upload component |
| `class` | `string` | `undefined` | Additional CSS classes |
| `showConfig` | `boolean` | `true` | Show configuration info |
| `autoLoadConfig` | `boolean` | `true` | Automatically load upload configuration |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `upload-start` | `[]` | Fired when upload begins |
| `upload-progress` | `[percentage: number]` | Upload progress updates |
| `upload-success` | `[files: UploadRecord[]]` | Successful upload completion |
| `upload-error` | `[error: string]` | Upload error occurred |
| `files-selected` | `[count: number]` | Files selected for upload |
| `file-removed` | `[id: string]` | File removed from selection |
| `config-loaded` | `[]` | Configuration loaded successfully |
| `config-error` | `[error: string]` | Configuration loading error |

#### Example Usage

```vue
<template>
  <ImageUpload
    context="avatar"
    :max-size="2 * 1024 * 1024"
    :allowed-types="['image/jpeg', 'image/png']"
    immediate
    @upload-success="onAvatarUploaded"
    @upload-error="onUploadError"
    @upload-progress="onProgress"
  />
</template>

<script setup>
import { ref } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'

const uploadProgress = ref(0)

const onAvatarUploaded = (files) => {
  const avatarUrl = files[0].url
  // Update user avatar
  updateUserAvatar(avatarUrl)
}

const onUploadError = (error) => {
  // Handle error
  showErrorMessage(error)
}

const onProgress = (percentage) => {
  uploadProgress.value = percentage
}
</script>
```

### MultipleImageUpload.vue

Specialized component for handling multiple image uploads with batch operations.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `context` | `string` | **required** | Upload context |
| `maxSize` | `number` | `undefined` | Maximum file size in bytes |
| `allowedTypes` | `string[]` | `undefined` | Allowed MIME types |
| `disabled` | `boolean` | `false` | Disable the component |
| `class` | `string` | `undefined` | Additional CSS classes |
| `showConfig` | `boolean` | `true` | Show configuration info |
| `autoLoadConfig` | `boolean` | `true` | Auto-load configuration |
| `maxFiles` | `number` | `10` | Maximum number of files |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `upload-start` | `[]` | Batch upload started |
| `upload-progress` | `[percentage: number]` | Overall progress |
| `upload-success` | `[files: UploadRecord[]]` | Batch upload completed |
| `upload-error` | `[error: string]` | Upload error |
| `files-selected` | `[count: number]` | Files selected |
| `file-removed` | `[id: string]` | File removed |
| `file-uploaded` | `[file: UploadRecord]` | Individual file uploaded |
| `batch-complete` | `[stats: object]` | Batch operation completed |

### ImagePreview.vue

Component for displaying uploaded images with metadata and full-size preview.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `upload` | `UploadRecord` | **required** | Upload record to display |
| `showFullSize` | `boolean` | `true` | Enable full-size preview |
| `showMetadata` | `boolean` | `true` | Show file metadata |
| `showCopyButton` | `boolean` | `true` | Show copy URL button |
| `thumbnailSize` | `'sm' \| 'md' \| 'lg'` | `'md'` | Thumbnail size |
| `class` | `string` | `undefined` | Additional CSS classes |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `image-load` | `[]` | Image loaded successfully |
| `image-error` | `[error: string]` | Image loading error |
| `url-copied` | `[url: string]` | URL copied to clipboard |
| `full-size-open` | `[]` | Full-size preview opened |
| `full-size-close` | `[]` | Full-size preview closed |

## Composables

### useImageUpload

Core composable for image upload functionality.

#### Parameters

```typescript
interface ImageUploadOptions {
  context: string;
  maxSize?: number;
  allowedTypes?: string[];
  multiple?: boolean;
  immediate?: boolean;
}
```

#### Return Value

```typescript
{
  // State
  state: UploadState;
  filePreviews: FilePreview[];
  validationErrors: FileValidationError[];
  config: ImageUploadOptions;

  // Computed
  isValid: boolean;
  hasFiles: boolean;
  canUpload: boolean;
  uploadStats: UploadStats;

  // Methods
  selectFiles: (files: FileList | File[]) => void;
  uploadFiles: () => Promise<UploadRecord[]>;
  uploadSingleFileById: (id: string) => Promise<UploadRecord | null>;
  retryFailedUploads: () => Promise<UploadRecord[]>;
  removeFile: (id: string) => void;
  clearFiles: () => void;
  resetState: () => void;
  cleanup: () => void;
  formatFileSize: (bytes: number) => string;
  getFileExtension: (filename: string) => string;
}
```

#### Example Usage

```typescript
import { useImageUpload } from '@/composables/useImageUpload'

const {
  state,
  filePreviews,
  isValid,
  canUpload,
  selectFiles,
  uploadFiles,
  removeFile
} = useImageUpload({
  context: 'assignment',
  maxSize: 10 * 1024 * 1024, // 10MB
  allowedTypes: ['image/jpeg', 'image/png'],
  multiple: true
})

// Select files programmatically
const handleFileInput = (event) => {
  const files = event.target.files
  if (files) {
    selectFiles(files)
  }
}

// Upload selected files
const handleUpload = async () => {
  if (canUpload.value) {
    try {
      const uploadedFiles = await uploadFiles()
      console.log('Uploaded:', uploadedFiles)
    } catch (error) {
      console.error('Upload failed:', error)
    }
  }
}
```

### useUploadConfig

Composable for managing upload configuration.

#### Return Value

```typescript
{
  // State
  config: UploadConfigResponse | null;
  isLoading: boolean;
  error: string | null;

  // Computed
  availableContexts: string[];

  // Methods
  fetchConfig: () => Promise<void>;
  getContextConfig: (context: string) => UploadConfig | null;
  getMaxSize: (context: string) => number;
  getAllowedTypes: (context: string) => string[];
  getAllowedExtensions: (context: string) => string[];
  isValidContext: (context: string) => boolean;
  getMaxSizeText: (context: string) => string;
  formatFileSize: (bytes: number) => string;
  getSecuritySettings: () => SecuritySettings;
  getPerformanceSettings: () => PerformanceSettings;
  isFileTypeAllowed: (context: string, mimeType: string) => boolean;
  isFileExtensionAllowed: (context: string, extension: string) => boolean;
  isFileSizeAllowed: (context: string, size: number) => boolean;
  getValidationRules: (context: string) => ValidationRules;
}
```

## Configuration

### Upload Contexts

The system supports multiple upload contexts, each with specific configurations:

#### Available Contexts

- **avatar**: User profile pictures (2MB limit, JPEG/PNG/WebP)
- **assignment**: Assignment-related images (10MB limit, JPEG/PNG/GIF/WebP)
- **general**: General purpose uploads (5MB limit, JPEG/PNG/GIF/WebP/SVG)

#### Context Configuration

```php
// config/uploads.php
'contexts' => [
    'avatar' => [
        'max_size' => 2048, // KB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'directory' => 'avatars',
        'generate_thumbnails' => true,
        'public' => true,
        'disk' => 'avatars',
    ],
    // ... other contexts
]
```

### Environment Variables

```env
# Storage Configuration
IMAGE_STORAGE_DRIVER=local
AVATAR_STORAGE_DRIVER=s3
DEFAULT_UPLOAD_MAX_SIZE=10240

# Context-specific limits
AVATAR_MAX_SIZE=2048
ASSIGNMENT_MAX_SIZE=10240
GENERAL_MAX_SIZE=5120

# S3 Configuration (when using S3 driver)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_URL=https://your-bucket.s3.amazonaws.com

# Security Settings
UPLOAD_SCAN_MALWARE=false
UPLOAD_SCAN_MALICIOUS_CONTENT=true
UPLOAD_CHECK_FILE_ENTROPY=true
UPLOAD_DETECT_POLYGLOT_FILES=true

# Performance Settings
UPLOAD_CHUNK_SIZE=1024
UPLOAD_MEMORY_LIMIT=256M
UPLOAD_TIMEOUT=300
CHUNKED_UPLOAD_ENABLED=true
CHUNKED_UPLOAD_CHUNK_SIZE=5242880
```

## Examples

### Avatar Upload with Preview

```vue
<template>
  <div class="space-y-4">
    <div class="flex items-center space-x-4">
      <div class="h-20 w-20 rounded-full overflow-hidden bg-gray-200">
        <img
          v-if="currentAvatar"
          :src="currentAvatar.url"
          :alt="user.name"
          class="h-full w-full object-cover"
        />
        <div v-else class="h-full w-full flex items-center justify-center">
          <Icon name="user" class="h-8 w-8 text-gray-400" />
        </div>
      </div>
      <div>
        <h3 class="font-medium">Profile Picture</h3>
        <p class="text-sm text-gray-600">Upload a new avatar</p>
      </div>
    </div>

    <ImageUpload
      context="avatar"
      immediate
      class="max-w-md"
      @upload-success="handleAvatarUpload"
      @upload-error="handleUploadError"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'
import Icon from '@/components/Icon.vue'

const currentAvatar = ref(null)
const user = ref({ name: 'John Doe' })

const handleAvatarUpload = (files) => {
  currentAvatar.value = files[0]
  // Update user profile
  updateUserProfile({ avatar_url: files[0].url })
}

const handleUploadError = (error) => {
  console.error('Avatar upload failed:', error)
}
</script>
```

### Assignment Image Gallery

```vue
<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold">Assignment Images</h2>
      <p class="text-sm text-gray-600">Upload images related to this assignment</p>
    </div>

    <MultipleImageUpload
      context="assignment"
      :max-files="10"
      @upload-success="handleAssignmentImages"
      @file-uploaded="handleSingleImageUpload"
    />

    <!-- Image Gallery -->
    <div v-if="assignmentImages.length > 0" class="space-y-4">
      <h3 class="font-medium">Uploaded Images</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <ImagePreview
          v-for="image in assignmentImages"
          :key="image.id"
          :upload="image"
          thumbnail-size="lg"
          @url-copied="handleUrlCopied"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import MultipleImageUpload from '@/components/MultipleImageUpload.vue'
import ImagePreview from '@/components/ImagePreview.vue'

const assignmentImages = ref([])

const handleAssignmentImages = (files) => {
  assignmentImages.value.push(...files)
}

const handleSingleImageUpload = (file) => {
  assignmentImages.value.push(file)
}

const handleUrlCopied = (url) => {
  console.log('URL copied:', url)
}
</script>
```

### Custom Upload with Progress

```vue
<template>
  <div class="space-y-4">
    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
      <input
        ref="fileInput"
        type="file"
        multiple
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />
      
      <div class="text-center">
        <Icon name="upload" class="mx-auto h-12 w-12 text-gray-400" />
        <div class="mt-4">
          <button
            @click="$refs.fileInput.click()"
            class="text-blue-600 hover:text-blue-500"
          >
            Select images
          </button>
          <p class="text-sm text-gray-600">or drag and drop</p>
        </div>
      </div>
    </div>

    <!-- File List -->
    <div v-if="hasFiles" class="space-y-2">
      <div
        v-for="preview in filePreviews"
        :key="preview.id"
        class="flex items-center space-x-3 p-3 border rounded-lg"
      >
        <img
          :src="preview.url"
          :alt="preview.file.name"
          class="h-12 w-12 object-cover rounded"
        />
        <div class="flex-1">
          <p class="text-sm font-medium">{{ preview.file.name }}</p>
          <p class="text-xs text-gray-600">{{ formatFileSize(preview.file.size) }}</p>
          
          <!-- Progress bar -->
          <div v-if="preview.uploadState?.isUploading" class="mt-1">
            <div class="bg-gray-200 rounded-full h-2">
              <div
                class="bg-blue-600 h-2 rounded-full transition-all"
                :style="{ width: `${preview.uploadState.progress.percentage}%` }"
              ></div>
            </div>
          </div>
        </div>
        
        <button
          @click="removeFile(preview.id)"
          class="text-red-600 hover:text-red-500"
        >
          <Icon name="x" class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Upload Button -->
    <button
      v-if="canUpload"
      @click="handleUpload"
      :disabled="state.isUploading"
      class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 disabled:opacity-50"
    >
      {{ state.isUploading ? 'Uploading...' : 'Upload Images' }}
    </button>
  </div>
</template>

<script setup>
import { useImageUpload } from '@/composables/useImageUpload'
import Icon from '@/components/Icon.vue'

const {
  state,
  filePreviews,
  hasFiles,
  canUpload,
  selectFiles,
  uploadFiles,
  removeFile,
  formatFileSize
} = useImageUpload({
  context: 'general',
  multiple: true
})

const handleFileSelect = (event) => {
  const files = event.target.files
  if (files) {
    selectFiles(files)
  }
}

const handleUpload = async () => {
  try {
    const uploadedFiles = await uploadFiles()
    console.log('Upload completed:', uploadedFiles)
  } catch (error) {
    console.error('Upload failed:', error)
  }
}
</script>
```

### Programmatic Upload

```typescript
import { useImageUpload } from '@/composables/useImageUpload'

// Initialize upload composable
const upload = useImageUpload({
  context: 'assignment',
  multiple: true,
  maxSize: 5 * 1024 * 1024 // 5MB
})

// Function to handle file selection from any source
async function uploadFilesFromDataTransfer(dataTransfer: DataTransfer) {
  const files = Array.from(dataTransfer.files).filter(file => 
    file.type.startsWith('image/')
  )
  
  if (files.length === 0) {
    console.warn('No image files found')
    return
  }

  // Select files
  upload.selectFiles(files)

  // Check if files are valid
  if (!upload.isValid.value) {
    console.error('Validation errors:', upload.validationErrors.value)
    return
  }

  // Upload files
  try {
    const uploadedFiles = await upload.uploadFiles()
    console.log('Successfully uploaded:', uploadedFiles)
    return uploadedFiles
  } catch (error) {
    console.error('Upload failed:', error)
    throw error
  }
}

// Usage with drag and drop
element.addEventListener('drop', async (event) => {
  event.preventDefault()
  
  if (event.dataTransfer) {
    await uploadFilesFromDataTransfer(event.dataTransfer)
  }
})
```

## API Reference

### Types

#### UploadRecord

```typescript
interface UploadRecord {
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
```

#### UploadState

```typescript
interface UploadState {
  isUploading: boolean;
  progress: UploadProgress;
  error: string | null;
  success: boolean;
  uploadedFile: UploadRecord | null;
  uploadedFiles: UploadRecord[];
}
```

#### FilePreview

```typescript
interface FilePreview {
  file: File;
  url: string;
  id: string;
  uploadState?: FileUploadState;
}
```

#### ValidationError

```typescript
interface FileValidationError {
  field: string;
  message: string;
}
```

### Backend API Endpoints

#### Upload Image

```http
POST /api/images/upload
Content-Type: multipart/form-data

file: File
context: string
```

**Response:**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": {
    "upload": {
      "id": "uuid",
      "filename": "generated-filename.jpg",
      "original_name": "original-file.jpg",
      "mime_type": "image/jpeg",
      "size": 1024000,
      "context": "avatar",
      "path": "avatars/generated-filename.jpg",
      "url": "https://example.com/storage/avatars/generated-filename.jpg",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

#### Get Upload Configuration

```http
GET /api/upload/config
```

**Response:**
```json
{
  "success": true,
  "data": {
    "contexts": {
      "avatar": {
        "max_size": 2048,
        "allowed_types": ["image/jpeg", "image/png"],
        "directory": "avatars",
        "generate_thumbnails": true,
        "public": true
      }
    },
    "defaults": {
      "max_size": 10240,
      "allowed_types": ["image/jpeg", "image/png"]
    }
  }
}
```

## Troubleshooting

### Common Issues

#### 1. Configuration Not Loading

**Problem:** Upload configuration fails to load or shows warnings.

**Solution:**
- Ensure the `/api/upload/config` endpoint is accessible
- Check that upload contexts are properly configured in `config/uploads.php`
- Verify environment variables are set correctly

```vue
<template>
  <ImageUpload
    context="avatar"
    :auto-load-config="false"
    @config-error="handleConfigError"
  />
</template>

<script setup>
const handleConfigError = (error) => {
  console.error('Config error:', error)
  // Handle gracefully or show user-friendly message
}
</script>
```

#### 2. File Validation Errors

**Problem:** Files are rejected with validation errors.

**Solution:**
- Check file size limits in context configuration
- Verify allowed file types match your files
- Ensure files are actual images

```javascript
// Debug validation
const { validationErrors } = useImageUpload({ context: 'avatar' })

watch(validationErrors, (errors) => {
  errors.forEach(error => {
    console.log(`Validation error in ${error.field}: ${error.message}`)
  })
})
```

#### 3. Upload Progress Not Updating

**Problem:** Progress bar doesn't show or update during upload.

**Solution:**
- Ensure you're listening to the `upload-progress` event
- Check that the server supports progress tracking
- Verify XMLHttpRequest is being used (not fetch)

```vue
<template>
  <ImageUpload
    context="assignment"
    @upload-progress="updateProgress"
  />
  <div v-if="uploading">
    Progress: {{ progress }}%
  </div>
</template>

<script setup>
const progress = ref(0)
const uploading = ref(false)

const updateProgress = (percentage) => {
  progress.value = percentage
  uploading.value = percentage < 100
}
</script>
```

#### 4. Memory Issues with Large Files

**Problem:** Browser becomes unresponsive with large files or many files.

**Solution:**
- Use chunked uploads for large files
- Limit the number of concurrent uploads
- Implement file size warnings

```javascript
// Check file size before processing
const MAX_SAFE_SIZE = 50 * 1024 * 1024 // 50MB

const handleFileSelect = (files) => {
  const largeFiles = Array.from(files).filter(file => file.size > MAX_SAFE_SIZE)
  
  if (largeFiles.length > 0) {
    console.warn('Large files detected, consider chunked upload')
  }
  
  selectFiles(files)
}
```

#### 5. CSRF Token Issues

**Problem:** Uploads fail with CSRF token errors.

**Solution:**
- Ensure CSRF token meta tag is present in your HTML
- Verify the token is being sent with requests

```html
<!-- In your HTML head -->
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Performance Tips

1. **Optimize Image Previews**: Use `URL.createObjectURL()` for local previews instead of base64
2. **Limit Concurrent Uploads**: Don't upload too many files simultaneously
3. **Use Appropriate Contexts**: Choose the right context for your use case
4. **Enable Chunked Uploads**: For large files, enable chunked upload in configuration
5. **Clean Up Resources**: Always call `cleanup()` when components are unmounted

### Security Considerations

1. **Validate on Server**: Always validate files on the server side
2. **Use Unique Filenames**: Enable unique filename generation
3. **Scan for Malware**: Consider enabling malware scanning for production
4. **Limit File Types**: Be restrictive with allowed file types
5. **Check File Signatures**: Enable file signature validation
