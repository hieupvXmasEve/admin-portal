import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage<PageProps>();

    const permissions = computed(() => {
        const auth = page.props.auth;
        return auth?.permissions || [];
    });

    const currentCampusId = computed(() => {
        const auth = page.props.auth;
        return auth?.current_campus_id || null;
    });

    // Kiểm tra một quyền
    const can = (permission: string) => {
        return permissions.value.includes(permission);
    };

    // Kiểm tra nhiều quyền (OR logic)
    const canAny = (permissionList: string[]) => {
        return permissionList.some((permission) => can(permission));
    };

    // Kiểm tra nhiều quyền (AND logic)
    const canAll = (permissionList: string[]) => {
        return permissionList.every((permission) => can(permission));
    };

    return {
        permissions,
        currentCampusId,
        can,
        canAny,
        canAll,
    };
}
