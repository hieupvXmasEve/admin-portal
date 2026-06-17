<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Bell, GraduationCap, Layers, Lock, Send } from 'lucide-vue-next';

defineProps<{ jobs: { charge_generation: boolean; dng_push: boolean; reminder: boolean } }>();

const tiles = [
    {
        key: 'charge_generation' as const,
        title: 'Sinh phí hàng loạt',
        desc: 'HP · EGC · Phí phi học vụ',
        icon: GraduationCap,
        href: financeRoutes.batchStudio.charges(),
    },
    {
        key: 'dng_push' as const,
        title: 'Lập yêu cầu thanh toán DNG',
        desc: 'Tạo yêu cầu thanh toán cho nhiều SV',
        icon: Send,
        href: financeRoutes.batchStudio.dng(),
    },
    {
        key: 'reminder' as const,
        title: 'Nhắc nợ hàng loạt',
        desc: 'Gửi email nhắc thanh toán SV / phụ huynh',
        icon: Bell,
        href: financeRoutes.batchStudio.reminders(),
    },
];
</script>

<template>
    <Head title="Batch Studio" />

    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <div class="bg-primary/10 text-primary flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                <Layers class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Batch Studio</h1>
                <p class="text-muted-foreground mt-1 max-w-2xl text-sm">Một khuôn xem trước → xác nhận → kết quả cho mọi thao tác hàng loạt. Số liệu preview được khóa bằng token trước khi ghi.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <component :is="jobs[tile.key] ? Link : 'div'" v-for="tile in tiles" :key="tile.key" :href="jobs[tile.key] ? tile.href : undefined" class="group block h-full">
                <Card class="h-full transition" :class="jobs[tile.key] ? 'hover:border-primary/40 hover:shadow-md' : 'cursor-not-allowed opacity-60'">
                    <CardHeader class="space-y-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg border" :class="jobs[tile.key] ? 'bg-background' : 'bg-muted'">
                            <component :is="tile.icon" class="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle class="text-lg">{{ tile.title }}</CardTitle>
                            <CardDescription class="mt-1">{{ tile.desc }}</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <p v-if="jobs[tile.key]" class="text-primary inline-flex items-center gap-1 text-sm font-medium opacity-0 transition group-hover:opacity-100">
                            Mở wizard
                            <ArrowRight class="h-4 w-4" />
                        </p>
                        <p v-else class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                            <Lock class="h-3.5 w-3.5" />
                            Bạn không có quyền chạy việc này
                        </p>
                    </CardContent>
                </Card>
            </component>
        </div>
    </div>
</template>
