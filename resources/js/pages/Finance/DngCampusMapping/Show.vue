<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { financeRoutes } from '@/utils/routes';
import { Head, useForm } from '@inertiajs/vue3';

interface Props {
    campus: {
        id: number;
        name: string;
        code: string;
    };
    mapping: {
        provider_code: string;
        updated_at: string | null;
    } | null;
    can_manage: boolean;
}

const props = defineProps<Props>();

const form = useForm({
    provider_code: props.mapping?.provider_code ?? '',
});

const submit = (): void => {
    form.put(financeRoutes.collect.updateDngCampusMapping(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="DNG Campus Mapping" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">DNG Campus Mapping</h1>
            <p class="text-muted-foreground mt-1 text-sm">Configure the DNG provider code used for payment requests and reconciliation at the selected campus.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ campus.name }}</CardTitle>
                <CardDescription
                    >Internal campus code: <span class="font-mono">{{ campus.code }}</span></CardDescription
                >
            </CardHeader>
            <CardContent>
                <form v-if="can_manage" class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="provider_code">DNG provider campus code</Label>
                        <Input id="provider_code" v-model="form.provider_code" class="font-mono" placeholder="e.g., FAUDN" :class="{ 'border-destructive': form.errors.provider_code }" />
                        <InputError :message="form.errors.provider_code" />
                    </div>

                    <p v-if="mapping?.updated_at" class="text-muted-foreground text-sm">Last updated {{ new Date(mapping.updated_at).toLocaleString() }}.</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save mapping' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="space-y-2 text-sm">
                    <p class="text-muted-foreground">You can view this Finance configuration but do not have permission to change it.</p>
                    <p class="font-mono font-medium">{{ mapping?.provider_code ?? 'Not configured' }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
