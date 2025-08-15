import { useApi } from '@/composables/useApiRequest';
import type { SystemConfig } from '@/types/systemConfig';
import { computed, reactive, readonly, ref } from 'vue';

const systemConfig = reactive<SystemConfig>({
  app_name: '',
  logo_full: '/placeholder.svg',
  logo_text: '/placeholder.svg',
  copyright_text: '',
  country: ''
})

const isLoaded = ref(false)
const isLoading = ref(false)
const imageCacheBuster = ref(Date.now())

export function useSystemConfig() {
    const api = useApi();

    const loadConfig = async (): Promise<void> => {
        if (isLoaded.value || isLoading.value) {
            return;
        }

        try {
            isLoading.value = true;
            const response = await api.get<SystemConfig>('/api/system-config');

            if (response.data && response.data.value?.success) {
                Object.assign(systemConfig, response.data.value.data);
                isLoaded.value = true;
            } else {
                throw new Error(response.data?.value?.message || 'Failed to load system config');
            }
        } catch (error) {
            console.error('Failed to load system config:', error);

            // Set fallback values
            Object.assign(systemConfig, {
                app_name: 'SwinX',
                logo_full: '/storage/branding/logo-full.png',
                logo_text: '/storage/branding/logo-text.svg',
                copyright_text: '© 2025 Asia Vietnam University. All rights reserved.',
                country: 'Việt Nam',
            });
        } finally {
            isLoading.value = false;
        }
    };

    const getConfig = (): SystemConfig => {
        return systemConfig;
    };

    const get = (key: keyof SystemConfig, fallback: string = ''): string => {
        return systemConfig[key] || fallback;
    };
    const getLogoFull = computed(() => `${get('logo_full')}?v=${imageCacheBuster.value}`);
    const getLogoText = computed(() => `${get('logo_text')}?v=${imageCacheBuster.value}`);

    // Autoload config when composable is first used
    if (!isLoaded.value && !isLoading.value) {
        loadConfig();
    }

    return {
        systemConfig: readonly(systemConfig),
        isLoaded: readonly(isLoaded),
        isLoading: readonly(isLoading),
        loadConfig,
        getConfig,
        get,
        getLogoFull,
        getLogoText
    };
}
