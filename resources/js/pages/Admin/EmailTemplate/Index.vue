<script setup lang="ts">
import { useEmailTemplate } from '@/composables/useEmailTemplate';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { useDebounceFn } from '@vueuse/core';
import {
    AlertTriangleIcon,
    BellIcon,
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    CopyIcon,
    EyeIcon,
    FileTextIcon,
    GraduationCapIcon,
    MailIcon,
    MegaphoneIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    SearchIcon,
    TrashIcon,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import TemplatePreviewModal from './components/TemplatePreviewModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
interface EmailTemplate {
    id: number;
    name: string;
    type: string;
    subject: string;
    html_content: string;
    text_content?: string;
    variables?: string[];
    is_active: boolean;
    version: number;
    description?: string;
    created_at: string;
    updated_at: string;
}

const { templates, templateTypes, isLoading, isRefreshing, loadTemplates, deleteTemplate: deleteTemplateAction, previewTemplate: previewTemplateAction } = useEmailTemplate();
const { confirmDelete } = useGlobalConfirmDialog();

const filters = ref({
    type: '',
    is_active: 'all',
    search: '',
});

const showPreviewModal = ref(false);
const previewingTemplate = ref<EmailTemplate | null>(null);
const previewData = ref<any>(null);

onMounted(() => {
    loadTemplates();
});

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

const applyFilters = () => {
    loadTemplates(filters.value);
};

const refreshTemplates = () => {
    loadTemplates(filters.value);
};

const editTemplate = (template: EmailTemplate) => {
    router.visit(`/systems/email-templates/${template.id}/edit`)
};

const createVersion = (template: EmailTemplate) => {
    router.visit(`/systems/email-templates/create?template_id=${template.id}&create_version=true`)
};

const previewTemplate = async (template: EmailTemplate) => {
    try {
        const preview = await previewTemplateAction(template.id);
        previewingTemplate.value = template;
        previewData.value = preview;
        showPreviewModal.value = true;
    } catch (error) {
        console.error('Failed to preview template:', error);
    }
};

const deleteTemplate = (template: EmailTemplate) => {
    confirmDelete(
        template.name,
        'template',
        async () => {
            await deleteTemplateAction(template.id);
            loadTemplates(filters.value);
        }
    );
};


const previousPage = () => {
    if (templates.value.prev_page_url) {
        loadTemplates(filters.value, templates.value.current_page - 1);
    }
};

const nextPage = () => {
    if (templates.value.next_page_url) {
        loadTemplates(filters.value, templates.value.current_page + 1);
    }
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
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div>
                    <label for="type-filter" class="block text-sm font-medium text-gray-700"> Template Type </label>
                    <select id="type-filter" v-model="filters.type" @change="applyFilters" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Types</option>
                        <option v-for="(label, value) in templateTypes" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="status-filter" class="block text-sm font-medium text-gray-700"> Status </label>
                    <select id="status-filter" v-model="filters.is_active" @change="applyFilters" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700"> Search </label>
                    <div class="relative mt-1">
                        <input
                            id="search"
                            v-model="filters.search"
                            @input="debouncedSearch"
                            type="text"
                            placeholder="Search templates..."
                            class="block w-full rounded-md border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                        <SearchIcon class="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Templates List -->
        <div class="overflow-hidden bg-white shadow sm:rounded-md">
            <div v-if="isLoading" class="p-6">
                <div class="animate-pulse space-y-4">
                    <div v-for="i in 5" :key="i" class="h-20 rounded bg-gray-200"></div>
                </div>
            </div>

            <ul v-else-if="templates.data && templates.data.length > 0" class="divide-y divide-gray-200">
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
                                    <span>{{ template.variables?.length || 0 }} variables</span>
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
                <div class="flex items-center justify-between">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button
                            @click="previousPage"
                            :disabled="!templates.prev_page_url"
                            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <button
                            @click="nextPage"
                            :disabled="!templates.next_page_url"
                            class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium">{{ templates.from || 0 }}</span>
                                to
                                <span class="font-medium">{{ templates.to || 0 }}</span>
                                of
                                <span class="font-medium">{{ templates.total || 0 }}</span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
                                <button
                                    @click="previousPage"
                                    :disabled="!templates.prev_page_url"
                                    class="relative inline-flex items-center rounded-l-md border border-gray-300 bg-white px-2 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <ChevronLeftIcon class="h-5 w-5" />
                                </button>
                                <button
                                    @click="nextPage"
                                    :disabled="!templates.next_page_url"
                                    class="relative inline-flex items-center rounded-r-md border border-gray-300 bg-white px-2 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <ChevronRightIcon class="h-5 w-5" />
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <TemplatePreviewModal v-model:open="showPreviewModal" :template="previewingTemplate" :preview-data="previewData" />
    </div>
</template>
