<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        // This will be implemented later

        // For now, show placeholder
        setTimeout(() => {
            loading.value = false;
        }, 1000);
    } catch (err) {
        error.value = 'Failed to load recent activities';
        loading.value = false;
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2"> 🕒 Recent Activities </CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
                <span class="text-muted-foreground ml-3">Loading activities...</span>
            </div>

            <div v-else-if="error" class="py-8 text-center text-red-600">
                <div class="mb-2 text-xl">❌</div>
                <div>{{ error }}</div>
            </div>

            <div v-else
                class="text-muted-foreground border-muted-foreground/25 rounded-lg border-2 border-dashed py-12 text-center">
                <div class="mb-4 text-4xl">📋</div>
                <div class="text-lg font-medium">Recent Activities Feed</div>
                <div class="mt-2 text-sm">Latest course registrations, class sessions, and bookings</div>
                <div class="text-muted-foreground/70 mt-1 text-xs">Data endpoint: /api/admin/dashboard/recent-activities
                </div>
            </div>
        </CardContent>
    </Card>
</template>
