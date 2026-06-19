<script setup lang="ts">
import MoneyFlowGraph from '@/components/finance/audit/MoneyFlowGraph.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency, formatDate } from '@/utils/format';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Copy, Download, ExternalLink, RefreshCw, Search, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface ResolutionMatch {
    type: string;
    id: number;
    label: string;
    sublabel: string;
}
interface Resolution {
    status: 'empty' | 'single' | 'ambiguous';
    target: { type: string; id: number } | null;
    matches: ResolutionMatch[];
}
interface GraphNode {
    key: string;
    type: string;
    id: number;
    label: string;
    status: string | null;
    amount: number | null;
    date: string | null;
}
interface GraphEdge {
    from: string;
    to: string;
    kind: string;
    amount: number | null;
}
interface DerivedBalanceRow {
    invoice_id: number;
    invoice_number: string;
    cached_total_amount: number;
    cached_paid_amount: number;
    derived_net: number;
    derived_paid: number;
    drift: boolean;
}
interface Graph {
    subject: { student_code: string | null; full_name: string | null } | null;
    student_id: number | null;
    nodes: GraphNode[];
    edges: GraphEdge[];
    derived_balance: DerivedBalanceRow[];
}
interface TimelineEvent {
    at: string | null;
    type: string;
    signed_amount: number;
    label: string;
    refs: Record<string, number>;
}
interface Warning {
    code: string;
    severity: string;
    label: string;
    kind: 'invariant' | 'invariant_error' | 'cache_drift';
    sample_ids: number[];
}

const props = defineProps<{
    filters: {
        q: string | null;
        target_type: string | null;
        target_id: number | null;
        semester_id: number | null;
        billing_cycle_id: number | null;
        finding_code?: string | null;
    };
    resolution: Resolution;
    links: { source: { url: string; label: string } | null };
    allowed_actions: { export: boolean };
    graph?: Graph;
    timeline?: TimelineEvent[];
    warnings?: Warning[];
}>();

const search = useForm('finance-audit-search', { q: props.filters.q ?? '' });

const submitSearch = (): void => {
    search.get(route('finance.audit.index'), { preserveScroll: true, preserveState: true });
};

const isSingle = computed(() => props.resolution.status === 'single');

const severityClass = (severity: string): string => {
    switch (severity) {
        case 'CRITICAL':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
        case 'ERROR':
            return 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200';
        default:
            return 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200';
    }
};

const shareUrl = computed(() => {
    if (!isSingle.value || !props.resolution.target) return null;
    return route('finance.audit.index', {
        target_type: props.resolution.target.type,
        target_id: props.resolution.target.id,
    });
});

const copyShareLink = async (): Promise<void> => {
    if (shareUrl.value) await navigator.clipboard.writeText(shareUrl.value);
};

const refreshDeferred = (): void => {
    router.reload({ only: ['graph', 'timeline', 'warnings'] });
};
</script>

