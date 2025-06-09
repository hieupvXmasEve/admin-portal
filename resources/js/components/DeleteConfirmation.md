# Delete Confirmation Component & Composable

A reusable delete confirmation system that provides a consistent way to handle delete actions across the application.

## Components

### `DeleteConfirmationDialog.vue`
A reusable dialog component built on top of shadcn-vue AlertDialog components.

### `useDeleteConfirmation()` Composable
A composable function that manages dialog state and handles confirmation logic.

## Usage

### Basic Setup

```vue
<script setup lang="ts">
import DeleteConfirmationDialog from '@/components/DeleteConfirmationDialog.vue'
import { useDeleteConfirmation } from '@/composables'

// Initialize the composable
const deleteConfirmation = useDeleteConfirmation()

// Define your delete function
const deleteItem = (item) => {
  deleteConfirmation.showConfirmation({
    message: `Are you sure you want to delete ${item.name}?`,
    onConfirm: async () => {
      // Your delete logic here
      await api.delete(`/items/${item.id}`)
      // Handle success
    }
  })
}
</script>

<template>
  <!-- Your component content -->
  
  <!-- Add the dialog component -->
  <DeleteConfirmationDialog
    :open="deleteConfirmation.isOpen.value"
    :loading="deleteConfirmation.isLoading.value"
    :options="deleteConfirmation.options.value"
    @confirm="deleteConfirmation.handleConfirm"
    @cancel="deleteConfirmation.handleCancel"
    @update:open="deleteConfirmation.closeDialog"
  />
</template>
```

### Advanced Configuration

```vue
<script setup lang="ts">
const deleteUser = (user) => {
  deleteConfirmation.showConfirmation({
    title: 'Delete User Account',
    message: `Are you sure you want to delete ${user.name}? This will permanently remove their account and all associated data.`,
    confirmText: 'Delete Account',
    cancelText: 'Keep Account',
    onConfirm: async () => {
      try {
        await userApi.delete(user.id)
        // Handle success
        users.value = users.value.filter(u => u.id !== user.id)
      } catch (error) {
        // Error handling - throw to keep dialog open
        throw new Error('Failed to delete user')
      }
    },
    onCancel: () => {
      console.log('User deletion cancelled')
    }
  })
}
</script>
```

### Inertia.js Integration

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const deleteStudent = (student) => {
  deleteConfirmation.showConfirmation({
    title: 'Delete Student',
    message: `Are you sure you want to delete ${student.full_name}? This action cannot be undone.`,
    onConfirm: () => {
      return new Promise((resolve, reject) => {
        router.delete(route('students.destroy', student.id), {
          onSuccess: () => resolve(),
          onError: (errors) => {
            console.error('Failed to delete student:', errors)
            reject(new Error('Failed to delete student'))
          },
        })
      })
    },
  })
}
</script>
```

## API Reference

### `useDeleteConfirmation()`

Returns an object with the following properties and methods:

#### Properties
- `isOpen` - Reactive boolean indicating if dialog is open
- `isLoading` - Reactive boolean indicating if confirmation action is in progress
- `options` - Reactive object containing current dialog configuration

#### Methods
- `showConfirmation(options)` - Shows the confirmation dialog
- `handleConfirm()` - Executes the confirm action
- `handleCancel()` - Executes the cancel action and closes dialog
- `closeDialog()` - Closes the dialog and resets state

### `DeleteConfirmationOptions` Interface

```typescript
interface DeleteConfirmationOptions {
  title?: string              // Dialog title (default: "Confirm Delete")
  message: string            // Required: Dialog description text
  confirmText?: string       // Confirm button text (default: "Delete")
  cancelText?: string        // Cancel button text (default: "Cancel")
  onConfirm: () => void | Promise<void>  // Required: Confirm callback
  onCancel?: () => void      // Optional: Cancel callback
}
```

## Features

- **Consistent UI**: Uses shadcn-vue AlertDialog components
- **Loading States**: Shows loading indicator during async operations
- **Error Handling**: Keeps dialog open on error, allowing retry
- **Customizable Text**: All text content can be customized
- **Async Support**: Handles both sync and async confirm actions
- **TypeScript**: Full TypeScript support with proper types

## Styling

The confirm button automatically uses destructive styling (red background) appropriate for delete actions:

```css
class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
```

## Best Practices

1. **Always provide meaningful messages**: Include what will be deleted and mention if it's irreversible
2. **Use specific titles**: Instead of generic "Delete", use "Delete User", "Delete Project", etc.
3. **Handle errors properly**: Throw errors in onConfirm to keep dialog open for retry
4. **Use async/await**: For better error handling with async operations
5. **Provide context**: Include entity names in the confirmation message

## Migration from inline confirms

### Before (inline confirm)
```vue
const deleteItem = (item) => {
  if (confirm(`Delete ${item.name}?`)) {
    // delete logic
  }
}
```

### After (using composable)
```vue
const deleteItem = (item) => {
  deleteConfirmation.showConfirmation({
    message: `Are you sure you want to delete ${item.name}?`,
    onConfirm: async () => {
      // delete logic
    }
  })
}
``` 
