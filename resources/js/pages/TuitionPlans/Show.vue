<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { route } from 'ziggy-js';
import { Edit, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

interface CurriculumVersion {
    id: number;
    name: string;
    program: {
        id: number;
        name: string;
    };
}

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface TuitionPlanTerm {
    id: number;
    semester_id: number;
    term_number: number;
    amount: number;
    due_date: string | null;
    semester: Semester;
}

interface TuitionPlan {
    id: number;
    curriculum_version_id: number;
    intake_semester_id: number;
    total_amount: number;
    currency: string;
    is_active: boolean;
    curriculum_version: CurriculumVersion;
    intake_semester: Semester;
    terms: TuitionPlanTerm[];
}

interface Props {
    tuitionPlan: TuitionPlan;
}

const props = defineProps<Props>();

const formatCurrency = (amount: number, currency: string = 'VND') => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const formatDate = (date: string | null) => {
    if (!date) return 'Not set';
    return new Date(date).toLocaleDateString();
};

const handleDelete = () => {
    router.delete(route('tuition-plans.destroy', props.tuitionPlan.id), {
        onSuccess: () => {
            toast.success('Tuition plan deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete tuition plan');
        },
    });
};
</script>

<template>
    <Head title="Tuition Plan Details" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Tuition Plan Details</h1>
                <p class="text-muted-foreground">View tuition plan information and payment terms</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('tuition-plans.index')">
                    <Button variant="outline">Back to List</Button>
                </Link>
                <Link :href="route('tuition-plans.edit', tuitionPlan.id)">
                    <Button>
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                </Link>
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This action cannot be undone. This will permanently delete the tuition plan and all its terms.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction @click="handleDelete">Delete</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Program</p>
                        <p class="text-lg">{{ tuitionPlan.curriculum_version.program.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Curriculum Version</p>
                        <p class="text-lg">{{ tuitionPlan.curriculum_version.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Intake Semester</p>
                        <p class="text-lg">{{ tuitionPlan.intake_semester.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Amount</p>
                        <p class="text-lg font-semibold">{{ formatCurrency(tuitionPlan.total_amount, tuitionPlan.currency) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Currency</p>
                        <p class="text-lg">{{ tuitionPlan.currency }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Status</p>
                        <Badge :variant="tuitionPlan.is_active ? 'default' : 'secondary'">
                            {{ tuitionPlan.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Payment Terms</CardTitle>
                <CardDescription>Breakdown of tuition payments across semesters</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="tuitionPlan.terms.length === 0" class="text-center py-8 text-muted-foreground">
                    No payment terms defined for this tuition plan.
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="term in tuitionPlan.terms"
                        :key="term.id"
                        class="flex items-center justify-between p-4 border rounded-lg"
                    >
                        <div class="flex-1 grid gap-4 md:grid-cols-4">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Term</p>
                                <p class="text-lg font-semibold">Term {{ term.term_number }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Semester</p>
                                <p class="text-lg">{{ term.semester.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Amount</p>
                                <p class="text-lg font-semibold">
                                    {{ formatCurrency(term.amount, tuitionPlan.currency) }}
                                    <Badge v-if="term.amount === 0" variant="outline" class="ml-2">Zero Amount</Badge>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Due Date</p>
                                <p class="text-lg">{{ formatDate(term.due_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
