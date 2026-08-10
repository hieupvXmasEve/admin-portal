<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    Building2,
    CalendarClock,
    CheckCircle2,
    Gift,
    GraduationCap,
    Link2,
    LogIn,
    RefreshCw,
    Route as RouteIcon,
    XCircle,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface UnmappedRow {
    kind: string;
    crm_value: string;
    affected_count: number;
}

interface Semester {
    code: string;
    name: string;
}

interface CatalogOption {
    code: string;
    name: string;
}

// `amount`/`discount_value` are decimal-cast columns — Laravel serializes
// those as strings, not numbers.
interface ScholarshipOption extends CatalogOption {
    type: 'percentage' | 'fixed_amount';
    amount: string;
}

interface VoucherOption extends CatalogOption {
    voucher_type: 'informational' | 'discount';
    discount_type: 'percentage' | 'fixed_amount' | null;
    discount_value: string | null;
}

interface IntegrationSettings {
    login_url: string | null;
    data_url: string | null;
    username: string | null;
    has_password: boolean;
    timeout: number;
    logged_in: boolean;
    token_obtained_at: string | null;
}

interface SyncSummary {
    total: number;
    created: number;
    updated: number;
    skipped: number;
    failed: number;
    elapsed_seconds: number;
    failures: { student_code: string | null; error_class: string; field: string | null }[];
}

interface Props {
    unmapped: UnmappedRow[];
    intake: { crm_value: string; local_code: string | null };
    semesters: Semester[];
    integration: IntegrationSettings;
    campuses: CatalogOption[];
    programs: CatalogOption[];
    scholarships: ScholarshipOption[];
    vouchers: VoucherOption[];
}

const props = defineProps<Props>();

/**
 * Inertia's `onSuccess` fires for any successful visit (302→200), including
 * `back()->with(['error' => ...])` — a caught business exception is still an
 * HTTP success from Inertia's point of view. `onError` only fires for actual
 * validation failures (422). So `onSuccess` must read the real flash content
 * rather than assume it — otherwise a genuine server-side failure (e.g. a
 * failed CRM login) still shows a hardcoded "success" toast.
 */
function toastFromFlash(fallbackSuccessMessage: string): void {
    const flash = (usePage().props.flash as Record<string, unknown>) ?? {};

    if (typeof flash.error === 'string' && flash.error !== '') {
        toast.error(flash.error);
    } else if (typeof flash.warning === 'string' && flash.warning !== '') {
        toast.warning(flash.warning);
    } else if (typeof flash.success === 'string' && flash.success !== '') {
        toast.success(flash.success);
    } else {
        toast.success(fallbackSuccessMessage);
    }
}

const kindMeta: Record<string, { label: string; icon: typeof Building2 }> = {
    campus: { label: 'Campus', icon: Building2 },
    major: { label: 'Major', icon: GraduationCap },
    specialization: { label: 'Specialization', icon: GraduationCap },
    scholarship: { label: 'Scholarship', icon: Award },
    pathway_gateway: { label: 'Pathway gateway', icon: RouteIcon },
    uu_dai_gc: { label: 'Ưu đãi GC', icon: Gift },
};

const groups = computed(() => {
    const byKind = new Map<string, UnmappedRow[]>();
    for (const row of props.unmapped) {
        const list = byKind.get(row.kind) ?? [];
        list.push(row);
        byKind.set(row.kind, list);
    }

    return byKind;
});

// One draft local-code value per "kind:crm_value" row, bound to its Input/Select.
const localCodeDrafts = reactive<Record<string, string>>({});
const rowKey = (kind: string, crmValue: string): string => `${kind}:${crmValue}`;

// Catalog-backed kinds get a Select (SaveCrmValueMappingRequest already
// validates local_code against these tables — typing was error-prone for no
// reason). `pathway_gateway` and `uu_dai_gc` both resolve against the same
// `voucher_definitions` catalog (confirmed against live data — CRM's
// "Taiwan Gateway"/"Taiwan Pathway" and "50% học phí kỳ GC" are voucher
// codes, not a separate concept). `specialization` stays free-text — no CRM
// field currently feeds that kind, so there is nothing live to pick from.
function catalogFor(kind: string): CatalogOption[] | null {
    if (kind === 'campus') {
        return props.campuses;
    }
    if (kind === 'major') {
        return props.programs;
    }
    if (kind === 'scholarship') {
        return props.scholarships;
    }
    if (kind === 'pathway_gateway' || kind === 'uu_dai_gc') {
        return props.vouchers;
    }

    return null;
}

