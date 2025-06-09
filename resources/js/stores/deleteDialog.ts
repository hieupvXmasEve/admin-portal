import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface DeleteDialogOptions {
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  item?: any // The item being deleted (for context)
}

export interface DeleteDialogCallbacks {
  onConfirm: () => void | Promise<void>
  onCancel?: () => void
}

export const useDeleteDialogStore = defineStore('deleteDialog', () => {
  // State
  const isOpen = ref(false)
  const isLoading = ref(false)
  const options = ref<DeleteDialogOptions | null>(null)
  const callbacks = ref<DeleteDialogCallbacks | null>(null)

  // Actions
  const showDialog = (dialogOptions: DeleteDialogOptions, dialogCallbacks: DeleteDialogCallbacks) => {
    console.log('Store showDialog called with:', { dialogOptions, dialogCallbacks });

    options.value = {
      title: 'Confirm Delete',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      ...dialogOptions,
    }
    callbacks.value = dialogCallbacks
    isOpen.value = true

    console.log('Store state after showDialog:', {
      isOpen: isOpen.value,
      options: options.value,
      callbacks: callbacks.value
    });
  }

  const hideDialog = () => {
    console.log('Store hideDialog called, current state:', {
      isOpen: isOpen.value,
      isLoading: isLoading.value,
      hasCallbacks: !!callbacks.value
    });

    // Only clear state if dialog is actually open
    if (isOpen.value || isLoading.value) {
      isOpen.value = false
      isLoading.value = false
      options.value = null
      callbacks.value = null
      console.log('Store hideDialog: State cleared');
    } else {
      console.log('Store hideDialog: Dialog already closed, ignoring');
    }
  }

  const handleConfirm = async () => {
    console.log('Store handleConfirm called, callbacks:', callbacks.value);
    if (!callbacks.value?.onConfirm) {
      console.log('No onConfirm callback found');
      return;
    }

    try {
      isLoading.value = true
      console.log('About to call onConfirm callback');
      console.log('Callback type:', typeof callbacks.value.onConfirm);
      console.log('Callback function:', callbacks.value.onConfirm.toString());

      const result = callbacks.value.onConfirm();
      console.log('onConfirm callback result:', result);

      if (result instanceof Promise) {
        console.log('Result is a Promise, awaiting...');
        await result;
        console.log('Promise resolved successfully');
      } else {
        console.log('Result is not a Promise, continuing...');
      }

      console.log('onConfirm callback completed successfully');
      hideDialog()
    } catch (error) {
      console.error('Delete confirmation error:', error)
      // Keep dialog open on error so user can retry or cancel
    } finally {
      isLoading.value = false
    }
  }

  const handleCancel = () => {
    if (callbacks.value?.onCancel) {
      callbacks.value.onCancel()
    }
    hideDialog()
  }

  return {
    // State
    isOpen,
    isLoading,
    options,
    callbacks,

    // Actions
    showDialog,
    hideDialog,
    handleConfirm,
    handleCancel,
  }
})
