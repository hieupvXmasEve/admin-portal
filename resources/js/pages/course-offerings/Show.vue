<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
import { courseRoutes } from '@/utils/routes';
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
    availableRooms: Room[];
    siblingOfferings: CourseOffering[];
    scoresData?: ScoresData;
    surveyData?: SurveyData;
    surveyForms?: { id: number; title: string; code: string }[];
}

const props = defineProps<Props>();

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
    router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [],
    });
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

const editCourseOffering = () => {
    router.visit(route('course-offerings.edit', props.courseOffering.id));
};
</script>

<template>
    <Head title="Course Offering Details" />

    <!-- Header -->
    <div class="flex flex-col items-center md:flex-row md:justify-between">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ courseOffering.course_code }} - {{ courseOffering.course_title }}</h1>
                <p class="text-muted-foreground">Course offering details and enrollment information</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Link v-if="!courseOffering.section_code && courseOffering.current_enrollment > 0 && !courseOffering.class_sessions?.length" :href="route('course-offerings.split.show', courseOffering.id)">
                <Button variant="outline">
                    <Users class="mr-2 h-4 w-4" />
                    Split into Sections
                </Button>
            </Link>
            <Button variant="outline" size="sm" @click="editCourseOffering">
                <Edit class="mr-2 h-4 w-4" />
                Edit
            </Button>
            <Link :href="courseRoutes.offerings.index()">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
            </Link>
        </div>
    </div>

    <!-- Lifecycle Header -->
    <CourseLifecycleHeader :course-offering="courseOffering" />

    <!-- Tabs -->
    <Tabs :model-value="currentTab" @update:model-value="handleTabChange" class="w-full">
        <TabsList class="grid w-full grid-cols-5">
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="sessions">Sessions</TabsTrigger>
            <TabsTrigger value="students">Students</TabsTrigger>
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
