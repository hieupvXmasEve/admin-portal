<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, GraduationCap, Layers, Lock, Send } from 'lucide-vue-next';

defineProps<{ jobs: { charge_generation: boolean; dng_push: boolean } }>();

const tiles = [
    {
        key: 'charge_generation' as const,
        n: '1',
        title: 'Sinh phí hàng loạt',
        desc: 'HP · EGC · Phí phi học vụ',
        cta: 'Mở sinh phí',
        icon: GraduationCap,
        href: financeRoutes.batchStudio.charges(),
    },
    {
        key: 'dng_push' as const,
        n: '2',
        title: 'Lập yêu cầu thanh toán DNG',
        desc: 'Tạo yêu cầu thanh toán cho nhiều SV',
        cta: 'Xem lệnh thu',
        icon: Send,
        href: financeRoutes.batchStudio.dng(),
    },
];
</script>

<template>
    <Head title="Sinh phí & lệnh thu" />

    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <div class="bg-primary/10 text-primary flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                <Layers class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Sinh phí & lệnh thu</h1>
                <p class="text-muted-foreground mt-1 max-w-2xl text-sm">Sinh phí trước, lập lệnh thu sau. Lập lệnh thu dùng được riêng nếu đã có khoản phải thu.</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-stretch">
            <template v-for="(tile, index) in tiles" :key="tile.key">
                <component :is="jobs[tile.key] ? Link : 'div'" :href="jobs[tile.key] ? tile.href : undefined" class="group block h-full min-w-0 flex-1">
                    <Card class="h-full transition" :class="jobs[tile.key] ? 'hover:border-primary/40 hover:shadow-md' : 'cursor-not-allowed opacity-60'">
                        <CardHeader class="space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full border text-sm font-semibold" :class="jobs[tile.key] ? 'border-primary/40 bg-primary/5 text-primary' : 'bg-muted text-muted-foreground'">
                                    {{ tile.n }}
                                </span>
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg border" :class="jobs[tile.key] ? 'bg-background' : 'bg-muted'">
                                    <component :is="tile.icon" class="h-5 w-5" />
                                </div>
                            </div>
                            <div>
                                <CardTitle class="text-lg">{{ tile.title }}</CardTitle>
                                <CardDescription class="mt-1">{{ tile.desc }}</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <p v-if="jobs[tile.key]" class="text-primary inline-flex items-center gap-1 text-sm font-medium opacity-0 transition group-hover:opacity-100">
                                {{ tile.cta }}
                                <ArrowRight class="h-4 w-4" />
                            </p>
                            <p v-else class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                <Lock class="h-3.5 w-3.5" />
                                Bạn không có quyền chạy việc này
                            </p>
                        </CardContent>
                    </Card>
                </component>
                <div v-if="index === 0" class="text-muted-foreground hidden shrink-0 items-center md:flex" aria-hidden="true">
                    <ArrowRight class="h-6 w-6" />
                </div>
            </template>
        </div>
    </div>
</template>
