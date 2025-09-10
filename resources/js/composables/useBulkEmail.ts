import { useApi } from '@/composables/useApiRequest';
import { ref } from 'vue';

interface EmailTemplate {
    id: number;
    name: string;
    type: string;
    subject: string;
    html_content: string;
}

interface UserRole {
    id: number;
    name: string;
    users_count?: number;
}

interface Campus {
    id: number;
    name: string;
    users_count?: number;
}

interface EmailLog {
    id: number;
    recipient: string;
    subject: string;
    status: string;
    sent_at?: string;
    created_at: string;
    template?: EmailTemplate;
}

interface PaginatedEmailLogs {
    data: EmailLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    prev_page_url?: string;
    next_page_url?: string;
}

interface BulkEmailData {
    recipients: string[];
    subject: string;
    content: string;
    template_id?: number;
    template_variables?: Record<string, string>;
    template_variables_per_recipient?: Record<string, Record<string, string>>;
    attachments?: File[];
    chunk_size?: number;
}

export function useBulkEmail() {
    const api = useApi();

    // Helper for FormData requests
    const postFormData = async (url: string, formData: FormData) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': token || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json();
        return { data: { value: data } }; // Match the structure expected by useApi
    };

    const emailTemplates = ref<EmailTemplate[]>([]);
    const userRoles = ref<UserRole[]>([]);
    const campuses = ref<Campus[]>([]);
    const emailLogs = ref<PaginatedEmailLogs>({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    });

    const isLoading = ref(false);
    const isSubmitting = ref(false);

    const loadEmailTemplates = async () => {
        try {
            const response = await api.get('/api/email-templates', { is_active: 'all', per_page: 100 });

            if (response.data.value?.success && response.data.value.data) {
                emailTemplates.value = response.data.value.data.data || response.data.value.data;
            }
        } catch (error) {
            console.error('Failed to load email templates:', error);
        }
    };

    const loadUserGroups = async () => {
        try {
            // Load user roles
            const rolesResponse = await api.get('/api/emails/user-roles');
            if (rolesResponse.data.value?.success && rolesResponse.data.value.data) {
                userRoles.value = rolesResponse.data.value.data;
            }

            // Load campuses
            const campusesResponse = await api.get('/api/emails/campuses');
            if (campusesResponse.data.value?.success && campusesResponse.data.value.data) {
                campuses.value = campusesResponse.data.value.data;
            }
        } catch (error) {
            console.error('Failed to load user groups:', error);
        }
    };

    const sendBulkEmail = async (data: BulkEmailData) => {
        isSubmitting.value = true;

        try {
            const formData = new FormData();

            // Add recipients as JSON
            // formData.append('recipients', JSON.stringify(data.recipients));
            data.recipients.forEach((email, index) => {
                formData.append(`recipients[${index}]`, email);
            });
            formData.append('subject', data.subject);
            formData.append('content', data.content);

            if (data.template_id) {
                formData.append('template_id', data.template_id.toString());
            }

            if (data.chunk_size) {
                formData.append('chunk_size', data.chunk_size.toString());
            }

            // Add template variables (global)
            if (data.template_variables) {
                Object.entries(data.template_variables).forEach(([key, value]) => {
                    formData.append(`template_variables[${key}]`, value);
                });
            }

            // Add per-recipient template variables
            if (data.template_variables_per_recipient) {
                Object.entries(data.template_variables_per_recipient).forEach(([email, vars]) => {
                    Object.entries(vars).forEach(([key, value]) => {
                        formData.append(`template_variables_per_recipient[${email}][${key}]`, value);
                    });
                });
            }

            // Add attachments
            if (data.attachments) {
                data.attachments.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });
            }

            const response = await postFormData('/api/emails/send-bulk', formData);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to send bulk email');
        } catch (error) {
            console.error('Failed to send bulk email:', error);
            throw error;
        } finally {
            isSubmitting.value = false;
        }
    };

    const sendSingleEmail = async (data: { recipient: string; subject: string; content: string; template_id?: number; attachments?: File[] }) => {
        try {
            const formData = new FormData();

            formData.append('recipient', data.recipient);
            formData.append('subject', data.subject);
            formData.append('content', data.content);

            if (data.template_id) {
                formData.append('template_id', data.template_id.toString());
            }

            // Add attachments
            if (data.attachments) {
                data.attachments.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });
            }

            const response = await postFormData('/api/emails/send', formData);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to send email');
        } catch (error) {
            console.error('Failed to send email:', error);
            throw error;
        }
    };

    const loadEmailLogs = async (filters: any = {}, page: number = 1) => {
        isLoading.value = true;

        try {
            const params = new URLSearchParams();

            if (filters.status) params.append('status', filters.status);
            if (filters.startDate) params.append('start_date', filters.startDate);
            if (filters.endDate) params.append('end_date', filters.endDate);
            if (filters.search) params.append('recipient', filters.search);
            params.append('page', page.toString());
            params.append('per_page', '15');

            const response = await api.get(`/api/emails/logs?${params}`);

            if (response.data.value?.success && response.data.value.data) {
                emailLogs.value = response.data.value.data;
            }
        } catch (error) {
            console.error('Failed to load email logs:', error);
            throw error;
        } finally {
            isLoading.value = false;
        }
    };

    const retryEmail = async (emailLogId: number) => {
        try {
            const response = await api.post(`/api/emails/logs/${emailLogId}/retry`, {});

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to retry email');
        } catch (error) {
            console.error('Failed to retry email:', error);
            throw error;
        }
    };

    const getEmailStatistics = async (startDate?: string, endDate?: string) => {
        try {
            const params = new URLSearchParams();

            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);

            const response = await api.get(`/api/emails/statistics?${params}`);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to get email statistics');
        } catch (error) {
            console.error('Failed to get email statistics:', error);
            throw error;
        }
    };

    const scheduleReminder = async (data: { type: string; recipients: string[]; schedule_time: string; data?: any }) => {
        try {
            const response = await api.post('/api/emails/schedule-reminder', data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to schedule reminder');
        } catch (error) {
            console.error('Failed to schedule reminder:', error);
            throw error;
        }
    };

    const sendNotification = async (data: { event_type: string; recipients: string[]; data?: any; check_preferences?: boolean }) => {
        try {
            const response = await api.post('/api/emails/send-notification', data);

            if (response.data.value?.success && response.data.value.data) {
                return response.data.value.data;
            }

            throw new Error(response.data.value?.message || 'Failed to send notification');
        } catch (error) {
            console.error('Failed to send notification:', error);
            throw error;
        }
    };

    return {
        // State
        emailTemplates,
        userRoles,
        campuses,
        emailLogs,
        isLoading,
        isSubmitting,

        // Actions
        loadEmailTemplates,
        loadUserGroups,
        sendBulkEmail,
        sendSingleEmail,
        loadEmailLogs,
        retryEmail,
        getEmailStatistics,
        scheduleReminder,
        sendNotification,
    };
}
