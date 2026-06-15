import type { SemesterContext } from '@/types/finance';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFinanceSemester() {
    const page = usePage();
    const context = computed(() => (page.props.semester as SemesterContext | null | undefined) ?? null);

    const selectedId = computed(() => context.value?.selected_id ?? null);

    function labelFor(id: number | null | undefined): string {
        const resolvedId = id ?? context.value?.selected_id ?? null;
        if (resolvedId === null) {
            return 'Tất cả kỳ';
        }

        const option = context.value?.options.find((o) => o.id === resolvedId);
        if (!option) {
            return 'Chưa chọn kỳ';
        }

        const suffix = option.is_active ? ' · hiện tại' : '';

        return `${option.name} (${option.code})${suffix}`;
    }

    const selectedLabel = computed(() => labelFor(selectedId.value));

    return { context, selectedId, selectedLabel, labelFor };
}