# Design Document

## Overview

The flexible image upload system provides a reusable, scalable solution for handling image uploads across the Laravel application. The system is built with a modular architecture that allows easy switching between storage backends (local, S3, Google Cloud Storage) without code changes. The frontend component is designed to be reusable across different contexts like avatars, assignments, and general image uploads.

## Architecture

### Backend Architecture

The backend follows Laravel's filesystem abstraction pattern using the Storage facade, which provides a unified API regardless of the underlying storage driver. The system uses a service-oriented architecture with clear separation of concerns:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Controller    │───▶│  Upload Service  │───▶│ Storage Driver  │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │ Validation Rules │    │   File System   │
                       └──────────────────┘    └─────────────────┘
```

### Frontend Architecture

The frontend uses a composable-based architecture with Vue 3 Composition API, providing reusable logic and a flexible component system:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ Upload Component│───▶│  useImageUpload  │───▶│   API Service   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌──────────────────┐
                       │ Progress Tracking│
                       └──────────────────┘
```

## Components and Interfaces

### Backend Components

#### 1. ImageUploadController
- Handles HTTP requests for image uploads
- Validates request parameters and files
- Delegates business logic to the service layer
- Returns standardized JSON responses

#### 2. ImageUploadService
- Core business logic for image processing
- Handles file validation, storage, and URL generation
- Manages different upload contexts (avatars, assignments, etc.)
- Provides methods for cleanup and file management

#### 3. ImageUploadRequest
- Form request validation class
- Validates file types, sizes, and upload context
- Provides custom validation rules for different contexts

#### 4. Storage Configuration
- Configurable storage disks for different contexts
- Environment-based driver selection
- Context-specific directory organization

### Frontend Components

#### 1. ImageUpload.vue
- Main upload component with drag-and-drop support
- Configurable for different contexts and requirements
- Emits events for upload lifecycle management
- Supports multiple file selection and progress tracking

#### 2. useImageUpload Composable
- Reusable upload logic and state management
- Handles API communication and error handling
- Provides reactive state for upload progress
- Manages file validation and preview generation

#### 3. ImagePreview.vue
- Displays uploaded images with preview functionality
- Supports different display modes (thumbnail, full-size)
- Provides copy-to-clipboard functionality for URLs

## Data Models

### Upload Record Model
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

### Upload Configuration
```typescript
interface UploadConfig {
  context: string;
  maxSize: number;
  allowedTypes: string[];
  directory: string;
  generateThumbnails: boolean;
  publicAccess: boolean;
}
```

### Storage Configuration
```php
// config/filesystems.php
'disks' => [
    'images' => [
        'driver' => env('IMAGE_STORAGE_DRIVER', 'local'),
        'root' => storage_path('app/public/images'),
        'url' => env('APP_URL').'/storage/images',
        'visibility' => 'public',
    ],
    'avatars' => [
        'driver' => env('AVATAR_STORAGE_DRIVER', 'local'),
        'root' => storage_path('app/public/avatars'),
        'url' => env('APP_URL').'/storage/avatars',
        'visibility' => 'public',
    ],
]
```

## Error Handling

### Backend Error Handling
- File validation errors with specific messages
- Storage driver failures with fallback mechanisms
- Memory limit handling for large files
- Disk space validation before upload
- Malicious file detection and rejection

### Frontend Error Handling
- Client-side validation before upload
- Network error handling with retry mechanisms
- Progress tracking with timeout handling
- User-friendly error messages
- Graceful degradation for unsupported browsers

## Testing Strategy

### Backend Testing
- Unit tests for upload service methods
- Integration tests for storage driver switching
- Feature tests for API endpoints
- File validation testing with various file types
- Security testing for malicious file uploads

### Frontend Testing
- Component testing for upload interface
- Composable testing for upload logic
- E2E testing for complete upload workflows
- Accessibility testing for upload components
- Performance testing for large file uploads

### Test Data Management
- Factory classes for upload records
- Mock storage drivers for testing
- Test file fixtures for various scenarios
- Cleanup mechanisms for test files

## Security Considerations

### File Validation
- MIME type validation on server side
- File signature verification
- File size limits per context
- Filename sanitization
- Extension whitelist validation

### Storage Security
- Unique filename generation to prevent conflicts
- Directory traversal prevention
- Access control for sensitive uploads
- Virus scanning integration points
- Rate limiting for upload endpoints

### Access Control
- User-based upload permissions
- Context-specific access rules
- Public vs private file handling
- URL signing for temporary access
- Audit logging for upload activities

## Performance Optimization

### Upload Performance
- Chunked upload support for large files
- Progress tracking with minimal overhead
- Memory-efficient file processing
- Asynchronous processing for thumbnails
- CDN integration for file delivery

### Storage Optimization
- Automatic file compression options
- Thumbnail generation strategies
- Cache headers for static files
- Lazy loading for image previews
- Cleanup jobs for orphaned files

## Configuration Management

### Environment Variables
```env
# Storage Configuration
IMAGE_STORAGE_DRIVER=local
AVATAR_STORAGE_DRIVER=s3
DEFAULT_UPLOAD_MAX_SIZE=10240

# S3 Configuration (when using S3 driver)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=

# Upload Contexts
AVATAR_MAX_SIZE=2048
ASSIGNMENT_MAX_SIZE=10240
GENERAL_MAX_SIZE=5120
```

### Context Configuration
```php
// config/uploads.php
return [
    'contexts' => [
        'avatar' => [
            'max_size' => env('AVATAR_MAX_SIZE', 2048),
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'directory' => 'avatars',
            'generate_thumbnails' => true,
            'public' => true,
        ],
        'assignment' => [
            'max_size' => env('ASSIGNMENT_MAX_SIZE', 10240),
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'directory' => 'assignments',
            'generate_thumbnails' => false,
            'public' => false,
        ],
    ],
];
```
