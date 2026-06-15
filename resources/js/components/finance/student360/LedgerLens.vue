<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { LedgerEvent, LedgerGroup } from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { Deferred } from '@inertiajs/vue3';

defineProps<{ timeline?: LedgerEvent[]; groups?: LedgerGroup[] }>();
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="text-sm">Sổ cái</CardTitle>
        </CardHeader>
        <CardContent>
            <Tabs default-value="ledger" class="w-full">
                <TabsList>
                    <TabsTrigger value="ledger">Sổ cái</TabsTrigger>
                    <TabsTrigger value="timeline">Dòng thời gian</TabsTrigger>
                </TabsList>

                <TabsContent value="ledger" class="mt-3">
                    <Deferred data="ledger_groups">
                        <template #fallback>
                            <Skeleton class="h-24 w-full" />
                        </template>
                        <div v-if="groups && groups.length" class="space-y-4">
                            <section v-for="group in groups" :key="group.semester.id ?? 'none'">
                                <h3 class="text-muted-foreground mb-1 text-xs font-semibold uppercase">
                                    {{ group.semester.name }}
                                </h3>
                                <div v-for="invoice in group.invoices" :key="invoice.id" class="mb-2 rounded-md border p-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm font-medium">
                                        <span>
                                            {{ invoice.invoice_number }}
                                            <Badge variant="secondary" class="ml-1">{{ invoice.status }}</Badge>
                                        </span>
                                        <span class="tabular-nums">{{ formatCurrency(invoice.remaining) }} còn nợ</span>
                                    </div>
                                    <ul class="text-muted-foreground mt-1 space-y-0.5 text-xs">
                                        <li v-for="line in invoice.lines" :key="line.id" class="flex justify-between gap-2">
                                            <span>{{ line.label }}</span>
                                            <span class="tabular-nums">{{ formatCurrency(line.outstanding) }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </section>
                        </div>
                        <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hóa đơn.</p>
                    </Deferred>
                </TabsContent>

                <TabsContent value="timeline" class="mt-3">
                    <Deferred data="ledger">
                        <template #fallback>
                            <Skeleton class="h-24 w-full" />
                        </template>
                        <div v-if="timeline && timeline.length" class="divide-y">
                            <div
                                v-for="(event, idx) in timeline"
                                :key="idx"
                                class="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <span class="font-medium">{{ event.label }}</span>
                                    <span class="text-muted-foreground ml-2 text-xs">
                                        {{ event.at ? formatDate(event.at) : '—' }}
                                    </span>
                                </div>
                                <span
                                    class="tabular-nums"
                                    :class="event.signed_amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'"
                                >
                                    {{ formatCurrency(event.signed_amount) }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hoạt động.</p>
                    </Deferred>
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>