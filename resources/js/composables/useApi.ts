import { ref } from 'vue';

interface ApiOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    headers?: Record<string, string>;
    body?: any;
}

interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data: T;
}

export function useApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    const call = async <T = any>(url: string, options: ApiOptions = {}): Promise<ApiResponse<T> | null> => {
        loading.value = true;
        error.value = null;

        try {
            const { method = 'GET', headers = {}, body } = options;

            const config: RequestInit = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    ...headers,
                },
            };

            if (body && method !== 'GET') {
                config.body = JSON.stringify(body);
            }

            const response = await fetch(url, config);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data: ApiResponse<T> = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
            error.value = errorMessage;
            console.error('API call failed:', errorMessage);
            return null;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        call,
    };
}
