<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { GripVertical, Loader2, Package, Plus, Star, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
}

interface MerchandiseImage {
    id: number;
    path: string;
    sort_order: number;
    is_primary: boolean;
}

interface MerchandiseVariant {
    id: number;
    campus_id: number;
    campus?: Campus;
    color: string | null;
    size: string | null;
    sku: string | null;
    stock_quantity: number;
    is_active: boolean;
}

interface MerchandiseDetail {
    id: number;
    name: string;
    description: string | null;
    gold_price: number;
    status: string;
    images: MerchandiseImage[];
    variants: MerchandiseVariant[];
}

interface ValidationErrorItem {
    field: string | null;
    detail: string | null;
}

interface Props {
    merchandise: MerchandiseDetail | null;
    isEditing: boolean;
    campuses: Campus[];
    statuses: string[];
    // Accepted for prop-shape parity with the backend contract; the stock
    // adjustment dialog that consumes movement types lives on the Show page.
    movementTypes: string[];
}

const props = defineProps<Props>();
const api = useApi();
const { confirmDelete } = useGlobalConfirmDialog();

// ── Basic details form ──────────────────────────────────────────────────────

const detailsForm = reactive({
    name: props.merchandise?.name ?? '',
    description: props.merchandise?.description ?? '',
    gold_price: props.merchandise?.gold_price ?? 0,
    status: props.merchandise?.status ?? props.statuses[0] ?? 'active',
});

const detailsErrors = ref<Record<string, string>>({});
const isSavingDetails = ref(false);

const fieldErrorsFrom = (errors: ValidationErrorItem[] | undefined): Record<string, string> => {
    const map: Record<string, string> = {};
    for (const item of errors ?? []) {
        if (item.field) map[item.field] = item.detail ?? 'Invalid value';
    }
    return map;
};

const submitDetails = async () => {
    isSavingDetails.value = true;
    detailsErrors.value = {};

    const payload = {
        name: detailsForm.name,
        description: detailsForm.description || null,
        gold_price: Number(detailsForm.gold_price),
        status: detailsForm.status,
    };

    try {
        const response = props.isEditing && props.merchandise ? await api.put(route('merchandise.update', props.merchandise.id), payload) : await api.post(route('merchandise.store'), payload);

        const body = response.data.value as (ApiResponse & { errors?: ValidationErrorItem[] }) | null;

        if (body?.success && body.data) {
            toast.success(body.message || (props.isEditing ? 'Merchandise updated successfully' : 'Merchandise created successfully'));
            router.visit(route('merchandise.show', body.data.id));
            return;
        }

        detailsErrors.value = fieldErrorsFrom(body?.errors);
        toast.error(body?.message || 'Failed to save merchandise. Please check the form for errors.');
    } catch {
        toast.error('Failed to save merchandise.');
    } finally {
        isSavingDetails.value = false;
    }
};

// ── Images ───────────────────────────────────────────────────────────────

const isUploadingImage = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const reloadMerchandise = () => router.reload({ only: ['merchandise'] });

const handleImageSelected = async (event: Event) => {
    const merchandise = props.merchandise;
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!merchandise || !file) return;

    isUploadingImage.value = true;

    try {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('is_primary', merchandise.images.length === 0 ? '1' : '0');

        const response = await api.post(route('merchandise.images.store', merchandise.id), formData);
        const body = response.data.value as ApiResponse | null;

        if (body?.success) {
            toast.success('Image uploaded successfully');
            reloadMerchandise();
        } else {
            toast.error(body?.message || 'Failed to upload image');
        }
    } catch {
        toast.error('Failed to upload image');
    } finally {
        isUploadingImage.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
};

const setPrimaryImage = async (image: MerchandiseImage) => {
    try {
        const response = await api.post(route('merchandise.images.set-primary', image.id), {});
        const body = response.data.value as ApiResponse | null;

        if (body?.success) {
            toast.success('Primary image updated');
            reloadMerchandise();
        } else {
            toast.error(body?.message || 'Failed to update primary image');
        }
    } catch {
        toast.error('Failed to update primary image');
    }
};

const removeImage = (image: MerchandiseImage) => {
    confirmDelete(`image #${image.id}`, 'image', async () => {
        try {
            const response = await api.delete(route('merchandise.images.destroy', image.id));
            const body = response.data.value as ApiResponse | null;

            if (body?.success) {
                toast.success('Image removed');
                reloadMerchandise();
            } else {
                toast.error(body?.message || 'Failed to remove image');
            }
        } catch {
            toast.error('Failed to remove image');
        }
    });
};

