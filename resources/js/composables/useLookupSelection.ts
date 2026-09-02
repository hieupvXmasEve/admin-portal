import { financeRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export interface LookupSelectableRow {
    key: number | string;
    studentId: number;
}

export function useLookupSelection() {
    const selected = ref<Map<number | string, number>>(new Map());

    function toggle(row: LookupSelectableRow): void {
        const next = new Map(selected.value);
        if (next.has(row.key)) {
            next.delete(row.key);
        } else {
            next.set(row.key, row.studentId);
        }
        selected.value = next;
    }

    function toggleAll(rows: LookupSelectableRow[], on: boolean): void {
        const next = new Map(selected.value);
        for (const row of rows) {
            if (on) {
                next.set(row.key, row.studentId);
            } else {
                next.delete(row.key);
            }
        }
        selected.value = next;
    }

    function isSelected(key: number | string): boolean {
        return selected.value.has(key);
    }

    function clear(): void {
        selected.value = new Map();
    }

    const count = computed(() => selected.value.size);
    const studentIds = computed(() => [...new Set([...selected.value.values()])]);

    function sendToBatch(): void {
        const ids = studentIds.value;
        if (ids.length === 0) {
            return;
        }

        router.get(financeRoutes.batchStudio.dng(), { student_ids: ids }, { preserveScroll: false });
    }

    return { selected, toggle, toggleAll, isSelected, clear, count, studentIds, sendToBatch };
}
