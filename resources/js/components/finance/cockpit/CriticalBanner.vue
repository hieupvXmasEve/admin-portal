<script setup lang="ts">
import { financeRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';

defineProps<{ criticalCount: number }>();
</script>

<template>
    <div v-if="criticalCount > 0" class="flex items-center justify-between rounded-md border border-red-300 bg-red-50 p-3 dark:bg-red-950">
        <div class="flex items-center gap-2 text-red-800 dark:text-red-200">
            <ShieldAlert class="size-5" />
            <span class="font-medium">{{ criticalCount }} vi phạm toàn vẹn tiền nghiêm trọng cần xử lý ngay.</span>
        </div>
        <Link :href="financeRoutes.audit()" class="text-sm font-medium text-red-700 underline dark:text-red-300">Xem chi tiết</Link>
    </div>
</template>