<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { AlertTriangleIcon, BellIcon, CheckCircleIcon, ClockIcon, CopyIcon, EyeIcon, FileTextIcon, GraduationCapIcon, MailIcon, MegaphoneIcon, PencilIcon, PlusIcon, RefreshCwIcon, SearchIcon, TrashIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import TemplatePreviewModal from './components/TemplatePreviewModal.vue';

interface EmailTemplate {
    id: number;
    name: string;
    type: string;
    subject: string;
    is_active: boolean;
    version: number;
    description?: string;
    variables?: string[];
    created_at: string;
    updated_at: string;
    variables_count: number;
}

interface PaginatedTemplates {
    data: EmailTemplate[];
    current_page: number;
    from: number;
    to: number;
    total: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    templates: PaginatedTemplates;
    templateTypes: Record<string, string>;
    filters: {
        type: string;
        is_active: string;
        search: string;
    };
}

const props = defineProps<Props>();
const { confirmDelete } = useGlobalConfirmDialog();

const filters = ref({
    type: props.filters.type || 'all',
    is_active: props.filters.is_active || 'all',
    search: props.filters.search || '',
});

const showPreviewModal = ref(false);
const previewingTemplate = ref<EmailTemplate | null>(null);
const previewData = ref<any>(null);
const isRefreshing = ref(false);

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

const applyFilters = () => {
    router.get('/systems/email-templates', filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const refreshTemplates = () => {
    isRefreshing.value = true;
    router.get('/systems/email-templates', filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const editTemplate = (template: EmailTemplate) => {
    router.visit(`/systems/email-templates/${template.id}/edit`);
};

const createVersion = (template: EmailTemplate) => {
    router.visit(`/systems/email-templates/create?template_id=${template.id}&create_version=true`);
};

const previewTemplate = async (template: EmailTemplate) => {
    try {
        const response = await fetch(`/systems/email-templates/${template.id}/preview`);
        const data = await response.json();

        if (data.success) {
            previewingTemplate.value = template;
            previewData.value = data.previewData;
            showPreviewModal.value = true;
        } else {
            console.error('Failed to preview template:', data.message);
        }
    } catch (error) {
        console.error('Failed to preview template:', error);
    }
};

const deleteTemplate = (template: EmailTemplate) => {
    confirmDelete(template.name, 'template', () => {
        router.delete(`/systems/email-templates/${template.id}`, {
            onSuccess: () => {
                // Refresh the current page
                router.get('/systems/email-templates', filters.value, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            },
        });
    });
};

const handlePaginationNavigate = (url: string) => {
    router.get(url, filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const newFilters = { ...filters.value, per_page: pageSize };
    router.get('/systems/email-templates', newFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const getTypeColor = (type: string) => {
    const colors = {
        welcome: 'bg-blue-500',
        grade_notification: 'bg-green-500',
        course_registration: 'bg-purple-500',
        academic_hold: 'bg-red-500',
        enrollment_confirmation: 'bg-emerald-500',
        assessment_deadline: 'bg-orange-500',
        system_announcement: 'bg-indigo-500',
        reminder: 'bg-yellow-500',
        custom: 'bg-gray-500',
    };
    return colors[type as keyof typeof colors] || 'bg-gray-500';
};

const getTypeIcon = (type: string) => {
    const icons = {
        welcome: BellIcon,
        grade_notification: GraduationCapIcon,
        course_registration: FileTextIcon,
        academic_hold: AlertTriangleIcon,
        enrollment_confirmation: CheckCircleIcon,
        assessment_deadline: ClockIcon,
        system_announcement: MegaphoneIcon,
        reminder: ClockIcon,
        custom: FileTextIcon,
    };
    return icons[type as keyof typeof icons] || FileTextIcon;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Email Templates</h1>
                <p class="mt-1 text-sm text-gray-500">Manage email templates for automated notifications and communications</p>
            </div>
            <div class="flex items-center space-x-3">
                <button
                    @click="refreshTemplates"
                    :disabled="isRefreshing"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm leading-4 font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                >
                    <RefreshCwIcon :class="['mr-2 h-4 w-4', { 'animate-spin': isRefreshing }]" />
                    Refresh
                </button>
                <a
                    href="/systems/email-templates/create"
                    class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-3 py-2 text-sm leading-4 font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                >
                    <PlusIcon class="mr-2 h-4 w-4" />
                    Create Template
                </a>
            </div>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="type-filter">Template Type</Label>
                        <Select v-model="filters.type" @update:model-value="applyFilters">
                            <SelectTrigger id="type-filter">
                                <SelectValue placeholder="All Types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem v-for="(label, value) in templateTypes" :key="value" :value="value">
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="status-filter">Status</Label>
                        <Select v-model="filters.is_active" @update:model-value="applyFilters">
                            <SelectTrigger id="status-filter">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="search">Search</Label>
                        <div class="relative">
                            <SearchIcon class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input id="search" v-model="filters.search" @input="debouncedSearch" type="text" placeholder="Search templates..." class="pl-10" />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Templates List -->
        <div class="overflow-hidden bg-white shadow sm:rounded-md">
            <ul v-if="templates.data && templates.data.length > 0" class="divide-y divide-gray-200">
                <li v-for="template in templates.data" :key="template.id" class="px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div :class="['flex h-10 w-10 items-center justify-center rounded-lg', getTypeColor(template.type)]">
                                    <component :is="getTypeIcon(template.type)" class="h-5 w-5 text-white" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center">
                                    <p class="truncate text-sm font-medium text-gray-900">
                                        {{ template.name }}
                                    </p>
                                    <span v-if="template.is_active" class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"> Active </span>
                                    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800"> v{{ template.version }} </span>
                                </div>
                                <div class="mt-1">
                                    <p class="truncate text-sm text-gray-600">{{ template.subject }}</p>
                                </div>
                                <div class="mt-1 flex items-center text-xs text-gray-500">
                                    <span class="capitalize">{{ templateTypes[template.type] || template.type }}</span>
                                    <span class="mx-2">•</span>
                                    <span>{{ template.variables_count || 0 }} variables</span>
                                    <span class="mx-2">•</span>
                                    <span>Updated {{ formatDate(template.updated_at) }}</span>
                                </div>
                                <div v-if="template.description" class="mt-1">
                                    <p class="truncate text-xs text-gray-500">{{ template.description }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button
                                @click="previewTemplate(template)"
                                class="inline-flex items-center rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                            >
                                <EyeIcon class="mr-1 h-3 w-3" />
                                Preview
                            </button>
                            <button
                                @click="editTemplate(template)"
                                class="inline-flex items-center rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                            >
                                <PencilIcon class="mr-1 h-3 w-3" />
                                Edit
                            </button>
                            <button
                                @click="createVersion(template)"
                                class="inline-flex items-center rounded border border-transparent bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                            >
                                <CopyIcon class="mr-1 h-3 w-3" />
                                New Version
                            </button>
                            <button
                                @click="deleteTemplate(template)"
                                class="inline-flex items-center rounded border border-red-300 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 shadow-sm hover:bg-red-50 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none"
                            >
                                <TrashIcon class="mr-1 h-3 w-3" />
                                Delete
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-else class="p-6 text-center">
                <MailIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">No templates</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first email template.</p>
                <div class="mt-6">
                    <a
                        href="/systems/email-templates/create"
                        class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                    >
                        <PlusIcon class="mr-2 h-4 w-4" />
                        Create Template
                    </a>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="templates.data && templates.data.length > 0" class="border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                <DataPagination :pagination-data="templates" item-name="templates" :page-size-options="[10, 15, 25, 50]" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </div>
        </div>

        <!-- Preview Modal -->
        <TemplatePreviewModal v-model:open="showPreviewModal" :template="previewingTemplate" :preview-data="previewData" />
    </div>
</template>
