<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Check, ChevronsUpDown, KeyRound, Save, Search, TestTube2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface ProviderOption {
    id: string;
    label: string;
}

interface ModelOption {
    id: string;
    label: string;
}

interface ProviderSetting {
    id: number | null;
    provider: string;
    default_model: string;
    enabled: boolean;
    daily_limit_cents: number | null;
    monthly_limit_cents: number | null;
    has_api_key: boolean;
    api_key_mask: string | null;
    tested_at: string | null;
    last_test_status: string | null;
    last_test_error_code: string | null;
    last_used_at: string | null;
}

const props = defineProps<{
    setting: ProviderSetting;
    available_providers: ProviderOption[];
    available_models: Record<string, ModelOption[]>;
    permissions: {
        can_manage: boolean;
        can_test: boolean;
    };
}>();

const form = useForm({
    provider: props.setting.provider,
    default_model: props.setting.default_model,
    api_key: '',
    enabled: props.setting.enabled,
    daily_limit_cents: props.setting.daily_limit_cents,
    monthly_limit_cents: props.setting.monthly_limit_cents,
});

const testForm = useForm({});
const clearKeyForm = useForm({});
const modelSelectOpen = ref(false);
const modelSearchQuery = ref('');

const selectedModels = computed(() => props.available_models[form.provider] ?? []);
const selectedProvider = computed(() => props.available_providers.find((provider) => provider.id === form.provider));
const selectedModel = computed(() => selectedModels.value.find((model) => model.id === form.default_model) ?? null);
const selectedModelLabel = computed(() => (selectedModel.value ? `${selectedModel.value.label} (${selectedModel.value.id})` : 'Select model'));
const normalizedModelSearchQuery = computed(() => modelSearchQuery.value.trim().toLowerCase());
const filteredModels = computed(() => {
    if (!normalizedModelSearchQuery.value) {
        return selectedModels.value;
    }

    const terms = normalizedModelSearchQuery.value.split(/\s+/).filter(Boolean);

    return selectedModels.value.filter((model) => {
        const searchableText = `${model.label} ${model.id}`.toLowerCase();

        return terms.every((term) => searchableText.includes(term));
    });
});
const visibleModels = computed(() => filteredModels.value.slice(0, 100));

const statusVariant = computed(() => {
    if (props.setting.last_test_status === 'success') {
        return 'success';
    }

    if (props.setting.last_test_status === 'failed') {
        return 'destructive';
    }

    return 'secondary';
});

const statusLabel = computed(() => {
    if (props.setting.last_test_status === 'success') {
        return 'Tested';
    }

    if (props.setting.last_test_status === 'failed') {
        return props.setting.last_test_error_code ?? 'Failed';
    }

    return 'Untested';
});

const keyLabel = computed(() => (props.setting.has_api_key ? props.setting.api_key_mask : 'No key stored'));

watch(
    () => form.provider,
    () => {
        modelSearchQuery.value = '';

        if (!selectedModels.value.some((model) => model.id === form.default_model)) {
            form.default_model = selectedModels.value[0]?.id ?? '';
        }
    },
);

watch(modelSelectOpen, (isOpen) => {
    if (!isOpen) {
        modelSearchQuery.value = '';
    }
});

const selectModel = (value: unknown) => {
    const model = typeof value === 'object' && value !== null && 'id' in value ? (value as ModelOption) : null;

    if (!model) {
        return;
    }

    form.default_model = model.id;
    modelSearchQuery.value = '';
    modelSelectOpen.value = false;
};

const submit = () => {
    form.put(route('ai.provider-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.api_key = '';
        },
    });
};

const testProvider = () => {
    testForm.post(route('ai.provider-settings.test'), {
        preserveScroll: true,
    });
};

const clearKey = () => {
    clearKeyForm.delete(route('ai.provider-settings.key.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            form.api_key = '';
            form.enabled = false;
        },
    });
};
</script>

