import { useConfirmDialogStore, type ConfirmDialogOptions, type ConfirmDialogCallbacks } from '@/stores/confirmDialog'

export function useGlobalConfirmDialog() {
  const confirmDialogStore = useConfirmDialogStore()

  /**
   * Show a confirmation dialog
   * @param options Dialog configuration options
   * @param callbacks Confirmation and cancellation callbacks
   */
  const showConfirmDialog = (options: ConfirmDialogOptions, callbacks: ConfirmDialogCallbacks) => {
    confirmDialogStore.showDialog(options, callbacks)
  }

  /**
   * Quick confirmation dialog with minimal configuration
   * @param message The confirmation message
   * @param onConfirm The confirmation callback
   * @param onCancel Optional cancellation callback
   */
  const quickConfirm = (
    message: string,
    onConfirm: () => void | Promise<void>,
    onCancel?: () => void
  ) => {
    confirmDialogStore.showDialog(
      { message },
      { onConfirm, onCancel }
    )
  }

  /**
   * Delete item with contextual message (backward compatibility)
   * @param itemName Name of the item being deleted
   * @param itemType Type of item (e.g., 'user', 'project', 'student')
   * @param onConfirm The confirmation callback
   * @param onCancel Optional cancellation callback
   */
  const confirmDelete = (
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

    confirmDialogStore.showDialog(options, callbacks)
  }

  /**
   * Close the confirmation dialog
   */
  const closeDialog = () => {
    confirmDialogStore.hideDialog()
  }

  return {
    // Main methods
    showConfirmDialog,
    quickConfirm,
    confirmDelete, // Keep for backward compatibility with delete operations
    closeDialog,

    // Store state (read-only)
    isOpen: confirmDialogStore.isOpen,
    isLoading: confirmDialogStore.isLoading,
    options: confirmDialogStore.options,
  }
}

// Export types for convenience
export type { ConfirmDialogOptions, ConfirmDialogCallbacks } from '@/stores/confirmDialog'
