<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, Calendar, DollarSign, Hash, Loader2, Trash2, Users, X } from 'lucide-vue-next';
import { route } from 'ziggy-js';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ref } from 'vue';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
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
    amount: number | string;
    total_amount: number | string | null;
    total_terms: number | null;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

interface Props {
    scholarship: Scholarship;
    students: StudentScholarshipAward[];
}

const props = defineProps<Props>();

const deleteForm = useForm({});
const isDeleting = ref(false);
const removeAssignmentDialogOpen = ref(false);
const assignmentToRemove = ref<StudentScholarshipAward | null>(null);
const removeAssignmentForm = useForm({});

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatAmount = (amount: number | string | null, type: string) => {
    const numericAmount = Number(amount ?? 0);

    if (type === 'percentage') {
        return `${numericAmount}%`;
    }

    return formatCurrency(numericAmount);
};

const formatCurrency = (amount: number | string | null) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
};

const hasFixedCalculation = (scholarship: Scholarship): boolean => {
    return scholarship.type === 'fixed_amount' && scholarship.total_amount !== null && scholarship.total_terms !== null;
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

const confirmRemoveAssignment = (assignment: StudentScholarshipAward) => {
    assignmentToRemove.value = assignment;
    removeAssignmentDialogOpen.value = true;
};

const removeAssignment = () => {
    if (!assignmentToRemove.value) return;

    removeAssignmentForm.delete(route('student-scholarships.destroy', assignmentToRemove.value.id), {
        onSuccess: () => {
            removeAssignmentDialogOpen.value = false;
            assignmentToRemove.value = null;
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
            <Button variant="outline" as-child>
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
        <AlertDescription> This scholarship has expired and can no longer be assigned to students. Consider updating the validity period or creating a new scholarship. </AlertDescription>
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

                <div v-if="hasFixedCalculation(scholarship)" class="flex items-start gap-3">
                    <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                    <div class="flex-1">
                        <p class="text-muted-foreground text-sm">Fixed Amount Calculation</p>
                        <p class="text-sm">{{ formatCurrency(scholarship.total_amount) }} / {{ scholarship.total_terms }} terms</p>
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
                <CardDescription>Students currently receiving this scholarship ({{ students.length }} total) </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="students.length > 0" class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Awarded Date</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="award in students" :key="award.id">
                                <TableCell>
                                    <div>
                                        <p class="font-medium">{{ award.student.full_name }}</p>
                                        <p class="text-muted-foreground text-sm">{{ award.student.student_id }}</p>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Calendar class="text-muted-foreground h-4 w-4" />
                                        <span class="text-sm">{{ formatDate(award.awarded_at) }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button variant="ghost" size="sm" @click="confirmRemoveAssignment(award)">
                                        <X class="text-destructive h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-else class="text-muted-foreground py-8 text-center text-sm">
                    <p>No students assigned yet</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Danger Zone -->
    <Card class="border-destructive mt-6">
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

    <!-- Remove Assignment Confirmation Dialog -->
    <AlertDialog v-model:open="removeAssignmentDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Remove Scholarship Assignment?</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to remove the scholarship assignment for
                    <strong>{{ assignmentToRemove?.student.full_name }}</strong
                    >? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="removeAssignment" class="bg-destructive hover:bg-destructive/90">
                    <Loader2 v-if="removeAssignmentForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Remove Assignment
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
