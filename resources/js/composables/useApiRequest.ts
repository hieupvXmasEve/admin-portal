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
        }).post(data).json<ApiResponse<T>>();
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
        }).put(data).json<ApiResponse<T>>();
    };

    /**
     * DELETE request
     * @param url - API endpoint URL
     * @returns Promise with standardized API response
     */
    const del = async <T = any>(url: string) => {
        return useApiRequest(url, {
            method: 'DELETE',
        }).delete().json<ApiResponse<T>>();
    };

    /**
     * GET request
     * @param url - API endpoint URL
     * @param params - Query parameters
     * @returns Promise with standardized API response
     */
    const get = async <T = any>(url: string, params?: Record<string, any>) => {
        const queryString = params ? '?' + new URLSearchParams(params).toString() : '';
        return useApiRequest(url + queryString, {
            method: 'GET',
        }).get().json<ApiResponse<T>>();
    };

    return {
        post,
        put,
        delete: del,
        get,
    };
}