<template>
    <Head title="Finance Audit Workspace" />

    <!-- Editorial header: scale contrast over a card grid -->
    <header class="space-y-1">
        <h1 class="text-3xl font-bold tracking-tight">Audit Workspace</h1>
        <p class="text-muted-foreground max-w-2xl text-sm">Resolve any finance identifier — invoice number, payment ref, DNG id, student code — to one student's money graph, signed ledger timeline, and integrity warnings. Read-only.</p>
    </header>

    <!-- Universal search -->
    <form class="flex gap-2" @submit.prevent="submitSearch">
        <div class="relative flex-1">
            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
            <Input v-model="search.q" class="pl-9" placeholder="INV-2026-001, payment:1234, SWB12345, item id, or student name…" aria-label="Universal finance search" />
        </div>
        <Button type="submit" :disabled="search.processing">Search</Button>
    </form>

    <!-- Resolution: empty -->
    <Card v-if="resolution.status === 'empty'" class="border-dashed">
        <CardContent class="flex flex-col items-center gap-2 py-12 text-center">
            <Search class="text-muted-foreground h-8 w-8" />
            <p class="font-medium">No visible match in this campus</p>
            <p class="text-muted-foreground max-w-md text-sm">
                Nothing resolved for that identifier. Bare numbers are never guessed — use a prefix like
                <code class="bg-muted rounded px-1">payment:1234</code> for an exact id.
            </p>
        </CardContent>
    </Card>

    <!-- Resolution: ambiguous -->
    <Card v-else-if="resolution.status === 'ambiguous'">
        <CardHeader>
            <CardTitle class="text-base">Multiple matches — pick one</CardTitle>
        </CardHeader>
        <CardContent class="divide-y">
            <Link
                v-for="match in resolution.matches"
                :key="`${match.type}:${match.id}`"
                :href="route('finance.audit.index', { target_type: match.type, target_id: match.id })"
                class="hover:bg-muted/60 focus-visible:bg-muted flex items-center justify-between py-3 transition-colors"
            >
                <span class="font-medium">{{ match.label }}</span>
                <span class="text-muted-foreground text-sm">{{ match.sublabel }}</span>
            </Link>
        </CardContent>
    </Card>

    <!-- Resolution: single target -->
    <div v-else-if="isSingle" class="space-y-6">
        <Alert v-if="filters.finding_code">
            <AlertDescription>
                Đang xem theo phát hiện <strong>{{ filters.finding_code }}</strong>. Bảng "Sức khỏe dữ liệu" (Cockpit) là nguồn của lối tắt này.
            </AlertDescription>
        </Alert>

        <!-- Action bar -->
        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" class="capitalize">{{ resolution.target?.type }} #{{ resolution.target?.id }}</Badge>
            <div class="ml-auto flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="copyShareLink"><Copy class="mr-1 h-4 w-4" /> Copy link</Button>
                <Button variant="outline" size="sm" @click="refreshDeferred"><RefreshCw class="mr-1 h-4 w-4" /> Refresh</Button>
                <a v-if="links.source" :href="links.source.url">
                    <Button variant="outline" size="sm"><ExternalLink class="mr-1 h-4 w-4" /> {{ links.source.label }}</Button>
                </a>
                <Button variant="outline" size="sm" :disabled="true" :title="allowed_actions.export ? 'Export coming soon' : 'No export permission'"> <Download class="mr-1 h-4 w-4" /> Export </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main column: graph summary + timeline -->
            <div class="space-y-6 lg:col-span-2">
                <Deferred data="graph">
                    <template #fallback>
                        <Skeleton class="h-56 w-full" />
                    </template>
                    <template #default>
                        <Card v-if="graph">
                            <CardHeader>
                                <CardTitle class="text-base">
                                    {{ graph.subject?.full_name ?? 'Money graph' }}
                                    <span v-if="graph.subject?.student_code" class="text-muted-foreground ml-2 text-sm font-normal">
                                        {{ graph.subject.student_code }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <MoneyFlowGraph :graph="graph" />

                                <Separator />

                                <!-- Derived balance: cached vs settlement-derived -->
                                <div v-if="graph.derived_balance.length" class="space-y-2">
                                    <p class="text-sm font-semibold">Derived balance (cache vs SettlementService)</p>
                                    <div v-for="row in graph.derived_balance" :key="row.invoice_id" class="rounded-md border p-3 text-sm" :class="row.drift ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40' : ''">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ row.invoice_number }}</span>
                                            <Badge v-if="row.drift" class="bg-amber-200 text-amber-900"> <AlertTriangle class="mr-1 h-3 w-3" /> drift </Badge>
                                        </div>
                                        <div class="text-muted-foreground mt-1 grid grid-cols-2 gap-x-4">
                                            <span>Cached paid: {{ formatCurrency(row.cached_paid_amount) }}</span>
                                            <span>Derived paid: {{ formatCurrency(row.derived_paid) }}</span>
                                            <span>Cached total: {{ formatCurrency(row.cached_total_amount) }}</span>
                                            <span>Derived net: {{ formatCurrency(row.derived_net) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </template>
                </Deferred>

                <Deferred data="timeline">
                    <template #fallback>
                        <Skeleton class="h-40 w-full" />
                    </template>
                    <template #default>
                        <Card>
                            <CardHeader><CardTitle class="text-base">Signed ledger timeline</CardTitle></CardHeader>
                            <CardContent>
                                <p v-if="!timeline?.length" class="text-muted-foreground text-sm">No ledger events.</p>
                                <ol v-else class="space-y-2">
                                    <li v-for="(event, index) in timeline" :key="index" class="flex items-center justify-between border-l-2 py-1 pl-3" :class="event.signed_amount < 0 ? 'border-red-400' : 'border-emerald-400'">
                                        <span class="text-sm">
                                            {{ event.label }}
                                            <span class="text-muted-foreground">· {{ event.at ? formatDate(event.at) : '—' }}</span>
                                        </span>
                                        <span class="font-mono text-sm tabular-nums" :class="event.signed_amount < 0 ? 'text-red-600' : 'text-emerald-600'">
                                            {{ event.signed_amount < 0 ? '−' : '+' }}{{ formatCurrency(Math.abs(event.signed_amount)) }}
                                        </span>
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>
                    </template>
                </Deferred>
            </div>

            <!-- Side rail: warnings -->
            <Deferred data="warnings">
                <template #fallback>
                    <Skeleton class="h-56 w-full" />
                </template>
                <template #default>
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base"><ShieldAlert class="h-4 w-4" /> Warnings</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p v-if="!warnings?.length" class="text-muted-foreground text-sm">No integrity warnings for this subject.</p>
                            <div v-for="(warning, index) in warnings" :key="index" class="rounded-md border p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-muted-foreground font-mono text-xs">{{ warning.code }}</span>
                                    <Badge :class="severityClass(warning.severity)">{{ warning.severity }}</Badge>
                                </div>
                                <p class="mt-1 text-sm">
                                    <span v-if="warning.kind === 'invariant_error'" class="font-medium text-amber-700">Audit unavailable: </span>
                                    {{ warning.label }}
                                </p>
                                <p v-if="warning.sample_ids.length" class="text-muted-foreground mt-1 text-xs">sample ids: {{ warning.sample_ids.slice(0, 5).join(', ') }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </Deferred>
        </div>
    </div>
</template>
