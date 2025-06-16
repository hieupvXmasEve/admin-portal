<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import type { CourseRegistration, Semester } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, CreditCard, Plus, Users } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    registrations: PaginatedResponse<CourseRegistration>;
    filters: {
        search?: string;
        semester_id?: string;
        status?: string;
        payment_status?: string;
    };
    semesters: Semester[];
    statusOptions: { value: string; label: string }[];
    paymentStatusOptions: { value: string; label: string }[];
}

const props = defineProps<Props>();
const breadcrumbs = ref([
    {
        title: 'Course Registrations',
        href: '/course-registrations',
    },
]);

const filters = ref({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id || 'all',
    status: props.filters.status || 'all',
    payment_status: props.filters.payment_status || 'all',
});

const updateFilters = () => {
    const filterParams = {
        search: filters.value.search || undefined,
        semester_id: filters.value.semester_id === 'all' ? undefined : filters.value.semester_id,
        status: filters.value.status === 'all' ? undefined : filters.value.status,
        payment_status: filters.value.payment_status === 'all' ? undefined : filters.value.payment_status,
    };

    router.get('/course-registrations', filterParams, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handlePageChange = (url: string) => {
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
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

const getPaymentStatusVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'overdue':
            return 'destructive';
        case 'waived':
            return 'outline';
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
</script>

<template>
    <Head title="Course Registrations" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4">
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
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Registrations</CardTitle>
                        <Users class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ registrations.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Registrations</CardTitle>
                        <BookOpen class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{
                                registrations.data.filter((r: CourseRegistration) => ['registered', 'confirmed'].includes(r.registration_status))
                                    .length
                            }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pending Payments</CardTitle>
                        <CreditCard class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">
                            {{ registrations.data.filter((r: CourseRegistration) => r.payment_status === 'pending').length }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Page</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ registrations.data.length }}</div>
                        <p class="text-muted-foreground text-xs">of {{ registrations.total }} total</p>
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
                            <DebouncedInput
                                v-model="filters.search"
                                @debounced="handleSearch"
                                placeholder="Search students or courses..."
                                :debounce="300"
                            />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Semester</label>
                            <Select v-model="filters.semester_id" @update:model-value="updateFilters">
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
                            <label class="text-sm font-medium">Registration Status</label>
                            <Select v-model="filters.status" @update:model-value="updateFilters">
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

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Payment Status</label>
                            <Select v-model="filters.payment_status" @update:model-value="updateFilters">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Payment Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Payment Statuses</SelectItem>
                                    <SelectItem v-for="option in paymentStatusOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
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
                                <h4 class="text-muted-foreground text-base">
                                    {{ registration.course_offering?.course_code }} - {{ registration.course_offering?.course_title }}
                                </h4>
                                <div class="flex items-center gap-2 text-sm">
                                    <span>{{ registration.semester?.name }}</span>
                                    <span>•</span>
                                    <span>{{ registration.course_offering?.campus?.name }}</span>
                                    <span>•</span>
                                    <span>{{ registration.credit_hours }} credits</span>
                                    <span>•</span>
                                    <span>Registered: {{ new Date(registration.registration_date).toLocaleDateString() }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="getStatusVariant(registration.registration_status)">
                                        {{ registration.registration_status }}
                                    </Badge>
                                    <Badge :variant="getPaymentStatusVariant(registration.payment_status)">
                                        {{ registration.payment_status }}
                                    </Badge>
                                    <Badge variant="outline">{{ registration.registration_method }}</Badge>
                                </div>
                            </div>
                            <div class="space-y-2 text-right">
                                <div>
                                    <p class="text-lg font-bold">${{ registration.tuition_amount.toFixed(2) }}</p>
                                    <p class="text-muted-foreground text-sm">Total Amount</p>
                                </div>
                                <div class="flex gap-2">
                                    <Link :href="`/course-registrations/${registration.id}`">
                                        <Button variant="outline" size="sm">View</Button>
                                    </Link>
                                    <Link :href="`/course-registrations/${registration.id}/edit`">
                                        <Button variant="outline" size="sm">Edit</Button>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="deleteRegistration(registration)"
                                        class="text-destructive hover:text-destructive"
                                    >
                                        Delete
                                    </Button>
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
            <DataPagination v-if="registrations.data.length > 0" :pagination-data="registrations" @navigate="handlePageChange" />
        </div>
    </AppLayout>
</template>
