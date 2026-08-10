<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
}

const props = defineProps<Props>();

const kindLabels: Record<string, string> = {
    campus: 'Campus',
    major: 'Major',
    specialization: 'Specialization',
    scholarship: 'Scholarship',
    pathway_gateway: 'Pathway gateway',
    uu_dai_gc: 'Ưu đãi GC',
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

// One draft local-code value per "kind:crm_value" row, bound to its Input.
const localCodeDrafts = reactive<Record<string, string>>({});
const rowKey = (kind: string, crmValue: string): string => `${kind}:${crmValue}`;

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
            onSuccess: () => toast.success(`Mapping saved for "${crmValue}".`),
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
        onSuccess: () => toast.success('Target intake saved.'),
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
            toast.success('CRM connection settings saved.');
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
            onSuccess: () => toast.success('Logged in to CRM.'),
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

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">CRM value mappings</h1>
            <p class="text-muted-foreground text-sm">
                Resolve raw CRM values discovered by sync into local codes. Saving a mapping backfills every pending
                application that carries the raw value — no re-sync needed.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Sync now</CardTitle>
                <CardDescription>Log in once, then sync as many times as needed — sync reuses the stored token and never logs in on its own.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" :disabled="isLoggingIn" @click="loginNow">
                        {{ isLoggingIn ? 'Logging in…' : integration.logged_in ? 'Re-login' : 'Login' }}
                    </Button>
                    <span class="text-sm" :class="integration.logged_in ? 'text-emerald-700' : 'text-muted-foreground'">
                        {{ integration.logged_in ? `Logged in${integration.token_obtained_at ? ' at ' + new Date(integration.token_obtained_at).toLocaleString() : ''}` : 'Not logged in' }}
                    </span>
                </div>

                <Button :disabled="isSyncing || !integration.logged_in" :title="!integration.logged_in ? 'Log in to the CRM first' : undefined" @click="syncNow">
                    {{ isSyncing ? 'Syncing…' : 'Sync now' }}
                </Button>

                <div v-if="lastSync" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-muted-foreground text-xs">Total</div>
                            <div class="font-semibold">{{ lastSync.total }}</div>
                        </div>
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-muted-foreground text-xs">Created</div>
                            <div class="font-semibold">{{ lastSync.created }}</div>
                        </div>
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-muted-foreground text-xs">Updated</div>
                            <div class="font-semibold">{{ lastSync.updated }}</div>
                        </div>
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-muted-foreground text-xs">Skipped</div>
                            <div class="font-semibold">{{ lastSync.skipped }}</div>
                        </div>
                        <div class="rounded-md border px-3 py-2" :class="lastSync.failed > 0 ? 'border-destructive' : ''">
                            <div class="text-muted-foreground text-xs">Failed</div>
                            <div class="font-semibold" :class="lastSync.failed > 0 ? 'text-destructive' : ''">{{ lastSync.failed }}</div>
                        </div>
                    </div>
                    <p class="text-muted-foreground text-xs">Elapsed: {{ lastSync.elapsed_seconds }}s</p>

                    <div v-if="lastSync.failures.length > 0" class="space-y-1">
                        <div v-for="(failure, index) in lastSync.failures" :key="index" class="rounded-md bg-red-50 px-3 py-2 text-sm">
                            <span class="font-medium">{{ failure.student_code ?? '(no student_code)' }}</span>
                            — {{ failure.error_class }}<span v-if="failure.field"> ({{ failure.field }})</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>CRM connection</CardTitle>
                <CardDescription>Credentials used to log in to the CRM and pull New Enrollment records. Changes take effect on the next sync run — no deploy needed.</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-sm font-medium" for="crm-login-url">Login URL</label>
                    <Input id="crm-login-url" v-model="integrationForm.login_url" placeholder="https://crm.example.com/api/login" />
                    <p v-if="integrationForm.errors.login_url" class="text-destructive text-xs">{{ integrationForm.errors.login_url }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium" for="crm-data-url">New Enrollment data URL</label>
                    <Input id="crm-data-url" v-model="integrationForm.data_url" placeholder="https://crm.example.com/api/ne" />
                    <p v-if="integrationForm.errors.data_url" class="text-destructive text-xs">{{ integrationForm.errors.data_url }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium" for="crm-username">Username</label>
                    <Input id="crm-username" v-model="integrationForm.username" />
                    <p v-if="integrationForm.errors.username" class="text-destructive text-xs">{{ integrationForm.errors.username }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium" for="crm-password">
                        Password
                        <span class="text-muted-foreground font-normal">{{ integration.has_password ? '(set — leave blank to keep it)' : '(not set)' }}</span>
                    </label>
                    <Input id="crm-password" v-model="integrationForm.password" type="password" placeholder="••••••••" autocomplete="new-password" />
                    <p v-if="integrationForm.errors.password" class="text-destructive text-xs">{{ integrationForm.errors.password }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium" for="crm-timeout">Timeout (seconds)</label>
                    <Input id="crm-timeout" v-model.number="integrationForm.timeout" type="number" min="1" max="600" />
                    <p v-if="integrationForm.errors.timeout" class="text-destructive text-xs">{{ integrationForm.errors.timeout }}</p>
                </div>
                <div class="md:col-span-2">
                    <Button :disabled="integrationForm.processing" @click="saveIntegration">Save connection settings</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Target intake</CardTitle>
                <CardDescription>The single intake applied to every pending application on sync and on mapping save.</CardDescription>
            </CardHeader>
            <CardContent class="flex items-end gap-3">
                <div class="w-64 space-y-1">
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

        <Card v-for="[kind, rows] in groups" :key="kind">
            <CardHeader>
                <CardTitle>{{ kindLabels[kind] ?? kind }}</CardTitle>
                <CardDescription>{{ rows.length }} unmapped value(s)</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="row in rows" :key="row.crm_value" class="flex items-center gap-3 border-b pb-3 last:border-b-0 last:pb-0">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ row.crm_value }}</div>
                        <Badge variant="secondary" class="mt-1">{{ row.affected_count }} application(s)</Badge>
                    </div>
                    <Input
                        v-model="localCodeDrafts[rowKey(kind, row.crm_value)]"
                        placeholder="Local code"
                        class="w-56"
                        @keyup.enter="saveMapping(kind, row.crm_value)"
                    />
                    <Button size="sm" @click="saveMapping(kind, row.crm_value)">Save</Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="unmapped.length === 0">
            <CardContent class="text-muted-foreground py-8 text-center text-sm"> No unmapped CRM values. Everything discovered by sync has a local code. </CardContent>
        </Card>
    </div>
</template>
