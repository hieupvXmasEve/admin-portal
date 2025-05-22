import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermission() {
    const page = usePage();

    // Computed để luôn đồng bộ khi props thay đổi (hot reload, chuyển trang, ...)
    const permissions = computed<string[]>(() => {
        return (page.props.permissions as string[]) ?? [];
    });

    const can = (code: string) => {
        return permissions.value.includes(code);
    };

    return {
        permissions,
        can,
    };
}