const moveImage = async (image: MerchandiseImage, direction: -1 | 1) => {
    const merchandise = props.merchandise;
    if (!merchandise) return;

    const ordered = [...merchandise.images].sort((a, b) => a.sort_order - b.sort_order);
    const index = ordered.findIndex((item) => item.id === image.id);
    const targetIndex = index + direction;
    if (index === -1 || targetIndex < 0 || targetIndex >= ordered.length) return;

    const reordered = [...ordered];
    [reordered[index], reordered[targetIndex]] = [reordered[targetIndex], reordered[index]];

    try {
        const response = await api.post(route('merchandise.images.reorder', merchandise.id), {
            order: reordered.map((item) => item.id),
        });
        const body = response.data.value as ApiResponse | null;

        if (body?.success) {
            reloadMerchandise();
        } else {
            toast.error(body?.message || 'Failed to reorder images');
        }
    } catch {
        toast.error('Failed to reorder images');
    }
};

// ── Variant matrix ──────────────────────────────────────────────────────────

const newVariant = reactive({
    campus_id: props.campuses[0]?.id ?? null,
    color: '',
    size: '',
    sku: '',
    stock_quantity: 0,
    is_active: true,
});
const isCreatingVariant = ref(false);

const addVariant = async () => {
    const merchandise = props.merchandise;
    if (!merchandise || !newVariant.campus_id) return;

    isCreatingVariant.value = true;

    try {
        const response = await api.post(route('merchandise.variants.store', merchandise.id), {
            campus_id: newVariant.campus_id,
            color: newVariant.color || null,
            size: newVariant.size || null,
            sku: newVariant.sku || null,
            stock_quantity: Number(newVariant.stock_quantity),
            is_active: newVariant.is_active,
        });
        const body = response.data.value as (ApiResponse & { errors?: ValidationErrorItem[] }) | null;

        if (body?.success) {
            toast.success('Variant created successfully');
            newVariant.color = '';
            newVariant.size = '';
            newVariant.sku = '';
            newVariant.stock_quantity = 0;
            reloadMerchandise();
            return;
        }

        toast.error(body?.message || 'Failed to create variant');
    } catch {
        toast.error('Failed to create variant');
    } finally {
        isCreatingVariant.value = false;
    }
};

const variantEdits = reactive<Record<number, { color: string; size: string; sku: string; is_active: boolean }>>({});
const savingVariantId = ref<number | null>(null);

const editableVariant = (variant: MerchandiseVariant) => {
    if (!variantEdits[variant.id]) {
        variantEdits[variant.id] = {
            color: variant.color ?? '',
            size: variant.size ?? '',
            sku: variant.sku ?? '',
            is_active: variant.is_active,
        };
    }
    return variantEdits[variant.id];
};

