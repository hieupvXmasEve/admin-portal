# Global Delete Dialog System

A centralized, toast-notification-style delete confirmation system built with Pinia state management and Vue 3 Composition API.

## Overview

The Global Delete Dialog System provides a consistent way to handle delete confirmations throughout the application. It uses Pinia for state management, making it accessible from any component without prop drilling or complex event handling.

## Architecture

### Components
- **`GlobalDeleteDialog.vue`** - The UI component (automatically included in app.ts)
- **`useDeleteDialogStore`** - Pinia store for state management
- **`useGlobalDeleteDialog`** - Composable providing the public API

### Features
- ✅ **Global State Management** - Single dialog instance using Pinia
- ✅ **Toast-like API** - Can be triggered from anywhere in the app
- ✅ **Loading States** - Shows loading indicators during async operations
- ✅ **Error Handling** - Keeps dialog open on errors for retry
- ✅ **TypeScript Support** - Full type safety with proper interfaces
- ✅ **Customizable Content** - All text content can be customized
- ✅ **Async/Sync Support** - Handles both synchronous and asynchronous operations
- ✅ **Consistent UI** - Uses shadcn-vue AlertDialog components

## Setup

The global delete dialog is automatically available throughout the application. The `GlobalDeleteDialog` component is included in the root app component and the Pinia store is configured globally.

## Usage

### Basic Import

```vue
<script setup lang="ts">
import { useGlobalDeleteDialog } from '@/composables'

const deleteDialog = useGlobalDeleteDialog()
</script>
```

### Method 1: `deleteItem()` - Contextual Delete

Best for standard entity deletions with auto-generated messages.

```vue
<script setup lang="ts">
const deleteUser = (user) => {
  deleteDialog.deleteItem(
    user.name,           // Item name
    'user',              // Item type
    async () => {        // Confirm callback
      await api.delete(`/users/${user.id}`)
      // Handle success (refresh data, show toast, etc.)
    },
    () => {              // Cancel callback (optional)
      console.log('Deletion cancelled')
    }
  )
}
</script>
```

**Generated Dialog:**
- Title: "Delete User"
- Message: "Are you sure you want to delete John Doe? This action cannot be undone."
- Confirm Button: "Delete User"

### Method 2: `confirmDelete()` - Full Control

Best for custom messages, bulk operations, or complex scenarios.

```vue
<script setup lang="ts">
const deleteBulkItems = (items) => {
  deleteDialog.confirmDelete(
    {
      title: 'Delete Multiple Items',
      message: `Are you sure you want to delete ${items.length} items? This will permanently remove all selected data and cannot be undone.`,
      confirmText: `Delete ${items.length} Items`,
      cancelText: 'Keep Items',
    },
    {
      onConfirm: async () => {
        await api.post('/bulk-delete', { ids: items.map(i => i.id) })
        // Handle success
      },
      onCancel: () => {
        console.log('Bulk deletion cancelled')
      }
    }
  )
}
</script>
```

### Method 3: `quickDelete()` - Minimal Configuration

Best for simple confirmations with minimal setup.

```vue
<script setup lang="ts">
const quickDelete = (item) => {
  deleteDialog.quickDelete(
    `Delete ${item.name}? This cannot be undone.`,
    async () => {
      await api.delete(`/items/${item.id}`)
      // Handle success
    }
  )
}
</script>
```

## API Reference

### `useGlobalDeleteDialog()`

Returns an object with the following methods and properties:

#### Methods

##### `deleteItem(itemName, itemType, onConfirm, onCancel?)`
- **itemName**: `string` - Name of the item being deleted
- **itemType**: `string` - Type of item (e.g., 'user', 'project', 'student')
- **onConfirm**: `() => void | Promise<void>` - Confirmation callback
- **onCancel**: `() => void` - Optional cancellation callback

##### `confirmDelete(options, callbacks)`
- **options**: `DeleteDialogOptions` - Dialog configuration
- **callbacks**: `DeleteDialogCallbacks` - Confirmation and cancellation callbacks

##### `quickDelete(message, onConfirm, onCancel?)`
- **message**: `string` - The confirmation message
- **onConfirm**: `() => void | Promise<void>` - Confirmation callback
- **onCancel**: `() => void` - Optional cancellation callback

##### `closeDialog()`
Programmatically close the dialog.

#### Properties (Read-only)

- **isOpen**: `Ref<boolean>` - Whether dialog is currently open
- **isLoading**: `Ref<boolean>` - Whether confirmation action is in progress
- **options**: `Ref<DeleteDialogOptions | null>` - Current dialog configuration

### Type Definitions

```typescript
interface DeleteDialogOptions {
  title?: string          // Dialog title (default: "Confirm Delete")
  message: string        // Required: Dialog description text
  confirmText?: string   // Confirm button text (default: "Delete")
  cancelText?: string    // Cancel button text (default: "Cancel")
  item?: any            // The item being deleted (for context)
}

interface DeleteDialogCallbacks {
  onConfirm: () => void | Promise<void>  // Required: Confirmation callback
  onCancel?: () => void                  // Optional: Cancellation callback
}
```

