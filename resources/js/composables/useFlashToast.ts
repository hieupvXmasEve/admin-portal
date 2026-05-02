import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';

type ToastType = 'success' | 'error' | 'warning' | 'info';

const TOAST_TYPE_KEYS: ToastType[] = ['success', 'error', 'warning', 'info'];

let isListenerActive = false;

function showToast(type: ToastType, message: string) {
    const fn = toast[type] ?? toast.success;
    fn(message);
}

/**
 * Process Inertia v3 native flash data (page.flash) and show toasts.
 *
 * Supports two patterns:
 *  1. { message: '...', type?: 'success' | 'error' | ... }
 *  2. { success: '...', error: '...' }  (keyed by toast type)
 */
function processFlash(flash: Record<string, unknown>) {
    // Pattern 1: explicit message + optional type
    if (typeof flash.message === 'string') {
        const type = (TOAST_TYPE_KEYS.includes(flash.type as ToastType) ? flash.type : 'success') as ToastType;
        showToast(type, flash.message);
        return;
    }

    // Pattern 2: keyed by type (e.g. flash.success = '...')
    for (const key of TOAST_TYPE_KEYS) {
        if (typeof flash[key] === 'string') {
            showToast(key, flash[key]);
        }
    }
}

/**
 * Global flash-to-toast bridge for Inertia v3 native flash (page.flash).
 *
 * Mount once in AppLayout. Listens to the `flash` router event which fires
 * whenever a response (or client-side call) sets page.flash data.
 *
 * Legacy session-based flash (page.props.flash) is NOT handled here —
 * those are consumed per-page or will be migrated to Inertia::flash().
 */
export function useFlashToast() {
    let removeListener: (() => void) | null = null;

    onMounted(() => {
        if (!isListenerActive) {
            isListenerActive = true;

            removeListener = router.on('flash', (event) => {
                const flash = event.detail.flash as Record<string, unknown>;
                processFlash(flash);
            });
        }
    });

    onUnmounted(() => {
        if (removeListener) {
            removeListener();
            isListenerActive = false;
        }
    });
}