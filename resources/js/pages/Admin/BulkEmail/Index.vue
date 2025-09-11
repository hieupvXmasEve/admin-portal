<script setup lang="ts">
import EmailEditor from '@/components/EmailEditor.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApiRequest';
import { useBulkEmail } from '@/composables/useBulkEmail';
import { useStudentEmailVariables } from '@/composables/useStudentEmailVariables';
import type { BulkEmailStudent } from '@/types/email';
import { STUDENT_EMAIL_VARIABLES } from '@/types/email';
import { ClockIcon, EyeIcon, PaperclipIcon, SendIcon, UploadIcon, UserPlus, Users, XIcon } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BulkEmailProgressModal from './components/BulkEmailProgressModal.vue';
import EmailHistoryModal from './components/EmailHistoryModal.vue';
import EmailPreviewModal from './components/EmailPreviewModal.vue';

const { emailTemplates, userRoles, campuses, isSubmitting, loadEmailTemplates, loadUserGroups, sendBulkEmail } = useBulkEmail();

const recipientMode = ref<'manual' | 'groups' | 'upload' | 'students'>('students');
const showProgressModal = ref(false);
const showPreviewModal = ref(false);
const showHistoryModal = ref(false);
const showStudentModal = ref(false);
const currentBatchId = ref<string | null>(null);
const uploadedFile = ref<File | null>(null);
const uploadedRecipients = ref<string[]>([]);

// Student selection functionality
const api = useApi();
const studentIds = ref('');
const selectedStudents = ref<BulkEmailStudent[]>([]);
const isSearchingStudents = ref(false);

// Use student email variables composable
const { buildPerRecipientVariables: buildStudentVars } = useStudentEmailVariables(selectedStudents);

const form = reactive({
    manualRecipients: '',
    selectedRoles: [] as number[],
    selectedCampuses: [] as number[],
    templateId: '',
    subject: '',
    content: '',
    templateVariables: {} as Record<string, string>,
    templateVariablesPerRecipient: {} as Record<string, Record<string, string>>, // keyed by email
    attachments: [] as File[],
    chunkSize: 100,
    sendTest: false,
});

const errors = ref<Record<string, string[]>>({});

// Computed properties
const selectedTemplate = computed(() => {
    if (!form.templateId) return null;
    return emailTemplates.value.find((t) => t.id === parseInt(form.templateId)) || null;
});

const requiredTemplateVariables = computed(() => {
    if (!selectedTemplate.value) return [];

    const content = selectedTemplate.value.subject + ' ' + selectedTemplate.value.html_content;
    const matches = content.match(/\{\{([^}]+)\}\}/g);

    if (!matches) return [];

    return [...new Set(matches.map((match) => match.replace(/[{}]/g, '')))];
});

onMounted(() => {
    loadEmailTemplates();
    loadUserGroups();
});

const getTotalRecipients = () => {
    switch (recipientMode.value) {
        case 'manual':
            return parseManualRecipients().length;
        case 'groups':
            return getSelectedGroupsCount();
        case 'upload':
            return uploadedRecipients.value.length;
        case 'students':
            return selectedStudents.value.length;
        default:
            return 0;
    }
};

const parseManualRecipients = () => {
    if (!form.manualRecipients.trim()) return [];

    return form.manualRecipients
        .split(/[,\n]/)
        .map((email) => email.trim())
        .filter((email) => email && email.includes('@'));
};

const getSelectedGroupsCount = () => {
    let count = 0;

    userRoles.value.forEach((role) => {
        if (form.selectedRoles.includes(role.id)) {
            count += role.users_count || 0;
        }
    });

    campuses.value.forEach((campus) => {
        if (form.selectedCampuses.includes(campus.id)) {
            count += campus.users_count || 0;
        }
    });

    return count;
};

const handleCsvUpload = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    uploadedFile.value = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target?.result as string;
        const emails = text
            .split(/[,\n\r]/)
            .map((email) => email.trim())
            .filter((email) => email && email.includes('@'));

        uploadedRecipients.value = emails;
    };
    reader.readAsText(file);
};

const handleAttachments = (event: Event) => {
    const files = Array.from((event.target as HTMLInputElement).files || []);
    form.attachments.push(...files);
};

