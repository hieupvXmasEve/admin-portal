<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import {
    Check,
    Info,
    Loader2,
    Search,
    Send,
    User,
    X
} from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import * as z from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { NOTIFICATION_ROUTE_NAMES } from '@/constants/notification-routes';

interface Program {
    id: number;
    name: string;
}

interface Recipient {
    id: number;
    name: string;
    code?: string;
    email: string;
}

interface Props {
    categories: Array<{ value: string; label: string; description: string }>;
    programs: Program[];
    currentCampusId: number | null;
}

const props = defineProps<Props>();

const api = useApi();

// --- Selection State ---
const search = ref('');
const programId = ref('all');
const notifiableType = ref<'student' | 'user' | 'lecturer'>('student');
const recipients = ref<Recipient[]>([]);
const selectedRecipients = ref<Recipient[]>([]);
const isSearching = ref(false);

const recipientTypes = [
    { value: 'student', label: 'Student' },
    { value: 'user', label: 'Staff/User' },
    { value: 'lecturer', label: 'Lecturer' },
];

const searchTargets = async () => {
    if (!search.value && programId.value === 'all' && notifiableType.value === 'student') {
        recipients.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const { data: apiData } = await api.get<Recipient[]>(route(NOTIFICATION_ROUTE_NAMES.SEARCH_TARGETS), {
            search: search.value,
            type: notifiableType.value,
            program_id: notifiableType.value === 'student' && programId.value !== 'all' ? programId.value : null,
            campus_id: props.currentCampusId,
        });

        if (apiData.value?.success && apiData.value?.data) {
            recipients.value = apiData.value.data.filter(
                (r: Recipient) => !selectedRecipients.value.some(sel => sel.id === r.id)
            );
        }
    } catch (error) {
        toast.error('Failed to search recipients');
    } finally {
        isSearching.value = false;
    }
};

const handleRecipientTypeChange = () => {
    recipients.value = [];
    selectedRecipients.value = [];
    search.value = '';
    programId.value = 'all';
};

const toggleRecipient = (recipient: Recipient) => {
    const index = selectedRecipients.value.findIndex(r => r.id === recipient.id);
    if (index === -1) {
        selectedRecipients.value.push(recipient);
        recipients.value = recipients.value.filter(r => r.id !== recipient.id);
    } else {
        selectedRecipients.value.splice(index, 1);
        if (search.value || (notifiableType.value === 'student' && programId.value !== 'all')) {
            recipients.value.push(recipient);
        }
    }
};

const removeSelected = (id: number) => {
    selectedRecipients.value = selectedRecipients.value.filter(r => r.id !== id);
};

const addAllVisible = () => {
    recipients.value.forEach(r => {
        if (!selectedRecipients.value.some(sel => sel.id === r.id)) {
            selectedRecipients.value.push(r);
        }
    });
    recipients.value = [];
};

// --- Form State ---
const formSchema = toTypedSchema(z.object({
    category: z.string().min(1, 'Please select a category'),
    title: z.string().min(1, 'Title is required').max(255),
    message: z.string().min(1, 'Message is required'),
    is_important: z.boolean().default(false),
    action_url: z.string().optional().or(z.literal('')).refine((val) => {
        if (!val) return true;
        if (val.startsWith('/')) {
            return /^\/[^\s]*$/.test(val);
        }
        if (val.startsWith('h')) {
            try {
                new URL(val);
                return true;
            } catch (_) {
                return false;
            }
        }
        return false;
    }, {
        message: "URL phải bắt đầu bằng '/' hoặc là một liên kết (http/https) hợp lệ"
    }),
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
    if (selectedRecipients.value.length === 0) {
        toast.error('Please select at least one recipient');
        return;
    }

    const payload = {
        ...values,
        notifiable_type: notifiableType.value,
        notifiable_ids: selectedRecipients.value.map(r => r.id),
        campus_id: props.currentCampusId,
    };

    try {
        const { data: apiData } = await api.post(route(NOTIFICATION_ROUTE_NAMES.STORE), payload);

        if (apiData.value?.success) {
            toast.success(`Notification queued for ${selectedRecipients.value.length} recipients`);
            resetForm();
            selectedRecipients.value = [];
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
                <p class="text-muted-foreground text-sm">Send a manual notification to selected recipients via Realtime.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Recipient Selection -->
            <Card class="lg:col-span-1">
                <CardHeader>
                    <CardTitle>Recipients</CardTitle>
                    <CardDescription>Search and select recipients to receive this notification.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label>Send to</Label>
                        <Select v-model="notifiableType" @update:model-value="handleRecipientTypeChange">
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in recipientTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="notifiableType === 'student'" class="space-y-2">
                        <Label>Program filter</Label>
                        <Select v-model="programId" @update:model-value="searchTargets">
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
                        <Label>Search {{ notifiableType === 'student' ? 'Student' : (notifiableType === 'user' ? 'Staff'
                            : 'Lecturer') }}</Label>
                        <div class="flex gap-2">
                            <Input v-model="search" placeholder="Name, code or email..." @keyup.enter="searchTargets" />
                            <Button size="icon" @click="searchTargets" :disabled="isSearching">
                                <Search v-if="!isSearching" class="h-4 w-4" />
                                <Loader2 v-else class="h-4 w-4 animate-spin" />
                            </Button>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div v-if="recipients.length > 0" class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500">{{ recipients.length }} results found</span>
                            <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click="addAllVisible">Add
                                All</Button>
                        </div>
                        <ScrollArea class="h-[200px] rounded-md border p-2">
                            <div class="space-y-1">
                                <div v-for="r in recipients" :key="r.id"
                                    class="flex cursor-pointer items-center justify-between rounded-md p-2 hover:bg-gray-100"
                                    @click="toggleRecipient(r)">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ r.name }}</span>
                                        <span v-if="r.code" class="text-xs text-gray-500">{{ r.code }}</span>
                                        <span v-else class="text-xs text-gray-400">{{ r.email }}</span>
                                    </div>
                                    <Check class="h-4 w-4 text-transparent hover:text-gray-400" />
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Selected Recipients -->
                    <div class="space-y-2 pt-4 border-t">
                        <Label class="flex items-center justify-between">
                            Selected
                            <Badge variant="secondary">{{ selectedRecipients.length }}</Badge>
                        </Label>
                        <ScrollArea v-if="selectedRecipients.length > 0"
                            class="h-[250px] rounded-md border p-2 bg-gray-50/50">
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="r in selectedRecipients" :key="r.id" variant="outline" class="bg-white">
                                    {{ r.name }}
                                    <span
                                        class="ml-1 cursor-pointer hover:text-red-500 p-0.5 rounded-full hover:bg-red-50 transition-colors"
                                        @click.stop="removeSelected(r.id)">
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
                            <Input id="action_url" v-model="actionUrl" placeholder="route (e.g. /user/123)"
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
                                This notification will be queued for delivery to the recipient's portal.
                                Recipients online will receive it instantly; others will see it in their inbox.
                                Only recipients belonging to your current campus will be notified.
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="justify-end border-t pt-4">
                        <Button type="submit" :disabled="isSubmitting" class="w-full md:w-auto">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Send v-else class="mr-2 h-4 w-4" />
                            Send to {{ selectedRecipients.length }} Recipient(s)
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    </div>
</template>
