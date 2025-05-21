<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Users',
        href: '/users',
    },
];
</script>

<template>
    <Head title="List Users" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1>Users</h1>
        </div>
    </AppLayout>
</template>

<style scoped></style>
