import { useApi } from '@/composables/useApiRequest';
import { ref } from 'vue';

interface EmailTemplate {
    id: number;
    name: string;
    type: string;
    subject: string;
    html_content: string;
    text_content?: string;
    variables?: string[];
    is_active: boolean;
    version: number;
    description?: string;
    created_at: string;
    updated_at: string;
}

interface TemplateForm {
    name: string;
    type: string;
    subject: string;
    html_content: string;
    text_content?: string;
    description?: string;
    is_active: boolean;
}

interface PaginatedTemplates {
    data: EmailTemplate[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    prev_page_url?: string;
    next_page_url?: string;
}

export function useEmailTemplate() {
    const api = useApi();

    const templates = ref<PaginatedTemplates>({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    });

    const templateTypes = ref<Record<string, string>>({});
    const isLoading = ref(false);
    const isRefreshing = ref(false);

    const loadTemplateTypes = async () => {
        try {
            const response = await api.get('/api/email-templates/types');

            if (response.data.value?.success && response.data.value.data) {
                templateTypes.value = response.data.value.data;
            }
        } catch (error) {
            console.error('Failed to load template types:', error);
        }
    };

    const loadTemplates = async (filters: any = {is_active: 'all'}, page: number = 1) => {
        isLoading.value = true;
        isRefreshing.value = true;

        try {
            console.log('%c filters', 'color: red', filters);

            const params = new URLSearchParams();

            if (filters.type) params.append('type', filters.type);
            if (filters.is_active !== '') params.append('is_active', filters.is_active);
            if (filters.search) params.append('search', filters.search);
            params.append('page', page.toString());
            params.append('per_page', '15');

            const response = await api.get(`/api/email-templates?${params}`);

            if (response.data.value?.success && response.data.value.data) {
                templates.value = response.data.value.data;
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
            throw error;
        } finally {
            isLoading.value = false;
            isRefreshing.value = false;
        }
    };

    const createTemplate = async (data: TemplateForm) => {
        try {
            const response = await api.post<EmailTemplate>('/api/email-templates', data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to create template');
        } catch (error) {
            console.error('Failed to create template:', error);
            throw error;
        }
    };

    const updateTemplate = async (id: number, data: Partial<TemplateForm>) => {
        try {
            const response = await api.put<EmailTemplate>(`/api/email-templates/${id}`, data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to update template');
        } catch (error) {
            console.error('Failed to update template:', error);
            throw error;
        }
    };

    const deleteTemplate = async (id: number) => {
        try {
            const response = await api.delete(`/api/email-templates/${id}`);

            if (response.data.value?.success) {
                return true;
            }

            throw new Error(response.data.value?.message || 'Failed to delete template');
        } catch (error) {
            console.error('Failed to delete template:', error);
            throw error;
        }
    };

    const previewTemplate = async (id: number, variables: Record<string, string> = {}) => {
        try {
            const response = await api.post(`/api/email-templates/${id}/preview`, {
                variables,
            });

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to preview template');
        } catch (error) {
            console.error('Failed to preview template:', error);
            throw error;
        }
    };

    const createNewVersion = async (id: number, data: TemplateForm) => {
        try {
            const response = await api.post<EmailTemplate>(`/api/email-templates/${id}/version`, data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to create new version');
        } catch (error) {
            console.error('Failed to create new version:', error);
            throw error;
        }
    };

    const validateTemplate = async (content: string) => {
        try {
            const response = await api.post('/api/email-templates/validate', {
                content,
            });

            return response.data.value;
        } catch (error) {
            console.error('Failed to validate template:', error);
            throw error;
        }
    };

    const getTemplatesByType = async (type: string) => {
        try {
            const response = await api.get<EmailTemplate[]>(`/api/email-templates/type/${type}`);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to get templates by type');
        } catch (error) {
            console.error('Failed to get templates by type:', error);
            throw error;
        }
    };

    const getTemplate = async (id: number) => {
        try {
            const response = await api.get<EmailTemplate>(`/api/email-templates/${id}`);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to get template');
        } catch (error) {
            console.error('Failed to get template:', error);
            throw error;
        }
    };

    // Initialize template types on first use
    loadTemplateTypes();

    return {
        // State
        templates,
        templateTypes,
        isLoading,
        isRefreshing,

        // Actions
        loadTemplates,
        createTemplate,
        updateTemplate,
        deleteTemplate,
        previewTemplate,
        createNewVersion,
        validateTemplate,
        getTemplatesByType,
        getTemplate,
        loadTemplateTypes,
    };
}
