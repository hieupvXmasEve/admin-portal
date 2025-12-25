<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import { formRunSchema } from '@/schemas/formRun';
import { useApi } from '@/composables/useApiRequest';
import { toast } from 'vue-sonner';

interface Props {
    forms: { id: number; title: string; type: string }[];
    semesters: { id: string; name: string }[];
    departments: { id: number; name: string }[];
}

const props = defineProps<Props>();
const api = useApi();

const { handleSubmit, isSubmitting, errors, defineField, values } = useForm({
    validationSchema: toTypedSchema(formRunSchema),
    initialValues: {
        form_id: '',
        scope_type: 'global',
        scope_id: '',
        semester_id: '',
        start_at: '',
        end_at: '',
        is_mandatory: false,
        submission_limit_per_user: 1
    }
});

const [formId] = defineField('form_id');
const [scopeType] = defineField('scope_type');
const [scopeId] = defineField('scope_id');
const [semesterId] = defineField('semester_id');
const [startAt] = defineField('start_at');
const [endAt] = defineField('end_at');
const [isMandatory] = defineField('is_mandatory');

const isContextVisible = computed(() => {
    return ['course', 'department', 'semester'].includes(values.scope_type || '');
});

const onSubmit = handleSubmit(async (formValues) => {
    try {
        const payload = { ...formValues };
        if (payload.scope_type === 'semester') {
            payload.scope_id = payload.semester_id;
        }
        
        // Clean up empty or none value for backend
        if (!payload.semester_id || payload.semester_id === 'none') {
            payload.semester_id = undefined;
        }

        const { data, error } = await api.post(route('forms.admin.runs.store'), payload);
        
        if (data.value?.success) {
            toast.success(data.value.message || 'Form Run created successfully.');
            router.visit(route('forms.admin.runs.index'));
        } else {
            toast.error(data.value?.message || error.value?.message || 'Failed to create form run.');
        }
    } catch (err: any) {
        toast.error('An unexpected error occurred.');
    }
});
</script>

<template>
    <Head title="Create Run" />
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="router.visit(route('forms.admin.runs.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <h1 class="text-2xl font-bold tracking-tight">Create Form Run</h1>
    </div>

    <Card class="shadow-lg border-primary/10">
        <CardHeader>
            <CardTitle>Configuration</CardTitle>
            <CardDescription>Setup a new survey or query campaign for students.</CardDescription>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="onSubmit" class="space-y-6">
                
                <div class="space-y-2">
                    <Label :class="{ 'text-destructive': errors.form_id }">Form Template</Label>
                    <Select v-model="formId">
                        <SelectTrigger :class="{ 'border-destructive': errors.form_id }" class="w-full">
                            <SelectValue placeholder="Select a form..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="f in forms" :key="f.id" :value="f.id.toString()">
                                <div class="flex items-center justify-between w-full gap-2">
                                    <span>{{ f.title }}</span>
                                    <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground">{{ f.type }}</span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.form_id" class="text-sm font-medium text-destructive">{{ errors.form_id }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <Label>Context Type</Label>
                        <Select v-model="scopeType">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="global">Global</SelectItem>
                                <SelectItem value="department">Department</SelectItem>
                                <SelectItem value="semester">Semester</SelectItem>
                                <SelectItem value="course">Course Offering</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Dynamic Scope Inputs -->
                    <div v-if="values.scope_type === 'department'" class="space-y-2">
                        <Label :class="{ 'text-destructive': errors.scope_id }">Department</Label>
                        <Select v-model="scopeId">
                                <SelectTrigger :class="{ 'border-destructive': errors.scope_id }">
                                    <SelectValue placeholder="Select Dept" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="d in departments" :key="d.id" :value="d.id.toString()">{{ d.name }}</SelectItem>
                                </SelectContent>
                        </Select>
                        <p v-if="errors.scope_id" class="text-sm font-medium text-destructive">{{ errors.scope_id }}</p>
                    </div>
                    
                    <div v-else-if="values.scope_type === 'semester'" class="space-y-2">
                            <Label :class="{ 'text-destructive': errors.semester_id }">Semester</Label>
                            <Select v-model="semesterId">
                                <SelectTrigger :class="{ 'border-destructive': errors.semester_id }">
                                    <SelectValue placeholder="Select Semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in semesters" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                                </SelectContent>
                        </Select>
                        <p v-if="errors.semester_id" class="text-sm font-medium text-destructive">{{ errors.semester_id }}</p>
                    </div>

                    <div v-else-if="values.scope_type === 'course'" class="space-y-2">
                        <Label :class="{ 'text-destructive': errors.scope_id }">Course Offering ID</Label>
                        <Input v-model="scopeId" :class="{ 'border-destructive': errors.scope_id }" placeholder="Enter ID" />
                        <p class="text-xs text-muted-foreground" v-if="!errors.scope_id">Enter the numeric ID of the offering.</p>
                        <p v-if="errors.scope_id" class="text-sm font-medium text-destructive">{{ errors.scope_id }}</p>
                    </div>
                </div>
                
                <!-- Semester Constraint for Dept -->
                <div v-if="values.scope_type === 'department'" class="space-y-3 p-4 bg-muted/30 rounded-lg border border-border/50">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm font-semibold">Semester Restriction</Label>
                            <span class="text-[10px] uppercase font-bold tracking-wider text-muted-foreground bg-muted px-1.5 py-0.5 rounded">Optional</span>
                        </div>
                        <Select v-model="semesterId">
                            <SelectTrigger class="bg-background">
                                <SelectValue placeholder="None (All students)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None (All students)</SelectItem>
                                <SelectItem v-for="s in semesters" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                            </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground italic">Limit this department form to students active in a specific semester.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <Label :class="{ 'text-destructive': errors.start_at }">Start At</Label>
                        <Input type="datetime-local" v-model="startAt" :class="{ 'border-destructive': errors.start_at }" />
                        <p v-if="errors.start_at" class="text-sm font-medium text-destructive">{{ errors.start_at }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label :class="{ 'text-destructive': errors.end_at }">End At</Label>
                        <Input type="datetime-local" v-model="endAt" :class="{ 'border-destructive': errors.end_at }" />
                        <p v-if="errors.end_at" class="text-sm font-medium text-destructive">{{ errors.end_at }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-4 p-4 rounded-lg bg-primary/5 border border-primary/10">
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <Label for="mandatory" class="text-base">Mandatory Gate</Label>
                            <p class="text-sm text-muted-foreground">Block portal access until completed</p>
                        </div>
                        <Switch v-model="isMandatory" id="mandatory" />
                    </div>
                    
                    <div v-if="values.is_mandatory" class="bg-destructive/10 text-destructive text-xs p-2 rounded border border-destructive/20 flex items-start gap-2 animate-in fade-in slide-in-from-top-1">
                        <div class="w-1 h-1 rounded-full bg-destructive mt-1.5" />
                        <span>Warning: This will prevent students from using the portal until they submit this form. Use with caution.</span>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <Button type="button" variant="outline" @click="router.visit(route('forms.admin.runs.index'))">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[120px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isSubmitting ? 'Creating...' : 'Create Run' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
