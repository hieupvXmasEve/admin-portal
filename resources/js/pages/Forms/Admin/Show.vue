<script setup lang="ts">
import FormCloneModal from '@/components/forms/FormCloneModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Form } from '@/types/forms';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArrowLeft, Copy, Edit, MoreHorizontal, Settings, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    form: Form;
    statistics: {
        total_responses: number;
        submitted: number;
        approved: number;
        rejected: number;
        pending_review: number;
        anonymous_responses: number;
    };
}

const props = defineProps<Props>();
console.log('props', props.form);
// Local state
const currentTab = ref('overview');
const showCloneModal = ref(false);

// Computed
const currentVersion = computed(() => props.form.current_version || props.form.versions?.[0]);
const publishedVersion = computed(() => props.form.latest_published_version);
const allQuestions = computed(() => {
    const questions = [];

    // Add questions from sections
    if (currentVersion.value?.sections) {
        currentVersion.value.sections.forEach((section) => {
            if (section.questions) {
                questions.push(...section.questions.map((q) => ({ ...q, section: section.title })));
            }
        });
    }

    // Add standalone questions
    if (currentVersion.value?.questions) {
        const standaloneQuestions = currentVersion.value.questions.filter((q) => !q.section_id);
        questions.push(...standaloneQuestions.map((q) => ({ ...q, section: null })));
    }

    return questions.sort((a, b) => a.order_index - b.order_index);
});

// Computed for published version questions
const publishedQuestions = computed(() => {
    const questions = [];

    // Add questions from sections in published version
    if (publishedVersion.value?.sections) {
        publishedVersion.value.sections.forEach((section) => {
            if (section.questions) {
                questions.push(...section.questions.map((q) => ({ ...q, section: section.title })));
            }
        });
    }

    // Add standalone questions from published version
    if (publishedVersion.value?.questions) {
        const standaloneQuestions = publishedVersion.value.questions.filter((q) => !q.section_id);
        questions.push(...standaloneQuestions.map((q) => ({ ...q, section: null })));
    }

    return questions.sort((a, b) => a.order_index - b.order_index);
});

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'archived':
            return 'outline';
        default:
            return 'secondary';
    }
};

const getTypeBadgeVariant = (type: string) => {
    switch (type) {
        case 'feedback':
            return 'default';
        case 'survey':
            return 'secondary';
        case 'query':
            return 'outline';
        default:
            return 'secondary';
    }
};

const getQuestionTypeLabel = (type: string) => {
    const typeLabels = {
        short_text: 'Short Text',
        long_text: 'Long Text',
        single_choice: 'Single Choice',
        multi_choice: 'Multiple Choice',
        likert: 'Likert Scale',
        rating: 'Rating',
        date: 'Date',
        number: 'Number',
        file: 'File Upload',
        matrix: 'Matrix',
        yes_no: 'Yes/No',
    };
    return typeLabels[type] || type;
};

// Methods
const handleAction = (action: string) => {
    switch (action) {
        case 'edit':
            router.visit(route('forms.admin.edit', props.form.id));
            break;
        case 'clone':
            showCloneModal.value = true;
            break;
        case 'archive':
            if (confirm('Are you sure you want to archive this form?')) {
                router.post(route('forms.admin.archive', props.form.id));
            }
            break;
        case 'restore':
            router.post(route('forms.admin.restore', props.form.id));
            break;
        case 'delete':
            if (confirm('Are you sure you want to delete this form? This action cannot be undone.')) {
                router.delete(route('forms.admin.destroy', props.form.id));
            }
            break;
    }
};

const publishVersion = (versionId: number) => {
    if (confirm('Are you sure you want to publish this version?')) {
        router.post(route('forms.admin.version.publish', [props.form.id, versionId]));
    }
};
</script>

