<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CAMPUS_ROUTE_NAMES } from '@/constants';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, LogOut, MapPin } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
    code?: string;
    url?: string;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    campuses: Campus[];
}

const props = defineProps<Props>();
const selectedCampus = ref<Campus | null>(null);

const form = useForm({
    selectedCampus: '' as string | number,
});

onMounted(() => {
    if (props.campuses?.length) {
        // Auto-select first campus and set form value
        selectedCampus.value = props.campuses[0];
        form.selectedCampus = props.campuses[0].id;
    }
});

const handleCampusSelect = (campus: Campus) => {
    selectedCampus.value = campus;
    form.selectedCampus = campus.id;
};

const handleClearSelection = () => {
    selectedCampus.value = null;
    form.selectedCampus = '';
};

const submit = () => {
    if (!selectedCampus.value) return;

    form.post(route(CAMPUS_ROUTE_NAMES.SELECT_CAMPUS_SET_CURRENT), {
        onSuccess: () => {
            console.log('Campus selected successfully:', selectedCampus.value);
        },
        onError: (errors) => {
            console.error('Error selecting campus:', errors);
        },
    });
};

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <Head title="Select campus" />
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-4 md:p-8">
        <div class="mx-auto max-w-6xl">
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="mb-2 text-3xl font-bold text-gray-900 md:text-4xl">Select Your Campus</h1>
                <p class="mx-auto max-w-2xl text-lg text-gray-600">Choose your Swinburne campus to continue with your university experience</p>
            </div>

            <!-- Campus Grid -->
            <div v-if="campuses?.length" class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-2">
                <Card
                    v-for="campus in campuses"
                    :key="campus.id"
                    :class="['cursor-pointer p-0 transition-all duration-300 hover:scale-105 hover:shadow-lg', selectedCampus?.id === campus.id ? 'bg-blue-50 shadow-lg ring-2 ring-blue-500' : 'hover:shadow-md']"
                    @click="handleCampusSelect(campus)"
                >
                    <CardContent class="p-0">
                        <!-- Campus Image -->
                        <div class="relative">
                            <img :src="campus.url || `/placeholder.svg?height=200&width=300&text=${campus.code || campus.name.charAt(0)}`" :alt="campus.name" class="h-48 w-full rounded-t-lg object-cover" />

                            <!-- Selection Indicator -->
                            <div v-if="selectedCampus?.id === campus.id" class="absolute top-3 right-3 rounded-full bg-blue-500 p-1">
                                <CheckCircle2 class="h-5 w-5 text-white" />
                            </div>

                            <!-- Campus Code Badge -->
                            <Badge v-if="campus.code" variant="secondary" class="absolute top-3 left-3 bg-white/90 text-gray-800">
                                {{ campus.code }}
                            </Badge>
                        </div>

                        <!-- Campus Info -->
                        <div class="p-4">
                            <div class="flex items-start gap-2">
                                <MapPin class="mt-1 h-4 w-4 flex-shrink-0 text-gray-500" />
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 md:text-base">
                                        {{ campus.name }}
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- No Campuses Message -->
            <div v-else class="py-12 text-center">
                <p class="text-lg text-gray-500">No campuses available</p>
            </div>

            <!-- Selected Campus Info -->
            <div v-if="selectedCampus" class="mb-6 rounded-lg border border-blue-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="h-6 w-6 text-blue-500" />
                    <div>
                        <h3 class="font-semibold text-gray-900">Selected Campus</h3>
                        <p class="text-gray-600">{{ selectedCampus.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                <Button @click="submit" :disabled="!selectedCampus || form.processing" size="lg" class="w-full px-8 py-3 text-lg font-semibold disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                    {{ form.processing ? 'Processing...' : selectedCampus ? 'Go to Dashboard' : 'Select a Campus' }}
                </Button>

                <Button v-if="selectedCampus" variant="outline" @click="handleClearSelection" size="lg" class="w-full px-6 sm:w-auto" :disabled="form.processing"> Clear Selection </Button>
            </div>

            <!-- Help Text -->
            <div class="mt-6 text-center">
                <div class="text-sm text-gray-500">
                    Need help? Contact your campus administration for assistance.
                    <Link class="inline-flex cursor-pointer items-center font-bold text-red-500 hover:underline" method="post" :href="route('logout')" @click="handleLogout" as="button">
                        <LogOut class="mr-1 h-4 w-4" />
                        Log out
                    </Link>
                    if you're done.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles if needed */
</style>
