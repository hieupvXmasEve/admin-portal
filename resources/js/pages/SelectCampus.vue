<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    campuses: Array<{
        id: number;
        name: string;
    }>,
});

const form = useForm({
    selectedCampus: '' as string | number,
});

onMounted(() => {
    if (props.campuses?.length) {
        form.selectedCampus = props.campuses[0].id;
    }
});

const submit = () => {
    form.post(route('select-campus.set-current'), {
        onSuccess: () => {
            console.log('ok');
            // window.location.href = route('dashboard');
        },
    });
};
</script>

<template>
    <div class="flex h-screen items-center justify-center">
        <div class="w-96 rounded bg-white p-8 shadow">
            <h2 class="mb-4 text-lg font-bold">Choose Campus</h2>
            <div v-if="campuses?.length">
                <!--                <div v-if="error" class="mb-2 text-red-500">{{ error }}</div>-->
                <!--                <div v-if="success" class="mb-2 text-green-500">Lưu thành công!</div>-->
                <RadioGroup v-model="form.selectedCampus">
                    <div v-for="campus in campuses" :key="campus.id" class="mb-2 flex items-center space-x-2">
                        <RadioGroupItem :id="'campus-' + campus.id" :value="campus.id" class="cursor-pointer" />
                        <Label :for="'campus-' + campus.id" class="cursor-pointer">{{ campus.name }}</Label>
                    </div>
                </RadioGroup>
                <button @click="submit" class="mt-4 cursor-pointer rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Confirm</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-white {
    background: #fff;
}
</style>
