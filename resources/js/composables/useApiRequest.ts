import { createFetch } from '@vueuse/core';

/**
 * Custom fetch instance configured for Laravel API requests
 * Automatically includes CSRF token and proper headers
 */
export const useApiRequest = createFetch({
    baseUrl: '',
    options: {
        beforeFetch({ options }) {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            options.headers = {
                ...options.headers,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            };

            return { options };
        },
        onFetchError({ error, data }) {
            console.error('API Request Error:', error);
            return { error, data };
        },
    },
});

/**
 * Convenience wrapper for common API operations
 */
export function useApi() {
    /**
     * POST request with JSON payload
     */
    const post = async <T = any>(url: string, data: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'POST',
        }).post(data).json<{
            success: boolean;
            message: string;
            data: T;
            error?: string;
        }>();
    };

    /**
     * PUT request with JSON payload
     */
    const put = async <T = any>(url: string, data: Record<string, any>) => {
        return useApiRequest(url, {
            method: 'PUT',
        }).put(data).json<{
            success: boolean;
            message: string;
            data: T;
            error?: string;
        }>();
    };

    /**
     * DELETE request
     */
    const del = async <T = any>(url: string) => {
        return useApiRequest(url, {
            method: 'DELETE',
        }).delete().json<{
            success: boolean;
            message: string;
            data: T;
            error?: string;
        }>();
    };

    /**
     * GET request
     */
    const get = async <T = any>(url: string, params?: Record<string, any>) => {
        const queryString = params ? '?' + new URLSearchParams(params).toString() : '';
        return useApiRequest(url + queryString, {
            method: 'GET',
        }).get().json<{
            success: boolean;
            message: string;
            data: T;
            error?: string;
        }>();
    };

    return {
        post,
        put,
        delete: del,
        get,
    };
}
