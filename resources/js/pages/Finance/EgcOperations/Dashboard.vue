<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRightLeft, BarChart3, CheckCircle2, DollarSign, Play, RefreshCw, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

interface Semester {
    id: number;
    name: string;
}

interface KpiStats {
    total_egc_students: number;
    charged_count: number;
    blocks_with_results: number;
    eligible_retakes: number;
    discounts_applied: number;
}

defineProps<{
    stats: KpiStats;
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null };
}>();

const { selectedId, selectedLabel } = useFinanceSemester();

const semesterQuery = computed(() => (selectedId.value ? { semester_id: selectedId.value } : {}));
</script>

<template>
    <Head title="EGC Operations Dashboard" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Operations</h1>
                <p class="text-muted-foreground text-sm">EGC student finance management dashboard · {{ selectedLabel }}</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">EGC Students</CardTitle>
                    <BarChart3 class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_egc_students }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Charged</CardTitle>
                    <DollarSign class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.charged_count }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Eligible Retakes</CardTitle>
                    <RefreshCw class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.eligible_retakes }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Discounts Applied</CardTitle>
                    <CheckCircle2 class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.discounts_applied }}</div>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link :href="financeRoutes.batchStudio.charges({ fee_category: 'egc', ...semesterQuery })">
                <Card class="hover:bg-muted/50 cursor-pointer transition-colors">
                    <CardContent class="flex items-center gap-3 pt-6">
                        <Play class="h-8 w-8 text-blue-500" />
                        <div>
                            <div class="font-semibold">Sinh phí EGC</div>
                            <div class="text-muted-foreground text-sm">Create EGC block charges</div>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <Link :href="route('finance.egc.block-results.index', semesterQuery)">
                <Card class="hover:bg-muted/50 cursor-pointer transition-colors">
                    <CardContent class="flex items-center gap-3 pt-6">
                        <TrendingUp class="h-8 w-8 text-green-500" />
                        <div>
                            <div class="font-semibold">Block Results</div>
                            <div class="text-muted-foreground text-sm">Sync academic results</div>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <Link :href="route('finance.egc.retake-adjustments.index', semesterQuery)">
                <Card class="hover:bg-muted/50 cursor-pointer transition-colors">
                    <CardContent class="flex items-center gap-3 pt-6">
                        <RefreshCw class="h-8 w-8 text-orange-500" />
                        <div>
                            <div class="font-semibold">Retake Adjustments</div>
                            <div class="text-muted-foreground text-sm">Apply retake discounts</div>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <Link :href="route('finance.egc.carry-forward.index', semesterQuery)">
                <Card class="hover:bg-muted/50 cursor-pointer transition-colors">
                    <CardContent class="flex items-center gap-3 pt-6">
                        <ArrowRightLeft class="h-8 w-8 text-purple-500" />
                        <div>
                            <div class="font-semibold">Carry Forward</div>
                            <div class="text-muted-foreground text-sm">Release unused EGC balance</div>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>