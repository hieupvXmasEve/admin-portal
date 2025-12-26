<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useForm } from 'vee-validate';
import * as z from 'zod';
import { toTypedSchema } from '@vee-validate/zod';
import { route } from 'ziggy-js';
import {
    Send,
    Search,
    User,
    X,
    Loader2,
    AlertCircle,
    Info,
    ExternalLink,
    Clock,
    Check
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useApi } from '@/composables/useApiRequest';
import { NOTIFICATION_ROUTE_NAMES } from '@/constants/notification-routes';

interface Program {
    id: number;
    name: string;
}

interface Student {
    id: number;
    full_name: string;
    student_id: string;
    email: string;
}

interface Props {
    categories: Array<{ value: string; label: string; description: string }>;
    programs: Program[];
}

const props = defineProps<Props>();

const api = useApi();

// --- Selection State ---
const search = ref('');
const programId = ref('all');
const students = ref<Student[]>([]);
const selectedStudents = ref<Student[]>([]);
const isSearching = ref(false);

const searchStudents = async () => {
    if (!search.value && programId.value === 'all') {
        students.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const { data: apiData } = await api.get<Student[]>(route(NOTIFICATION_ROUTE_NAMES.SEARCH_STUDENTS), {
            search: search.value,
            program_id: programId.value === 'all' ? null : programId.value
        });

        if (apiData.value?.success && apiData.value?.data) {
            students.value = apiData.value.data.filter(
                (s: Student) => !selectedStudents.value.some(sel => sel.id === s.id)
            );
        }
    } catch (error) {
        toast.error('Failed to search students');
    } finally {
        isSearching.value = false;
    }
};

const toggleStudent = (student: Student) => {
    const index = selectedStudents.value.findIndex(s => s.id === student.id);
    if (index === -1) {
        selectedStudents.value.push(student);
        students.value = students.value.filter(s => s.id !== student.id);
    } else {
        selectedStudents.value.splice(index, 1);
        if (search.value || programId.value !== 'all') {
            students.value.push(student);
        }
    }
};

const removeSelected = (id: number) => {
    selectedStudents.value = selectedStudents.value.filter(s => s.id !== id);
};

const addAllVisible = () => {
    students.value.forEach(s => {
        if (!selectedStudents.value.some(sel => sel.id === s.id)) {
            selectedStudents.value.push(s);
        }
    });
    students.value = [];
};

// --- Form State ---
const formSchema = toTypedSchema(z.object({
    category: z.string().min(1, 'Please select a category'),
    title: z.string().min(1, 'Title is required').max(255),
    message: z.string().min(1, 'Message is required'),
    is_important: z.boolean().default(false),
    action_url: z.string().url('Invalid URL').optional().or(z.literal('')),
    action_text: z.string().max(50).optional().or(z.literal('')),
}));

const { handleSubmit, isSubmitting, errors, defineField, resetForm } = useForm({
    validationSchema: formSchema,
    initialValues: {
        category: 'system',
        title: '',
        message: '',
        is_important: false,
        action_url: '',
        action_text: '',
    }
});

const [category] = defineField('category');
const [title] = defineField('title');
const [message] = defineField('message');
const [isImportant] = defineField('is_important');
const [actionUrl] = defineField('action_url');
const [actionText] = defineField('action_text');

const onSubmit = handleSubmit(async (values) => {
    if (selectedStudents.value.length === 0) {
        toast.error('Please select at least one recipient');
        return;
    }

    const payload = {
        ...values,
        student_ids: selectedStudents.value.map(s => s.id)
    };

    try {
        const { data: apiData } = await api.post(route(NOTIFICATION_ROUTE_NAMES.STORE), payload);

        if (apiData.value?.success) {
            toast.success(`Notification sent to ${selectedStudents.value.length} students`);
            resetForm();
            selectedStudents.value = [];
            router.visit(route(NOTIFICATION_ROUTE_NAMES.INDEX));
        } else {
            toast.error(apiData.value?.message || 'Failed to send notification');
        }
    } catch (e: any) {
        toast.error('Failed to send notification');
    }
});

</script>

