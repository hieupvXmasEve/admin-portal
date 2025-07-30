import { onMounted, ref } from 'vue';
import { useApi } from './useApiRequest';

export interface FilterOption {
    id: number;
    name: string;
    code?: string;
}

export function useFilterOptions() {
    const { get } = useApi();

    // State
    const semesters = ref<FilterOption[]>([]);
    const campuses = ref<FilterOption[]>([]);
    const faculties = ref<string[]>([]);
    const departments = ref<string[]>([]);
    const loading = ref(false);

    // Methods
    const fetchSemesters = async () => {
        try {
            // TODO: Replace with actual API endpoint when available
            // For now, return mock data
            semesters.value = [
                { id: 1, name: 'Semester 1 2024', code: 'S1-2024' },
                { id: 2, name: 'Semester 2 2024', code: 'S2-2024' },
                { id: 3, name: 'Semester 1 2025', code: 'S1-2025' },
            ];
        } catch (error) {
            console.error('Failed to fetch semesters:', error);
        }
    };

    const fetchCampuses = async () => {
        try {
            // TODO: Replace with actual API endpoint when available
            // For now, return mock data
            campuses.value = [
                { id: 1, name: 'Hawthorn Campus', code: 'HAW' },
                { id: 2, name: 'Croydon Campus', code: 'CRO' },
                { id: 3, name: 'Wantirna Campus', code: 'WAN' },
            ];
        } catch (error) {
            console.error('Failed to fetch campuses:', error);
        }
    };

    const fetchFaculties = async () => {
        try {
            // TODO: Replace with actual API endpoint when available
            // For now, return mock data
            faculties.value = ['Faculty of Business and Law', 'Faculty of Health, Arts and Design', 'Faculty of Science, Engineering and Technology'];
        } catch (error) {
            console.error('Failed to fetch faculties:', error);
        }
    };

    const fetchDepartments = async () => {
        try {
            // TODO: Replace with actual API endpoint when available
            // For now, return mock data
            departments.value = [
                'Computer Science and Software Engineering',
                'Information Technology',
                'Business Administration',
                'Engineering',
                'Health Sciences',
                'Design',
            ];
        } catch (error) {
            console.error('Failed to fetch departments:', error);
        }
    };

    const fetchAllOptions = async () => {
        loading.value = true;
        try {
            await Promise.all([fetchSemesters(), fetchCampuses(), fetchFaculties(), fetchDepartments()]);
        } finally {
            loading.value = false;
        }
    };

    // Auto-fetch on mount
    onMounted(() => {
        fetchAllOptions();
    });

    return {
        // State
        semesters: semesters,
        campuses: campuses,
        faculties: faculties,
        departments: departments,
        loading: loading,

        // Methods
        fetchSemesters,
        fetchCampuses,
        fetchFaculties,
        fetchDepartments,
        fetchAllOptions,
    };
}
