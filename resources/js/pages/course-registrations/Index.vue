<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import type { CourseRegistration, Semester } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, CreditCard, Plus, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    registrations: PaginatedResponse<CourseRegistration>;
    statistics: {
        total_registrations: number;
        active_registrations: number;
        pending_registrations: number;
    };
    filters: {
        search?: string;
        semester_id?: string;
        status?: string;
        course_offering_id?: string;
        per_page?: number;
    };
    semesters: Semester[];
    courseOfferings: { value: number; label: string }[];
    statusOptions: { value: string; label: string }[];
}
const props = defineProps<Props>();
const filters = ref({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id || 'all',
    status: props.filters.status || 'all',
    course_offering_id: props.filters.course_offering_id || 'all',
    per_page: props.filters.per_page || props.registrations.per_page || 15,
});

const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.semester_id && newFilters.semester_id !== 'all') params.set('semester_id', newFilters.semester_id);
    if (newFilters.status && newFilters.status !== 'all') params.set('status', newFilters.status);
    if (newFilters.course_offering_id && newFilters.course_offering_id !== 'all') params.set('course_offering_id', newFilters.course_offering_id);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/course-registrations${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['registrations', 'filters', 'courseOfferings'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['registrations'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
        case 'confirmed':
            return 'default';
        case 'completed':
            return 'secondary';
        case 'dropped':
        case 'withdrawn':
            return 'destructive';
        default:
            return 'outline';
    }
};

const deleteRegistration = (registration: CourseRegistration) => {
    if (confirm('Are you sure you want to delete this registration?')) {
        router.delete(`/course-registrations/${registration.id}`, {
            onSuccess: () => {
                // Success message handled by backend
            },
        });
    }
};

// Reset course offering filter when semester changes
watch(
    () => filters.value.semester_id,
    (newSemesterId) => {
        if (newSemesterId === 'all') {
            filters.value.course_offering_id = 'all';
        } else {
            // Reset to 'all' when semester changes to load new course offerings
            filters.value.course_offering_id = 'all';
        }
    },
);

const clearFilters = () => {
    filters.value = {
        search: '',
        semester_id: 'all',
        status: 'all',
        course_offering_id: 'all',
        per_page: 15,
    };
    router.visit('/course-registrations', {
        preserveState: true,
        preserveScroll: true,
        only: ['registrations', 'filters', 'courseOfferings'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.semester_id !== 'all' || filters.value.status !== 'all' || filters.value.course_offering_id !== 'all';
});
</script>

<template>
    <Head title="Course Registrations" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Course Registrations</h1>
            <p class="text-muted-foreground">Manage student course registrations and enrollment</p>
        </div>
        <Link href="/course-registrations/create">
            <Button>
                <Plus class="mr-2 h-4 w-4" />
                Register Student
            </Button>
        </Link>
    </div>

    <!-- Quick Stats -->
    <div class="grid gap-4 md:grid-cols-3">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Registrations</CardTitle>
                <Users class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_registrations }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Active Registrations</CardTitle>
                <BookOpen class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-green-600">
                    {{ statistics.active_registrations }}
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Pending Registrations</CardTitle>
                <CreditCard class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-orange-600">
                    {{ statistics.pending_registrations }}
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Filters -->
    <Card>
        <CardHeader>
            <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Search</label>
                    <DebouncedInput v-model="filters.search" @debounced="handleSearch" placeholder="Search students or courses..." :debounce="300" />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Semester</label>
                    <Select v-model="filters.semester_id" @update:model-value="() => applyFilters(filters)">
                        <SelectTrigger>
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                {{ semester.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Course Offering</label>
                    <Select v-model="filters.course_offering_id" @update:model-value="() => applyFilters(filters)" :disabled="filters.semester_id === 'all'">
                        <SelectTrigger>
                            <SelectValue placeholder="All Course Offerings" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Course Offerings</SelectItem>
                            <SelectItem v-for="offering in courseOfferings" :key="offering.value" :value="offering.value.toString()">
                                {{ offering.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Registration Status</label>
                    <Select v-model="filters.status" @update:model-value="() => applyFilters(filters)">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div v-if="hasActiveFilters" class="flex justify-end pt-4">
                <Button variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>
        </CardContent>
    </Card>

    <!-- Registration List -->
    <div class="grid gap-4">
        <Card v-for="registration in registrations.data" :key="registration.id">
            <CardContent class="p-6">
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold">Student ID: {{ registration.student_id }}</h3>
                            <Badge variant="outline">Registration #{{ registration.id }}</Badge>
                        </div>
                        <h4 class="text-muted-foreground text-base">{{ registration.course_offering?.course_code }} - {{ registration.course_offering?.course_title }}</h4>
                        <div class="flex items-center gap-2 text-sm">
                            <span>{{ registration.semester?.name }}</span>
                            <span>•</span>
                            <span>{{ registration.credit_hours }} credits</span>
                            <span>•</span>
                            <span>Registered: {{ new Date(registration.registration_date).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="getStatusVariant(registration.registration_status)">
                                {{ registration.registration_status }}
                            </Badge>

                            <Badge variant="outline">{{ registration.registration_method }}</Badge>
                        </div>
                    </div>
                    <div class="space-y-2 text-right">
                        <div>
                            <p class="text-lg font-bold">{{ registration.credit_hours }} Credits</p>
                            <p class="text-muted-foreground text-sm">Credit Hours</p>
                        </div>
                        <div class="flex gap-2">
                            <Link :href="`/course-registrations/${registration.id}`">
                                <Button variant="outline" size="sm">View</Button>
                            </Link>
                            <Link :href="`/course-registrations/${registration.id}/edit`">
                                <Button variant="outline" size="sm">Edit</Button>
                            </Link>
                            <Button variant="outline" size="sm" @click="deleteRegistration(registration)" class="text-destructive hover:text-destructive"> Delete </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Empty State -->
    <Card v-if="registrations.data.length === 0">
        <CardContent class="p-6 text-center">
            <p class="text-muted-foreground">No course registrations found.</p>
            <Link href="/course-registrations/create" class="mt-4 inline-block">
                <Button>Register First Student</Button>
            </Link>
        </CardContent>
    </Card>

    <!-- Pagination -->
    <DataPagination v-if="registrations.data.length > 0" :pagination-data="registrations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