<template>

    <Head title="Send Notification" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Send Notification</h1>
                <p class="text-muted-foreground text-sm">Send a manual notification to selected students via DB and
                    Realtime.</p>
            </div>
            <Button variant="outline" @click="router.visit(route(NOTIFICATION_ROUTE_NAMES.INDEX))">
                <Clock class="mr-2 h-4 w-4" />
                View History
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Recipient Selection -->
            <Card class="lg:col-span-1">
                <CardHeader>
                    <CardTitle>Recipients</CardTitle>
                    <CardDescription>Search and select students to receive this notification.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label>Program</Label>
                        <Select v-model="programId" @update:model-value="searchStudents">
                            <SelectTrigger>
                                <SelectValue placeholder="All Programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Programs</SelectItem>
                                <SelectItem v-for="p in programs" :key="p.id" :value="p.id.toString()">
                                    {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Search Student</Label>
                        <div class="flex gap-2">
                            <Input v-model="search" placeholder="Name, code or email..."
                                @keyup.enter="searchStudents" />
                            <Button size="icon" @click="searchStudents" :disabled="isSearching">
                                <Search v-if="!isSearching" class="h-4 w-4" />
                                <Loader2 v-else class="h-4 w-4 animate-spin" />
                            </Button>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div v-if="students.length > 0" class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500">{{ students.length }} results found</span>
                            <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click="addAllVisible">Add
                                All</Button>
                        </div>
                        <ScrollArea class="h-[200px] rounded-md border p-2">
                            <div class="space-y-1">
                                <div v-for="s in students" :key="s.id"
                                    class="flex cursor-pointer items-center justify-between rounded-md p-2 hover:bg-gray-100"
                                    @click="toggleStudent(s)">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ s.full_name }}</span>
                                        <span class="text-xs text-gray-500">{{ s.student_id }}</span>
                                    </div>
                                    <Check class="h-4 w-4 text-transparent hover:text-gray-400" />
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Selected Students -->
                    <div class="space-y-2 pt-4 border-t">
                        <Label class="flex items-center justify-between">
                            Selected
                            <Badge variant="secondary">{{ selectedStudents.length }}</Badge>
                        </Label>
                        <ScrollArea v-if="selectedStudents.length > 0"
                            class="h-[250px] rounded-md border p-2 bg-gray-50/50">
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="s in selectedStudents" :key="s.id" variant="outline" class="bg-white">
                                    {{ s.full_name }}
                                    <span
                                        class="ml-1 cursor-pointer hover:text-red-500 p-0.5 rounded-full hover:bg-red-50 transition-colors"
                                        @click.stop="removeSelected(s.id)">
                                        <X class="h-3 w-3" />
                                    </span>
                                </Badge>
                            </div>
                        </ScrollArea>
                        <div v-else
                            class="flex flex-col items-center justify-center h-[100px] border border-dashed rounded-md text-gray-400">
                            <User class="h-6 w-6 mb-1 opacity-20" />
                            <span class="text-xs">No recipients selected</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Message Form -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Notification Details</CardTitle>
                    <CardDescription>Compose the content of your notification.</CardDescription>
                </CardHeader>
                <form @submit="onSubmit">
                    <CardContent class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="category">Category</Label>
                            <Select v-model="category">
                                <SelectTrigger :class="{ 'border-destructive': errors.category }">
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in categories" :key="cat.value" :value="cat.value">
                                        {{ cat.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.category" class="text-xs text-destructive">{{ errors.category }}</p>
                        </div>

                        <div class="flex items-center space-x-2 pt-8">
                            <Switch id="important" v-model:checked="isImportant" />
                            <Label for="important" class="cursor-pointer">Mark as Important</Label>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="title" placeholder="Notification Subject"
                                :class="{ 'border-destructive': errors.title }" />
                            <p v-if="errors.title" class="text-xs text-destructive">{{ errors.title }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="message">Message</Label>
                            <Textarea id="message" v-model="message" placeholder="Enter your detailed message here..."
                                rows="5" :class="{ 'border-destructive': errors.message }" />
                            <p v-if="errors.message" class="text-xs text-destructive">{{ errors.message }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="action_url">Action Link (Optional)</Label>
                            <Input id="action_url" v-model="actionUrl" placeholder="https://..."
                                :class="{ 'border-destructive': errors.action_url }" />
                            <p v-if="errors.action_url" class="text-xs text-destructive">{{ errors.action_url }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="action_text">Link Text (Optional)</Label>
                            <Input id="action_text" v-model="actionText" placeholder="View Detail"
                                :class="{ 'border-destructive': errors.action_text }" />
                            <p v-if="errors.action_text" class="text-xs text-destructive">{{ errors.action_text }}</p>
                        </div>

                        <div class="md:col-span-2 bg-blue-50 border border-blue-100 rounded-md p-3 flex gap-3">
                            <Info class="h-5 w-5 text-blue-500 shrink-0" />
                            <div class="text-xs text-blue-700 leading-relaxed">
                                This notification will be delivered instantly to the student's portal if they are
                                online, and stored in their inbox database for later viewing.
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="justify-end border-t pt-4">
                        <Button type="submit" :disabled="isSubmitting" class="w-full md:w-auto">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Send v-else class="mr-2 h-4 w-4" />
                            Send to {{ selectedStudents.length }} Recipient(s)
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    </div>
</template>
