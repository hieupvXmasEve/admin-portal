<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { financeRoutes } from '@/utils/routes';
import { Head, useForm } from '@inertiajs/vue3';

interface Props {
    settings: {
        credit_offset_enabled: boolean;
        credit_offset_min_balance: number;
        updated_at: string | null;
    } | null;
    can_manage_settings: boolean;
    dng_campus_mapping: {
        campus: {
            id: number;
            name: string;
            code: string;
        };
        mapping: {
            provider_code: string;
            updated_at: string | null;
        } | null;
    } | null;
    can_manage_dng_campus_mapping: boolean;
}

const props = defineProps<Props>();

const settingsForm = useForm({
    credit_offset_enabled: props.settings?.credit_offset_enabled ?? false,
    credit_offset_min_balance: props.settings?.credit_offset_min_balance ?? 0,
});

const submitSettings = (): void => {
    settingsForm.put(financeRoutes.settings.update(), {
        preserveScroll: true,
    });
};

const mappingForm = useForm({
    provider_code: props.dng_campus_mapping?.mapping?.provider_code ?? '',
});

const submitMapping = (): void => {
    mappingForm.put(financeRoutes.collect.updateDngCampusMapping(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Finance Settings" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Finance Settings</h1>
            <p class="text-muted-foreground mt-1 text-sm">Configure Finance-wide behavior and DNG campus mapping.</p>
        </div>

        <Card v-if="settings">
            <CardHeader>
                <CardTitle>Credit offset on charge generation</CardTitle>
                <CardDescription>When enabled, a student's own unapplied cash automatically offsets the charge just created, if the balance meets the threshold.</CardDescription>
            </CardHeader>
            <CardContent>
                <form v-if="can_manage_settings" class="grid gap-6" @submit.prevent="submitSettings">
                    <div class="flex items-center gap-3">
                        <Switch id="credit_offset_enabled" v-model="settingsForm.credit_offset_enabled" />
                        <Label for="credit_offset_enabled">Enable credit offset on charge generation</Label>
                    </div>
                    <InputError :message="settingsForm.errors.credit_offset_enabled" />

                    <div class="grid gap-1.5">
                        <Label for="credit_offset_min_balance">Minimum unapplied balance to trigger offset (VND)</Label>
                        <Input
                            id="credit_offset_min_balance"
                            v-model.number="settingsForm.credit_offset_min_balance"
                            type="number"
                            min="0"
                            step="1"
                            :class="{ 'border-destructive': settingsForm.errors.credit_offset_min_balance }"
                        />
                        <InputError :message="settingsForm.errors.credit_offset_min_balance" />
                    </div>

                    <p v-if="settings.updated_at" class="text-muted-foreground text-sm">Last updated {{ new Date(settings.updated_at).toLocaleString() }}.</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="settingsForm.processing">
                            {{ settingsForm.processing ? 'Saving...' : 'Save settings' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="space-y-2 text-sm">
                    <p class="text-muted-foreground">You can view this Finance configuration but do not have permission to change it.</p>
                    <p>Credit offset: <span class="font-medium">{{ settings.credit_offset_enabled ? 'Enabled' : 'Disabled' }}</span></p>
                    <p>Minimum balance: <span class="font-medium">{{ settings.credit_offset_min_balance.toLocaleString() }} VND</span></p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="dng_campus_mapping">
            <CardHeader>
                <CardTitle>DNG Campus Mapping — {{ dng_campus_mapping.campus.name }}</CardTitle>
                <CardDescription
                    >Internal campus code: <span class="font-mono">{{ dng_campus_mapping.campus.code }}</span>. Used for DNG payment requests and reconciliation at the selected
                    campus.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form v-if="can_manage_dng_campus_mapping" class="grid gap-4" @submit.prevent="submitMapping">
                    <div class="grid gap-1.5">
                        <Label for="provider_code">DNG provider campus code</Label>
                        <Input
                            id="provider_code"
                            v-model="mappingForm.provider_code"
                            class="font-mono"
                            placeholder="e.g., FAUDN"
                            :class="{ 'border-destructive': mappingForm.errors.provider_code }"
                        />
                        <InputError :message="mappingForm.errors.provider_code" />
                    </div>

                    <p v-if="dng_campus_mapping.mapping?.updated_at" class="text-muted-foreground text-sm">
                        Last updated {{ new Date(dng_campus_mapping.mapping.updated_at).toLocaleString() }}.
                    </p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="mappingForm.processing">
                            {{ mappingForm.processing ? 'Saving...' : 'Save mapping' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="space-y-2 text-sm">
                    <p class="text-muted-foreground">You can view this Finance configuration but do not have permission to change it.</p>
                    <p class="font-mono font-medium">{{ dng_campus_mapping.mapping?.provider_code ?? 'Not configured' }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
