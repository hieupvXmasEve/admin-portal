<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePermission } from '@/composables/usePermission';
import { useStudentImpersonation } from '@/composables/useStudentImpersonation';
import { STUDENT_HUB_TABS } from '@/pages/students/AcademicSummary/hub-tabs';
import type { Student, StudentHubContext } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ClipboardCheck, Download, Edit, GraduationCap, LogIn, RotateCw } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    student: StudentHubContext;
    currentTab?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentTab: 'overview',
});

const { loginAsStudent } = useStudentImpersonation();
const { can } = usePermission();

// Data-driven, permission-aware tabs: later slices add tabs in hub-tabs.ts,
// never here in the shell (ADR-0007).
const visibleTabs = computed(() => STUDENT_HUB_TABS.filter((tab) => !tab.permission || can(tab.permission)));

const programLabel = computed(() => {
    const program = props.student.program?.name;
    const specialization = props.student.specialization?.name;
    return [program, specialization].filter(Boolean).join(' • ');
});

const initials = computed(() =>
    props.student.full_name
        .split(' ')
        .filter(Boolean)
        .slice(-2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

const goBack = () => {
    router.visit(studentRoutes.list());
};

const exportSummary = () => {
    // Real export lands in issue 04; placeholder until then.
    toast.warning('The feature is coming soon!');
};

const editStudent = () => {
    router.visit(studentRoutes.edit(props.student.id));
};

const goToStudentPlacementAndProgression = () => {
    router.visit(studentRoutes.studentPlacement(props.student.id));
};

const goToStudentActions = () => {
    router.visit(studentRoutes.studentStatusActionIndex(props.student.id));
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

const formatStatus = (status: string) => status.replace(/_/g, ' ').toUpperCase();
</script>

<template>
    <div class="space-y-6">
        <!-- Persistent context bar (present on every tab) -->
        <Card class="overflow-hidden">
            <div class="from-primary/10 via-primary/5 h-1.5 w-full bg-gradient-to-r to-transparent" />
            <CardContent class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" class="hidden shrink-0 sm:inline-flex" aria-label="Back to student list" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>

                    <Avatar class="ring-border size-16 shrink-0 ring-2">
                        <AvatarImage :src="student.avatar_url || '/placeholder.svg'" :alt="student.full_name" />
                        <AvatarFallback>{{ initials || 'AU' }}</AvatarFallback>
                    </Avatar>

                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-bold tracking-tight">{{ student.full_name }}</h1>
                        <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <span class="font-medium">{{ student.student_id }}</span>
                            <span aria-hidden="true">·</span>
                            <Badge :variant="getStatusBadgeVariant(student.status) as any">{{ formatStatus(student.status) }}</Badge>
                            <template v-if="student.intake">
                                <span aria-hidden="true">·</span>
                                <span>K{{ student.intake }}</span>
                            </template>
                        </div>
                        <p v-if="programLabel" class="text-muted-foreground mt-1 flex items-center gap-1.5 truncate text-sm">
                            <GraduationCap class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ programLabel }}</span>
                        </p>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="flex shrink-0 items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="flex items-center gap-2">
                                Actions
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem class="flex items-center gap-2" @click="() => loginAsStudent(student as unknown as Student)">
                                <LogIn class="h-4 w-4" />
                                Login as student
                            </DropdownMenuItem>
                            <DropdownMenuItem class="flex items-center gap-2" @click="editStudent">
                                <Edit class="h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="can('change_student_status')" class="flex items-center gap-2" @click="goToStudentPlacementAndProgression">
                                <RotateCw class="h-4 w-4" />
                                EGC Placement &amp; Progression
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="can('view_student_action')" class="flex items-center gap-2" @click="goToStudentActions">
                                <ClipboardCheck class="h-4 w-4" />
                                View Student Actions
                            </DropdownMenuItem>
                            <DropdownMenuItem class="flex items-center gap-2" @click="exportSummary">
                                <Download class="h-4 w-4" />
                                Export
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardContent>
        </Card>

        <!-- Tabs + active tab content -->
        <Card>
            <CardHeader class="pb-0">
                <nav class="flex w-full flex-wrap gap-2">
                    <Link
                        v-for="tab in visibleTabs"
                        :key="tab.key"
                        :href="route(tab.route, student.id)"
                        :class="[
                            'flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                            currentTab === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
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
