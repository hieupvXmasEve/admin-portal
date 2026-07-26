<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import type { BrandingSlot, SystemConfig, SystemConfigTextUpdate } from '@/types/systemConfig';
import { Head, useForm } from '@inertiajs/vue3';
import { ImageOff, Save, Settings, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';

interface Props {
    config: SystemConfig;
    permissions: { can_manage: boolean };
}

interface BrandingAsset {
    slot: BrandingSlot;
    label: string;
    description: string;
    url_key: keyof Pick<SystemConfig, 'logo_full_url' | 'logo_text_url' | 'favicon_url' | 'apple_touch_icon_url'>;
    preview_class: string;
}

const props = defineProps<Props>();

const brandingAssets: BrandingAsset[] = [
    { slot: 'logo_full', label: 'Full logo', description: 'Use for sign-in and larger brand placements.', url_key: 'logo_full_url', preview_class: 'h-20 w-full' },
    { slot: 'logo_text', label: 'Compact logo', description: 'Use for compact navigation and small brand placements.', url_key: 'logo_text_url', preview_class: 'h-14 w-40' },
    { slot: 'favicon', label: 'Favicon', description: 'Use for browser tabs and bookmarks.', url_key: 'favicon_url', preview_class: 'size-12' },
    { slot: 'apple_touch_icon', label: 'Apple touch icon', description: 'Use when the app is saved to an Apple device home screen.', url_key: 'apple_touch_icon_url', preview_class: 'size-16' },
];

const textValues = (config: SystemConfig): SystemConfigTextUpdate => ({
    app_name: config.app_name,
    copyright_text: config.copyright_text,
    country: config.country,
});

const textForm = useForm<SystemConfigTextUpdate>(textValues(props.config));
const uploadForms = {
    logo_full: useForm({ slot: 'logo_full' as BrandingSlot, file: null as File | null }),
    logo_text: useForm({ slot: 'logo_text' as BrandingSlot, file: null as File | null }),
    favicon: useForm({ slot: 'favicon' as BrandingSlot, file: null as File | null }),
    apple_touch_icon: useForm({ slot: 'apple_touch_icon' as BrandingSlot, file: null as File | null }),
};
const inputKeys = reactive<Record<BrandingSlot, number>>({ logo_full: 0, logo_text: 0, favicon: 0, apple_touch_icon: 0 });
const localPreviews = reactive<Record<BrandingSlot, string | null>>({ logo_full: null, logo_text: null, favicon: null, apple_touch_icon: null });
const imageFailures = reactive<Record<BrandingSlot, boolean>>({ logo_full: false, logo_text: false, favicon: false, apple_touch_icon: false });

const canManage = computed(() => props.permissions.can_manage);
const hasTextChanges = computed(() => textForm.isDirty);

watch(
    () => props.config,
    (config) => {
        textForm.defaults(textValues(config));
        textForm.reset();
    },
    { deep: true },
);

const currentUrl = (asset: BrandingAsset): string | null => props.config[asset.url_key];
const previewUrl = (asset: BrandingAsset): string | null => localPreviews[asset.slot] ?? currentUrl(asset);

const selectFile = (asset: BrandingAsset, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    const form = uploadForms[asset.slot];

    form.clearErrors('file');
    form.file = file;
    imageFailures[asset.slot] = false;

    if (localPreviews[asset.slot]) {
        URL.revokeObjectURL(localPreviews[asset.slot] as string);
    }
    localPreviews[asset.slot] = file ? URL.createObjectURL(file) : null;
};

const resetUpload = (slot: BrandingSlot) => {
    const preview = localPreviews[slot];
    if (preview) URL.revokeObjectURL(preview);

    uploadForms[slot].reset();
    uploadForms[slot].clearErrors();
    localPreviews[slot] = null;
    inputKeys[slot] += 1;
};

const submitText = () => {
    textForm.put(route('system.config.update'), { preserveScroll: true });
};

const submitUpload = (slot: BrandingSlot) => {
    const form = uploadForms[slot];
    if (!form.file) {
        form.setError('file', 'Select a PNG, JPEG, or WebP image before uploading.');
        return;
    }

    form.post(route('system.config.upload'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => resetUpload(slot),
    });
};

onBeforeUnmount(() => {
    Object.values(localPreviews).forEach((preview) => {
        if (preview) URL.revokeObjectURL(preview);
    });
});
</script>

<template>
    <Head title="System configuration" />

    <div class="space-y-6 p-6">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Settings class="size-6" />
                System configuration
            </h1>
            <p class="text-muted-foreground">Manage the staff application’s public name, legal text, and branding.</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Application details</CardTitle>
                <CardDescription>These values appear across the staff application after you save them.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="submitText">
                    <div class="space-y-2">
                        <Label for="app_name">Application name</Label>
                        <Input id="app_name" v-model="textForm.app_name" :disabled="textForm.processing || !canManage" autocomplete="organization" required />
                        <InputError :message="textForm.errors.app_name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="copyright_text">Copyright text</Label>
                        <Textarea id="copyright_text" v-model="textForm.copyright_text" :disabled="textForm.processing || !canManage" rows="3" required />
                        <InputError :message="textForm.errors.copyright_text" />
                    </div>
                    <div class="space-y-2">
                        <Label for="country">Country</Label>
                        <Input id="country" v-model="textForm.country" :disabled="textForm.processing || !canManage" autocomplete="country-name" required />
                        <InputError :message="textForm.errors.country" />
                    </div>
                    <div class="flex flex-wrap justify-end gap-3 border-t pt-6">
                        <Button type="button" variant="outline" :disabled="textForm.processing || !hasTextChanges || !canManage" @click="textForm.reset()">Reset</Button>
                        <Button type="submit" :disabled="textForm.processing || !hasTextChanges || !canManage">
                            <Save class="mr-2 size-4" />
                            {{ textForm.processing ? 'Saving…' : 'Save changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Branding assets</CardTitle>
                <CardDescription>Upload raster PNG, JPEG, or WebP files. New assets receive immutable URLs, so no cache refresh is required.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6 lg:grid-cols-2">
                <section v-for="asset in brandingAssets" :key="asset.slot" class="space-y-4 rounded-lg border p-4" :aria-labelledby="`${asset.slot}-heading`">
                    <div>
                        <h2 :id="`${asset.slot}-heading`" class="font-semibold">{{ asset.label }}</h2>
                        <p class="text-muted-foreground text-sm">{{ asset.description }}</p>
                    </div>

                    <div class="bg-muted/40 flex min-h-24 items-center justify-center rounded-md border p-3">
                        <img v-if="previewUrl(asset) && !imageFailures[asset.slot]" :src="previewUrl(asset) as string" :alt="`${asset.label} preview`" :class="[asset.preview_class, 'object-contain']" @error="imageFailures[asset.slot] = true" />
                        <div v-else class="text-muted-foreground flex items-center gap-2 text-sm" role="status">
                            <ImageOff class="size-4" />
                            {{ imageFailures[asset.slot] ? 'The current asset could not be displayed.' : 'No asset uploaded yet.' }}
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="submitUpload(asset.slot)">
                        <div class="space-y-2">
                            <Label :for="`${asset.slot}-file`">Choose {{ asset.label.toLowerCase() }}</Label>
                            <Input :key="inputKeys[asset.slot]" :id="`${asset.slot}-file`" type="file" accept="image/png,image/jpeg,image/webp" :disabled="uploadForms[asset.slot].processing || !canManage" @change="selectFile(asset, $event)" />
                            <InputError :message="uploadForms[asset.slot].errors.file" />
                        </div>
                        <div v-if="uploadForms[asset.slot].progress" class="space-y-1" aria-live="polite">
                            <div class="text-muted-foreground flex justify-between text-xs">
                                <span>Uploading</span><span>{{ uploadForms[asset.slot].progress?.percentage }}%</span>
                            </div>
                            <Progress :model-value="uploadForms[asset.slot].progress?.percentage ?? 0" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" :disabled="uploadForms[asset.slot].processing || !uploadForms[asset.slot].file || !canManage" @click="resetUpload(asset.slot)">Clear</Button>
                            <Button type="submit" :disabled="uploadForms[asset.slot].processing || !uploadForms[asset.slot].file || !canManage">
                                <Upload class="mr-2 size-4" />
                                {{ uploadForms[asset.slot].processing ? 'Uploading…' : 'Upload asset' }}
                            </Button>
                        </div>
                    </form>
                </section>
            </CardContent>
        </Card>

        <p v-if="!canManage" class="text-muted-foreground text-sm" role="status">You do not have permission to update global system configuration.</p>
    </div>
</template>
