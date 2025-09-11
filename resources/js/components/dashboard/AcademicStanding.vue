<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { DonutChart } from '@/components/ui/chart-donut';
import { useApi } from '@/composables/useApiRequest';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface AcademicStandingItem {
    standing: string;
    label: string;
    count: number;
    percentage: number;
    avg_gpa: number;
    avg_cumulative_gpa: number;
    fill: string;
}

interface AcademicStandingData {
    total_students: number;
    standings: AcademicStandingItem[];
}

const api = useApi();
const dataRef = ref<AcademicStandingData | null>(null);
const loading = ref<boolean>(true);
const error = ref<string | null>(null);

const standingChartData = computed(() => {
    if (!dataRef.value?.standings) return [] as Array<{ standing: string; value: number; fill: string }>;
    return dataRef.value.standings.map((item) => ({ standing: item.label, value: item.count, fill: item.fill }));
});

const valueFormatter = (value: number) => value.toLocaleString();

const loadData = async (): Promise<void> => {
    try {
        const response = await api.get<AcademicStandingData>('/api/dashboard/academic-standing');
        dataRef.value = response.data.value?.data ?? null;
    } catch (err) {
        console.error('Failed to load academic standing:', err);
        error.value = 'Failed to load academic standing data';
        toast.error('Failed to load academic standing');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadData();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-lg">Academic Standing</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="border-primary h-6 w-6 animate-spin rounded-full border-b-2"></div>
                <span class="text-muted-foreground ml-3">Loading academic standing...</span>
            </div>
            <div v-else-if="error" class="py-8 text-center text-red-600">{{ error }}</div>
            <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="h-[300px]">
                        <DonutChart v-if="standingChartData.length > 0" :data="standingChartData" index="standing" category="value" :value-formatter="valueFormatter" :show-legend="false" class="h-full" />
                        <div v-else class="text-muted-foreground flex h-full items-center justify-center">No data available</div>
                    </div>
                </div>
                <div>
                    <div class="space-y-3">
                        <div v-for="standing in dataRef?.standings" :key="standing.standing" class="bg-muted/50 rounded-lg p-3">
                            <div class="mb-2 flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: standing.fill }"></div>
                                <div class="text-sm font-medium">{{ standing.label }}</div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span>Count:</span>
                                    <span class="font-medium">{{ standing.count.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Percentage:</span>
                                    <span class="font-medium">{{ standing.percentage }}%</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Avg GPA:</span>
                                    <span class="font-medium">{{ standing.avg_gpa }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