const saveVariant = async (variant: MerchandiseVariant) => {
    const edits = editableVariant(variant);
    savingVariantId.value = variant.id;

    try {
        const response = await api.put(route('merchandise.variants.update', variant.id), {
            color: edits.color || null,
            size: edits.size || null,
            sku: edits.sku || null,
            is_active: edits.is_active,
        });
        const body = response.data.value as ApiResponse | null;

        if (body?.success) {
            toast.success('Variant updated successfully');
            reloadMerchandise();
        } else {
            toast.error(body?.message || 'Failed to update variant');
        }
    } catch {
        toast.error('Failed to update variant');
    } finally {
        savingVariantId.value = null;
    }
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Merchandise' : 'Create Merchandise'" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">
                {{ isEditing ? 'Edit Merchandise' : 'Create Merchandise' }}
            </h2>
            <p class="text-muted-foreground mt-1 text-sm">Manage the store item, its images, and per-campus stock.</p>
        </div>
        <Button variant="outline" as-child>
            <Link :href="isEditing && merchandise ? route('merchandise.show', merchandise.id) : route('merchandise.index')"> Cancel </Link>
        </Button>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Item Details</CardTitle>
            <CardDescription>Name, description, gold price, and store visibility.</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-6" @submit.prevent="submitDetails">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label for="name">Name <span class="text-destructive">*</span></Label>
                        <Input id="name" v-model="detailsForm.name" type="text" required maxlength="150" :disabled="isSavingDetails" />
                        <p v-if="detailsErrors.name" class="text-destructive text-sm">{{ detailsErrors.name }}</p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" v-model="detailsForm.description" rows="4" :disabled="isSavingDetails" />
                        <p v-if="detailsErrors.description" class="text-destructive text-sm">{{ detailsErrors.description }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="gold_price">Gold Price <span class="text-destructive">*</span></Label>
                        <Input id="gold_price" v-model.number="detailsForm.gold_price" type="number" step="1" min="0" required :disabled="isSavingDetails" />
                        <p v-if="detailsErrors.gold_price" class="text-destructive text-sm">{{ detailsErrors.gold_price }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="detailsForm.status" :disabled="isSavingDetails">
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="status in statuses" :key="status" :value="status">{{ status }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="detailsErrors.status" class="text-destructive text-sm">{{ detailsErrors.status }}</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="isSavingDetails" class="gap-2">
                        <Loader2 v-if="isSavingDetails" class="h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Save Details' : 'Create Merchandise' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>

    <template v-if="isEditing && merchandise">
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Images</CardTitle>
                <CardDescription>Upload product photos. The primary image shows in the catalog list.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap gap-4">
                    <div v-for="image in [...merchandise.images].sort((a, b) => a.sort_order - b.sort_order)" :key="image.id" class="relative w-32 space-y-2 rounded-md border p-2">
                        <img :src="image.path" :alt="`Merchandise image ${image.id}`" width="120" height="120" loading="lazy" class="h-28 w-full rounded object-cover" />
                        <Badge v-if="image.is_primary" variant="success" class="absolute top-3 left-3">
                            <Star class="h-3 w-3" />
                        </Badge>
                        <div class="flex items-center justify-between gap-1">
                            <Button variant="ghost" size="icon" title="Move earlier" @click="moveImage(image, -1)">
                                <GripVertical class="h-3 w-3" />
                            </Button>
                            <Button v-if="!image.is_primary" variant="ghost" size="icon" title="Set as primary" @click="setPrimaryImage(image)">
                                <Star class="h-3 w-3" />
                            </Button>
                            <Button variant="ghost" size="icon" title="Remove image" @click="removeImage(image)">
                                <Trash2 class="text-destructive h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                    <div v-if="merchandise.images.length === 0" class="text-muted-foreground flex h-28 w-32 items-center justify-center rounded-md border border-dashed text-xs">No images yet</div>
                </div>

                <div class="space-y-2">
                    <Label for="image-upload">Upload image</Label>
                    <input
                        id="image-upload"
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        :disabled="isUploadingImage"
                        class="border-input file:bg-secondary file:text-secondary-foreground block w-full rounded-md border text-sm file:mr-4 file:border-0 file:px-4 file:py-2"
                        @change="handleImageSelected"
                    />
                </div>
            </CardContent>
        </Card>

        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Variants</CardTitle>
                <CardDescription>Per-campus color/size combinations and starting stock.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <Table v-if="merchandise.variants.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Campus</TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead>Size</TableHead>
                            <TableHead>SKU</TableHead>
                            <TableHead>Stock</TableHead>
                            <TableHead>Active</TableHead>
                            <TableHead></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="variant in merchandise.variants" :key="variant.id">
                            <TableCell>{{ variant.campus?.name ?? variant.campus_id }}</TableCell>
                            <TableCell>
                                <Input v-model="editableVariant(variant).color" type="text" class="h-8 w-28" />
                            </TableCell>
                            <TableCell>
                                <Input v-model="editableVariant(variant).size" type="text" class="h-8 w-20" />
                            </TableCell>
                            <TableCell>
                                <Input v-model="editableVariant(variant).sku" type="text" class="h-8 w-32" />
                            </TableCell>
                            <TableCell>
                                <span class="text-muted-foreground" title="Adjust stock from the merchandise detail page">{{ variant.stock_quantity }}</span>
                            </TableCell>
                            <TableCell>
                                <Checkbox v-model="editableVariant(variant).is_active" />
                            </TableCell>
                            <TableCell>
                                <Button size="sm" variant="outline" :disabled="savingVariantId === variant.id" @click="saveVariant(variant)">
                                    {{ savingVariantId === variant.id ? 'Saving...' : 'Save' }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-muted-foreground flex items-center gap-2 text-sm">
                    <Package class="h-4 w-4" />
                    No variants yet — add the first one below.
                </p>

                <div class="grid grid-cols-2 gap-3 rounded-md border p-4 md:grid-cols-6">
                    <div class="space-y-1">
                        <Label>Campus</Label>
                        <Select v-model="newVariant.campus_id">
                            <SelectTrigger><SelectValue placeholder="Campus" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label>Color</Label>
                        <Input v-model="newVariant.color" type="text" />
                    </div>
                    <div class="space-y-1">
                        <Label>Size</Label>
                        <Input v-model="newVariant.size" type="text" />
                    </div>
                    <div class="space-y-1">
                        <Label>SKU</Label>
                        <Input v-model="newVariant.sku" type="text" />
                    </div>
                    <div class="space-y-1">
                        <Label>Initial stock</Label>
                        <Input v-model.number="newVariant.stock_quantity" type="number" min="0" step="1" />
                    </div>
                    <div class="flex items-end">
                        <Button type="button" class="w-full gap-2" :disabled="isCreatingVariant" @click="addVariant">
                            <Plus class="h-4 w-4" />
                            Add variant
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </template>
</template>
