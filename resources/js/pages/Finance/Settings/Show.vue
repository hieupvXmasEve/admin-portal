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
    };
    can_manage: boolean;
}

const props = defineProps<Props>();

const form = useForm({
    credit_offset_enabled: props.settings.credit_offset_enabled,
    credit_offset_min_balance: props.settings.credit_offset_min_balance,
});

const submit = (): void => {
    form.put(financeRoutes.settings.update(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Finance Settings" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Finance Settings</h1>
            <p class="text-muted-foreground mt-1 text-sm">Configure how student unapplied cash is handled when a new charge is generated.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Credit offset on charge generation</CardTitle>
                <CardDescription>When enabled, a student's own unapplied cash automatically offsets the charge just created, if the balance meets the threshold.</CardDescription>
            </CardHeader>
            <CardContent>
                <form v-if="can_manage" class="grid gap-6" @submit.prevent="submit">
                    <div class="flex items-center gap-3">
                        <Switch id="credit_offset_enabled" v-model="form.credit_offset_enabled" />
                        <Label for="credit_offset_enabled">Enable credit offset on charge generation</Label>
                    </div>
                    <InputError :message="form.errors.credit_offset_enabled" />

                    <div class="grid gap-1.5">
                        <Label for="credit_offset_min_balance">Minimum unapplied balance to trigger offset (VND)</Label>
                        <Input
                            id="credit_offset_min_balance"
                            v-model.number="form.credit_offset_min_balance"
                            type="number"
                            min="0"
                            step="1"
                            :class="{ 'border-destructive': form.errors.credit_offset_min_balance }"
                        />
                        <InputError :message="form.errors.credit_offset_min_balance" />
                    </div>

                    <p v-if="settings.updated_at" class="text-muted-foreground text-sm">Last updated {{ new Date(settings.updated_at).toLocaleString() }}.</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save settings' }}
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
    </div>
</template>
