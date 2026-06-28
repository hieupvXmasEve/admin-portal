<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { EgcPanel } from '@/types/models';
import { AlertTriangle, GraduationCap, Languages } from 'lucide-vue-next';

interface Props {
    egc: EgcPanel;
}

defineProps<Props>();

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <Languages class="h-4 w-4" />
                EGC English level & IELTS
            </CardTitle>
            <CardDescription>
                English-level history and IELTS records sit here, out of the main timeline (ADR-0009).
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-5">
            <div class="flex flex-wrap gap-6">
                <div>
                    <p class="text-muted-foreground text-xs uppercase tracking-wide">Starting level</p>
                    <p class="text-lg font-semibold">{{ egc.starting_level ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground text-xs uppercase tracking-wide">Current level</p>
                    <p class="text-lg font-semibold">{{ egc.current_level ?? '—' }}</p>
                </div>
            </div>

            <div>
                <p class="mb-2 flex items-center gap-1.5 text-sm font-medium">
                    <GraduationCap class="h-4 w-4" />
                    Progression history
                </p>
                <div v-if="egc.history.length === 0" class="text-muted-foreground text-sm">No level changes or IELTS events recorded.</div>
                <ul v-else class="space-y-2">
                    <li v-for="row in egc.history" :key="row.id" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                        <div>
                            <span class="font-medium">{{ row.label }}</span>
                            <span v-if="row.from_english_level !== null && row.to_english_level !== null" class="text-muted-foreground ml-2">
                                L{{ row.from_english_level }} → L{{ row.to_english_level }}
                            </span>
                        </div>
                        <span class="text-muted-foreground">{{ formatDate(row.occurred_at) }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium">IELTS records</p>
                <div v-if="egc.ielts.length === 0" class="text-muted-foreground text-sm">No IELTS certificates recorded.</div>
                <ul v-else class="space-y-2">
                    <li v-for="cert in egc.ielts" :key="cert.id" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ cert.overall_score ?? '—' }}</span>
                            <Badge v-if="cert.missing_documents" variant="outline" class="flex items-center gap-1 border-amber-300 text-amber-700">
                                <AlertTriangle class="h-3 w-3" />
                                Scan missing
                            </Badge>
                        </div>
                        <span class="text-muted-foreground">{{ formatDate(cert.issue_date) }}</span>
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
