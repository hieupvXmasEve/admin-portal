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

    // Filter menu items based on user permissions
    const filterMenuItems = <T extends { requiredPermissions?: string[]; children?: T[] }>(items: T[]): T[] => {
        return items.filter(item => {
            // Filter children recursively if they exist
            if (item.children) {
                item.children = filterMenuItems(item.children);
            }
            
            // For items with children, only show if they have at least one visible child
            if (item.children) {
                return item.children.length > 0;
            }
            
            // For items without children, check permissions
            if (!item.requiredPermissions || item.requiredPermissions.length === 0) {
                return true;
            }
            
            // Check if user has any of the required permissions
            return canAny(item.requiredPermissions);
        });
    };

    return {
        permissions,
        currentCampusId,
        can,
        canAny,
        canAll,
        filterMenuItems,
    };
}