const currencyFormatter = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });

// What the currently-selected code is actually worth — shown under the Select
// only after a choice is made, so the dropdown list itself stays short (the
// option label is just "name (code)"; the value/percentage would make every
// row wrap on a long scholarship/voucher list).
function previewFor(kind: string, code: string | undefined): string | null {
    if (!code) {
        return null;
    }

    if (kind === 'scholarship') {
        const option = props.scholarships.find((s) => s.code === code);
        if (!option) {
            return null;
        }

        return option.type === 'percentage' ? `${option.amount}%` : currencyFormatter.format(Number(option.amount));
    }

    if (kind === 'pathway_gateway' || kind === 'uu_dai_gc') {
        const option = props.vouchers.find((v) => v.code === code);
        if (!option) {
            return null;
        }
        if (option.voucher_type === 'informational' || !option.discount_type || option.discount_value === null) {
            return 'Informational — no discount';
        }

        return option.discount_type === 'percentage' ? `${option.discount_value}%` : currencyFormatter.format(Number(option.discount_value));
    }

    return null;
}

function saveMapping(kind: string, crmValue: string): void {
    const localCode = localCodeDrafts[rowKey(kind, crmValue)]?.trim();
    if (!localCode) {
        return;
    }

    router.post(
        route('student-applications.crm-mappings.store'),
        { kind, crm_value: crmValue, local_code: localCode },
        {
            preserveScroll: true,
            onSuccess: () => toastFromFlash(`Mapping saved for "${crmValue}".`),
            onError: (errors) => toast.error(errors.local_code ?? errors.kind ?? 'Could not save this mapping.'),
        },
    );
}

const intakeForm = useForm({
    kind: 'intake',
    crm_value: props.intake.crm_value,
    local_code: props.intake.local_code ?? '',
});

function saveIntake(): void {
    intakeForm.post(route('student-applications.crm-mappings.store'), {
        preserveScroll: true,
        onSuccess: () => toastFromFlash('Target intake saved.'),
        onError: () => toast.error(intakeForm.errors.local_code ?? 'Could not save the target intake.'),
    });
}

// Password is write-only: the server never sends the real value back, so the
// field starts blank and a blank submit means "keep the current password".
const integrationForm = useForm({
    login_url: props.integration.login_url ?? '',
    data_url: props.integration.data_url ?? '',
    username: props.integration.username ?? '',
    password: '',
    timeout: props.integration.timeout,
});

function saveIntegration(): void {
    integrationForm.post(route('student-applications.crm-mappings.integration.store'), {
        preserveScroll: true,
        onSuccess: () => {
            integrationForm.password = '';
            toastFromFlash('CRM connection settings saved.');
        },
        onError: () => toast.error('Could not save the CRM connection settings — check the fields below.'),
    });
}

// Login is explicit and one-time: the token persists server-side
// (CrmIntegrationSettings), so Sync never logs in implicitly and is blocked
// client-side (and, authoritatively, server-side) until integration.logged_in.
const isLoggingIn = ref(false);

function loginNow(): void {
    isLoggingIn.value = true;
    router.post(
        route('student-applications.crm-mappings.login'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toastFromFlash('Logged in to CRM.'),
            onError: () => toast.error('CRM login failed — check the message below.'),
            onFinish: () => {
                isLoggingIn.value = false;
            },
        },
    );
}

// Synchronous by design (plan addendum): the request blocks until the whole
// batch is processed, so the button stays disabled with a spinner for the
// full duration rather than optimistically re-enabling.
const isSyncing = ref(false);
const lastSync = computed<SyncSummary | null>(() => (usePage().props.flash as Record<string, unknown>)?.crm_sync_summary as SyncSummary | null);

