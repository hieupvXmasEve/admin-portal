<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { format, formatDistanceToNow } from 'date-fns';
import { 
    ArrowLeft, 
    Calendar, 
    CheckCircle2, 
    Clock, 
    FileText, 
    MessageSquare, 
    Paperclip, 
    Send, 
    User as UserIcon 
} from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    ticket: any;
    staffs: any[];
    statusOptions: string[];
}

const props = defineProps<Props>();

const statusLabelMap: Record<string, string> = {
    open: 'Open',
    pending: 'Pending',
    answered: 'Answered',
    closed: 'Closed',
};

const statusVariantMap: Record<string, 'secondary' | 'outline' | 'default' | 'destructive'> = {
    open: 'secondary',
    pending: 'outline',
    answered: 'default',
    closed: 'destructive',
};

// Forms
const assignForm = useForm({
    assigned_to_user_id: props.ticket.assigned_to_user_id?.toString() || ''
});

const replyForm = useForm({
    message: '',
    is_official_answer: true,
    set_pending: false,
    attachment: null as File | null
});

// Computed
const replies = computed(() => props.ticket.query_ticket?.replies || []);
const isClosed = computed(() => props.ticket.query_status === 'closed');

// Methods
const formatDate = (date: string) => format(new Date(date), 'dd/MM/yyyy HH:mm');
const formatRelative = (date: string) => formatDistanceToNow(new Date(date), { addSuffix: true });

const onAssign = () => {
    assignForm.post(route('forms.admin.inbox.assign', props.ticket.id), { preserveScroll: true });
};

const onUpdateStatus = (val: string) => {
    router.post(route('forms.admin.inbox.status.update', props.ticket.id), { status: val }, { preserveScroll: true });
};

const onFileChange = (e: Event) => {
    const files = (e.target as HTMLInputElement).files;
    if (files && files.length > 0) replyForm.attachment = files[0];
};

const onReply = () => {
    replyForm.post(route('forms.admin.inbox.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            const input = document.getElementById('reply-attachment') as HTMLInputElement;
            if (input) input.value = '';
        }
    });
};
</script>

