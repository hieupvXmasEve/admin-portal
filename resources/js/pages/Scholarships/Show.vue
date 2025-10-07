<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, Calendar, DollarSign, Hash, Loader2, Trash2, Users } from 'lucide-vue-next';
import { route } from 'ziggy-js';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { ref } from 'vue';

interface Student {
    id: number;
    student_code: string;
    first_name: string;
    last_name: string;
}

interface StudentScholarshipAward {
    id: number;
    student_id: number;
    scholarship_code: string;
    awarded_at: string;
    student: Student;
}

interface Scholarship {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: 'percentage' | 'fixed_amount';
    amount: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

interface Props {
    scholarship: Scholarship;
    students: StudentScholarshipAward[];
    can: {
        update: boolean;
        delete: boolean;
    };
}

const props = defineProps<Props>();

const deleteForm = useForm({});
const isDeleting = ref(false);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatAmount = (amount: number, type: string) => {
    if (type === 'percentage') {
        return `${amount}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const getStatusVariant = (): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const now = new Date();
    const validFrom = new Date(props.scholarship.valid_from);
    const validUntil = new Date(props.scholarship.valid_until);

    if (!props.scholarship.is_active) return 'secondary';
    if (now > validUntil) return 'destructive';
    if (now >= validFrom && now <= validUntil) return 'default';
    return 'outline';
};

const getStatusLabel = (): string => {
    const now = new Date();
    const validFrom = new Date(props.scholarship.valid_from);
    const validUntil = new Date(props.scholarship.valid_until);

    if (!props.scholarship.is_active) return 'Inactive';
    if (now > validUntil) return 'Expired';
    if (now >= validFrom && now <= validUntil) return 'Active';
    return 'Upcoming';
};

const isExpired = (): boolean => {
    const now = new Date();
    const validUntil = new Date(props.scholarship.valid_until);
    return now > validUntil;
};

const deleteScholarship = () => {
    isDeleting.value = true;
    deleteForm.delete(route('scholarships.destroy', props.scholarship.id), {
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Scholarship: ${scholarship.code}`" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Scholarship Details</h2>
            <p class="text-muted-foreground mt-1 text-sm">View and manage scholarship information</p>
        </div>
        <div class="flex gap-2">
            <Button v-if="can.update" variant="outline" as-child>
                <Link :href="route('scholarships.edit', scholarship.id)">Edit Scholarship</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="route('scholarships.index')">Back to List</Link>
            </Button>
        </div>
    </div>

    <!-- Expired Warning -->
    <Alert v-if="isExpired()" variant="destructive" class="mt-6">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>
            This scholarship has expired and can no longer be assigned to students. Consider updating the validity period or creating a new scholarship.
        </AlertDescription>
    </Alert>

    <!-- Scholarship Information -->
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Scholarship Information</CardTitle>
                    <Badge :variant="getStatusVariant()">{{ getStatusLabel() }}</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-start gap-3">
                    <Hash class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Code</p>
                        <p class="font-mono font-medium">{{ scholarship.code }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Name</p>
                        <p class="font-medium">{{ scholarship.name }}</p>
                    </div>
                </div>

                <div v-if="scholarship.description" class="flex items-start gap-3">
                    <div class="text-muted-foreground mt-0.5 h-5 w-5"></div>
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Description</p>
                        <p class="text-sm">{{ scholarship.description }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Discount Type</p>
                        <Badge variant="outline">
                            {{ scholarship.type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                        </Badge>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Discount Amount</p>
                        <p class="text-lg font-semibold">{{ formatAmount(scholarship.amount, scholarship.type) }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <Calendar class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Valid Period</p>
                        <p class="text-sm">{{ formatDate(scholarship.valid_from) }}</p>
                        <p class="text-muted-foreground text-xs">to {{ formatDate(scholarship.valid_until) }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Assigned Students
                </CardTitle>
                <CardDescription>Students currently receiving this scholarship</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-4">
                    <p class="text-muted-foreground text-sm">Total Students</p>
                    <p class="text-3xl font-bold">{{ students.length }}</p>
                </div>

                <div v-if="students.length > 0" class="space-y-2">
                    <div v-for="award in students.slice(0, 5)" :key="award.id" class="border-border flex items-center justify-between rounded-lg border p-3">
                        <div>
                            <p class="font-medium">{{ award.student.first_name }} {{ award.student.last_name }}</p>
                            <p class="text-muted-foreground text-sm">{{ award.student.student_code }}</p>
                        </div>
                        <Badge variant="outline">{{ formatDate(award.awarded_at) }}</Badge>
                    </div>

                    <p v-if="students.length > 5" class="text-muted-foreground pt-2 text-center text-sm">
                        And {{ students.length - 5 }} more students...
                    </p>
                </div>

                <div v-else class="text-muted-foreground py-8 text-center text-sm">
                    <p>No students assigned yet</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Danger Zone -->
    <Card v-if="can.delete" class="border-destructive mt-6">
        <CardHeader>
            <CardTitle class="text-destructive">Danger Zone</CardTitle>
            <CardDescription>Irreversible actions for this scholarship</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">Delete Scholarship</p>
                    <p class="text-muted-foreground text-sm">Permanently remove this scholarship from the system</p>
                    <p v-if="students.length > 0" class="text-destructive mt-1 text-sm">⚠️ Cannot delete: {{ students.length }} student(s) are assigned to this scholarship</p>
                </div>
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive" :disabled="students.length > 0 || isDeleting">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This action cannot be undone. This will permanently delete the scholarship <strong>{{ scholarship.code }}</strong> from the system.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction @click="deleteScholarship" class="bg-destructive hover:bg-destructive/90">
                                <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                                Delete Scholarship
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </CardContent>
    </Card>
</template>
