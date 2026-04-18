import { useApi } from '@/composables/useApiRequest';
import { ref } from 'vue';

interface EmailConfiguration {
    id: number;
    campus_id: number | null;
    campus?: { id: number; name: string; code: string } | null;
    name: string;
    host: string;
    port: number;
    username?: string;
    encryption: string;
    from_address: string;
    from_name: string;
    is_active: boolean;
    daily_limit: number;
    rate_limit: number;
    last_tested_at?: string;
    test_result?: string;
}

interface Statistics {
    total_configurations: number;
    active_configurations: number;
    tested_configurations: number;
    successful_tests: number;
    test_success_rate: number;
}

interface ConfigurationForm {
    campus_id: number | null;
    name: string;
    host: string;
    port: number;
    username: string;
    password: string;
    encryption: string;
    from_address: string;
    from_name: string;
    daily_limit: number;
    rate_limit: number;
    is_active: boolean;
}

export function useSmtpConfiguration() {
    const api = useApi();

    const configurations = ref<EmailConfiguration[]>([]);
    const statistics = ref<Statistics>({
        total_configurations: 0,
        active_configurations: 0,
        tested_configurations: 0,
        successful_tests: 0,
        test_success_rate: 0,
    });
    const perCampusStats = ref<Record<string, { total: number; active: number }>>({});

    const isLoading = ref(false);
    const isRefreshing = ref(false);
    const testingConfigs = ref(new Set<number>());
    const activatingConfigs = ref(new Set<number>());

    const loadConfigurations = async () => {
        isLoading.value = true;
        isRefreshing.value = true;

        try {
            const response = await api.get('/api/email-configurations');

            const raw = response.data.value as any;
            if (raw?.success) {
                configurations.value = Array.isArray(raw.data) ? raw.data : [];
                if (raw.statistics) {
                    statistics.value = raw.statistics;
                }
                if (raw.per_campus_stats) {
                    perCampusStats.value = raw.per_campus_stats;
                }
            }
        } catch (error) {
            console.error('Failed to load configurations:', error);
            throw error;
        } finally {
            isLoading.value = false;
            isRefreshing.value = false;
        }
    };

    const createConfiguration = async (data: ConfigurationForm) => {
        try {
            const response = await api.post<EmailConfiguration>('/api/email-configurations', data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to create configuration');
        } catch (error) {
            console.error('Failed to create configuration:', error);
            throw error;
        }
    };

    const updateConfiguration = async (id: number, data: Partial<ConfigurationForm>) => {
        try {
            const response = await api.put<EmailConfiguration>(`/api/email-configurations/${id}`, data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to update configuration');
        } catch (error) {
            console.error('Failed to update configuration:', error);
            throw error;
        }
    };

    const deleteConfiguration = async (id: number) => {
        try {
            const response = await api.delete(`/api/email-configurations/${id}`);

            if (response.data.value?.success) {
                // Remove from local state
                configurations.value = configurations.value.filter((config) => config.id !== id);
                return true;
            }

            throw new Error(response.data.value?.message || 'Failed to delete configuration');
        } catch (error) {
            console.error('Failed to delete configuration:', error);
            throw error;
        }
    };

    const testConnection = async (configuration: EmailConfiguration) => {
        testingConfigs.value.add(configuration.id);

        try {
            const response = await api.post(`/api/email-configurations/${configuration.id}/test`, {});

            // Update the configuration in local state
            const index = configurations.value.findIndex((c) => c.id === configuration.id);
            if (index !== -1) {
                configurations.value[index] = {
                    ...configurations.value[index],
                    last_tested_at: new Date().toISOString(),
                    test_result: response.data.value?.success ? 'success' : 'failed',
                };
            }

            return response.data.value;
        } catch (error) {
            console.error('Failed to test connection:', error);

            // Update the configuration with failed status
            const index = configurations.value.findIndex((c) => c.id === configuration.id);
            if (index !== -1) {
                configurations.value[index] = {
                    ...configurations.value[index],
                    last_tested_at: new Date().toISOString(),
                    test_result: 'failed',
                };
            }

            throw error;
        } finally {
            testingConfigs.value.delete(configuration.id);
        }
    };

    const testConnectionWithData = async (data: ConfigurationForm) => {
        try {
            const response = await api.post('/api/email-configurations/test-data', data);
            return response.data.value;
        } catch (error) {
            console.error('Failed to test connection with data:', error);
            throw error;
        }
    };

    const setActiveConfiguration = async (id: number) => {
        activatingConfigs.value.add(id);

        try {
            const response = await api.post<EmailConfiguration>(`/api/email-configurations/${id}/activate`, {});

            if (response.data.value?.success) {
                const activated = configurations.value.find((c) => c.id === id);
                // Only deactivate configs in the same campus scope
                configurations.value = configurations.value.map((config) => ({
                    ...config,
                    is_active: config.id === id
                        ? true
                        : config.campus_id === (activated?.campus_id ?? null)
                            ? false
                            : config.is_active,
                }));

                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to activate configuration');
        } catch (error) {
            console.error('Failed to activate configuration:', error);
            throw error;
        } finally {
            activatingConfigs.value.delete(id);
        }
    };

    const getConfiguration = async (id: number) => {
        try {
            const response = await api.get<EmailConfiguration>(`/api/email-configurations/${id}`);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to get configuration');
        } catch (error) {
            console.error('Failed to get configuration:', error);
            throw error;
        }
    };

    return {
        // State
        configurations,
        statistics,
        perCampusStats,
        isLoading,
        isRefreshing,
        testingConfigs,
        activatingConfigs,

        // Actions
        loadConfigurations,
        createConfiguration,
        updateConfiguration,
        deleteConfiguration,
        testConnection,
        testConnectionWithData,
        setActiveConfiguration,
        getConfiguration,
    };
}
