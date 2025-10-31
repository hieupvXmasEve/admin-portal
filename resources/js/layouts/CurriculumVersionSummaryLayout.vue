<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables';
import { SharedData } from '@/types';
import { curriculumRoutes } from '@/utils/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    curriculumVersion: {
        id: number;
        version_code: string;
        notes?: string;
        created_at: string;
        program: {
            id: number;
            name: string;
            code: string;
        };
        specialization?: {
            id: number;
            name: string;
            code: string;
        } | null;
        effective_from_semester?: {
            id: number;
            name: string;
            code: string;
        } | null;
    };
}

defineProps<Props>();

const page = usePage<SharedData>();
const permission = usePermissions();
// Get current route name to determine active tab
const currentRouteName = computed(() => (page.props as any).ziggy?.route);

// Tab configuration with routes
const tabs = [
    {
        key: 'overview',
        label: 'Overview',
        route: 'curriculum_versions.summary.overview',
    },
    {
        key: 'units',
        label: 'Unit List',
        route: 'curriculum_versions.summary.units',
    },
    {
        key: 'modules',
        label: 'Modules',
        route: 'curriculum_versions.summary.modules',
    },
    {
        key: 'students',
        label: 'Student Stats',
        route: 'curriculum_versions.summary.students',
    },
    {
        key: 'deployments',
        label: 'Deployment Tracking',
        route: 'curriculum_versions.summary.deployments',
    },
];

// Determine active tab based on current route name
const activeTab = computed(() => {
    const routeName = currentRouteName.value;
    const tab = tabs.find((t) => t.route === routeName);
    return tab?.key || 'overview';
});

// Helper functions
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const handleExport = () => {
    // TODO: Implement export functionality based on current tab
    console.log('Export current tab data');
};
</script>

<template>
    <!-- Header Section -->
    <div class="bg-white">
        <div class="flex flex-col gap-4">
            <!-- Navigation and Title -->
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight">
                            {{ curriculumVersion.version_code || `Version #${curriculumVersion.id}` }}
                        </h1>
                        <Badge variant="secondary" class="text-xs">
                            {{ curriculumVersion.specialization ? 'Specialization Level' : 'Program Level' }}
                        </Badge>
                    </div>

                    <!-- Subheader Information -->
                    <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                        <span class="flex items-center gap-1"> <strong>Program:</strong> {{ curriculumVersion.program?.name }} ({{ curriculumVersion.program?.code }}) </span>
                        <span v-if="curriculumVersion.specialization" class="flex items-center gap-1"> <strong>Specialization:</strong> {{ curriculumVersion.specialization.name }} </span>
                        <span v-if="curriculumVersion.effective_from_semester" class="flex items-center gap-1"> <strong>Effective From:</strong> {{ curriculumVersion.effective_from_semester.name }} </span>
                        <span class="flex items-center gap-1"> <strong>Created:</strong> {{ formatDate(curriculumVersion.created_at) }} </span>
                    </div>

                    <p v-if="curriculumVersion.notes" class="text-muted-foreground text-sm">
                        {{ curriculumVersion.notes }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-2">
                    <Link :href="curriculumRoutes.curriculumVersions.index()">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to List
                        </Button>
                    </Link>

                    <Button v-if="permission.can('export_curriculum_version')" variant="outline" @click="handleExport">
                        <Download class="mr-2 h-4 w-4" />
                        Export
                    </Button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="border-t pt-4">
                <div class="bg-muted text-muted-foreground inline-flex h-9 w-full items-center justify-center rounded-lg p-[3px]">
                    <div class="grid w-full grid-cols-2 gap-4 md:grid-cols-5">
                        <Link
                            v-for="tab in tabs"
                            :key="tab.key"
                            :href="route(tab.route, { curriculum_version: curriculumVersion.id })"
                            :replace="true"
                            :preserve-scroll="true"
                            :prefetch="true"
                            class="tab-button"
                            :class="{
                                'tab-button--active': activeTab === tab.key,
                            }"
                            :data-state="activeTab === tab.key ? 'active' : 'inactive'"
                            data-slot="tabs-trigger"
                        >
                            {{ tab.label }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <slot />
</template>

<style scoped>
/* Base tab button styling */
.tab-button {
    display: inline-flex;
    height: calc(100% - 1px);
    flex: 1 1 0%;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
    color: hsl(var(--muted-foreground));
    background-color: transparent;
    position: relative;
}

.tab-button:focus-visible {
    outline: 2px solid transparent;
    outline-offset: 2px;
    box-shadow: 0 0 0 2px hsl(var(--ring));
}

.tab-button:disabled {
    pointer-events: none;
    opacity: 0.5;
}

/* Active state styling */
.tab-button--active,
.tab-button[data-state='active'] {
    background-color: white;
    color: hsl(var(--foreground));
    box-shadow:
        0 1px 3px 0 rgb(0 0 0 / 0.1),
        0 1px 2px -1px rgb(0 0 0 / 0.1);
}

/* Add a bottom border for active state */
.tab-button--active::after,
.tab-button[data-state='active']::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background-color: hsl(var(--primary));
    border-radius: 2px 2px 0 0;
}

/* Hover state for inactive tabs */
.tab-button:not(.tab-button--active):hover,
.tab-button[data-state='inactive']:hover {
    background-color: hsl(var(--muted));
    color: hsl(var(--foreground));
}
</style>
