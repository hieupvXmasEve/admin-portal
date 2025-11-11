<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type { QueryReply, QueryTicket } from '@/types/forms';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { format, formatDistanceToNow } from 'date-fns';
import { useForm as useVeeForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import * as z from 'zod';

interface Props {
    ticket: QueryTicket;
    statusOptions: string[];
}

const props = defineProps<Props>();
console.log('props', props.ticket);

// Zod schema for reply form validation
const replyFormSchema = toTypedSchema(
    z.object({
        message: z.string().min(1, 'Message is required'),
        is_official_answer: z.boolean().default(true),
        set_pending: z.boolean().default(false),
        attachment: z.instanceof(File).optional().nullable(),
    }),
);

const statusLabelMap: Record<string, string> = {
    open: 'Open',
    pending: 'Pending',
    answered: 'Answered',
    closed: 'Closed',
};

const statusVariantMap: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    open: 'secondary',
    pending: 'outline',
    answered: 'default',
    closed: 'destructive',
};

const topicLabel = computed(() => props.ticket.topic?.title ?? props.ticket.custom_topic_text ?? 'Other');

const orderedAnswers = computed(() => {
    const answers = props.ticket.response?.answers ?? [];
    return [...answers].sort((a, b) => {
        // Sort by answer ID since question data might not be available
        return a.id - b.id;
    });
});

const replies = computed<QueryReply[]>(() => props.ticket.replies ?? []);

const isClosed = computed(() => props.ticket.status === 'closed');

const formatDateTime = (value?: string | null) => {
    if (!value) return '—';
    try {
        return format(new Date(value), 'dd MMM yyyy HH:mm');
    } catch {
        return value;
    }
};

const formatRelative = (value?: string | null) => {
    if (!value) return '';
    try {
        return formatDistanceToNow(new Date(value), { addSuffix: true });
    } catch {
        return '';
    }
};

// vee-validate form setup
const { handleSubmit, setFieldValue, values, resetForm } = useVeeForm({
    validationSchema: replyFormSchema,
    initialValues: {
        message: '',
        is_official_answer: true,
        set_pending: false,
        attachment: null,
    },
});

const fileInputResetKey = ref<number>(0);

// Watch for official answer changes to disable pending checkbox
watch(
    () => values.is_official_answer,
    (isOfficial) => {
        if (isOfficial) {
            setFieldValue('set_pending', false);
        }
    },
);

const submitReply = handleSubmit((values) => {
    const formData = new FormData();
    formData.append('message', values.message);
    formData.append('is_official_answer', values.is_official_answer ? '1' : '0');
    formData.append('set_pending', values.set_pending ? '1' : '0');

    if (values.attachment) {
        formData.append('attachment', values.attachment);
    }
    // Use Inertia's router for form submission
    router.post(route('forms.queries.replies.store', props.ticket.id), formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            // Reset attachment field and remount the Input component
            setFieldValue('message', '');
            setFieldValue('is_official_answer', true);
            setFieldValue('set_pending', false);
            setFieldValue('attachment', null);
            fileInputResetKey.value += 1;
            resetForm();
        },
    });
});

const onAttachmentChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    setFieldValue('attachment', file);
};

const statusForm = useForm({
    status: props.ticket.status as any,
});

watch(
    () => props.ticket.status,
    (newStatus) => {
        statusForm.status = newStatus as any;
    },
);

const updateStatus = (value: string) => {
    if (!value || value === props.ticket.status) {
        return;
    }

    statusForm.status = value;
    statusForm.post(route('forms.queries.status.update', props.ticket.id), {
        preserveScroll: true,
    });
};

const closeTicket = () => {
    if (isClosed.value) {
        return;
    }

    statusForm.status = 'closed';
    statusForm.post(route('forms.queries.status.update', props.ticket.id), {
        preserveScroll: true,
    });
};

const backToList = () => {
    router.visit(route('forms.queries.index'));
};
</script>

