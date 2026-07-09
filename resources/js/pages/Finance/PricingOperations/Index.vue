<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { formatCurrency } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ObligationTypeOption {
    value: string;
    label: string;
    pricing_strategy: string;
    requires_catalog: boolean;
}

interface PricingRuleRow {
    id: number;
    obligation_type: string;
    amount: number | string;
    currency: string;
    rule_version: string;
    description: string | null;
    facts_match: Record<string, unknown> | null;
    is_active: boolean;
    effective_from: string | null;
    effective_until: string | null;
    created_at: string | null;
}

interface CoverageWarning {
    obligation_type: string;
    label: string;
    pricing_strategy: string;
}

interface PricingFilters {
    obligation_type: string;
    per_page: number;
}

const props = defineProps<{
    rules: PaginatedResponse<PricingRuleRow>;
    obligation_types: ObligationTypeOption[];
    coverage_warnings: CoverageWarning[];
    filters: PricingFilters;
}>();

const permission = usePermission();
const canManage = computed(() => permission.can('manage_finance_pricing_operations'));
const showCreateForm = ref(false);

const catalogTypes = computed(() => props.obligation_types.filter((t) => t.requires_catalog));

const typeLabel = (value: string): string => props.obligation_types.find((t) => t.value === value)?.label ?? value;

const { filters, setFilter, handlePaginationNavigate, handlePageSizeChange } = useDataTable<PricingFilters>({
    baseUrl: financeRoutes.pricingOperations.index(),
    initialFilters: {
        obligation_type: props.filters.obligation_type ?? 'all',
        per_page: props.filters.per_page ?? 50,
    },
    defaultValues: {
        obligation_type: 'all',
        per_page: 50,
    },
    immediateFields: ['obligation_type', 'per_page'],
    only: ['rules', 'filters', 'coverage_warnings'],
});

const createForm = useForm({
    obligation_type: catalogTypes.value[0]?.value ?? '',
    amount: null as number | null,
    currency: 'VND',
    rule_version: '',
    description: '',
    facts_match_json: '',
    is_active: true as boolean,
    effective_from: '',
    effective_until: '',
});

function submitCreate(): void {
    createForm.post(financeRoutes.pricingOperations.store(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.currency = 'VND';
            createForm.is_active = true;
            createForm.obligation_type = catalogTypes.value[0]?.value ?? '';
            showCreateForm.value = false;
        },
    });
}

function activate(rule: PricingRuleRow): void {
    router.post(financeRoutes.pricingOperations.activate(rule.id), {}, { preserveScroll: true });
}

function deactivate(rule: PricingRuleRow): void {
    router.post(financeRoutes.pricingOperations.deactivate(rule.id), {}, { preserveScroll: true });
}

function formatFacts(facts: Record<string, unknown> | null): string {
    if (!facts || Object.keys(facts).length === 0) {
        return '—';
    }
    return JSON.stringify(facts);
}
</script>

