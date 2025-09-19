<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useStudentImpersonation } from '@/composables/useStudentImpersonation';
import type { Student } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ArrowLeft, BarChart3, BookOpen, Download, Edit, GraduationCap, LogIn, Target, User, Users, Wallet } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email'>;
    currentTab?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentTab: 'overview',
});

// Student impersonation composable
const { loginAsStudent } = useStudentImpersonation();

const goBack = () => {
    router.visit(studentRoutes.list());
};

const exportSummary = () => {
    // TODO: Implement export functionality
    console.log('Export academic summary for student:', props.student.id);
    toast.warning('The feature is coming soon!');
};

const editStudent = (student: Pick<Student, 'id'>) => {
    router.visit(studentRoutes.edit(student.id));
};

const getStatusBadgeVariant = (status: string) => {
    const variants: Record<string, string> = {
        admitted: 'secondary',
        enrolled: 'default',
        active: 'default',
        inactive: 'outline',
        on_leave: 'outline',
        suspended: 'destructive',
        graduated: 'secondary',
        dropped_out: 'outline',
    };
    return variants[status] || 'outline';
};

const formatStatus = (status: string) => {
    return status.replace(/_/g, ' ').toUpperCase();
};

// Navigation tabs
const tabs = [
    { key: 'overview', label: 'Overview', icon: User, route: 'students.academic-summary.overview' },
    { key: 'registrations', label: 'Registrations', icon: BookOpen, route: 'students.academic-summary.registrations' },
    { key: 'scores', label: 'Scores', icon: Target, route: 'students.academic-summary.scores' },
    { key: 'attendance', label: 'Attendance', icon: Users, route: 'students.academic-summary.attendance' },
    { key: 'gpa', label: 'GPA', icon: BarChart3, route: 'students.academic-summary.gpa' },
    { key: 'graduation', label: 'Graduation', icon: GraduationCap, route: 'students.academic-summary.graduation' },
    { key: 'wallet', label: 'Wallet', icon: Wallet, route: 'students.academic-summary.wallet' },
];
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="goBack" class="flex items-center gap-2">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Student
                </Button>
                <div>
                    <h1 class="text-2xl font-bold">Academic Summary</h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-muted-foreground">{{ student.full_name }}</span>
                        <Badge :variant="getStatusBadgeVariant(student.status) as any">
                            {{ formatStatus(student.status) }}
                        </Badge>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="() => loginAsStudent(student as Student)" class="flex items-center gap-2">
                    <LogIn class="h-4 w-4" />
                    Login as student
                </Button>
                <Button variant="outline" size="sm" @click="() => editStudent(student)" class="flex items-center gap-2">
                    <Edit class="h-4 w-4" />
                    Edit
                </Button>
                <Button variant="outline" size="sm" @click="exportSummary" class="flex items-center gap-2">
                    <Download class="h-4 w-4" />
                    Export
                </Button>
            </div>
        </div>

        <!-- Main Content -->
        <Card>
            <CardHeader class="pb-0">
                <nav class="grid w-full grid-cols-7 gap-2">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.key"
                        :href="route(tab.route, student.id)"
                        :class="[
                            'flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            currentTab === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground hover:bg-muted',
                        ]"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                    </Link>
                </nav>
            </CardHeader>

            <CardContent class="p-6">
                <slot />
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Custom styles for better mobile responsiveness */
@media (max-width: 640px) {
    .grid-cols-7 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .grid-cols-7 > :nth-child(n + 4) {
        grid-column: span 1;
        margin-top: 0.5rem;
    }
}
</style>
