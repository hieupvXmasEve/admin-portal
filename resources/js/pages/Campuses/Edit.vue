<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    campus: {
        id: number;
        name: string;
        code: string;
        address: string;
    };
}

const props = defineProps<Props>();

const modalRef = ref<InstanceType<typeof Modal> | null>(null);

const form = useForm({
    name: props.campus.name,
    code: props.campus.code,
    address: props.campus.address,
});

const submit = () => {
    form.put(route('campuses.update', props.campus.id), {
        preserveScroll: true,
        onSuccess: () => {
            modalRef.value?.close();
        },
    });
};
</script>

<template>
    <Modal ref="modalRef">
        <div class="p-6">
            <h2 class="mb-1 text-lg font-semibold">Edit Campus</h2>
            <p class="text-muted-foreground mb-5 text-sm">Update campus location details.</p>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Campus Name <span class="text-destructive">*</span></Label>
                    <Input id="name" v-model="form.name" placeholder="e.g., Swinburne Hanoi" :class="{ 'border-destructive': form.errors.name }" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="code">Campus Code <span class="text-destructive">*</span></Label>
                    <Input id="code" v-model="form.code" placeholder="e.g., HN, HCM, DN" class="font-mono" :class="{ 'border-destructive': form.errors.code }" />
                    <InputError :message="form.errors.code" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="address">Campus Address <span class="text-destructive">*</span></Label>
                    <Textarea id="address" v-model="form.address" placeholder="Enter the full address of the campus..." rows="3" :class="{ 'border-destructive': form.errors.address }" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
