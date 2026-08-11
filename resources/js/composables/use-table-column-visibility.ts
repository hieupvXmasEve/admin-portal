import type { VisibilityState } from '@tanstack/vue-table';
import { ref, watch } from 'vue';

/**
 * Persist a DataTable's column visibility in localStorage, per browser (D4).
 * `storageKey` must be versioned by the caller (e.g. `student-applications:columns:v1`)
 * so a future change to the column set invalidates stored state instead of
 * silently reapplying a stale one — see useAppearance.ts for the only other
 * localStorage precedent in this codebase.
 */
export function useTableColumnVisibility(storageKey: string, defaultVisibility: VisibilityState, knownColumnIds: string[]) {
    const read = (): VisibilityState => {
        if (typeof window === 'undefined') {
            return { ...defaultVisibility };
        }

        const raw = localStorage.getItem(storageKey);
        if (!raw) {
            return { ...defaultVisibility };
        }

        try {
            const parsed = JSON.parse(raw) as VisibilityState;
            // Prune ids that no longer exist so a removed/renamed column can't
            // corrupt stored state or leave a phantom entry behind.
            const pruned: VisibilityState = { ...defaultVisibility };
            for (const id of knownColumnIds) {
                if (id in parsed) {
                    pruned[id] = parsed[id];
                }
            }
            return pruned;
        } catch {
            return { ...defaultVisibility };
        }
    };

    const columnVisibility = ref<VisibilityState>(read());

    watch(
        columnVisibility,
        (value) => {
            if (typeof window === 'undefined') {
                return;
            }
            localStorage.setItem(storageKey, JSON.stringify(value));
        },
        { deep: true },
    );

    return { columnVisibility };
}