<template>
    <Head title="Pricing Operations" />

    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Finance Office</p>
                <h1 class="text-3xl font-bold tracking-tight">Pricing Operations</h1>
                <p class="text-muted-foreground max-w-2xl text-sm">
                    Manage immutable pricing rule versions for catalog-priced obligation types. Change price by creating a new version — never edit history in place. Obligation types themselves are code-owned.
                </p>
            </div>
            <Button v-if="canManage" @click="showCreateForm = !showCreateForm">
                <Plus class="mr-2 h-4 w-4" />
                {{ showCreateForm ? 'Hide form' : 'New rule version' }}
            </Button>
        </header>

        <Alert v-if="coverage_warnings.length > 0" variant="destructive">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Missing usable pricing rules</AlertTitle>
            <AlertDescription>
                <p class="mb-2">
                    These registry types require catalog pricing but have no active rule currently in its effective window. Intake will fail for match-all fixed prices until a usable rule exists (facts_match still applies at price time):
                </p>
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="warning in coverage_warnings" :key="warning.obligation_type">
                        <span class="font-medium">{{ warning.label }}</span>
                        <span class="text-muted-foreground"> ({{ warning.obligation_type }} · {{ warning.pricing_strategy }})</span>
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Card v-if="showCreateForm && canManage">
            <CardHeader>
                <CardTitle>Create pricing rule version</CardTitle>
                <CardDescription>New versions are immutable. Activate/deactivate only toggles the active flag.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="space-y-2">
                        <Label for="obligation_type">Obligation type</Label>
                        <Select v-model="createForm.obligation_type">
                            <SelectTrigger id="obligation_type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in catalogTypes" :key="type.value" :value="type.value">
                                    {{ type.label }} ({{ type.value }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="createForm.errors.obligation_type" class="text-destructive text-xs">{{ createForm.errors.obligation_type }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="rule_version">Rule version</Label>
                        <Input id="rule_version" v-model="createForm.rule_version" placeholder="retake_fee:v2" />
                        <p v-if="createForm.errors.rule_version" class="text-destructive text-xs">{{ createForm.errors.rule_version }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="amount">Amount</Label>
                        <Input id="amount" v-model.number="createForm.amount" type="number" min="0" step="1" />
                        <p v-if="createForm.errors.amount" class="text-destructive text-xs">{{ createForm.errors.amount }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="currency">Currency</Label>
                        <Input id="currency" v-model="createForm.currency" maxlength="3" />
                        <p v-if="createForm.errors.currency" class="text-destructive text-xs">{{ createForm.errors.currency }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Effective from</Label>
                        <DatePicker v-model="createForm.effective_from" placeholder="Optional" />
                        <p v-if="createForm.errors.effective_from" class="text-destructive text-xs">{{ createForm.errors.effective_from }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Effective until</Label>
                        <DatePicker v-model="createForm.effective_until" placeholder="Optional" />
                        <p v-if="createForm.errors.effective_until" class="text-destructive text-xs">{{ createForm.errors.effective_until }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="createForm.description" />
                        <p v-if="createForm.errors.description" class="text-destructive text-xs">{{ createForm.errors.description }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="facts_match_json">facts_match (raw JSON object)</Label>
                        <Textarea
                            id="facts_match_json"
                            v-model="createForm.facts_match_json"
                            rows="3"
                            placeholder='{"campus_id": 1} — leave empty for match-all'
                            class="font-mono text-sm"
                        />
                        <p v-if="createForm.errors.facts_match_json" class="text-destructive text-xs">{{ createForm.errors.facts_match_json }}</p>
                    </div>

                    <div class="flex items-center gap-3 md:col-span-2">
                        <Button type="submit" :disabled="createForm.processing">Create version</Button>
                        <Button type="button" variant="outline" @click="showCreateForm = false">Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-4 space-y-0">
                <div>
                    <CardTitle>Rule versions</CardTitle>
                    <CardDescription>Active effective rules are selected by FinancePricingCatalog at intake.</CardDescription>
                </div>
                <Select :model-value="filters.obligation_type || 'all'" @update:model-value="(v) => setFilter('obligation_type', String(v))">
                    <SelectTrigger class="w-[260px]">
                        <SelectValue placeholder="All types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All types</SelectItem>
                        <SelectItem v-for="type in catalogTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent class="space-y-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Type</TableHead>
                            <TableHead>Version</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Effective</TableHead>
                            <TableHead>facts_match</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="rules.data.length === 0" :colspan="7">
                            No pricing rule versions yet. Create one or run <code class="text-xs">finance:seed-pricing-catalog</code>.
                        </TableEmpty>
                        <TableRow v-for="rule in rules.data" :key="rule.id">
                            <TableCell>
                                <div class="font-medium">{{ typeLabel(rule.obligation_type) }}</div>
                                <div class="text-muted-foreground text-xs">{{ rule.obligation_type }}</div>
                            </TableCell>
                            <TableCell>
                                <div class="font-mono text-sm">{{ rule.rule_version }}</div>
                                <div v-if="rule.description" class="text-muted-foreground text-xs">{{ rule.description }}</div>
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(Number(rule.amount)) }}
                                <span class="text-muted-foreground text-xs">{{ rule.currency }}</span>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="rule.is_active ? 'default' : 'secondary'">
                                    {{ rule.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-xs">
                                <div>{{ rule.effective_from ? String(rule.effective_from).slice(0, 10) : '—' }}</div>
                                <div>→ {{ rule.effective_until ? String(rule.effective_until).slice(0, 10) : 'open' }}</div>
                            </TableCell>
                            <TableCell class="max-w-[180px] truncate font-mono text-xs" :title="formatFacts(rule.facts_match)">
                                {{ formatFacts(rule.facts_match) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div v-if="canManage" class="flex justify-end gap-2">
                                    <Button v-if="!rule.is_active" size="sm" variant="outline" @click="activate(rule)">Activate</Button>
                                    <Button v-else size="sm" variant="outline" @click="deactivate(rule)">Deactivate</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <DataPagination
                    :pagination-data="rules"
                    item-name="rules"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>
    </div>
</template>
