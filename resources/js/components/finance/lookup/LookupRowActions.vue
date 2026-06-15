<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink, FileSearch } from 'lucide-vue-next';
import { financeRoutes } from '@/utils/routes';

defineProps<{ studentId: number; focus?: string; detailUrl?: string }>();
</script>

<template>
    <div class="flex items-center justify-end gap-1">
        <Link
            :href="financeRoutes.students.overview(studentId, focus)"
            class="rounded p-1 hover:bg-muted"
            title="Mở hồ sơ SV (360)"
        >
            <ExternalLink class="h-4 w-4" />
        </Link>
        <Link v-if="detailUrl" :href="detailUrl" class="rounded p-1 hover:bg-muted" title="Chi tiết (audit)">
            <FileSearch class="h-4 w-4" />
        </Link>
    </div>
</template>