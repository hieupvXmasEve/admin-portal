import { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermission() {
    const page = usePage<SharedData>();

    // Computed để luôn đồng bộ khi props thay đổi (hot reload, chuyển trang, ...)
    const permissions = computed<string[]>(() => {
        return page.props.auth.permissions ?? [];
    });

    const can = (code: string) => {
        return permissions.value.includes(code);
    };

    return {
        permissions,
        can,
    };
}
