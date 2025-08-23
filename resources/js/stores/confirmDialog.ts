import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface ConfirmDialogOptions {
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    item?: any; // The item being processed (for context)
}

export interface ConfirmDialogCallbacks {
    onConfirm: () => void | Promise<void>;
    onCancel?: () => void;
}

export const useConfirmDialogStore = defineStore('confirmDialog', () => {
    // State
    const isOpen = ref(false);
    const isLoading = ref(false);
    const options = ref<ConfirmDialogOptions | null>(null);
    const callbacks = ref<ConfirmDialogCallbacks | null>(null);

    // Actions
    const showDialog = (dialogOptions: ConfirmDialogOptions, dialogCallbacks: ConfirmDialogCallbacks) => {
        options.value = {
            title: 'Confirm Action',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            ...dialogOptions,
        };
        callbacks.value = dialogCallbacks;
        isOpen.value = true;
    };

    const hideDialog = () => {
        // Only clear state if dialog is actually open
        if (isOpen.value || isLoading.value) {
            isOpen.value = false;
            isLoading.value = false;
            options.value = null;
            callbacks.value = null;
        }
    };

    const handleConfirm = async () => {
        if (!callbacks.value?.onConfirm) {
            return;
        }

        try {
            isLoading.value = true;
            const result = callbacks.value.onConfirm();
            if (result instanceof Promise) {
                await result;
            }

            hideDialog();
        } catch (error) {
            console.error('Confirmation error:', error);
            // Keep dialog open on error so user can retry or cancel
        } finally {
            isLoading.value = false;
        }
    };

    const handleCancel = () => {
        if (callbacks.value?.onCancel) {
            callbacks.value.onCancel();
        }
        hideDialog();
    };

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
    };
});
