
<script setup lang="ts">
import { computed, ref } from 'vue'
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription
} from 'reka-ui'
import {
    AlertTriangleIcon,
    InfoIcon,
    CheckCircleIcon,
    XCircleIcon
} from 'lucide-vue-next'

interface Props {
    open: boolean
    title: string
    message: string
    confirmText?: string
    cancelText?: string
    confirmVariant?: 'primary' | 'danger' | 'warning' | 'success'
    icon?: 'warning' | 'info' | 'success' | 'error'
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'confirm'): void
    (e: 'cancel'): void
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    confirmVariant: 'primary',
    icon: 'warning'
})

const emit = defineEmits<Emits>()

const isLoading = ref(false)

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
})

const iconComponent = computed(() => {
    switch (props.icon) {
        case 'info':
            return InfoIcon
        case 'success':
            return CheckCircleIcon
        case 'error':
            return XCircleIcon
        default:
            return AlertTriangleIcon
    }
})

const iconClass = computed(() => {
    switch (props.icon) {
        case 'info':
            return 'text-blue-600'
        case 'success':
            return 'text-green-600'
        case 'error':
            return 'text-red-600'
        default:
            return 'text-red-600'
    }
})

const iconBgClass = computed(() => {
    switch (props.icon) {
        case 'info':
            return 'bg-blue-100'
        case 'success':
            return 'bg-green-100'
        case 'error':
            return 'bg-red-100'
        default:
            return 'bg-red-100'
    }
})

const confirmButtonClass = computed(() => {
    switch (props.confirmVariant) {
        case 'danger':
            return 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600'
        case 'warning':
            return 'bg-yellow-600 text-white hover:bg-yellow-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-600'
        case 'success':
            return 'bg-green-600 text-white hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600'
        default:
            return 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
    }
})

const handleConfirm = async () => {
    isLoading.value = true
    try {
        emit('confirm')
    } finally {
        isLoading.value = false
    }
}

const handleCancel = () => {
    emit('cancel')
    isOpen.value = false
}
</script>

<template>
    <DialogRoot v-model:open="isOpen">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" />
            <DialogContent class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div :class="[
                                'mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10',
                                iconBgClass
                            ]">
                                <component :is="iconComponent" :class="['h-6 w-6', iconClass]" />
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <DialogTitle class="text-base font-semibold leading-6 text-gray-900">
                                    {{ title }}
                                </DialogTitle>
                                <div class="mt-2">
                                    <DialogDescription class="text-sm text-gray-500">
                                        {{ message }}
                                    </DialogDescription>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button type="button" @click="handleConfirm" :disabled="isLoading" :class="[
                                'inline-flex w-full justify-center rounded-md px-3 py-2 text-sm font-semibold shadow-sm sm:ml-3 sm:w-auto',
                                confirmButtonClass,
                                { 'opacity-50 cursor-not-allowed': isLoading }
                            ]">
                                {{ isLoading ? 'Processing...' : confirmText }}
                            </button>
                            <button type="button" @click="handleCancel" :disabled="isLoading"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ cancelText }}
                            </button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
