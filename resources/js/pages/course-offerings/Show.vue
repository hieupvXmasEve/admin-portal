<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import CourseLifecycleHeader from '@/pages/course-offerings/components/CourseLifecycleHeader.vue';
import OverviewTab from '@/pages/course-offerings/components/tabs/OverviewTab.vue';
import type { ScoresData } from '@/pages/course-offerings/components/tabs/ScoresTab.vue';
import ScoresTab from '@/pages/course-offerings/components/tabs/ScoresTab.vue';
import SessionsTab from '@/pages/course-offerings/components/tabs/SessionsTab.vue';
import StudentsTab from '@/pages/course-offerings/components/tabs/StudentsTab.vue';
import type { SurveyData } from '@/pages/course-offerings/components/tabs/SurveyTab.vue';
import SurveyTab from '@/pages/course-offerings/components/tabs/SurveyTab.vue';
import type { AcademicRecord, ClassSession, CourseOffering, CourseRegistration, Room } from '@/types/models';
import type { OperationalState } from '@/types/operational-state';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Users } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: CourseOffering & {
        course_registrations: CourseRegistration[];
        class_sessions?: ClassSession[];
        academic_records?: AcademicRecord[];
    };
    operational_state: OperationalState;
    availableRooms: Room[];
    siblingOfferings: CourseOffering[];
    scoresData?: ScoresData;
    surveyData?: SurveyData;
    surveyForms?: { id: number; title: string; code: string }[];
}

defineProps<Props>();

// ---- URL-synced tab state (following clubs/Show.vue pattern) ----
const validTabs = ['overview', 'sessions', 'students', 'scores', 'survey'] as const;
type ValidTab = (typeof validTabs)[number];

const getCurrentTabFromURL = (): ValidTab => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as ValidTab;
    return validTabs.includes(tabParam) ? tabParam : 'overview';
};

const currentTab = ref<ValidTab>(getCurrentTabFromURL());

const updateTabInURL = (newTab: ValidTab) => {
    const url = new URL(window.location.href);
    if (newTab === 'overview') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', newTab);
    }
    // Use history.pushState so each tab switch creates a real history entry.
    // When the user navigates to a child page and presses Back, the browser
    // restores the exact URL (with ?tab=sessions etc.) and the component
    // re-reads the tab from the URL on mount — no extra wiring required.
    history.pushState(history.state, '', url.pathname + url.search);
};

const handleTabChange = (newTab: string | number) => {
    const tabValue = String(newTab) as ValidTab;
    if (validTabs.includes(tabValue)) {
        currentTab.value = tabValue;
        updateTabInURL(tabValue);
    }
};

onMounted(() => {
    const handlePopState = () => {
        currentTab.value = getCurrentTabFromURL();
    };
    window.addEventListener('popstate', handlePopState);
    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopState);
    });
});

const courseStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'completed':
            return 'outline';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const goBack = () => window.history.back();
</script>

<template>
    <Head title="Course Offering Details" />

    <!-- Header -->
    <div class="space-y-1 pb-2">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <!-- Title + meta -->
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ courseOffering.course_code }}
                        <span class="text-muted-foreground font-normal">—</span>
                        {{ courseOffering.course_title }}
                    </h1>
                    <Badge v-if="courseOffering.section_code" variant="secondary" class="shrink-0"> Section {{ courseOffering.section_code }} </Badge>
                    <Badge :variant="courseStatusVariant(courseOffering.course_status)" class="shrink-0 capitalize">
                        {{ courseOffering.course_status }}
                    </Badge>
                </div>
                <p class="text-muted-foreground mt-1 text-sm">{{ courseOffering.semester?.name }} · {{ courseOffering.current_enrollment }}/{{ courseOffering.max_capacity }} students</p>
            </div>

            <!-- Actions -->
            <div class="flex shrink-0 items-center gap-2">
                <Link v-if="!courseOffering.section_code && courseOffering.current_enrollment > 0 && !courseOffering.class_sessions?.length" :href="route('course-offerings.split.show', courseOffering.id)">
                    <Button variant="outline" size="sm">
                        <Users class="mr-1.5 h-3.5 w-3.5" />
                        Split into Sections
                    </Button>
                </Link>

                <Separator orientation="vertical" class="h-6" />

                <Button variant="outline" size="sm" @click="router.visit(route('course-offerings.edit', courseOffering.id))">
                    <Edit class="mr-1.5 h-3.5 w-3.5" />
                    Edit
                </Button>
                <Button variant="ghost" size="sm" @click="goBack">
                    <ArrowLeft class="mr-1.5 h-3.5 w-3.5" />
                    Back
                </Button>
            </div>
        </div>
    </div>

    <!-- Lifecycle Header (backend-derived operational state, ADR 0013) -->
    <CourseLifecycleHeader :course-offering-id="courseOffering.id" :operational-state="operational_state" />

    <!-- Tabs -->
    <Tabs :model-value="currentTab" class="mt-2 w-full" @update:model-value="handleTabChange">
        <TabsList class="grid w-full grid-cols-5">
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="sessions">Sessions</TabsTrigger>
            <TabsTrigger value="students">
                Students
                <Badge variant="secondary" class="ml-1.5 px-1.5 py-0 text-[11px]">
                    {{ courseOffering.current_enrollment }}
                </Badge>
            </TabsTrigger>
            <TabsTrigger value="scores">Scores</TabsTrigger>
            <TabsTrigger value="survey">Survey</TabsTrigger>
        </TabsList>

        <!-- Overview Tab -->
        <TabsContent value="overview" class="space-y-6">
            <OverviewTab :course-offering="courseOffering" :sibling-offerings="siblingOfferings" />
        </TabsContent>

        <!-- Sessions Tab -->
        <TabsContent value="sessions" class="space-y-6">
            <SessionsTab :course-offering="courseOffering" :available-rooms="availableRooms" />
        </TabsContent>

        <!-- Students Tab -->
        <TabsContent value="students" class="space-y-6">
            <StudentsTab :course-offering="courseOffering" :sibling-offerings="siblingOfferings" />
        </TabsContent>

        <!-- Scores Tab (deferred) -->
        <TabsContent value="scores" class="space-y-6">
            <Deferred data="scoresData">
                <template #fallback>
                    <div class="space-y-4">
                        <Skeleton class="h-32 w-full" />
                        <Skeleton class="h-64 w-full" />
                        <Skeleton class="h-96 w-full" />
                    </div>
                </template>
                <template #default="{ reloading }">
                    <ScoresTab :course-offering="courseOffering" :scores-data="scoresData" :class="{ 'opacity-50': reloading }" />
                </template>
            </Deferred>
        </TabsContent>

        <!-- Survey Tab (deferred) -->
        <TabsContent value="survey" class="space-y-6">
            <Deferred data="surveyData">
                <template #fallback>
                    <div class="space-y-4">
                        <Skeleton class="h-32 w-full" />
                        <Skeleton class="h-48 w-full" />
                    </div>
                </template>
                <template #default="{ reloading }">
                    <SurveyTab :course-offering="courseOffering" :survey-data="surveyData" :survey-forms="surveyForms" :class="{ 'opacity-50': reloading }" />
                </template>
            </Deferred>
        </TabsContent>
    </Tabs>
</template>
