import type { NavGroup, NavItem, PageProps } from '@/types';
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
    const filterMenuItems = (items: NavItem[]): NavItem[] => {
        return items.reduce<NavItem[]>((visibleItems, item) => {
            if (item.children) {
                const children = filterMenuItems(item.children);

                if (children.length > 0) {
                    visibleItems.push({ ...item, children });
                }

                return visibleItems;
            }

            if (!item.requiredPermissions || item.requiredPermissions.length === 0 || canAny(item.requiredPermissions)) {
                visibleItems.push({ ...item });
            }

            return visibleItems;
        }, []);
    };

    const filterMenuGroups = (groups: NavGroup[]): NavGroup[] => {
        return groups
            .map((group) => ({
                ...group,
                items: filterMenuItems(group.items),
            }))
            .filter((group) => group.items.length > 0);
    };

    return {
        permissions,
        currentCampusId,
        can,
        canAny,
        canAll,
        filterMenuItems,
        filterMenuGroups,
    };
}
