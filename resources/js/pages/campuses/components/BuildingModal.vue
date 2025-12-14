<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import type { Building } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

const props = defineProps<{
    open: boolean;
    campusId: number;
    building?: Building | null;
}>();

const emit = defineEmits(['update:open']);

const api = useApi();
const isEditing = computed(() => !!props.building);

const formSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Building Name is required'),
        code: z.string().min(1, 'Building Code is required'),
        description: z.string().optional(),
        address: z.string().optional(),
    }),
);

const { handleSubmit, isSubmitting, resetForm, setValues, errors, defineField } = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: '',
        code: '',
        description: '',
        address: '',
    },
});

const [name, nameAttrs] = defineField('name');
const [code, codeAttrs] = defineField('code');
const [description, descriptionAttrs] = defineField('description');
const [address, addressAttrs] = defineField('address');

watch(
    () => props.open,
    (newValue) => {
        if (newValue) {
            if (props.building) {
                setValues({
                    name: props.building.name,
                    code: props.building.code,
                    description: props.building.description || '',
                    address: props.building.address || '',
                });
            } else {
                resetForm();
            }
        }
    },
);

const closeDialog = () => {
    emit('update:open', false);
    resetForm();
};

const onSubmit = handleSubmit(async (values) => {
    try {
        if (isEditing.value && props.building) {
            await api.put(systemRoutes.campuses.buildings.update(props.campusId, props.building.id), values);
            toast.success('Building updated successfully');
        } else {
            await api.post(systemRoutes.campuses.buildings.store(props.campusId), values);
            toast.success('Building created successfully');
        }
        closeDialog();
        router.reload({ only: ['buildings'] });
    } catch (error: any) {
         if (error.response?.data?.errors) {
            // Already handled by component UI usually, but toast global error
            toast.error('Please check the form for errors.');
        } else {
            toast.error(isEditing.value ? 'Failed to update building' : 'Failed to create building');
        }
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Building' : 'Add New Building' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update the building details.' : 'Enter details for the new building.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit="onSubmit" class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="name">Building Name <span class="text-destructive">*</span></Label>
                    <Input id="name" v-model="name" v-bind="nameAttrs" placeholder="e.g. Science Block" :class="{ 'border-destructive': errors.name }" />
                    <span v-if="errors.name" class="text-destructive text-sm">{{ errors.name }}</span>
                </div>

                <div class="grid gap-2">
                    <Label for="code">Building Code <span class="text-destructive">*</span></Label>
                    <Input id="code" v-model="code" v-bind="codeAttrs" placeholder="e.g. BLD-01" :class="{ 'border-destructive': errors.code }" />
                    <span v-if="errors.code" class="text-destructive text-sm">{{ errors.code }}</span>
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="description" v-bind="descriptionAttrs" placeholder="Building description..." :class="{ 'border-destructive': errors.description }" />
                    <span v-if="errors.description" class="text-destructive text-sm">{{ errors.description }}</span>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="address" v-bind="addressAttrs" placeholder="Location in campus" :class="{ 'border-destructive': errors.address }" />
                    <span v-if="errors.address" class="text-destructive text-sm">{{ errors.address }}</span>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
