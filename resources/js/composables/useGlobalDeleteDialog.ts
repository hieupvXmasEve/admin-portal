import { useDeleteDialogStore, type DeleteDialogOptions, type DeleteDialogCallbacks } from '@/stores/deleteDialog'

export function useGlobalDeleteDialog() {
  const deleteDialogStore = useDeleteDialogStore()

  /**
   * Show a delete confirmation dialog
   * @param options Dialog configuration options
   * @param callbacks Confirmation and cancellation callbacks
   */
  const confirmDelete = (options: DeleteDialogOptions, callbacks: DeleteDialogCallbacks) => {
    deleteDialogStore.showDialog(options, callbacks)
  }

  /**
   * Quick delete confirmation with minimal configuration
   * @param message The confirmation message
   * @param onConfirm The confirmation callback
   * @param onCancel Optional cancellation callback
   */
  const quickDelete = (
    message: string,
    onConfirm: () => void | Promise<void>,
    onCancel?: () => void
  ) => {
    deleteDialogStore.showDialog(
      { message },
      { onConfirm, onCancel }
    )
  }

  /**
   * Delete item with contextual message
   * @param itemName Name of the item being deleted
   * @param itemType Type of item (e.g., 'user', 'project', 'student')
   * @param onConfirm The confirmation callback
   * @param onCancel Optional cancellation callback
   */
  const deleteItem = (
    itemName: string,
    itemType: string,
    onConfirm: () => void | Promise<void>,
    onCancel?: () => void
  ) => {

    const capitalizedType = itemType.charAt(0).toUpperCase() + itemType.slice(1)
    const options = {
      title: `Delete ${capitalizedType}`,
      message: `Are you sure you want to delete (${itemName})? This action cannot be undone.`,
      confirmText: `Delete ${capitalizedType}`,
    };
    const callbacks = { onConfirm, onCancel };

    deleteDialogStore.showDialog(options, callbacks)
  }

  /**
   * Close the delete dialog
   */
  const closeDialog = () => {
    deleteDialogStore.hideDialog()
  }

  return {
    // Main methods
    confirmDelete,
    quickDelete,
    deleteItem,
    closeDialog,

    // Store state (read-only)
    isOpen: deleteDialogStore.isOpen,
    isLoading: deleteDialogStore.isLoading,
    options: deleteDialogStore.options,
  }
}

// Export types for convenience
export type { DeleteDialogOptions, DeleteDialogCallbacks } from '@/stores/deleteDialog'
