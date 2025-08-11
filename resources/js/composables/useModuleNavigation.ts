import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface UseModuleNavigationOptions {
    /**
     * Base route name for the module index (e.g., 'units.index')
     */
    moduleIndexRoute: string;
}

export function useModuleNavigation(options: UseModuleNavigationOptions) {
    /**
     * Get current URL to use as return parameter
     */
    const getCurrentUrl = (): string => {
        return window.location.href;
    };

    /**
     * Get return URL from query params
     */
    const getReturnUrl = (): string | null => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('return');
    };

    /**
     * Build URL with return parameter
     * @param targetUrl - The destination URL
     * @param returnUrl - Optional return URL (defaults to current URL)
     */
    const buildUrlWithReturn = (targetUrl: string, returnUrl?: string): string => {
        try {
            // Validate inputs
            if (!targetUrl || typeof targetUrl !== 'string') {
                throw new Error('buildUrlWithReturn: targetUrl is required and must be a string');
            }

            const url = new URL(targetUrl, window.location.origin);
            const returnParam = returnUrl || getCurrentUrl();

            if (returnParam) {
                url.searchParams.set('return', returnParam);
            }

            //   console.log('buildUrlWithReturn - targetUrl:', targetUrl);
            //   console.log('buildUrlWithReturn - returnParam:', returnParam);
            //   console.log('buildUrlWithReturn - final URL:', url.toString());
            return url.toString();
        } catch (error) {
            console.error('buildUrlWithReturn error:', error);
            // Fallback to original URL if URL building fails
            return targetUrl;
        }
    };

    /**
     * Build route URL from route name and parameters
     * Simple URL building for common patterns
     * @param routeName - Target route name
     * @param params - Route parameters
     */
    const buildRouteUrl = (routeName: string, params: Record<string, any> = {}): string => {
        // console.log('buildRouteUrl - routeName:', routeName);
        // console.log('buildRouteUrl - params:', params);

        // Validate inputs
        if (!routeName || typeof routeName !== 'string') {
            throw new Error('buildRouteUrl: routeName is required and must be a string');
        }

        // Extract module name and action from route name
        const routeParts = routeName.split('.');
        const [module, action] = routeParts;

        if (!module) {
            throw new Error(`buildRouteUrl: invalid route name '${routeName}'`);
        }

        // console.log('buildRouteUrl - module:', module, 'action:', action);

        // Build base URL
        let baseUrl = `/${module}`;
        let finalUrl = '';

        // Handle different actions
        switch (action) {
            case 'index':
                finalUrl = baseUrl;
                break;
            case 'create':
                finalUrl = `${baseUrl}/create`;
                break;
            case 'show':
                const showId = params.unit || params[module] || params.id;
                if (!showId) {
                    throw new Error(`buildRouteUrl: missing ID parameter for show route. Got params: ${JSON.stringify(params)}`);
                }
                finalUrl = `${baseUrl}/${showId}`;
                break;
            case 'edit':
                const editId = params.unit || params[module] || params.id;
                if (!editId) {
                    throw new Error(`buildRouteUrl: missing ID parameter for edit route. Got params: ${JSON.stringify(params)}`);
                }
                finalUrl = `${baseUrl}/${editId}/edit`;
                break;
            case 'import':
                finalUrl = `${baseUrl}/import`;
                break;
            default:
                // For other actions, try to build URL
                let url = baseUrl;
                if (action) {
                    url += `/${action}`;
                }
                // Replace parameters
                for (const [key, value] of Object.entries(params)) {
                    // Skip null/undefined values
                    if (value == null) {
                        console.warn(`buildRouteUrl: skipping null/undefined parameter '${key}'`);
                        continue;
                    }

                    if (url.includes(`{${key}}`)) {
                        url = url.replace(`{${key}}`, String(value));
                    } else if (key === module || key === 'id') {
                        // Add ID parameter for detail routes
                        url += `/${value}`;
                    }
                }
                finalUrl = url;
                break;
        }

        // console.log('buildRouteUrl - final URL:', finalUrl);
        return finalUrl;
    };

    /**
     * Navigate back to return URL or module index
     */
    const navigateBack = () => {
        const returnUrl = getReturnUrl();

        if (returnUrl) {
            // Navigate to return URL
            router.visit(returnUrl, {
                preserveScroll: true,
                preserveState: true,
            });
        } else {
            // Fallback to module index
            const indexUrl = buildRouteUrl(options.moduleIndexRoute);
            router.visit(indexUrl, {
                preserveScroll: true,
                preserveState: true,
            });
        }
    };

    /**
     * Navigate to a route with return parameter preserved
     * @param routeName - Target route name
     * @param params - Route parameters
     * @param returnUrl - Optional return URL (defaults to current URL)
     */
    const navigateWithReturn = (routeName: string, params: Record<string, any> = {}, returnUrl?: string) => {
        const targetUrl = buildRouteUrl(routeName, params);
        const urlWithReturn = buildUrlWithReturn(targetUrl, returnUrl);

        router.visit(urlWithReturn, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    /**
     * Get URL for linking to module routes with return parameter
     * @param routeName - Target route name
     * @param params - Route parameters
     */
    const getLinkUrlWithReturn = (routeName: string, params: Record<string, any> = {}): string => {
        const targetUrl = buildRouteUrl(routeName, params);
        return buildUrlWithReturn(targetUrl);
    };

    /**
     * Check if current route has return parameter
     */
    const hasReturnParam = computed(() => {
        return getReturnUrl() !== null;
    });

    /**
     * Pass return parameter to next navigation if it exists in current URL
     * @param targetUrl - Target URL to append return param to
     */
    const appendReturnParamIfExists = (targetUrl: string): string => {
        const returnUrl = getReturnUrl();
        if (returnUrl) {
            const url = new URL(targetUrl, window.location.origin);
            url.searchParams.set('return', returnUrl);
            return url.toString();
        }
        return targetUrl;
    };

    return {
        getCurrentUrl,
        getReturnUrl,
        buildUrlWithReturn,
        navigateWithReturn,
        navigateBack,
        getLinkUrlWithReturn,
        appendReturnParamIfExists,
        hasReturnParam,
    };
}