<template>
    <Head :title="`Query #${ticket.id}`" />

    <div class="space-y-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="sm" @click="backToList">Back to Queries</Button>
            <Badge :variant="statusVariantMap[ticket.status] || 'secondary'">{{ statusLabelMap[ticket.status] || ticket.status }}</Badge>
        </div>

        <Card>
            <CardHeader class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        {{ topicLabel }}
                    </CardTitle>
                    <CardDescription>Ticket #{{ ticket.id }} &middot; {{ formatRelative(ticket.created_at) }}</CardDescription>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-[180px]">
                        <Label class="text-muted-foreground mb-1 block text-xs tracking-wide uppercase">Status</Label>
                        <Select :model-value="statusForm.status" @update:model-value="(value: any) => typeof value === 'string' && updateStatus(value)">
                            <SelectTrigger :disabled="statusForm.processing">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in statusOptions" :key="option" :value="option">
                                    {{ statusLabelMap[option] || option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button variant="destructive" :disabled="isClosed || statusForm.processing" @click="closeTicket"> Close Ticket </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label class="text-muted-foreground text-xs tracking-wide uppercase">Student</Label>
                        <div class="text-sm font-medium">
                            {{ ticket.response?.student?.full_name || 'Anonymous' }}
                        </div>
                        <div v-if="ticket.response?.student?.student_id" class="text-muted-foreground text-xs">
                            {{ ticket.response.student.student_id }}
                        </div>
                    </div>
                    <div>
                        <Label class="text-muted-foreground text-xs tracking-wide uppercase">Campus</Label>
                        <div class="text-sm font-medium">{{ ticket.response?.campus?.name || '—' }}</div>
                    </div>
                    <div>
                        <Label class="text-muted-foreground text-xs tracking-wide uppercase">Submitted</Label>
                        <div class="text-sm">{{ formatDateTime(ticket.response?.submitted_at) }}</div>
                    </div>
                    <div>
                        <Label class="text-muted-foreground text-xs tracking-wide uppercase">Ticket Created</Label>
                        <div class="text-sm">{{ formatDateTime(ticket.created_at) }}</div>
                    </div>
                    <div v-if="ticket.closed_at">
                        <Label class="text-muted-foreground text-xs tracking-wide uppercase">Closed At</Label>
                        <div class="text-sm">{{ formatDateTime(ticket.closed_at) }}</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Original Submission</CardTitle>
                <CardDescription>
                    <div class="space-y-1">
                        <div class="font-medium">{{ ticket.response?.form?.title }}</div>
                        <div v-if="ticket.response?.form?.type" class="text-xs">
                            Form Type: <span class="uppercase">{{ ticket.response.form.type }}</span>
                        </div>
                    </div>
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-for="(answer, index) in orderedAnswers" :key="answer.id" class="space-y-3">
                    <div v-if="index > 0" class="my-4">
                        <Separator />
                    </div>
                    <div class="space-y-3">
                        <div>
                            <div class="text-muted-foreground mb-1 text-xs tracking-wide uppercase">Question</div>
                            <div class="text-sm font-medium" v-html="answer.question?.text.replace(/\n/g, '<br>') || `Question #${answer.question_id}`"></div>
                            <div v-if="answer.question?.code" class="text-muted-foreground mt-1 text-xs">Code: {{ answer.question.code }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground mb-1 text-xs tracking-wide uppercase">Answer</div>
                            <div v-if="answer.answer_text || answer.formatted_value" class="text-sm whitespace-pre-wrap">
                                {{ answer.answer_text || answer.formatted_value }}
                            </div>
                            <div v-if="answer.selected_options && answer.selected_options.length" class="space-y-2">
                                <ul class="space-y-2 text-sm">
                                    <li v-for="option in answer.selected_options" :key="option.id" class="space-y-1">
                                        <!-- <div class="font-medium">{{ option.label }}</div> -->
                                        <div v-if="option.free_text" class="text-muted-foreground pl-4 italic">
                                            {{ option.free_text }}
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!answer.answer_text && !answer.formatted_value && (!answer.selected_options || !answer.selected_options.length)" class="text-muted-foreground text-sm italic">No answer provided</div>
                        </div>
                        <div v-if="answer.attachments && answer.attachments.length" class="space-y-1">
                            <div class="text-muted-foreground text-xs tracking-wide uppercase">Attachments</div>
                            <ul class="space-y-1 text-sm">
                                <li v-for="attachment in answer.attachments" :key="attachment.id">
                                    <a :href="attachment.download_url" class="text-primary hover:underline" target="_blank" rel="noopener">
                                        {{ attachment.file_name }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div v-if="ticket.response?.attachments && ticket.response.attachments.length" class="space-y-2">
                    <Separator />
                    <div class="text-muted-foreground text-xs tracking-wide uppercase">Additional Attachments</div>
                    <ul class="space-y-1 text-sm">
                        <li v-for="attachment in ticket.response.attachments" :key="attachment.id">
                            <a :href="attachment.download_url" class="text-primary hover:underline" target="_blank" rel="noopener">
                                {{ attachment.file_name }}
                            </a>
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Replies</CardTitle>
                <CardDescription>Review the conversation and provide updates or official answers.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <Separator />
                <form class="space-y-4" @submit="submitReply">
                    <FormField v-slot="{ value, handleChange }" name="message">
                        <FormItem>
                            <FormLabel>Message</FormLabel>
                            <FormControl>
                                <Textarea :model-value="value" @update:model-value="handleChange" :disabled="isClosed" rows="4" placeholder="Write your reply..." />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid gap-4 md:grid-cols-2">
                        <FormField v-slot="{ value, handleChange }" name="is_official_answer">
                            <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                                <FormControl>
                                    <Checkbox :model-value="value" @update:model-value="handleChange" :disabled="isClosed" />
                                </FormControl>
                                <div class="space-y-1 leading-none">
                                    <FormLabel>Mark as official answer</FormLabel>
                                </div>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="set_pending">
                            <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                                <FormControl>
                                    <Checkbox :model-value="value" @update:model-value="handleChange" :disabled="isClosed || values.is_official_answer" />
                                </FormControl>
                                <div class="space-y-1 leading-none">
                                    <FormLabel>Set ticket to pending</FormLabel>
                                </div>
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{}" name="attachment">
                        <FormItem>
                            <FormLabel>Attachment</FormLabel>
                            <FormControl>
                                <Input :key="fileInputResetKey" type="file" :disabled="isClosed" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.txt,.zip" @change="onAttachmentChange" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="isClosed || !values.message"> Send Reply </Button>
                    </div>
                </form>

                <div v-if="!replies.length" class="text-muted-foreground text-sm">No replies yet.</div>
                <div v-else class="space-y-4">
                    <div v-for="reply in replies" :key="reply.id" class="rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ reply.author?.name || 'Staff' }}</div>
                                <div class="text-muted-foreground text-xs">{{ formatDateTime(reply.created_at) }} &middot; {{ formatRelative(reply.created_at) }}</div>
                            </div>
                            <Badge v-if="reply.is_official_answer" variant="default">Official Answer</Badge>
                        </div>
                        <p class="text-muted-foreground mt-3 text-sm whitespace-pre-wrap">{{ reply.message }}</p>
                        <div v-if="reply.attachment" class="mt-3 text-sm">
                            <a :href="reply.attachment.download_url" class="text-primary hover:underline" target="_blank" rel="noopener">
                                {{ reply.attachment.file_name }}
                            </a>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
