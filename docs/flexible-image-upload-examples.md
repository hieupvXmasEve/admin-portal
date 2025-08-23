# Flexible Image Upload System - Examples

## Context-Specific Examples

This document provides practical examples for using the flexible image upload system across different contexts and use cases.

## Table of Contents

- [Avatar Upload Examples](#avatar-upload-examples)
- [Assignment Image Examples](#assignment-image-examples)
- [General Upload Examples](#general-upload-examples)
- [Advanced Integration Examples](#advanced-integration-examples)
- [Custom Validation Examples](#custom-validation-examples)

## Avatar Upload Examples

### Basic Avatar Upload

```vue
<template>
  <div class="avatar-upload-section">
    <div class="current-avatar">
      <img
        v-if="user.avatar_url"
        :src="user.avatar_url"
        :alt="user.name"
        class="h-24 w-24 rounded-full object-cover"
      />
      <div v-else class="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center">
        <Icon name="user" class="h-12 w-12 text-gray-400" />
      </div>
    </div>
    
    <ImageUpload
      context="avatar"
      immediate
      class="mt-4"
      @upload-success="handleAvatarUpdate"
      @upload-error="handleError"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'
import Icon from '@/components/Icon.vue'

const user = ref({
  name: 'John Doe',
  avatar_url: null
})

const handleAvatarUpdate = (files) => {
  user.value.avatar_url = files[0].url
  // Update user profile in backend
  updateUserProfile({ avatar_url: files[0].url })
}

const handleError = (error) => {
  console.error('Avatar upload failed:', error)
}
</script>
```##
# Avatar Upload with Crop Preview

```vue
<template>
  <div class="space-y-6">
    <div class="flex items-center space-x-6">
      <div class="avatar-preview">
        <img
          v-if="previewUrl"
          :src="previewUrl"
          alt="Avatar preview"
          class="h-32 w-32 rounded-full object-cover border-4 border-white shadow-lg"
        />
        <div v-else class="h-32 w-32 rounded-full bg-gray-200 flex items-center justify-center">
          <Icon name="user" class="h-16 w-16 text-gray-400" />
        </div>
      </div>
      
      <div class="upload-info">
        <h3 class="text-lg font-semibold">Profile Picture</h3>
        <p class="text-sm text-gray-600">Upload a square image for best results</p>
        <p class="text-xs text-gray-500">Recommended: 400x400px, max 2MB</p>
      </div>
    </div>

    <ImageUpload
      context="avatar"
      :max-size="2 * 1024 * 1024"
      immediate
      @upload-success="handleAvatarUpload"
      @files-selected="handleFileSelection"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'
import Icon from '@/components/Icon.vue'

const previewUrl = ref(null)

const handleAvatarUpload = (files) => {
  previewUrl.value = files[0].url
}

const handleFileSelection = (count) => {
  console.log(`${count} files selected`)
}
</script>
```

## Assignment Image Examples

### Assignment Image Gallery

```vue
<template>
  <div class="assignment-images">
    <div class="header">
      <h2 class="text-xl font-semibold">Assignment Images</h2>
      <p class="text-gray-600">Upload images related to your assignment</p>
    </div>

    <MultipleImageUpload
      context="assignment"
      :max-files="8"
      class="mt-6"
      @upload-success="handleImageUpload"
      @batch-complete="handleBatchComplete"
    />

    <!-- Image Gallery -->
    <div v-if="assignmentImages.length > 0" class="mt-8">
      <h3 class="text-lg font-medium mb-4">Uploaded Images ({{ assignmentImages.length }})</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ImagePreview
          v-for="image in assignmentImages"
          :key="image.id"
          :upload="image"
          thumbnail-size="lg"
          @url-copied="handleUrlCopy"
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

const handleImageUpload = (files) => {
  assignmentImages.value.push(...files)
}

const handleBatchComplete = (stats) => {
  console.log('Batch upload stats:', stats)
}

const handleUrlCopy = (url) => {
  console.log('URL copied:', url)
}
</script>
```

### Assignment with Image Annotations

```vue
<template>
  <div class="annotated-assignment">
    <form @submit.prevent="submitAssignment">
      <div class="form-section">
        <label class="block text-sm font-medium mb-2">Assignment Description</label>
        <textarea
          v-model="assignment.description"
          class="w-full p-3 border rounded-lg"
          rows="4"
          placeholder="Describe your assignment..."
        ></textarea>
      </div>

      <div class="form-section mt-6">
        <label class="block text-sm font-medium mb-2">Supporting Images</label>
        <MultipleImageUpload
          context="assignment"
          :max-files="5"
          @upload-success="handleImageUpload"
        />
      </div>

      <!-- Image annotations -->
      <div v-if="uploadedImages.length > 0" class="mt-6">
        <h3 class="text-lg font-medium mb-4">Image Annotations</h3>
        <div class="space-y-4">
          <div
            v-for="(image, index) in uploadedImages"
            :key="image.id"
            class="flex space-x-4 p-4 border rounded-lg"
          >
            <ImagePreview
              :upload="image"
              thumbnail-size="sm"
              :show-metadata="false"
              :show-copy-button="false"
            />
            <div class="flex-1">
              <label class="block text-sm font-medium mb-1">
                Description for {{ image.original_name }}
              </label>
              <textarea
                v-model="imageAnnotations[image.id]"
                class="w-full p-2 border rounded text-sm"
                rows="2"
                :placeholder="`Describe what this image shows...`"
              ></textarea>
            </div>
          </div>
        </div>
      </div>

      <button
        type="submit"
        class="mt-6 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"
      >
        Submit Assignment
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import MultipleImageUpload from '@/components/MultipleImageUpload.vue'
import ImagePreview from '@/components/ImagePreview.vue'

const assignment = ref({
  description: ''
})

const uploadedImages = ref([])
const imageAnnotations = ref({})

const handleImageUpload = (files) => {
  uploadedImages.value.push(...files)
  // Initialize annotations for new images
  files.forEach(file => {
    imageAnnotations.value[file.id] = ''
  })
}

const submitAssignment = async () => {
  const formData = {
    description: assignment.value.description,
    images: uploadedImages.value.map(img => ({
      id: img.id,
      annotation: imageAnnotations.value[img.id]
    }))
  }
  
  console.log('Submitting assignment:', formData)
  // Submit to backend
}
</script>
```

## General Upload Examples

### Document Attachment System

```vue
<template>
  <div class="document-attachments">
    <div class="upload-area">
      <h3 class="text-lg font-medium mb-4">Attach Images</h3>
      
      <ImageUpload
        context="general"
        multiple
        :max-size="5 * 1024 * 1024"
        @upload-success="handleAttachments"
        @upload-progress="updateProgress"
      />
      
      <!-- Progress indicator -->
      <div v-if="uploadProgress > 0 && uploadProgress < 100" class="mt-4">
        <div class="flex justify-between text-sm mb-1">
          <span>Uploading...</span>
          <span>{{ uploadProgress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="bg-blue-600 h-2 rounded-full transition-all"
            :style="{ width: `${uploadProgress}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Attachment list -->
    <div v-if="attachments.length > 0" class="mt-8">
      <h4 class="font-medium mb-4">Attachments ({{ attachments.length }})</h4>
      <div class="space-y-2">
        <div
          v-for="attachment in attachments"
          :key="attachment.id"
          class="flex items-center justify-between p-3 border rounded-lg"
        >
          <div class="flex items-center space-x-3">
            <img
              :src="attachment.url"
              :alt="attachment.original_name"
              class="h-10 w-10 object-cover rounded"
            />
            <div>
              <p class="text-sm font-medium">{{ attachment.original_name }}</p>
              <p class="text-xs text-gray-500">{{ formatFileSize(attachment.size) }}</p>
            </div>
          </div>
          
          <div class="flex items-center space-x-2">
            <button
              @click="copyUrl(attachment.url)"
              class="text-blue-600 hover:text-blue-700 text-sm"
            >
              Copy URL
            </button>
            <button
              @click="removeAttachment(attachment.id)"
              class="text-red-600 hover:text-red-700 text-sm"
            >
              Remove
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'

const attachments = ref([])
const uploadProgress = ref(0)

const handleAttachments = (files) => {
  attachments.value.push(...files)
}

const updateProgress = (percentage) => {
  uploadProgress.value = percentage
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const copyUrl = async (url) => {
  try {
    await navigator.clipboard.writeText(url)
    console.log('URL copied to clipboard')
  } catch (error) {
    console.error('Failed to copy URL:', error)
  }
}

const removeAttachment = (id) => {
  attachments.value = attachments.value.filter(att => att.id !== id)
}
</script>
```

## Advanced Integration Examples

### Form Integration with Validation

```vue
<template>
  <form @submit.prevent="submitForm" class="space-y-6">
    <!-- Form fields -->
    <div class="form-group">
      <label class="block text-sm font-medium mb-2">Title</label>
      <input
        v-model="form.title"
        type="text"
        class="w-full p-3 border rounded-lg"
        :class="{ 'border-red-500': errors.title }"
        required
      />
      <p v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</p>
    </div>

    <!-- Image upload with form validation -->
    <div class="form-group">
      <label class="block text-sm font-medium mb-2">Images</label>
      <ImageUpload
        context="general"
        multiple
        @upload-success="handleImageUpload"
        @upload-error="handleImageError"
        @files-selected="clearImageErrors"
      />
      <p v-if="errors.images" class="text-red-500 text-sm mt-1">{{ errors.images }}</p>
    </div>

    <!-- Submit button -->
    <button
      type="submit"
      :disabled="isSubmitting || !isFormValid"
      class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
    >
      {{ isSubmitting ? 'Submitting...' : 'Submit' }}
    </button>
  </form>
</template>

<script setup>
import { ref, computed } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'

const form = ref({
  title: '',
  images: []
})

const errors = ref({})
const isSubmitting = ref(false)

const isFormValid = computed(() => {
  return form.value.title.trim() && 
         form.value.images.length > 0 && 
         Object.keys(errors.value).length === 0
})

const handleImageUpload = (files) => {
  form.value.images = files
  delete errors.value.images
}

const handleImageError = (error) => {
  errors.value.images = error
}

const clearImageErrors = () => {
  delete errors.value.images
}

const submitForm = async () => {
  isSubmitting.value = true
  
  try {
    const response = await fetch('/api/submissions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        title: form.value.title,
        image_ids: form.value.images.map(img => img.id)
      })
    })
    
    if (response.ok) {
      console.log('Form submitted successfully')
    } else {
      const errorData = await response.json()
      errors.value = errorData.errors || {}
    }
  } catch (error) {
    console.error('Submission failed:', error)
  } finally {
    isSubmitting.value = false
  }
}
</script>
```

### Drag and Drop Integration

```vue
<template>
  <div
    class="drag-drop-area"
    :class="{ 'drag-over': isDragOver }"
    @drop="handleDrop"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
  >
    <div class="upload-content">
      <Icon name="upload-cloud" class="h-16 w-16 text-gray-400 mx-auto mb-4" />
      <h3 class="text-lg font-medium mb-2">Drop images here</h3>
      <p class="text-gray-600 mb-4">or click to select files</p>
      
      <input
        ref="fileInput"
        type="file"
        multiple
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />
      
      <button
        @click="$refs.fileInput.click()"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
      >
        Select Images
      </button>
    </div>

    <!-- Upload progress -->
    <div v-if="uploadState.isUploading" class="upload-progress">
      <div class="flex justify-between text-sm mb-2">
        <span>Uploading {{ filePreviews.length }} files...</span>
        <span>{{ uploadState.progress.percentage }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div
          class="bg-blue-600 h-2 rounded-full transition-all"
          :style="{ width: `${uploadState.progress.percentage}%` }"
        ></div>
      </div>
    </div>

    <!-- File previews -->
    <div v-if="filePreviews.length > 0" class="file-previews">
      <div class="grid grid-cols-3 md:grid-cols-6 gap-4 mt-6">
        <div
          v-for="preview in filePreviews"
          :key="preview.id"
          class="relative group"
        >
          <img
            :src="preview.url"
            :alt="preview.file.name"
            class="h-20 w-20 object-cover rounded-lg"
          />
          
          <!-- Upload status overlay -->
          <div
            v-if="preview.uploadState"
            class="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center"
            :class="{
              'opacity-0 group-hover:opacity-100': !preview.uploadState.isUploading && !preview.uploadState.error,
              'opacity-100': preview.uploadState.isUploading || preview.uploadState.error
            }"
          >
            <Icon
              v-if="preview.uploadState.isUploading"
              name="loader-2"
              class="h-6 w-6 text-white animate-spin"
            />
            <Icon
              v-else-if="preview.uploadState.success"
              name="check"
              class="h-6 w-6 text-green-400"
            />
            <Icon
              v-else-if="preview.uploadState.error"
              name="x"
              class="h-6 w-6 text-red-400"
            />
            <button
              v-else
              @click="removeFile(preview.id)"
              class="h-6 w-6 text-white hover:text-red-400"
            >
              <Icon name="x" class="h-6 w-6" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useImageUpload } from '@/composables/useImageUpload'
import Icon from '@/components/Icon.vue'

const isDragOver = ref(false)

const {
  uploadState,
  filePreviews,
  selectFiles,
  uploadFiles,
  removeFile
} = useImageUpload({
  context: 'general',
  multiple: true
})

const handleDrop = (event) => {
  event.preventDefault()
  isDragOver.value = false
  
  const files = event.dataTransfer.files
  if (files.length > 0) {
    selectFiles(files)
    uploadFiles()
  }
}

const handleDragOver = (event) => {
  event.preventDefault()
  isDragOver.value = true
}

const handleDragLeave = (event) => {
  event.preventDefault()
  isDragOver.value = false
}

const handleFileSelect = (event) => {
  const files = event.target.files
  if (files.length > 0) {
    selectFiles(files)
    uploadFiles()
  }
}
</script>

<style scoped>
.drag-drop-area {
  @apply border-2 border-dashed border-gray-300 rounded-lg p-8 text-center transition-colors;
}

.drag-drop-area.drag-over {
  @apply border-blue-500 bg-blue-50;
}
</style>
```

## Custom Validation Examples

### File Type and Size Validation

```vue
<template>
  <div class="custom-validation-upload">
    <div class="validation-rules mb-4 p-4 bg-gray-50 rounded-lg">
      <h4 class="font-medium mb-2">Upload Requirements:</h4>
      <ul class="text-sm text-gray-600 space-y-1">
        <li>• Maximum file size: {{ maxSizeText }}</li>
        <li>• Allowed formats: {{ allowedFormats.join(', ') }}</li>
        <li>• Maximum {{ maxFiles }} files</li>
        <li>• Minimum resolution: {{ minResolution.width }}x{{ minResolution.height }}px</li>
      </ul>
    </div>

    <ImageUpload
      context="general"
      multiple
      :max-size="maxSize"
      :allowed-types="allowedTypes"
      @files-selected="validateFiles"
      @upload-success="handleUpload"
    />

    <!-- Custom validation errors -->
    <div v-if="customErrors.length > 0" class="mt-4 space-y-2">
      <div
        v-for="error in customErrors"
        :key="error.id"
        class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg"
      >
        <Icon name="alert-circle" class="h-5 w-5 text-red-500 mr-3" />
        <div>
          <p class="text-sm font-medium text-red-800">{{ error.filename }}</p>
          <p class="text-sm text-red-600">{{ error.message }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import ImageUpload from '@/components/ImageUpload.vue'
import Icon from '@/components/Icon.vue'

const maxSize = 5 * 1024 * 1024 // 5MB
const maxFiles = 3
const allowedTypes = ['image/jpeg', 'image/png', 'image/webp']
const allowedFormats = ['JPEG', 'PNG', 'WebP']
const minResolution = { width: 800, height: 600 }

const customErrors = ref([])

const maxSizeText = computed(() => {
  return `${Math.round(maxSize / (1024 * 1024))}MB`
})

const validateFiles = async (count) => {
  customErrors.value = []
  
  // Get the actual files from the file input or drag event
  const fileInput = document.querySelector('input[type="file"]')
  if (!fileInput?.files) return
  
  const files = Array.from(fileInput.files)
  
  for (const file of files) {
    const errors = await validateSingleFile(file)
    if (errors.length > 0) {
      customErrors.value.push({
        id: `${file.name}_${Date.now()}`,
        filename: file.name,
        message: errors.join(', ')
      })
    }
  }
}

const validateSingleFile = async (file) => {
  const errors = []
  
  // Check file size
  if (file.size > maxSize) {
    errors.push(`File too large (max ${maxSizeText.value})`)
  }
  
  // Check file type
  if (!allowedTypes.includes(file.type)) {
    errors.push(`Invalid file type (allowed: ${allowedFormats.join(', ')})`)
  }
  
  // Check image dimensions
  try {
    const dimensions = await getImageDimensions(file)
    if (dimensions.width < minResolution.width || dimensions.height < minResolution.height) {
      errors.push(`Image too small (min ${minResolution.width}x${minResolution.height}px)`)
    }
  } catch (error) {
    errors.push('Could not read image dimensions')
  }
  
  return errors
}

const getImageDimensions = (file) => {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve({
        width: img.naturalWidth,
        height: img.naturalHeight
      })
    }
    
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Failed to load image'))
    }
    
    img.src = url
  })
}

const handleUpload = (files) => {
  console.log('Upload successful:', files)
  customErrors.value = []
}
</script>
```

These examples demonstrate various ways to integrate the flexible image upload system into different parts of your application, from simple avatar uploads to complex form integrations with custom validation.