const removeAttachment = (index: number) => {
    form.attachments.splice(index, 1);
};

const buildPerRecipientVariables = () => {
    form.templateVariablesPerRecipient = buildStudentVars();
};

const onTemplateChange = () => {
    if (form.templateId) {
        const template = emailTemplates.value.find((t) => t.id === parseInt(form.templateId));
        if (template) {
            form.subject = template.subject;
            form.content = template.html_content;

            // Reset template variables and initialize with required ones (fallbacks)
            form.templateVariables = {};
            requiredTemplateVariables.value.forEach((variable) => {
                form.templateVariables[variable] = '';
            });

            // When using students, derive per-recipient vars and populate global fallbacks
            if (recipientMode.value === 'students' && selectedStudents.value.length > 0) {
                buildPerRecipientVariables();

                // Also populate global template variables with fallback values
                // This ensures the form validation passes and provides defaults
                const firstStudent = selectedStudents.value[0];
                if (firstStudent) {
                    requiredTemplateVariables.value.forEach((variable) => {
                        switch (variable) {
                            case 'name':
                            case 'student_name':
                                form.templateVariables[variable] = firstStudent.fullname || '';
                                break;
                            case 'email':
                                form.templateVariables[variable] = firstStudent.email || '';
                                break;
                            case 'student_id':
                                form.templateVariables[variable] = firstStudent.student_id || '';
                                break;
                            // case 'program':
                            //     form.templateVariables[variable] = firstStudent.program_name || '';
                            //     break;
                            // case 'campus':
                            //     form.templateVariables[variable] = firstStudent.campus_name || '';
                            //     break;
                            // case 'specialization':
                            //     form.templateVariables[variable] = firstStudent.specialization_name || '';
                            //     break;
                            case 'curriculum_version':
                                form.templateVariables[variable] = firstStudent.curriculum_version_code || '';
                                break;
                            // case 'status':
                            //     form.templateVariables[variable] = firstStudent.status || '';
                            //     break;
                            default:
                                // Try to get from template_variables if available
                                // if (firstStudent.template_variables && firstStudent.template_variables[variable]) {
                                //     form.templateVariables[variable] = firstStudent.template_variables[variable];
                                // }
                                break;
                        }
                    });
                }
            }
        }
    } else {
        // Clear template variables when no template is selected
        form.templateVariables = {};
        form.templateVariablesPerRecipient = {};
    }
};

const interpolate = (tpl: string, vars: Record<string, string>) => {
    return tpl.replace(/\{\{\s*([^}]+)\s*\}\}/g, (_, key) => vars[key.trim()] ?? '');
};

const getPreviewSamples = () => {
    if (!form.templateId || recipientMode.value !== 'students' || selectedStudents.value.length === 0) return [];
    const samples = selectedStudents.value.slice(0, 3).map((s) => {
        const vars = form.templateVariablesPerRecipient[s.email] || {
            name: s.fullname || '',
            email: s.email || '',
            student_name: s.fullname || '',
            student_id: s.student_id || '',
            // program: s.program_name || '',
            // campus: s.campus_name || '',
        };
        return {
            email: s.email,
            name: s.fullname,
            subject: interpolate(form.subject, vars),
            content: interpolate(form.content, vars),
        };
    });
    return samples;
};

const previewEmail = () => {
    showPreviewModal.value = true;
};

const handleSubmit = async () => {
    errors.value = {};

    // Validate recipients
    const recipients = getRecipients();
    if (recipients.length === 0) {
        errors.value.recipients = ['Please select at least one recipient'];
        return;
    }

    try {
        const result = await sendBulkEmail({
            recipients,
            subject: form.subject,
            content: form.content,
            template_id: form.templateId ? parseInt(form.templateId) : undefined,
            template_variables: form.templateVariables,
            template_variables_per_recipient: form.templateVariablesPerRecipient,
            attachments: form.attachments,
            chunk_size: form.chunkSize,
        });

        currentBatchId.value = result.batch_id;
        showProgressModal.value = true;
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            console.error('Failed to send bulk email:', error);
        }
    }
};