<template>
    <Head :title="`Form: ${form.title}`" />

    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <Button variant="ghost" @click="router.visit(route('forms.admin.index'))">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <div class="flex items-center space-x-2">
                        <h1 class="text-3xl font-bold tracking-tight">{{ form.title }}</h1>
                        <Badge :variant="getStatusBadgeVariant(form.status)">{{ form.status }}</Badge>
                        <Badge :variant="getTypeBadgeVariant(form.type)">{{ form.type }}</Badge>
                    </div>
                    <p class="text-muted-foreground">{{ form.description || 'No description provided' }}</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <Link :href="route('forms.admin.edit', form.id)">
                    <Button>
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Form
                    </Button>
                </Link>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            <MoreHorizontal class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="handleAction('clone')">
                            <Copy class="mr-2 h-4 w-4" />
                            Clone Form
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem v-if="form.status !== 'archived'" @click="handleAction('archive')">
                            <Archive class="mr-2 h-4 w-4" />
                            Archive
                        </DropdownMenuItem>
                        <DropdownMenuItem v-else @click="handleAction('restore')">
                            <Settings class="mr-2 h-4 w-4" />
                            Restore
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleAction('delete')" class="text-destructive">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Main Content -->
            <div class="lg:col-span-3">
                <Tabs v-model:default-value="currentTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-5">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="structure">Structure</TabsTrigger>
                        <TabsTrigger value="versions">Versions</TabsTrigger>
                        <TabsTrigger value="targeting">Targeting</TabsTrigger>
                        <TabsTrigger value="analytics">Analytics</TabsTrigger>
                    </TabsList>

                    <!-- Overview Tab -->
                    <TabsContent value="overview" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Form Information -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Form Information</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-medium">Code</p>
                                            <p class="text-muted-foreground text-sm">{{ form.code }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Type</p>
                                            <p class="text-muted-foreground text-sm">{{ form.type }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Status</p>
                                            <p class="text-muted-foreground text-sm">{{ form.status }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Created</p>
                                            <p class="text-muted-foreground text-sm">{{ new Date(form.created_at).toLocaleDateString() }}</p>
                                        </div>
                                    </div>
                                    <Separator />
                                    <div>
                                        <p class="text-sm font-medium">Created By</p>
                                        <p class="text-muted-foreground text-sm">{{ form.creator?.name || 'Unknown' }}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Response Statistics -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Response Statistics</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="text-center">
                                            <p class="text-2xl font-bold">{{ statistics.total_responses }}</p>
                                            <p class="text-muted-foreground text-sm">Total Responses</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-2xl font-bold text-green-600">{{ statistics.approved }}</p>
                                            <p class="text-muted-foreground text-sm">Approved</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-2xl font-bold text-yellow-600">{{ statistics.pending_review }}</p>
                                            <p class="text-muted-foreground text-sm">Pending Review</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-2xl font-bold text-red-600">{{ statistics.rejected }}</p>
                                            <p class="text-muted-foreground text-sm">Rejected</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Visibility Settings -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Visibility & Access</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div>
                                        <p class="mb-2 text-sm font-medium">Who can submit this form:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge v-for="role in form.visibility_roles" :key="role.id" variant="secondary">
                                                {{ role.name }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Separator />
                                    <div>
                                        <p class="mb-2 text-sm font-medium">Result visibility:</p>
                                        <div class="space-y-2">
                                            <div v-for="visibility in form.result_visibility" :key="visibility.id" class="bg-muted/50 flex items-center justify-between rounded-lg px-3 py-2">
                                                <span class="text-sm">{{ visibility.role?.name }}</span>
                                                <Badge variant="outline">{{ visibility.visibility_level }}</Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Structure Tab -->
                    <TabsContent value="structure" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Form Structure</CardTitle>
                                <CardDescription v-if="publishedVersion"> Published version: {{ publishedVersion.version_no }} </CardDescription>
                                <CardDescription v-else class="text-amber-600"> No published version available </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="publishedVersion" class="space-y-6">
                                    <!-- Sections -->
                                    <div v-if="publishedVersion.sections && publishedVersion.sections.length > 0">
                                        <h3 class="mb-4 font-medium">Sections</h3>
                                        <div class="space-y-4">
                                            <div v-for="section in publishedVersion.sections" :key="section.id" class="rounded-lg border p-4">
                                                <div class="mb-3">
                                                    <h4 class="font-medium">{{ section.title }}</h4>
                                                    <p v-if="section.description" class="text-muted-foreground text-sm">{{ section.description }}</p>
                                                </div>
                                                <div v-if="section.questions && section.questions.length > 0" class="space-y-2">
                                                    <div v-for="question in section.questions" :key="question.id" class="bg-muted/50 flex items-center justify-between rounded px-3 py-2">
                                                        <div>
                                                            <p class="text-sm font-medium">{{ question.text }}</p>
                                                            <p class="text-muted-foreground text-xs">{{ getQuestionTypeLabel(question.type) }}</p>
                                                        </div>
                                                        <Badge v-if="question.is_required" variant="outline" size="sm">Required</Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Standalone Questions -->
                                    <div v-if="publishedQuestions.filter((q) => !q.section).length > 0">
                                        <h3 class="mb-4 font-medium">Questions</h3>
                                        <div class="space-y-2">
                                            <div v-for="question in publishedQuestions.filter((q) => !q.section)" :key="question.id" class="flex items-center justify-between rounded-lg border px-3 py-2">
                                                <div>
                                                    <p class="text-sm font-medium">{{ question.text }}</p>
                                                    <p class="text-muted-foreground text-xs">{{ getQuestionTypeLabel(question.type) }}</p>
                                                </div>
                                                <Badge v-if="question.is_required" variant="outline" size="sm">Required</Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State for Published Version -->
                                    <div v-if="publishedQuestions.length === 0" class="text-muted-foreground py-8 text-center">
                                        <p>No questions in published version</p>
                                        <p class="text-sm">The published version does not contain any questions</p>
                                    </div>
                                </div>

                                <!-- No Published Version State -->
                                <div v-else class="py-8 text-center">
                                    <div class="text-muted-foreground mb-4">
                                        <p class="text-lg font-medium">No Published Version Available</p>
                                        <p class="text-sm">This form does not have a published version yet.</p>
                                    </div>
                                    <div class="text-muted-foreground space-y-1 text-sm">
                                        <p>To see the form structure, you need to publish a version first.</p>
                                        <p>Go to the <strong>Versions</strong> tab to publish a version.</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Versions Tab -->
                    <TabsContent value="versions" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Form Versions</CardTitle>
                                <CardDescription>Manage different versions of this form</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div v-for="version in form.versions" :key="version.id" class="flex items-center justify-between rounded-lg border p-4">
                                        <div class="flex items-center space-x-4">
                                            <Badge variant="outline">v{{ version.version_no }}</Badge>
                                            <div>
                                                <p class="text-sm font-medium">
                                                    Version {{ version.version_no }}
                                                    <Badge v-if="version.is_published" variant="default" size="sm" class="ml-2">Published</Badge>
                                                </p>
                                                <p class="text-muted-foreground text-xs">Created {{ new Date(version.created_at).toLocaleDateString() }}</p>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <Button v-if="!version.is_published" variant="outline" size="sm" @click="publishVersion(version.id)"> Publish </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Targeting Tab -->
                    <TabsContent value="targeting" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Form Targets</CardTitle>
                                <CardDescription>Where and when this form is available</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div v-for="target in form.targets" :key="target.id" class="rounded-lg border p-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium">Campus</p>
                                                <p class="text-muted-foreground text-sm">{{ target.campus_name || 'All Campuses' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">Scope</p>
                                                <p class="text-muted-foreground text-sm">{{ target.scope_type }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">Start Date</p>
                                                <p class="text-muted-foreground text-sm">{{ new Date(target.start_at).toLocaleString() }}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">End Date</p>
                                                <p class="text-muted-foreground text-sm">{{ target.end_at ? new Date(target.end_at).toLocaleString() : 'No end date' }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <p class="text-muted-foreground text-sm">Submission limit: {{ target.submission_limit_per_user }} per user</p>
                                            <Badge :variant="target.is_active ? 'default' : 'secondary'">
                                                {{ target.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Analytics Tab -->
                    <TabsContent value="analytics" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base">Submission Rate</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold">{{ statistics.total_responses }}</p>
                                        <p class="text-muted-foreground text-sm">Total Submissions</p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base">Approval Rate</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-green-600">{{ statistics.total_responses > 0 ? Math.round((statistics.approved / statistics.total_responses) * 100) : 0 }}%</p>
                                        <p class="text-muted-foreground text-sm">{{ statistics.approved }} / {{ statistics.total_responses }}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base">Anonymous</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-blue-600">{{ statistics.anonymous_responses }}</p>
                                        <p class="text-muted-foreground text-sm">Anonymous Responses</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Response Status Breakdown</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Submitted</span>
                                        <div class="flex items-center space-x-2">
                                            <div class="bg-muted h-2 w-20 rounded-full">
                                                <div class="h-2 rounded-full bg-blue-500" :style="{ width: `${statistics.total_responses > 0 ? (statistics.submitted / statistics.total_responses) * 100 : 0}%` }"></div>
                                            </div>
                                            <span class="text-muted-foreground text-sm">{{ statistics.submitted }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Approved</span>
                                        <div class="flex items-center space-x-2">
                                            <div class="bg-muted h-2 w-20 rounded-full">
                                                <div class="h-2 rounded-full bg-green-500" :style="{ width: `${statistics.total_responses > 0 ? (statistics.approved / statistics.total_responses) * 100 : 0}%` }"></div>
                                            </div>
                                            <span class="text-muted-foreground text-sm">{{ statistics.approved }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Rejected</span>
                                        <div class="flex items-center space-x-2">
                                            <div class="bg-muted h-2 w-20 rounded-full">
                                                <div class="h-2 rounded-full bg-red-500" :style="{ width: `${statistics.total_responses > 0 ? (statistics.rejected / statistics.total_responses) * 100 : 0}%` }"></div>
                                            </div>
                                            <span class="text-muted-foreground text-sm">{{ statistics.rejected }}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Link :href="route('forms.admin.edit', form.id)">
                            <Button variant="outline" class="w-full justify-start">
                                <Edit class="mr-2 h-4 w-4" />
                                Edit Form
                            </Button>
                        </Link>
                        <Button variant="outline" class="w-full justify-start" @click="showCloneModal = true">
                            <Copy class="mr-2 h-4 w-4" />
                            Clone Form
                        </Button>
                        <Link :href="route('forms.review.index', { form: form.id })">
                            <Button variant="outline" class="w-full justify-start">
                                <Eye class="mr-2 h-4 w-4" />
                                View Responses
                            </Button>
                        </Link>
                    </CardContent>
                </Card> -->

                <Card class="mt-4">
                    <CardHeader>
                        <CardTitle>Form Health</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Active Targets</span>
                            <span class="text-muted-foreground text-sm">{{ form.active_targets_count || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Questions (Published)</span>
                            <span class="text-muted-foreground text-sm">{{ publishedQuestions.length }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Visibility Roles</span>
                            <span class="text-muted-foreground text-sm">{{ form.visibility_roles?.length || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Latest Version</span>
                            <span class="text-muted-foreground text-sm">v{{ form.latest_version || 1 }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Clone Modal -->
        <FormCloneModal v-model:open="showCloneModal" :form="form" />
    </div>
</template>
