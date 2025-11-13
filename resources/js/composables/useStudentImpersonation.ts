import { useApi } from '@/composables/useApiRequest';
import type { Student } from '@/types/models';
import { toast } from 'vue-sonner';

/**
 * Composable for student impersonation functionality
 * Allows admin users to log in as students for support purposes
 */
export function useStudentImpersonation() {
    const api = useApi();

    /**
     * Impersonate a student and open the student portal in a new tab
     * @param student - The student to impersonate
     */
    const loginAsStudent = async (student: Student) => {
        try {
            // Show confirmation dialog first
            if (!confirm(`Are you sure you want to log in as ${student.full_name}?\n\nThis will open the student portal in a new tab with their account.`)) {
                return;
            }
            const newTab: WindowProxy | null = window.open('about:blank');
            // Use the dedicated admin impersonation API endpoint
            const { data } = await api.post('/api/students/impersonate', {
                email: student.email, // Can also use student_id
                device_name: 'Admin Portal - Student Impersonation',
                purpose: 'support', // Track why we're impersonating
            });

            console.log('%c Impersonation response', 'color: red', data);

            if (data?.value?.success && newTab) {
                // Get the student portal URL from environment
                const studentPortalUrl = import.meta.env.VITE_APP_URL_FE || 'http://localhost:3000';

                // Open student portal in new tab with token as query parameter
                const portalUrl = `${studentPortalUrl}?access_token=${encodeURIComponent(data.value.data.token)}&redirect=dashboard`;

                // 👉 Update the stub tab with the real URL
                newTab.location.href = portalUrl;

                toast.success(`Successfully logged in as ${student.full_name}. Token expires in 2 hours.`);
            } else {
                toast.error(data?.value?.message || 'Failed to impersonate student');
            }
        } catch (error: any) {
            console.error('Student impersonation error:', error);
            let errorMessage = 'Failed to impersonate student';

            if (error.data?.message) {
                errorMessage = error.data.message;
            } else if (error.message) {
                errorMessage = error.message;
            }

            // Handle specific error cases
            if (error.response?.status === 403) {
                errorMessage = 'You do not have permission to impersonate students. Please contact your administrator.';
            } else if (error.response?.status === 404) {
                errorMessage = 'Student not found or not available for impersonation.';
            } else if (errorMessage.includes('inactive student')) {
                errorMessage = 'Cannot impersonate inactive student. Please check the student status.';
            } else if (errorMessage.includes('academic holds')) {
                errorMessage = 'Cannot impersonate student due to active academic holds.';
            }

            toast.error(errorMessage);
        }
    };

    return {
        loginAsStudent,
    };
}