const getRecipients = (): string[] => {
    switch (recipientMode.value) {
        case 'manual':
            return parseManualRecipients();
        case 'groups':
            // This would need to be implemented to fetch actual email addresses from selected groups
            return [];
        case 'upload':
            return uploadedRecipients.value;
        case 'students':
            return selectedStudents.value.map((student) => student.email);
        default:
            return [];
    }
};

const onSendingComplete = () => {
    showProgressModal.value = false;
    currentBatchId.value = null;

    // Reset form
    Object.assign(form, {
        manualRecipients: '',
        selectedRoles: [],
        selectedCampuses: [],
        templateId: '',
        subject: '',
        content: '',
        templateVariables: {},
        attachments: [],
        chunkSize: 100,
        sendTest: false,
    });

    uploadedFile.value = null;
    uploadedRecipients.value = [];
    selectedStudents.value = [];
};

// Student search functionality
const searchStudents = async () => {
    if (!studentIds.value.trim()) {
        toast.error('Please enter at least one student ID');
        return;
    }

    isSearchingStudents.value = true;

    try {
        const studentIdArray = studentIds.value
            .trim()
            .split(/\s+/)
            .filter((id) => id);

        const response = await api.post('/api/students/by-ids', {
            student_ids: studentIdArray,
        });

        if (response.data?.value?.success) {
            const foundStudents = response.data.value.data.students;
            const foundStudentIds = foundStudents.map((s: any) => s.student_id);
            const notFoundIds = studentIdArray.filter((id) => !foundStudentIds.includes(id));

            if (notFoundIds.length > 0) {
                toast.warning(`Some student IDs were not found: ${notFoundIds.join(', ')}`);
            }

            if (foundStudents.length > 0) {
                // Add found students to selected list (avoiding duplicates)
                const existingIds = new Set(selectedStudents.value.map((s) => s.student_id));
                const newStudents = foundStudents.filter((s: any) => !existingIds.has(s.student_id));

                selectedStudents.value.push(...newStudents);
                console.log(newStudents);
                toast.success(`Added ${newStudents.length} student(s)`);
            }

            // Clear the input
            studentIds.value = '';
            showStudentModal.value = false;
        } else {
            toast.error('Failed to search students');
        }
    } catch (error) {
        console.error('Error searching students:', error);
        toast.error('Failed to search students');
    } finally {
        isSearchingStudents.value = false;
    }
};

const removeStudent = (index: number) => {
    const removed = selectedStudents.value.splice(index, 1);
    if (removed.length && form.templateId) {
        // Rebuild both per-recipient and global variables if template selected
        onTemplateChange();
    }
};

