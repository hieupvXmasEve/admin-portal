import type { SharedData } from '@/types';
import type { SystemConfig } from '@/types/systemConfig';
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Reads the branding projection delivered with every Inertia response.
 *
 * Branding is intentionally not fetched or cached in the browser: the server
 * owns the projection and refreshes it after configuration mutations.
 */
export function useSystemConfig() {
    const page = usePage<SharedData>();
    const systemConfig = computed<SystemConfig>(() => page.props.system_config);

    watch(
        systemConfig,
        (config) => {
            document.documentElement.dataset.appName = config.app_name;
        },
        { immediate: true },
    );

    return { systemConfig };
}
