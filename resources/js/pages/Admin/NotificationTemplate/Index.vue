<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MailIcon, MailPlusIcon, PencilIcon, PlusCircleIcon } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
    code: string;
}

interface UpdatedBy {
    id: number;
    name: string;
}

interface NotificationTemplate {
    id: number;
    campus_id: number;
    campus: Campus | null;
    type_key: string;
    subject: string;
    updated_at: string;
    updated_by_user_id: number | null;
    updated_by: UpdatedBy | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTemplates {
    data: NotificationTemplate[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
}

interface MissingTemplate {
    type_key: string;
    variables: Record<string, { label: string; sample: unknown }>;
}

interface Props {
    templates: PaginatedTemplates;
    missingTemplates: MissingTemplate[];
    currentCampus: Campus;
}

const props = defineProps<Props>();

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('vi-VN');
};

const formatTypeKey = (key: string): string => {
    return key
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const handlePaginationNavigate = (url: string) => {
    // Use Inertia router so the SPA stack stays intact (scroll, flash, sticky
    // props) instead of forcing a full page reload via window.location.
    router.visit(url, { preserveScroll: true, preserveState: true });
};

const createForm = useForm({ type_key: '' });

const createTemplate = (typeKey: string) => {
    createForm.type_key = typeKey;
    createForm.post(route('admin.notification-templates.store'));
};

const formatVariableToken = (varName: string): string => `{{${varName}}}`;
</script>

<template>
    <Head title="Notification Templates" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Notification Email Templates</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Email templates for <span class="font-medium">{{ props.currentCampus.name }}</span>
                    ({{ props.currentCampus.code }}).
                </p>
            </div>
        </div>

        <!-- Missing templates — admin must create on demand instead of a migration backfilling them -->
        <Card v-if="props.missingTemplates.length > 0" class="border-amber-200 bg-amber-50/40">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-amber-800">
                    <MailPlusIcon class="h-5 w-5" />
                    Missing templates
                </CardTitle>
                <CardDescription class="text-amber-700">
                    These notification types are wired in code but have no row in <span class="font-medium">{{ props.currentCampus.name }}</span> yet.
                    Sending will fail until you create them. Click "Create" to author the subject + body from scratch.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <ul class="divide-y divide-amber-100">
                    <li
                        v-for="missing in props.missingTemplates"
                        :key="missing.type_key"
                        class="flex items-center justify-between px-6 py-4"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                                <MailPlusIcon class="h-5 w-5 text-amber-700" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ formatTypeKey(missing.type_key) }}
                                    </span>
                                    <Badge variant="outline" class="border-amber-300 text-amber-800 text-xs">
                                        {{ missing.type_key }}
                                    </Badge>
                                </div>
                                <p class="mt-0.5 text-xs text-amber-700">
                                    Variables:
                                    <span
                                        v-for="(meta, varName) in missing.variables"
                                        :key="varName"
                                        class="mr-1 font-mono"
                                    >{{ formatVariableToken(String(varName)) }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="ml-4 shrink-0">
                            <Button
                                size="sm"
                                variant="default"
                                :disabled="createForm.processing && createForm.type_key === missing.type_key"
                                @click="createTemplate(missing.type_key)"
                            >
                                <PlusCircleIcon class="mr-1.5 h-3.5 w-3.5" />
                                {{ createForm.processing && createForm.type_key === missing.type_key ? 'Creating...' : 'Create' }}
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Template list -->
        <Card>
            <CardHeader>
                <CardTitle>Templates</CardTitle>
                <CardDescription>
                    {{ props.templates.total }} template{{ props.templates.total !== 1 ? 's' : '' }}
                    in {{ props.currentCampus.name }}.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="props.templates.data.length > 0">
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="template in props.templates.data"
                            :key="template.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div class="flex items-start gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100">
                                    <MailIcon class="h-5 w-5 text-blue-600" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ template.campus?.name ?? 'Unknown Campus' }}
                                        </span>
                                        <Badge variant="secondary" class="text-xs">
                                            {{ formatTypeKey(template.type_key) }}
                                        </Badge>
                                    </div>
                                    <p class="mt-0.5 truncate text-sm text-gray-500 max-w-md">
                                        {{ template.subject }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        Updated {{ formatDate(template.updated_at) }}
                                        <template v-if="template.updated_by">
                                            by {{ template.updated_by.name }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <div class="ml-4 shrink-0">
                                <Link :href="route('admin.notification-templates.edit', { template: template.id })">
                                    <Button size="sm" variant="outline">
                                        <PencilIcon class="mr-1.5 h-3.5 w-3.5" />
                                        Edit
                                    </Button>
                                </Link>
                            </div>
                        </li>
                    </ul>

                    <!-- Pagination -->
                    <div class="border-t border-gray-200 px-6 py-3">
                        <DataPagination
                            :pagination-data="props.templates"
                            item-name="templates"
                            :page-size-options="[20, 50]"
                            @navigate="handlePaginationNavigate"
                        />
                    </div>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <MailIcon class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No templates</h3>
                    <p class="mt-1 text-sm text-gray-500">No notification templates found.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