watch([selectedStudents, () => form.templateId, recipientMode], () => {
    if (recipientMode.value === 'students' && form.templateId) {
        // Trigger the full template change logic to rebuild both per-recipient and global variables
        onTemplateChange();
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Bulk Email Composer</h1>
                <p class="mt-1 text-sm text-gray-500">Send emails to multiple recipients with template support and progress tracking</p>
            </div>
            <div class="flex items-center space-x-3">
                <button
                    @click="showHistoryModal = true"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm leading-4 font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                >
                    <ClockIcon class="mr-2 h-4 w-4" />
                    Email History
                </button>
            </div>
        </div>

        <!-- Composer Form -->
        <div class="rounded-lg bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Recipients Section -->
                    <div>
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Recipients</h3>

                        <!-- Recipient Selection Tabs -->
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button
                                    type="button"
                                    @click="recipientMode = 'students'"
                                    :class="['border-b-2 px-1 py-2 text-sm font-medium', recipientMode === 'students' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                                >
                                    Students
                                </button>
                                <button
                                    type="button"
                                    @click="recipientMode = 'manual'"
                                    :class="['border-b-2 px-1 py-2 text-sm font-medium', recipientMode === 'manual' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                                >
                                    Manual Entry
                                </button>
                                <button
                                    type="button"
                                    @click="recipientMode = 'groups'"
                                    :class="['border-b-2 px-1 py-2 text-sm font-medium', recipientMode === 'groups' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                                >
                                    User Groups
                                </button>
                                <button
                                    type="button"
                                    @click="recipientMode = 'upload'"
                                    :class="['border-b-2 px-1 py-2 text-sm font-medium', recipientMode === 'upload' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                                >
                                    Upload CSV
                                </button>
                            </nav>
                        </div>

                        <div class="mt-4">
                            <!-- Manual Entry -->
                            <div v-show="recipientMode === 'manual'" class="space-y-4">
                                <div>
                                    <label for="manual-recipients" class="block text-sm font-medium text-gray-700"> Email Addresses </label>
                                    <textarea
                                        id="manual-recipients"
                                        v-model="form.manualRecipients"
                                        rows="6"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        :class="{ 'border-red-300': errors.recipients }"
                                        placeholder="Enter email addresses, one per line or separated by commas"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">Enter one email address per line or separate multiple addresses with commas</p>
                                    <p v-if="errors.recipients" class="mt-1 text-sm text-red-600">{{ errors.recipients[0] }}</p>
                                </div>
                            </div>

                            <!-- User Groups -->
                            <div v-show="recipientMode === 'groups'" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-gray-700"> User Roles </label>
                                        <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-3">
                                            <div v-for="role in userRoles" :key="role.id" class="flex items-center">
                                                <input :id="`role-${role.id}`" v-model="form.selectedRoles" :value="role.id" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <label :for="`role-${role.id}`" class="ml-2 text-sm text-gray-700"> {{ role.name }} ({{ role.users_count || 0 }} users) </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-gray-700"> Campuses</label>
                                        <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-3">
                                            <div v-for="campus in campuses" :key="campus.id" class="flex items-center">
                                                <input :id="`campus-${campus.id}`" v-model="form.selectedCampuses" :value="campus.id" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <label :for="`campus-${campus.id}`" class="ml-2 text-sm text-gray-700"> {{ campus.name }} ({{ campus.users_count || 0 }} users) </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-lg bg-blue-50 p-3">
                                    <p class="text-sm text-blue-700">Selected groups will include: {{ getSelectedGroupsCount() }} recipients</p>
                                </div>
                            </div>

                            <!-- Upload CSV -->
                            <div v-show="recipientMode === 'upload'" class="space-y-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700"> Upload Recipients CSV </label>
                                    <div class="mt-1 flex justify-center rounded-md border-2 border-dashed border-gray-300 px-6 pt-5 pb-6">
                                        <div class="space-y-1 text-center">
                                            <UploadIcon class="mx-auto h-12 w-12 text-gray-400" />
                                            <div class="flex text-sm text-gray-600">
                                                <label
                                                    for="csv-upload"
                                                    class="relative cursor-pointer rounded-md bg-white font-medium text-indigo-600 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:outline-none hover:text-indigo-500"
                                                >
                                                    <span>Upload a file</span>
                                                    <input id="csv-upload" @change="handleCsvUpload" type="file" accept=".csv,.txt" class="sr-only" />
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">CSV or TXT up to 10MB</p>
                                        </div>
                                    </div>
                                    <div v-if="uploadedFile" class="mt-2 text-sm text-green-600">✓ {{ uploadedFile.name }} ({{ uploadedRecipients.length }} recipients)</div>
                                </div>
                            </div>

                            <!-- Students -->
                            <div v-show="recipientMode === 'students'" class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <Label for="selected-students" class="block text-sm font-medium text-gray-700">Selected Students</Label>
                                    <Dialog v-model:open="showStudentModal">
                                        <DialogTrigger as-child>
                                            <Button variant="outline" size="sm">
                                                <UserPlus class="mr-2 h-4 w-4" />
                                                Add Students
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent class="max-w-md">
                                            <DialogHeader>
                                                <DialogTitle class="flex items-center gap-2">
                                                    <Users class="h-5 w-5" />
                                                    Add Students
                                                </DialogTitle>
                                                <DialogDescription> Enter student IDs separated by whitespace to add them to the recipients. </DialogDescription>
                                            </DialogHeader>

                                            <div class="space-y-4">
                                                <div class="space-y-2">
                                                    <Label for="student-ids">Student IDs</Label>
                                                    <Input id="student-ids" v-model="studentIds" placeholder="Enter student IDs separated by spaces (e.g., ST001 ST002 ST003)" :disabled="isSearchingStudents" />
                                                    <p class="text-muted-foreground text-sm">Students will be added to the email recipients list</p>
                                                </div>
                                            </div>

                                            <DialogFooter>
                                                <DialogClose as-child>
                                                    <Button variant="outline" :disabled="isSearchingStudents"> Cancel </Button>
                                                </DialogClose>
                                                <Button @click="searchStudents" :disabled="isSearchingStudents || !studentIds.trim()">
                                                    {{ isSearchingStudents ? 'Searching...' : 'Add Students' }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>

                                <div class="min-h-[100px]">
                                    <div v-if="selectedStudents.length > 0" class="border-input bg-background flex flex-wrap gap-2 rounded-md border p-3">
                                        <div v-for="(student, index) in selectedStudents" :key="student.id" class="bg-secondary flex items-center gap-2 rounded-md px-3 py-2 text-sm">
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ student.student_id }}</span>
                                                <div class="text-xs text-gray-600">
                                                    <span>{{ student.fullname }}</span>
                                                    <span v-if="student.curriculum_version_code" class="ml-1">({{ student.curriculum_version_code }})</span>
                                                </div>
                                            </div>
                                            <Button @click="removeStudent(index)" variant="outline" class="flex size-5 cursor-pointer items-center justify-center rounded-sm text-gray-500 hover:border-red-500 hover:text-red-600">
                                                <XIcon class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>

                                    <div v-else class="flex h-24 items-center justify-center rounded-md border-2 border-dashed border-gray-300 text-gray-500">
                                        <div class="text-center">
                                            <Users class="mx-auto mb-2 h-8 w-8" />
                                            <p class="text-sm">No students selected</p>
                                            <p class="text-xs">Click "Add Students" to select recipients</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="selectedStudents.length > 0" class="rounded-lg bg-blue-50 p-3">
                                    <p class="text-sm text-blue-700">Selected {{ selectedStudents.length }} student(s) as email recipients</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Content Section -->
                    <div>
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Email Content</h3>

                        <div class="space-y-4">
                            <!-- Template Selection -->
                            <div>
                                <label for="template" class="block text-sm font-medium text-gray-700"> Email Template (Optional) </label>
                                <select id="template" v-model="form.templateId" @change="onTemplateChange" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select a template or compose manually</option>
                                    <option v-for="template in emailTemplates" :key="template.id" :value="template.id">{{ template.name }} ({{ template.type }})</option>
                                </select>
                            </div>

                            <!-- Subject -->
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700"> Subject * </label>
                                <input
                                    id="subject"
                                    v-model="form.subject"
                                    type="text"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    :class="{ 'border-red-300': errors.subject }"
                                    placeholder="Email subject line"
                                />
                                <p v-if="errors.subject" class="mt-1 text-sm text-red-600">{{ errors.subject[0] }}</p>
                            </div>

                            <!-- Content -->
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700"> Email Content * </label>
                                <EmailEditor v-model="form.content" min-height="300px" :common-variables="STUDENT_EMAIL_VARIABLES" :students="selectedStudents" :show-preview="true" placeholder="Start typing your email content..." />
                                <p v-if="errors.content" class="mt-1 text-sm text-red-600">{{ errors.content[0] }}</p>
                            </div>

                            <!-- Template Variables -->
                            <div v-if="requiredTemplateVariables.length > 0">
                                <label class="mb-2 block text-sm font-medium text-gray-700"> Template Variables * </label>
                                <div class="space-y-3 rounded-lg border border-gray-200 p-4">
                                    <!-- Student mode: auto-derived -->
                                    <div v-if="recipientMode === 'students' && selectedStudents.length > 0">
                                        <div class="mb-2 flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                            <p class="text-sm text-green-700">Variables are automatically derived from selected students' data</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div v-for="variable in requiredTemplateVariables" :key="variable" class="rounded bg-gray-50 p-2">
                                                <div class="font-medium text-gray-700">{{ variable.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase()) }}</div>
                                                <div class="mt-1 text-xs text-gray-500">
                                                    <template v-if="variable === 'name' || variable === 'student_name'">From student's fullname</template>
                                                    <template v-else-if="variable === 'email'">From student's email</template>
                                                    <template v-else-if="variable === 'student_id'">From student's ID</template>
                                                    <template v-else-if="variable === 'program'">From student's program</template>
                                                    <template v-else-if="variable === 'campus'">From student's campus</template>
                                                    <template v-else-if="variable === 'specialization'">From student's specialization</template>
                                                    <template v-else-if="variable === 'curriculum_version'">From student's curriculum version</template>
                                                    <template v-else-if="variable === 'status'">From student's status</template>
                                                    <template v-else>Per-student value</template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Manual/other modes: user input required -->
                                    <div v-else>
                                        <p class="text-sm text-gray-600">Fill in the fallback variables for the selected template:</p>
                                        <div v-for="variable in requiredTemplateVariables" :key="variable" class="space-y-1">
                                            <label :for="`var-${variable}`" class="block text-sm font-medium text-gray-700">{{ variable.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase()) }}</label>
                                            <input
                                                :id="`var-${variable}`"
                                                v-model="form.templateVariables[variable]"
                                                type="text"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                :placeholder="`Enter ${variable.replace('_', ' ')}`"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attachments -->
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700"> Attachments (Optional) </label>
                                <div class="mt-1 flex justify-center rounded-md border-2 border-dashed border-gray-300 px-6 pt-5 pb-6">
                                    <div class="space-y-1 text-center">
                                        <PaperclipIcon class="mx-auto h-8 w-8 text-gray-400" />
                                        <div class="flex text-sm text-gray-600">
                                            <label for="attachments" class="relative cursor-pointer rounded-md bg-white font-medium text-indigo-600 hover:text-indigo-500">
                                                <span>Upload files</span>
                                                <input id="attachments" @change="handleAttachments" type="file" multiple class="sr-only" />
                                            </label>
                                        </div>
                                        <p class="text-xs text-gray-500">Up to 10MB per file</p>
                                    </div>
                                </div>
                                <div v-if="form.attachments.length > 0" class="mt-2 space-y-1">
                                    <div v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ file.name }}</span>
                                        <button type="button" @click="removeAttachment(index)" class="text-red-600 hover:text-red-500">
                                            <XIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sending Options -->
                    <div>
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Sending Options</h3>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="chunk-size" class="block text-sm font-medium text-gray-700"> Batch Size </label>
                                <select id="chunk-size" v-model="form.chunkSize" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option :value="50">50 emails per batch</option>
                                    <option :value="100">100 emails per batch</option>
                                    <option :value="200">200 emails per batch</option>
                                    <option :value="500">500 emails per batch</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Smaller batches are processed more reliably but take longer</p>
                            </div>

                            <!--                            <div class="flex items-center">-->
                            <!--                                <input id="send-test" v-model="form.sendTest" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />-->
                            <!--                                <label for="send-test" class="ml-2 block text-sm text-gray-900"> Send test email to myself first </label>-->
                            <!--                            </div>-->
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="rounded-lg bg-gray-50 p-4">
                        <h4 class="mb-2 text-sm font-medium text-gray-900">Sending Summary</h4>
                        <div class="space-y-1 text-sm text-gray-600">
                            <p>Recipients: {{ getTotalRecipients() }}</p>
                            <p>Estimated batches: {{ Math.ceil(getTotalRecipients() / form.chunkSize) }}</p>
                            <p>Attachments: {{ form.attachments.length }}</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
                        <button
                            type="button"
                            @click="previewEmail"
                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                        >
                            <EyeIcon class="mr-2 h-4 w-4" />
                            Preview
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting || getTotalRecipients() === 0"
                            class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ isSubmitting ? 'Sending...' : 'Send Emails' }}
                            <SendIcon class="ml-2 h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Modal -->
        <BulkEmailProgressModal v-model:open="showProgressModal" :batch-id="currentBatchId" @complete="onSendingComplete" />

        <!-- Preview Modal -->
        <EmailPreviewModal v-model:open="showPreviewModal" :subject="form.subject" :content="form.content" :samples="getPreviewSamples()" />

        <!-- History Modal -->
        <EmailHistoryModal v-model:open="showHistoryModal" />
    </div>
</template>
