<script setup lang="ts">
import ModuleRecordView from '@/components/ModuleRecordView.vue';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables';
import { Head } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface SubUnitProgress {
    unit: Unit;
    grading_type: 'grade' | 'pass_fail';
    grade: number | null;
    letter_grade: string | null;
    status: string;
    academic_record_id: number | null;
}

interface Module {
    id: number;
    code: string;
    name: string;
    description: string | null;
    total_credits: number;
    grading_type: 'grade' | 'pass_fail';
}

interface ModuleProgress {
    module: Module;
    status: 'not_started' | 'in_progress' | 'passed' | 'failed';
    grade: number | null;
    completion: string;
    completed_count: number;
    total_count: number;
    sub_units: SubUnitProgress[];
}

const api = useApi();
const modules = ref<ModuleProgress[]>([]);
const loading = ref(true);

const fetchModuleProgress = async () => {
    loading.value = true;
    try {
        const { data } = await api.get('/api/module-progress');
        modules.value = data.data || [];
    } catch (error: any) {
        console.error('Error fetching module progress:', error);
        toast.error('Failed to load module progress', {
            description: error.response?.data?.message || 'An error occurred while loading your module progress.',
        });
    } finally {
        loading.value = false;
    }
};

const refresh = () => {
    fetchModuleProgress();
    toast.info('Refreshing module progress...');
};

onMounted(() => {
    fetchModuleProgress();
});
</script>

<template>
    <Head title="Module Progress" />

    <div class="container mx-auto py-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Module Progress</h1>
                <p class="text-muted-foreground mt-1">Track your academic progress organized by modules</p>
            </div>

            <Button variant="outline" size="sm" @click="refresh" :disabled="loading">
                <RefreshCw :class="{ 'animate-spin': loading }" class="mr-2 h-4 w-4" />
                Refresh
            </Button>
        </div>

        <ModuleRecordView :modules="modules" :loading="loading" />
    </div>
</template>