<template>
    <Head :title="`Query #${ticket.id}`" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" @click="router.visit(route('forms.admin.inbox.index'))">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight">Query #{{ ticket.id }}</h1>
                        <Badge :variant="statusVariantMap[ticket.query_status]">{{ statusLabelMap[ticket.query_status] }}</Badge>
                    </div>
                    <p class="text-muted-foreground text-sm mt-1">Submitted {{ formatRelative(ticket.created_at) }} &middot; {{ ticket.form.title }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Select :model-value="ticket.query_status" @update:model-value="(v: any) => onUpdateStatus(v as string)">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in statusOptions" :key="opt" :value="opt" class="capitalize">{{ opt }}</SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="destructive" size="sm" @click="onUpdateStatus('closed')" :disabled="isClosed">Close Ticket</Button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Original Submission -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg font-bold flex items-center gap-2">
                            <FileText class="h-5 w-5 text-muted-foreground" />
                            Original Submission
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Topic -->
                        <div v-if="ticket.query_ticket" class="pb-4 border-b">
                            <label class="text-[12px] font-bold text-muted-foreground uppercase tracking-wider mb-1 block">Support Topic</label>
                            <div class="text-sm font-bold">
                                {{ ticket.query_ticket.topic?.title || ticket.query_ticket.custom_topic_text || 'General Inquiry' }}
                            </div>
                        </div>

                        <!-- Answers -->
                        <div v-if="ticket.answers?.length" class="space-y-6">
                            <div v-for="ans in ticket.answers" :key="ans.id" class="space-y-2">
                                <div>
                                    <label class="text-[14px] font-bold text-muted-foreground uppercase tracking-wider">{{ ans.question?.text || 'Question' }}</label>
                                    <div v-if="ans.question?.code" class="text-[11px] text-muted-foreground font-mono">Code: {{ ans.question.code }}</div>
                                </div>
                                
                                <div class="text-sm leading-relaxed">
                                    <div v-if="ans.selected_options?.length" class="space-y-1">
                                        <div v-for="opt in ans.selected_options" :key="opt.id">
                                            <Badge variant="secondary" class="h-5 text-[14px] font-medium">{{ opt.label }}</Badge>
                                            <div v-if="opt.pivot?.free_text || opt.free_text" class="text-muted-foreground italic text-sm mt-1 ml-2">
                                                — {{ opt.pivot?.free_text || opt.free_text }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="ans.answer_text" class="whitespace-pre-wrap">{{ ans.answer_text }}</div>
                                    <div v-else-if="ans.formatted_value">{{ ans.formatted_value }}</div>
                                    <div v-else-if="ans.answer_number !== null && ans.answer_number !== undefined">{{ ans.answer_number }}</div>
                                    <div v-else-if="ans.answer_date">{{ formatDate(ans.answer_date) }}</div>
                                    <div v-else class="text-muted-foreground italic">—</div>
                                    
                                    <div v-if="ans.comment" class="mt-2 text-sm text-muted-foreground bg-muted/50 p-2 rounded">
                                        Note: {{ ans.comment }}
                                    </div>
                                </div>

                                <div v-if="ans.attachments?.length" class="flex flex-wrap gap-2 mt-2">
                                    <a v-for="file in ans.attachments" :key="file.id" :href="file.url" target="_blank"
                                       class="text-[14px] text-primary hover:underline flex items-center gap-1 p-1 bg-primary/5 rounded border border-primary/20">
                                        <Paperclip class="h-3 w-3" /> {{ file.original_name }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- General Attachments -->
                        <div v-if="ticket.attachments?.length" class="pt-4 border-t">
                            <label class="text-[14px] font-bold text-muted-foreground uppercase tracking-wider block mb-2">Additional Files</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <a v-for="file in ticket.attachments" :key="file.id" :href="file.url" target="_blank"
                                   class="text-sm border p-2 rounded-md flex items-center gap-2 hover:bg-muted/50 transition-colors">
                                    <Paperclip class="h-3.5 w-3.5 text-muted-foreground" />
                                    <span class="truncate">{{ file.original_name }}</span>
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Discussion -->
                <div class="space-y-4">
                    <h3 class="font-bold flex items-center gap-2">
                        <MessageSquare class="h-5 w-5 text-muted-foreground" /> Discussion
                    </h3>

                    <div v-if="replies.length" class="space-y-4">
                        <div v-for="reply in replies" :key="reply.id" 
                             class="flex gap-4 p-4 rounded-xl border group transition-all"
                             :class="reply.is_official_answer ? 'bg-primary/5 border-primary/10 ml-6' : 'bg-card mr-6 shadow-sm'">
                            <Avatar class="h-9 w-9 border">
                                <AvatarFallback class="text-sm font-bold">
                                    {{ reply.author?.name ? reply.author.name[0] : (reply.author_student?.full_name ? reply.author_student.full_name[0] : 'U') }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm">{{ reply.author?.name || reply.author_student?.full_name || 'System' }}</span>
                                        <span class="text-[12px] text-muted-foreground">{{ formatDate(reply.created_at) }}</span>
                                    </div>
                                    <Badge v-if="reply.is_official_answer" variant="secondary" class="text-[11px] px-1.5 h-4">OFFICIAL</Badge>
                                </div>
                                <div class="text-sm text-foreground/90 whitespace-pre-wrap leading-relaxed">{{ reply.message }}</div>
                                <div v-if="reply.upload_record" class="pt-2 border-t mt-2">
                                    <a :href="reply.upload_record.url" target="_blank" class="text-sm font-medium text-primary hover:underline inline-flex items-center gap-1.5">
                                        <Paperclip class="h-3 w-3" /> {{ reply.upload_record.original_name }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reply Box -->
                    <Card class="border-2 border-primary/10 shadow-lg">
                        <CardContent class="p-4 space-y-4">
                            <Textarea v-model="replyForm.message" placeholder="Type your response to the student..." class="min-h-[120px] shadow-none border-none focus-visible:ring-0 text-sm" :disabled="isClosed" />
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t">
                                <div class="flex items-center gap-4">
                                    <div class="relative group">
                                        <input type="file" id="reply-attachment" @change="onFileChange" class="absolute inset-0 opacity-0 cursor-pointer z-10" :disabled="isClosed" />
                                        <Button type="button" variant="outline" size="sm" class="h-8">
                                            <Paperclip class="h-3.5 w-3.5 mr-2" />
                                            <span class="max-w-[100px] truncate">{{ replyForm.attachment ? replyForm.attachment.name : 'Attach File' }}</span>
                                        </Button>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox id="official" v-model:checked="replyForm.is_official_answer" :disabled="isClosed" @update:checked="(v: any) => { if(v) replyForm.set_pending = false }" />
                                        <Label for="official" class="text-sm cursor-pointer">Official Answer</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox id="pending" v-model:checked="replyForm.set_pending" :disabled="isClosed || replyForm.is_official_answer" />
                                        <Label for="pending" class="text-sm cursor-pointer">Pending</Label>
                                    </div>
                                </div>
                                <Button size="sm" class="px-6" @click="onReply" :disabled="isClosed || !replyForm.message || replyForm.processing">
                                    <Send class="h-4 w-4 mr-2" /> Post Reply
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Student Info -->
                <Card>
                    <CardHeader class="pb-3 border-b bg-muted/5">
                        <CardTitle class="text-sm font-bold uppercase tracking-wider text-muted-foreground">Student Information</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-4">
                        <div v-if="ticket.student" class="flex gap-4 items-center">
                            <Avatar class="h-10 w-10">
                                <AvatarFallback class="bg-primary/5 text-primary">{{ ticket.student.full_name[0] }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <h4 class="font-bold text-sm">{{ ticket.student.full_name }}</h4>
                                <p class="text-sm text-primary font-mono">{{ ticket.student.student_id }}</p>
                            </div>
                        </div>
                        <div v-else class="text-sm italic text-muted-foreground">Anonymous Submission</div>
                        
                        <Separator />
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Campus:</span> <span class="font-medium">{{ ticket.campus?.name || '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Date Submitted:</span> <span class="font-medium">{{ formatDate(ticket.created_at) }}</span></div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Assignment -->
                <Card>
                    <CardHeader class="pb-3 border-b bg-muted/5">
                        <CardTitle class="text-sm font-bold uppercase tracking-wider text-muted-foreground">Ticket Assignment</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-4">
                        <div class="space-y-2">
                            <Label class="text-[12px] uppercase font-bold text-muted-foreground">Assigned To</Label>
                            <Select v-model="assignForm.assigned_to_user_id">
                                <SelectTrigger class="w-full h-9"><SelectValue placeholder="Unassigned" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="staff in staffs" :key="staff.id" :value="staff.id.toString()">{{ staff.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button variant="secondary" size="sm" class="w-full" @click="onAssign" :disabled="assignForm.processing || assignForm.assigned_to_user_id === ticket.assigned_to_user_id?.toString()">
                            Update Assignee
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
.backdrop-blur-sm {
    backdrop-filter: blur(8px);
}
</style>