function syncNow(): void {
    if (!props.integration.logged_in) {
        toast.error('Log in to the CRM first.');
        return;
    }

    isSyncing.value = true;
    router.post(
        route('student-applications.crm-mappings.sync'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const flash = (usePage().props.flash as Record<string, unknown>) ?? {};
                if (typeof flash.error === 'string' && flash.error !== '') {
                    toast.error(flash.error);
                    return;
                }

                const summary = lastSync.value;
                if (summary && summary.failed > 0) {
                    toast.warning(`Sync finished with ${summary.failed} failure(s) — see details below.`);
                } else {
                    toast.success('Sync finished.');
                }
            },
            onError: () => toast.error('Sync failed — check the message below.'),
            onFinish: () => {
                isSyncing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="CRM Value Mappings" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">CRM value mappings</h1>
            <p class="text-muted-foreground text-sm">
                Resolve raw CRM values discovered by sync into local codes. Saving a mapping backfills every pending
                application that carries the raw value — no re-sync needed.
            </p>
        </div>

        <!-- Step 1: connection setup — a prerequisite, so it comes first. -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <Link2 class="text-muted-foreground h-4 w-4" />
                    <CardTitle>1. CRM connection</CardTitle>
                </div>
                <CardDescription>Endpoint URLs and credentials used to log in and pull New Enrollment records. Changes take effect immediately — no deploy needed.</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="crm-login-url">Login URL</label>
                    <Input id="crm-login-url" v-model="integrationForm.login_url" placeholder="https://crm.example.com/api/login" />
                    <p v-if="integrationForm.errors.login_url" class="text-destructive text-xs">{{ integrationForm.errors.login_url }}</p>
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="crm-data-url">New Enrollment data URL</label>
                    <Input id="crm-data-url" v-model="integrationForm.data_url" placeholder="https://crm.example.com/api/ne" />
                    <p v-if="integrationForm.errors.data_url" class="text-destructive text-xs">{{ integrationForm.errors.data_url }}</p>
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="crm-username">Username</label>
                    <Input id="crm-username" v-model="integrationForm.username" />
                    <p v-if="integrationForm.errors.username" class="text-destructive text-xs">{{ integrationForm.errors.username }}</p>
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="crm-password">
                        Password
                        <span class="text-muted-foreground font-normal">{{ integration.has_password ? '(set — leave blank to keep it)' : '(not set)' }}</span>
                    </label>
                    <Input id="crm-password" v-model="integrationForm.password" type="password" placeholder="••••••••" autocomplete="new-password" />
                    <p v-if="integrationForm.errors.password" class="text-destructive text-xs">{{ integrationForm.errors.password }}</p>
                </div>
                <div class="space-y-1.5 md:col-span-2 md:max-w-xs">
                    <label class="text-sm font-medium" for="crm-timeout">Timeout (seconds)</label>
                    <Input id="crm-timeout" v-model.number="integrationForm.timeout" type="number" min="1" max="600" />
                    <p v-if="integrationForm.errors.timeout" class="text-destructive text-xs">{{ integrationForm.errors.timeout }}</p>
                </div>
                <div class="md:col-span-2">
                    <Button :disabled="integrationForm.processing" @click="saveIntegration">Save connection settings</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Step 2: log in once, then sync as many times as needed. -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <RefreshCw class="text-muted-foreground h-4 w-4" />
                    <CardTitle>2. Login &amp; sync</CardTitle>
                </div>
                <CardDescription>Sync reuses the stored token and never logs in on its own.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" :disabled="isLoggingIn" @click="loginNow">
                        <LogIn class="mr-1.5 h-4 w-4" />
                        {{ isLoggingIn ? 'Logging in…' : integration.logged_in ? 'Re-login' : 'Login' }}
                    </Button>
                    <span class="inline-flex items-center gap-1.5 text-sm" :class="integration.logged_in ? 'text-emerald-700' : 'text-muted-foreground'">
                        <CheckCircle2 v-if="integration.logged_in" class="h-4 w-4" />
                        <XCircle v-else class="h-4 w-4" />
                        {{ integration.logged_in ? `Logged in${integration.token_obtained_at ? ' at ' + new Date(integration.token_obtained_at).toLocaleString() : ''}` : 'Not logged in' }}
                    </span>

                    <Button class="ml-auto" :disabled="isSyncing || !integration.logged_in" :title="!integration.logged_in ? 'Log in to the CRM first' : undefined" @click="syncNow">
                        <RefreshCw class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': isSyncing }" />
                        {{ isSyncing ? 'Syncing…' : 'Sync now' }}
                    </Button>
                </div>

                <div v-if="lastSync" class="border-t pt-4">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div class="rounded-lg border bg-muted/30 px-3 py-2 text-center">
                            <div class="text-lg font-semibold">{{ lastSync.total }}</div>
                            <div class="text-muted-foreground text-xs">Total</div>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center">
                            <div class="text-lg font-semibold text-emerald-700">{{ lastSync.created }}</div>
                            <div class="text-xs text-emerald-700/80">Created</div>
                        </div>
                        <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-center">
                            <div class="text-lg font-semibold text-sky-700">{{ lastSync.updated }}</div>
                            <div class="text-xs text-sky-700/80">Updated</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center">
                            <div class="text-lg font-semibold text-amber-700">{{ lastSync.skipped }}</div>
                            <div class="text-xs text-amber-700/80">Skipped</div>
                        </div>
                        <div class="rounded-lg border px-3 py-2 text-center" :class="lastSync.failed > 0 ? 'border-destructive/40 bg-destructive/10' : 'bg-muted/30'">
                            <div class="text-lg font-semibold" :class="lastSync.failed > 0 ? 'text-destructive' : ''">{{ lastSync.failed }}</div>
                            <div class="text-xs" :class="lastSync.failed > 0 ? 'text-destructive/80' : 'text-muted-foreground'">Failed</div>
                        </div>
                    </div>
                    <p class="text-muted-foreground mt-2 text-xs">Elapsed: {{ lastSync.elapsed_seconds }}s</p>

                    <div v-if="lastSync.failures.length > 0" class="mt-3 space-y-1">
                        <div v-for="(failure, index) in lastSync.failures" :key="index" class="rounded-md bg-red-50 px-3 py-2 text-sm">
                            <span class="font-medium">{{ failure.student_code ?? '(no student_code)' }}</span>
                            — {{ failure.error_class }}<span v-if="failure.field"> ({{ failure.field }})</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Step 3: the one global setting applied at sync + mapping-save time. -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <CalendarClock class="text-muted-foreground h-4 w-4" />
                    <CardTitle>3. Target intake</CardTitle>
                </div>
                <CardDescription>The single intake applied to every pending application on sync and on mapping save.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-end gap-3">
                <div class="w-64 space-y-1.5">
                    <Select v-model="intakeForm.local_code">
                        <SelectTrigger>
                            <SelectValue placeholder="Select intake" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="semester in semesters" :key="semester.code" :value="semester.code">
                                {{ semester.name }} ({{ semester.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="intakeForm.errors.local_code" class="text-destructive text-xs">{{ intakeForm.errors.local_code }}</p>
                </div>
                <Button :disabled="!intakeForm.local_code || intakeForm.processing" @click="saveIntake">Save intake</Button>
            </CardContent>
        </Card>

        <!-- Step 4: resolve whatever sync discovered. -->
        <Card>
            <CardHeader>
                <CardTitle>4. Unmapped CRM values</CardTitle>
                <CardDescription v-if="unmapped.length > 0">{{ groups.size }} kind(s), {{ unmapped.length }} raw value(s) waiting for a local code.</CardDescription>
                <CardDescription v-else>Nothing to resolve — everything discovered by sync already has a local code.</CardDescription>
            </CardHeader>
            <CardContent v-if="unmapped.length > 0" class="divide-y">
                <div v-for="[kind, rows] in groups" :key="kind" class="py-4 first:pt-0 last:pb-0">
                    <div class="mb-3 flex items-center gap-2">
                        <component :is="kindMeta[kind]?.icon ?? Building2" class="text-muted-foreground h-4 w-4" />
                        <h3 class="text-sm font-semibold">{{ kindMeta[kind]?.label ?? kind }}</h3>
                        <Badge variant="secondary">{{ rows.length }}</Badge>
                    </div>
                    <div class="space-y-2">
                        <div v-for="row in rows" :key="row.crm_value" class="flex flex-wrap items-center gap-3 rounded-md border px-3 py-2">
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ row.crm_value }}</div>
                                <Badge variant="outline" class="mt-1 text-xs">{{ row.affected_count }} application(s)</Badge>
                            </div>
                            <div class="space-y-1">
                                <Select v-if="catalogFor(kind)" v-model="localCodeDrafts[rowKey(kind, row.crm_value)]">
                                    <SelectTrigger class="w-56">
                                        <SelectValue placeholder="Select…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in catalogFor(kind) ?? []" :key="option.code" :value="option.code">
                                            {{ option.name }} ({{ option.code }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-else
                                    v-model="localCodeDrafts[rowKey(kind, row.crm_value)]"
                                    placeholder="Local code"
                                    class="w-48"
                                    @keyup.enter="saveMapping(kind, row.crm_value)"
                                />
                                <p v-if="previewFor(kind, localCodeDrafts[rowKey(kind, row.crm_value)])" class="text-muted-foreground text-xs">
                                    {{ previewFor(kind, localCodeDrafts[rowKey(kind, row.crm_value)]) }}
                                </p>
                            </div>
                            <Button size="sm" @click="saveMapping(kind, row.crm_value)">Save</Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
