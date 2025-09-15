<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Form } from '@/types/forms';
import { router } from '@inertiajs/vue3';
import { CheckCircle, FileX, History } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormCard from '../components/FormCard.vue';

interface Props {
    forms: Form[];
    campus: any;
}

const props = defineProps<Props>();
console.log('%c props', 'color: red', props.forms);
const activeTab = ref('all');

const pendingForms = computed(() => {
    return props.forms.filter((form) => form.can_submit);
});

const completedForms = computed(() => {
    return props.forms.filter((form) => !form.can_submit && form.submission_count > 0);
});

const viewForm = (form: Form) => {
    if (form.can_submit) {
        router.visit(route('student.forms.show', form.id));
    } else {
        // View past submission
        router.visit(route('student.forms.history'));
    }
};

const viewHistory = () => {
    router.visit(route('student.forms.history'));
};
</script>

<template>
    <AppLayout title="Forms & Surveys">
        <div class="container mx-auto py-6">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Forms & Surveys</h1>
                <p class="text-muted-foreground mt-1">Complete feedback forms, surveys, and submit queries</p>
            </div>

            <!-- Forms Tabs -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="all">All Forms</TabsTrigger>
                    <TabsTrigger value="pending"> Pending ({{ pendingForms.length }}) </TabsTrigger>
                    <TabsTrigger value="completed"> Completed ({{ completedForms.length }}) </TabsTrigger>
                </TabsList>

                <TabsContent value="all" class="mt-6">
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <FormCard v-for="form in forms" :key="form.id" :form="form" @view="viewForm" />
                    </div>
                    <div v-if="forms.length === 0" class="py-12 text-center">
                        <FileX class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <p class="text-muted-foreground">No forms available</p>
                    </div>
                </TabsContent>

                <TabsContent value="pending" class="mt-6">
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <FormCard v-for="form in pendingForms" :key="form.id" :form="form" @view="viewForm" />
                    </div>
                    <div v-if="pendingForms.length === 0" class="py-12 text-center">
                        <CheckCircle class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <p class="text-muted-foreground">No pending forms</p>
                    </div>
                </TabsContent>

                <TabsContent value="completed" class="mt-6">
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <FormCard v-for="form in completedForms" :key="form.id" :form="form" :is-completed="true" @view="viewForm" />
                    </div>
                    <div v-if="completedForms.length === 0" class="py-12 text-center">
                        <FileX class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <p class="text-muted-foreground">No completed forms yet</p>
                    </div>
                </TabsContent>
            </Tabs>

            <!-- Quick Actions -->
            <div class="mt-8 flex justify-end gap-3">
                <Button variant="outline" @click="viewHistory">
                    <History class="mr-2 h-4 w-4" />
                    View Submission History
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
