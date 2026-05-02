<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { route } from 'ziggy-js';
import { ref } from 'vue';

interface Props {
    campus: { id: number; name: string };
}

const props = defineProps<Props>();

const modalRef = ref<InstanceType<typeof Modal> | null>(null);

const form = useForm({
    name: '',
    code: '',
    description: '',
    address: '',
});

const submit = () => {
    form.post(route('campuses.buildings.store', props.campus.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Đóng modal thủ công khi thành công
            modalRef.value?.close();
            form.reset();
        },
        onError: () => {
            // Error sẽ được hiển thị qua form.errors
        },
    });
};
</script>

<template>
    <Modal ref="modalRef">
        <div class="p-6">
            <h2 class="mb-1 text-lg font-semibold">Add Building</h2>
            <p class="text-muted-foreground mb-5 text-sm">Add a new building to {{ campus.name }}</p>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Building Name <span class="text-destructive">*</span></Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="e.g. Science Block"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="code">Building Code <span class="text-destructive">*</span></Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        placeholder="e.g. BLD-01"
                        :class="{ 'border-destructive': form.errors.code }"
                    />
                    <InputError :message="form.errors.code" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Building description..."
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="address">Address</Label>
                    <Input
                        id="address"
                        v-model="form.address"
                        placeholder="Location in campus"
                    />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Building' }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
