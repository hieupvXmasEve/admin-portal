<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { BarChart } from '@/components/ui/chart-bar';
import { LineChart } from '@/components/ui/chart-line';
import { useApi } from '@/composables/useApiRequest';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface GraduationYearlyRate {
    year: number;
    total_admitted: number;
    total_graduated: number;
    graduation_rate: number;
}

interface GraduationProgramRate {
    program_id: number;
    program_name: string;
    program_code: string;
    total_students: number;
    graduated_students: number;
    graduation_rate: number;
    fill: string;
}

interface GraduationRateData {
    yearly_rates: GraduationYearlyRate[];
    program_rates: GraduationProgramRate[];
}

const api = useApi();
const dataRef = ref<GraduationRateData | null>(null);
const loading = ref<boolean>(true);
const error = ref<string | null>(null);

const graduationYearlyChartData = computed(() => {
    if (!dataRef.value?.yearly_rates) return [] as Array<{ year: string; rate: number }>;
    return dataRef.value.yearly_rates.map((item) => ({ year: item.year.toString(), rate: item.graduation_rate }));
});

const graduationProgramChartData = computed(() => {
    if (!dataRef.value?.program_rates) return [] as Array<{ program: string; value: number; fill: string }>;
    return dataRef.value.program_rates.map((item) => ({ program: item.program_code, value: item.graduation_rate, fill: item.fill }));
});

const percentageFormatter = (value: number) => `${value}%`;

const loadData = async (): Promise<void> => {
    try {
        const response = await api.get<GraduationRateData>('/api/dashboard/graduation-rate');
        dataRef.value = response.data.value?.data ?? null;
    } catch (err) {
        console.error('Failed to load graduation rate:', err);
        error.value = 'Failed to load graduation rate data';
        toast.error('Failed to load graduation rate');
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
            <CardTitle class="text-lg">Graduation Rate</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="border-primary h-6 w-6 animate-spin rounded-full border-b-2"></div>
                <span class="text-muted-foreground ml-3">Loading graduation rate...</span>
            </div>
            <div v-else-if="error" class="py-8 text-center text-red-600">{{ error }}</div>
            <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Yearly Graduation Rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[300px]">
                            <LineChart v-if="graduationYearlyChartData.length > 0" :data="graduationYearlyChartData" index="year" :categories="['rate']" :value-formatter="percentageFormatter" class="h-full" />
                            <div v-else class="text-muted-foreground flex h-full items-center justify-center">No data available</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Program Graduation Rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[300px]">
                            <BarChart v-if="graduationProgramChartData.length > 0" :data="graduationProgramChartData" index="program" :categories="['value']" :value-formatter="percentageFormatter" class="h-full" />
                            <div v-else class="text-muted-foreground flex h-full items-center justify-center">No data available</div>
                        </div>
                    </CardContent>
                </Card>
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-lg">Graduation Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="program in dataRef?.program_rates" :key="program.program_id" class="bg-muted/50 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ program.program_name }} ({{ program.program_code }})</div>
                                        <div class="text-muted-foreground text-sm">{{ program.total_students }} total students</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold" :class="program.graduation_rate >= 80 ? 'text-green-600' : program.graduation_rate >= 60 ? 'text-yellow-600' : 'text-red-600'">{{ program.graduation_rate }}%</div>
                                        <div class="text-muted-foreground text-sm">{{ program.graduated_students }} graduated</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CardContent>
    </Card>
</template>