<template>
    <Head title="AI Provider Settings" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">AI Provider Settings</h1>
                <p class="text-muted-foreground text-sm">Governance boundary for staff AI runtime credentials.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{{ selectedProvider?.label ?? form.provider }}</Badge>
                <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
            </div>
        </div>

        <Alert v-if="!permissions.can_manage">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Read-only</AlertTitle>
            <AlertDescription>Your account can view this configuration but cannot change it.</AlertDescription>
        </Alert>

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Provider</CardTitle>
                    <CardDescription>{{ selectedModel?.label ?? form.default_model }}</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-5 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="ai_provider">Provider</Label>
                        <Select id="ai_provider" v-model="form.provider" :disabled="!permissions.can_manage">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select provider" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="provider in available_providers" :key="provider.id" :value="provider.id">
                                    {{ provider.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.provider" class="text-destructive text-sm">{{ form.errors.provider }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="default_model">Default model</Label>
                        <Combobox :model-value="selectedModel" by="id" v-model:open="modelSelectOpen" v-model:search-term="modelSearchQuery" :ignore-filter="true" :disabled="!permissions.can_manage" @update:model-value="selectModel">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child>
                                    <Button id="default_model" type="button" variant="outline" class="w-full justify-between" :class="cn(form.errors.default_model && 'border-destructive')" :disabled="!permissions.can_manage">
                                        <span class="truncate" :class="{ 'text-muted-foreground': !selectedModel }">{{ selectedModelLabel }}</span>
                                        <ChevronsUpDown class="text-muted-foreground ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                                <div class="relative w-full items-center">
                                    <ComboboxInput class="h-10 rounded-none border-0 border-b pr-4 pl-10 focus-visible:ring-0" placeholder="Search models" @update:model-value="(value) => (modelSearchQuery = String(value))" />
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center justify-center px-3">
                                        <Search class="text-muted-foreground size-4" />
                                    </span>
                                </div>

                                <ComboboxViewport class="max-h-[320px] overflow-y-auto">
                                    <ComboboxEmpty v-if="visibleModels.length === 0"> No models found. </ComboboxEmpty>

                                    <ComboboxGroup v-else>
                                        <ComboboxItem v-for="model in visibleModels" :key="model.id" :value="model" class="data-[highlighted]:bg-primary data-[highlighted]:text-primary-foreground px-3 py-2">
                                            <div class="flex min-w-0 flex-1 flex-col">
                                                <span class="truncate text-sm font-medium">{{ model.label }}</span>
                                                <span class="text-muted-foreground truncate text-xs">{{ model.id }}</span>
                                            </div>
                                            <ComboboxItemIndicator>
                                                <Check class="h-4 w-4" />
                                            </ComboboxItemIndicator>
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxViewport>
                            </ComboboxList>
                        </Combobox>
                        <p v-if="form.errors.default_model" class="text-destructive text-sm">{{ form.errors.default_model }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="api_key">API key</Label>
                        <Input id="api_key" v-model="form.api_key" type="password" autocomplete="new-password" :disabled="!permissions.can_manage" />
                        <p class="text-muted-foreground flex items-center gap-2 text-sm">
                            <KeyRound class="h-4 w-4" />
                            <span>{{ keyLabel }}</span>
                        </p>
                        <p v-if="form.errors.api_key" class="text-destructive text-sm">{{ form.errors.api_key }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Status</Label>
                        <div class="flex h-9 items-center gap-3 rounded-md border px-3">
                            <Switch v-model:checked="form.enabled" :disabled="!permissions.can_manage" />
                            <span class="text-sm font-medium">{{ form.enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <p v-if="form.errors.enabled" class="text-destructive text-sm">{{ form.errors.enabled }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Cost Limits</CardTitle>
                    <CardDescription>Cents per staff account.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-5 lg:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="daily_limit_cents">Daily limit</Label>
                        <Input id="daily_limit_cents" v-model.number="form.daily_limit_cents" type="number" min="0" step="1" :disabled="!permissions.can_manage" />
                        <p v-if="form.errors.daily_limit_cents" class="text-destructive text-sm">{{ form.errors.daily_limit_cents }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="monthly_limit_cents">Monthly limit</Label>
                        <Input id="monthly_limit_cents" v-model.number="form.monthly_limit_cents" type="number" min="0" step="1" :disabled="!permissions.can_manage" />
                        <p v-if="form.errors.monthly_limit_cents" class="text-destructive text-sm">{{ form.errors.monthly_limit_cents }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" :disabled="!permissions.can_test || testForm.processing" @click="testProvider">
                        <TestTube2 class="h-4 w-4" />
                        {{ testForm.processing ? 'Testing' : 'Test provider' }}
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button type="button" variant="outline" :disabled="!permissions.can_manage || !setting.has_api_key || clearKeyForm.processing">
                                <Trash2 class="h-4 w-4" />
                                Clear key
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Clear stored API key?</AlertDialogTitle>
                                <AlertDialogDescription>The provider will be disabled until a new key is saved.</AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction class="bg-destructive hover:bg-destructive/90 text-white" @click="clearKey">Clear key</AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>

                <Button type="submit" :disabled="!permissions.can_manage || form.processing">
                    <Save class="h-4 w-4" />
                    {{ form.processing ? 'Saving' : 'Save settings' }}
                </Button>
            </div>
        </form>
    </div>
</template>
