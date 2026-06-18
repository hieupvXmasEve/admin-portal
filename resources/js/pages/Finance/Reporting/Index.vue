<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import AppLayout from '@/layouts/AppLayout.vue';
import CollectionProgress from '@/pages/Finance/Reporting/CollectionProgress.vue';
import FeeMonitor from '@/pages/Finance/Reporting/FeeMonitor.vue';
import { financeRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, BarChart3, Clock, Link2, ReceiptText } from 'lucide-vue-next';
import { computed } from 'vue';

interface ReportingView {
    key: 'fee-monitor' | 'collection-progress' | 'dng-lifecycle';
    label: string;
    description: string;
    status: 'planned' | 'implemented';
    obeys_semester: boolean;
}

const props = defineProps<{
    active_view: ReportingView['key'];
    views: ReportingView[];
    computed_at: string;
    actions: { export_enabled: boolean };
    fee_monitor?: Record<string, unknown>;
    collection_progress?: Record<string, unknown>;
}>();

const { selectedLabel } = useFinanceSemester();

const icons = {
    'fee-monitor': ReceiptText,
    'collection-progress': BarChart3,
    'dng-lifecycle': Link2,
};

const activeView = computed(() => props.views.find((view) => view.key === props.active_view) ?? props.views[0]);

const viewPropMap: Record<string, string> = {
    'fee-monitor': 'fee_monitor',
    'collection-progress': 'collection_progress',
};

const updateView = (value: string | number): void => {
    const view = String(value);
    const baseOnly = ['active_view', 'views', 'computed_at', 'actions'];
    const only = viewPropMap[view] ? [...baseOnly, viewPropMap[view]] : baseOnly;

    router.get(
        financeRoutes.reporting.index({ view }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only,
        },
    );
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Finance Reporting" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Finance Office</p>
                <h1 class="text-2xl font-semibold tracking-tight">Finance Reporting</h1>
                <p class="text-muted-foreground max-w-3xl text-sm">Monitoring shell for fee completeness, collection progress, and DNG/payment lifecycle. Runtime metrics and worklists are implemented by the next reporting lens stories.</p>
            </div>

            <div class="grid gap-2 text-sm sm:grid-cols-2 lg:min-w-96">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Semester context</CardDescription>
                        <CardTitle class="text-base">{{ selectedLabel }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Computed at</CardDescription>
                        <CardTitle class="text-base">{{ new Date(props.computed_at).toLocaleString() }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>
        </div>

        <Tabs :model-value="props.active_view" @update:model-value="updateView">
            <TabsList class="grid w-full grid-cols-1 sm:grid-cols-3">
                <TabsTrigger v-for="view in props.views" :key="view.key" :value="view.key">
                    <component :is="icons[view.key]" class="size-4" />
                    {{ view.label }}
                </TabsTrigger>
            </TabsList>

            <TabsContent v-for="view in props.views" :key="view.key" :value="view.key" class="mt-4">
                <FeeMonitor v-if="view.key === 'fee-monitor' && props.fee_monitor" :fee_monitor="props.fee_monitor as any" />

                <CollectionProgress v-else-if="view.key === 'collection-progress' && props.collection_progress" :collection_progress="props.collection_progress as any" />

                <Card v-else>
                    <CardHeader>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <component :is="icons[view.key]" class="text-primary size-5" />
                                    <CardTitle>{{ view.label }}</CardTitle>
                                </div>
                                <CardDescription>{{ view.description }}</CardDescription>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Badge variant="outline">{{ view.obeys_semester ? 'Semester-bound' : 'Cross-semester queue' }}</Badge>
                                <Badge :variant="view.status === 'implemented' ? 'default' : 'secondary'">
                                    {{ view.status === 'implemented' ? 'Live lens' : 'Shell only' }}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="border-border bg-muted/30 flex min-h-44 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
                            <AlertCircle class="text-muted-foreground mb-3 size-6" />
                            <h2 class="text-base font-semibold">{{ activeView?.key === view.key ? 'Ready for lens implementation' : 'Lens placeholder' }}</h2>
                            <p class="text-muted-foreground mt-1 max-w-xl text-sm">
                                This shell keeps navigation, campus scope, semester context, and freshness behavior stable. The data query, filters, drilldowns, and statistics for this lens stay in its dedicated implementation story.
                            </p>
                            <p v-if="!props.actions.export_enabled" class="text-muted-foreground mt-4 inline-flex items-center gap-1.5 text-xs">
                                <Clock class="size-3.5" />
                                Export is intentionally deferred from this reporting page.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