## Integration Examples

### Inertia.js Integration

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const deleteStudent = (student) => {
  deleteDialog.deleteItem(
    student.full_name,
    'student',
    () => {
      return new Promise((resolve, reject) => {
        router.delete(route('students.destroy', student.id), {
          onSuccess: () => resolve(),
          onError: (errors) => {
            console.error('Failed to delete student:', errors)
            reject(new Error('Failed to delete student'))
          },
        })
      })
    }
  )
}
</script>
```

### API Integration with Error Handling

```vue
<script setup lang="ts">
import { useApiRequest } from '@/composables'

const { request } = useApiRequest()

const deleteProject = (project) => {
  deleteDialog.confirmDelete(
    {
      title: 'Delete Project',
      message: `Are you sure you want to delete "${project.name}"? This will remove all associated files and data.`,
    },
    {
      onConfirm: async () => {
        try {
          await request.delete(`/api/projects/${project.id}`)
          // Success - dialog will auto-close
          projects.value = projects.value.filter(p => p.id !== project.id)
          showToast('Project deleted successfully')
        } catch (error) {
          // Error - dialog stays open for retry
          throw new Error('Failed to delete project. Please try again.')
        }
      }
    }
  )
}
</script>
```

### Bulk Operations

```vue
<script setup lang="ts">
const selectedItems = ref([])

const deleteBulkItems = () => {
  if (selectedItems.value.length === 0) return

  deleteDialog.confirmDelete(
    {
      title: 'Delete Selected Items',
      message: `Are you sure you want to delete ${selectedItems.value.length} selected items? This action cannot be undone.`,
      confirmText: `Delete ${selectedItems.value.length} Items`,
    },
    {
      onConfirm: async () => {
        const ids = selectedItems.value.map(item => item.id)
        await api.post('/bulk-delete', { ids })
        
        // Remove from local state
        items.value = items.value.filter(item => !ids.includes(item.id))
        selectedItems.value = []
        
        showToast(`${ids.length} items deleted successfully`)
      }
    }
  )
}
</script>
```

## Best Practices

### 1. Use Appropriate Method
- **`deleteItem()`** - For standard entity deletions
- **`confirmDelete()`** - For complex scenarios or custom messaging
- **`quickDelete()`** - For simple, quick confirmations

### 2. Provide Context
```vue
// ❌ Not specific enough
deleteDialog.quickDelete('Delete this item?', onConfirm)

// ✅ Provide clear context
deleteDialog.deleteItem(user.name, 'user account', onConfirm)
```

### 3. Handle Errors Properly
```vue
// ✅ Throw errors to keep dialog open for retry
onConfirm: async () => {
  try {
    await api.delete(url)
  } catch (error) {
    throw new Error('Failed to delete. Please try again.')
  }
}
```

### 4. Use Meaningful Messages
```vue
// ❌ Generic message
message: 'Are you sure?'

// ✅ Specific and informative
message: `Are you sure you want to delete "${item.name}"? This will permanently remove all associated data and cannot be undone.`
```

### 5. Provide Cancel Callbacks When Needed
```vue
deleteDialog.deleteItem(
  item.name,
  'item',
  onConfirm,
  () => {
    // Track cancellation for analytics
    analytics.track('delete_cancelled', { item_type: 'user' })
  }
)
```

## Migration from Component-based Dialogs

### Before (Component-based)
```vue
<template>
  <DeleteConfirmationDialog
    :open="isDeleteDialogOpen"
    :loading="isDeleting"
    @confirm="handleConfirm"
    @cancel="handleCancel"
  />
</template>

<script setup lang="ts">
const isDeleteDialogOpen = ref(false)
const isDeleting = ref(false)
const itemToDelete = ref(null)

const showDeleteDialog = (item) => {
  itemToDelete.value = item
  isDeleteDialogOpen.value = true
}

const handleConfirm = async () => {
  isDeleting.value = true
  // deletion logic
  isDeleting.value = false
  isDeleteDialogOpen.value = false
}
</script>
```

### After (Global System)
```vue
<script setup lang="ts">
import { useGlobalDeleteDialog } from '@/composables'

const deleteDialog = useGlobalDeleteDialog()

const showDeleteDialog = (item) => {
  deleteDialog.deleteItem(item.name, 'item', async () => {
    // deletion logic
  })
}
</script>
```

## Troubleshooting

### Dialog Not Appearing
1. Ensure Pinia is properly configured in `app.ts`
2. Verify `GlobalDeleteDialog` component is included in the root app
3. Check for JavaScript errors in the console

### State Not Updating
1. Ensure you're using the composable methods, not directly accessing the store
2. Check that Pinia store is properly initialized

### TypeScript Errors
1. Ensure proper imports: `import { useGlobalDeleteDialog } from '@/composables'`
2. Verify type definitions are exported in `composables/index.ts`

### Dialog Closing Unexpectedly
1. Check that your `onConfirm` callback doesn't throw unhandled errors
2. Ensure Promise rejections are properly handled 
