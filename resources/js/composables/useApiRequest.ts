import { createFetch } from '@vueuse/core';

/**
 * Custom fetch instance configured for Laravel API requests
 * Automatically includes CSRF token and proper headers
 */
const useApiRequest = createFetch({
    baseUrl: '',
    options: {
        credentials: 'include', // Include cookies for Laravel Sanctum session auth
        beforeFetch({ options }) {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            options.headers = {
                ...options.headers,
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token || '',
                'X-Requested-With': 'XMLHttpRequest', // Laravel SPA authentication requirement
            };

            // Ensure credentials are included for session-based auth
            options.credentials = 'include';

            return { options };
        },
        onFetchError({ error, data, response }) {
            console.error('API Request Error:', {
                error,
                data,
                status: response?.status,
                statusText: response?.statusText,
            });
            return { error, data };
        },
    },
});

/**
 * Standard Laravel API response structure
 */
export interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data: T;
    error?: string;
    errors?: string[];
}

/**
 * Convenience wrapper for common API operations
 * All methods return responses with consistent structure: { success, message, data }
 */
export function useApi() {
    /**
     * POST request with JSON payload
     * @param url - API endpoint URL
     * @param data - Request payload
     * @returns Promise with standardized API response
     */
    const post = async <T = any>(url: string, data: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'POST',
        })
            .post(data)
            .json<ApiResponse<T>>();
    };

    /**
     * PUT request with JSON payload
     * @param url - API endpoint URL
     * @param data - Request payload
     * @returns Promise with standardized API response
     */
    const put = async <T = any>(url: string, data: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'PUT',
        })
            .put(data)
            .json<ApiResponse<T>>();
    };

    /**
     * PATCH request with JSON payload
     * @param url - API endpoint URL
     * @param data - Request payload
     * @returns Promise with standardized API response
     */
    const patch = async <T = any>(url: string, data: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'PATCH',
        })
            .patch(data)
            .json<ApiResponse<T>>();
    };

    /**
     * DELETE request
     * @param url - API endpoint URL
     * @param data - Optional request payload
     * @returns Promise with standardized API response
     */
    const del = async <T = any>(url: string, data?: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'DELETE',
        })
            .delete(data)
            .json<ApiResponse<T>>();
    };

    /**
     * GET request
     * @param url - API endpoint URL
     * @param params - Query parameters
     * @param options - Additional options including AbortController
     * @returns Promise with standardized API response
     */
    const get = async <T = any>(url: string, params?: Record<string, any>, options?: { signal?: AbortSignal }) => {
        let queryString = '';
        if (params) {
            // Handle nested params object (e.g. { params: { key: 'value' } })
            const actualParams = params.params || params;

            // Convert parameters to query string, handling different data types
            const searchParams = new URLSearchParams();
            for (const [key, value] of Object.entries(actualParams)) {
                if (value !== null && value !== undefined) {
                    searchParams.append(key, String(value));
                }
            }
            queryString = searchParams.toString() ? '?' + searchParams.toString() : '';
        }

        return useApiRequest(url + queryString, {
            method: 'GET',
            signal: options?.signal,
        })
            .get()
            .json<ApiResponse<T>>();
    };

    return {
        post,
        put,
        patch,
        delete: del,
        get,
    };
}
