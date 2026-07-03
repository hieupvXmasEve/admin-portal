import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

/**
 * Mutating actions in the Course Offering Cockpit (ADR 0013) post to a web
 * route and refresh the backend-derived cockpit props via an Inertia partial
 * reload — no full page navigation, no parallel endpoint. Every cockpit
 * mutation should go through this so `operational_state` is always
 * re-derived after a write.
 */
export function useCockpitAction() {
    const isPending = ref(false);

    const submit = (url: string, data: RequestPayload = {}, refreshProps: string[] = ['operational_state']) => {
        router.post(url, data, {
            only: refreshProps,
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isPending.value = true;
            },
            onFinish: () => {
                isPending.value = false;
            },
        });
    };

    return { isPending: readonly(isPending), submit };
}
