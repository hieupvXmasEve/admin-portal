<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import type { CourseOffering, Student } from '@/types/models';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowRightLeft, Save, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

// Props
interface Props {
    open: boolean;
    currentOfferingId: number;
    student: Student | null;
    siblingOfferings: Partial<CourseOffering>[];
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const api = useApi();

// Form validation schema
const moveStudentSchema = toTypedSchema(
    z.object({
        target_course_offering_id: z.string({
            required_error: 'Please select a target section',
        }).min(1, 'Please select a target section'),
        force_move: z.boolean().default(false),
    })
);

// Initialize form
const { handleSubmit, resetForm, isSubmitting } = useForm({
    validationSchema: moveStudentSchema,
    initialValues: {
        target_course_offering_id: '',
        force_move: false,
    },
});

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const studentName = computed(() => props.student?.full_name || 'Unknown Student');

// Methods
const onSubmit = handleSubmit(async (formValues) => {
    if (!props.student) return;

    try {
        const result = await api.post(`/course-offerings/${props.currentOfferingId}/move-student`, {
            student_id: props.student.id,
            target_course_offering_id: parseInt(formValues.target_course_offering_id),
            force_move: formValues.force_move,
        });

        if (result.data.value?.success) {
            toast.success(result.data.value.message || 'Student moved successfully');
            emit('success');
            handleClose();
        } else {
            toast.error(result.data.value?.message || 'Failed to move student');
        }
    } catch (error: any) {
        console.error('Error moving student:', error);
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors;
            if (validationErrors) {
                Object.keys(validationErrors).forEach((field) => {
                    toast.error(validationErrors[field][0]);
                });
            }
        } else {
            toast.error(error.response?.data?.message || 'Failed to move student');
        }
    }
});

const handleClose = () => {
    resetForm();
    emit('update:open', false);
};

// Reset form when modal opens/closes
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            resetForm();
        }
    }
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ArrowRightLeft class="h-5 w-5" />
                    Move Student to Another Section
                </DialogTitle>
                <DialogDescription>
                    Move <strong>{{ studentName }}</strong> to another section of the same unit.
                    Attendance records will be migrated where possible.
                </DialogDescription>
            </DialogHeader>

            <form @submit="onSubmit" class="space-y-4">
                <FormField v-slot="{ componentField }" name="target_course_offering_id">
                    <FormItem>
                        <FormLabel>Target Section</FormLabel>
                        <Select v-bind="componentField">
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select target section" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                <SelectItem
                                    v-for="offering in siblingOfferings"
                                    :key="offering.id"
                                    :value="String(offering.id)"
                                >
                                    {{ offering.section_code || 'No Section Code' }}
                                    (Enrollment: {{ offering.current_enrollment }}/{{ offering.max_capacity }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="force_move">
                    <FormItem class="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                        <FormControl>
                            <input
                                type="checkbox"
                                :checked="componentField.modelValue"
                                @change="componentField['onUpdate:modelValue']?.($event.target.checked)"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                        </FormControl>
                        <div class="space-y-1 leading-none">
                            <FormLabel>Force Move</FormLabel>
                            <p class="text-sm text-muted-foreground">
                                Ignore capacity limits in the target section.
                            </p>
                        </div>
                    </FormItem>
                </FormField>

                <DialogFooter class="gap-2 pt-4">
                    <Button type="button" variant="outline" @click="handleClose" :disabled="isSubmitting">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSubmitting ? 'Moving...' : 'Move Student' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
