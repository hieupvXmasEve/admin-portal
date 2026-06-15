<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/types/finance';

interface GraphNode {
    key: string;
    type: string;
    id: number;
    label: string;
    status?: string | null;
    amount?: number | null;
    date?: string | null;
}

interface GraphEdge {
    from: string;
    to: string;
    kind: string;
    amount?: number | null;
}

const props = defineProps<{ graph: { nodes: GraphNode[]; edges: GraphEdge[] } }>();

const COLUMN_ORDER = ['student', 'invoice', 'invoice_line', 'charge', 'payment', 'dng'] as const;
const COLUMN_LABEL: Record<string, string> = {
    student: 'Sinh viên',
    invoice: 'Invoice',
    invoice_line: 'Dòng phí',
    charge: 'Charge',
    payment: 'Thanh toán',
    dng: 'DNG',
};

const columns = computed(() =>
    COLUMN_ORDER
        .map((type) => ({ type, label: COLUMN_LABEL[type] ?? type, nodes: props.graph.nodes.filter((n) => n.type === type) }))
        .filter((c) => c.nodes.length > 0),
);

const nodeByKey = computed(() => new Map(props.graph.nodes.map((n) => [n.key, n])));

const edgesByKind = computed(() => {
    const map = new Map<string, GraphEdge[]>();
    for (const edge of props.graph.edges) {
        const arr = map.get(edge.kind) ?? [];
        arr.push(edge);
        map.set(edge.kind, arr);
    }

    return [...map.entries()].map(([kind, edges]) => ({ kind, edges }));
});

function label(key: string): string {
    return nodeByKey.value.get(key)?.label ?? key;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-3" :style="{ gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }">
            <div v-for="col in columns" :key="col.type" class="flex flex-col gap-2">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{{ col.label }} ({{ col.nodes.length }})</p>
                <div v-for="node in col.nodes" :key="node.key" class="rounded-lg border p-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium">{{ node.label }}</span>
                        <Badge v-if="node.status" variant="outline" class="shrink-0 text-xs">{{ node.status }}</Badge>
                    </div>
                    <p v-if="node.amount != null" class="text-right text-muted-foreground tabular-nums">{{ formatCurrency(node.amount) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border p-3">
            <p class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Liên kết dòng tiền</p>
            <div v-for="group in edgesByKind" :key="group.kind" class="mb-2 last:mb-0">
                <p class="text-sm font-medium">{{ group.kind }} ({{ group.edges.length }})</p>
                <ul class="ml-3 list-disc text-sm text-muted-foreground">
                    <li v-for="(edge, index) in group.edges" :key="index" class="tabular-nums">
                        {{ label(edge.from) }} → {{ label(edge.to) }}
                        <span v-if="edge.amount != null"> · {{ formatCurrency(edge.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>