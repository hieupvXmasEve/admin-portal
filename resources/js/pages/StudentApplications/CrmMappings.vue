<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
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

interface Props {
    unmapped: UnmappedRow[];
    intake: { crm_value: string; local_code: string | null };
    semesters: Semester[];
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
