<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Shuffle, Trash2, Users } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import { z } from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';

import type { CourseOffering, Lecture, Student } from '@/types/models';

interface Props {
    courseOffering: CourseOffering;
    enrolledStudents: Student[];
    lectures: Lecture[];
}

interface SectionData {
    section_code: string;
    max_capacity: number;
    lecture_id: string;
    location?: string;
    student_ids: number[];
}

interface SplitFormData {
    number_of_sections: number;
    assignment_mode: 'equal' | 'custom';
    sections: SectionData[];
}

const props = defineProps<Props>();

const breadcrumbItems = [
    { title: 'Course Offerings', href: '/course-offerings' },
    { title: props.courseOffering.course_code + ' - ' + props.courseOffering.course_title, href: `/course-offerings/${props.courseOffering.id}` },
    { title: 'Split into Sections', href: `/course-offerings/${props.courseOffering.id}/split` },
];

// Form schema
const formSchema = toTypedSchema(
    z.object({
        number_of_sections: z.number().min(2, 'At least 2 sections required').max(10, 'Maximum 10 sections allowed'),
        assignment_mode: z.enum(['equal', 'custom']),
        sections: z
            .array(
                z.object({
                    section_code: z.string().min(1, 'Section code is required'),
                    max_capacity: z.number().min(1, 'Capacity must be at least 1'),
                    lecture_id: z.string(),
                    location: z.string().optional(),
                    student_ids: z.array(z.number()).min(1, 'At least one student must be assigned'),
                }),
            )
            .min(2, 'At least 2 sections required'),
    }),
);

const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        number_of_sections: 2,
        assignment_mode: 'equal' as const,
        sections: [] as SectionData[],
    } satisfies SplitFormData,
    // Prevent initial validation by setting all fields as not touched
    initialTouched: {
        number_of_sections: false,
        assignment_mode: false,
        sections: false,
    },
    // Validate only on submit initially
    validateOnMount: false,
});

// Remove reactive backup state and complex watchers - use simpler approach
const activeTab = ref('configure');

// Simple loading state to prevent flash
const isFormMounted = ref(false);

// Use Vue's onMounted instead of nextTick for better timing
onMounted(() => {
    isFormMounted.value = true;
});

// Get student by ID
const getStudentById = (id: number) => {
    return props.enrolledStudents.find((s) => s.id === id);
};

// Get unassigned students
const unassignedStudents = computed(() => {
    const assignedIds = values.sections?.flatMap((s) => s.student_ids) || [];
    return props.enrolledStudents.filter((s) => !assignedIds.includes(s.id));
});

// Get instructor by ID
const getInstructorById = (id: string) => {
    if (id === 'none') return null;
    return props.lectures.find((i) => i.id.toString() === id);
};

// Validation checks
const validationSummary = computed(() => {
    const totalAssigned = values.sections?.reduce((sum, section) => sum + section.student_ids.length, 0) || 0;
    const totalStudents = props.enrolledStudents.length;

    return {
        totalAssigned,
        totalStudents,
        unassignedCount: totalStudents - totalAssigned,
        isValid: totalAssigned === totalStudents && totalAssigned > 0,
    };
});

// Watch for validation status to enable preview tab
watch(
    () => validationSummary.value.isValid,
    () => {
        // Optionally auto-switch to preview when valid
        // if (validationSummary.value.isValid && activeTab.value === 'configure') {
        //     activeTab.value = 'preview';
        // }
    },
);

// Auto-distribute students equally
const distributeStudentsEqually = (sections: SectionData[]) => {
    const shuffledStudents = [...props.enrolledStudents].sort(() => Math.random() - 0.5);
    const studentsPerSection = Math.ceil(shuffledStudents.length / sections.length);

    sections.forEach((section, index) => {
        const startIndex = index * studentsPerSection;
        const endIndex = Math.min(startIndex + studentsPerSection, shuffledStudents.length);
        section.student_ids = shuffledStudents.slice(startIndex, endIndex).map((s) => s.id);
    });

    setFieldValue('sections', sections);
};

// Initialize sections when number of sections changes
watch(
    () => values.number_of_sections,
    (newCount) => {
        if (newCount && newCount > 0) {
            const newSections: SectionData[] = [];
            for (let i = 0; i < newCount; i++) {
                const sectionLetter = String.fromCharCode(65 + i); // A, B, C, etc.
                newSections.push({
                    section_code: sectionLetter,
                    max_capacity: Math.ceil(props.courseOffering.max_capacity / newCount),
                    lecture_id: props.courseOffering.lecture_id?.toString() || 'none',
                    location: props.courseOffering.location || '',
                    student_ids: [],
                });
            }
            setFieldValue('sections', newSections);

            // Auto-distribute students if equal mode
            if (values.assignment_mode === 'equal') {
                distributeStudentsEqually(newSections);
            }
        }
    },
    { immediate: true },
);

// Watch assignment mode changes
watch(
    () => values.assignment_mode,
    (mode) => {
        if (mode === 'equal' && values.sections) {
            distributeStudentsEqually(values.sections);
        }
    },
);

// Shuffle students redistribution
const shuffleStudents = () => {
    if (values.sections) {
        distributeStudentsEqually(values.sections);
    }
};

// Add student to section
const addStudentToSection = (sectionIndex: number, studentId: number) => {
    if (values.sections) {
        const updatedSections = [...values.sections];

        // Remove from other sections first
        updatedSections.forEach((section) => {
            section.student_ids = section.student_ids.filter((id) => id !== studentId);
        });

        // Add to target section
        updatedSections[sectionIndex].student_ids.push(studentId);
        setFieldValue('sections', updatedSections);
    }
};

// Remove student from section
const removeStudentFromSection = (sectionIndex: number, studentId: number) => {
    if (values.sections) {
        const updatedSections = [...values.sections];
        updatedSections[sectionIndex].student_ids = updatedSections[sectionIndex].student_ids.filter((id) => id !== studentId);
        setFieldValue('sections', updatedSections);
    }
};

// Form submission
const onSubmit = handleSubmit((formValues) => {
    router.post(`/course-offerings/${props.courseOffering.id}/split`, formValues, {
        onSuccess: () => {
            // Course offering has been successfully split and deleted
            // User will be redirected to the course offerings index with success message
        },
        onError: (errors) => {
            console.error('Split failed:', errors);
        },
    });
});

// Handle capacity changes
const updateSectionCapacity = (sectionIndex: number, newCapacity: number) => {
    if (values.sections) {
        const updatedSections = [...values.sections];
        updatedSections[sectionIndex].max_capacity = newCapacity;
        setFieldValue('sections', updatedSections);
    }
};

// Handle instructor changes
const updateSectionInstructor = (sectionIndex: number, instructorId: any) => {
    if (values.sections) {
        const updatedSections = [...values.sections];
        updatedSections[sectionIndex].lecture_id = String(instructorId || 'none');
        setFieldValue('sections', updatedSections);
    }
};

// Handle location changes
const updateSectionLocation = (sectionIndex: number, location: any) => {
    if (values.sections) {
        const updatedSections = [...values.sections];
        updatedSections[sectionIndex].location = String(location || '');
        setFieldValue('sections', updatedSections);
    }
};
</script>

<template>
    <Head title="Split Course Offering" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="h-full space-y-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Split Course Offering</h1>
                    <p class="text-muted-foreground">
                        Split {{ courseOffering.course_code }} - {{ courseOffering.course_title }} into multiple sections
                    </p>
                </div>
                <Link :href="`/course-offerings/${courseOffering.id}`">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Course
                    </Button>
                </Link>
            </div>

            <!-- Course Overview -->
            <Card>
                <CardHeader>
                    <CardTitle>Course Overview</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Course</p>
                            <p class="text-lg font-semibold">{{ courseOffering.course_code }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Semester</p>
                            <p class="text-lg font-semibold">{{ courseOffering.semester?.name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Current Enrollment</p>
                            <p class="text-lg font-semibold">{{ courseOffering.current_enrollment }} students</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Capacity</p>
                            <p class="text-lg font-semibold">{{ courseOffering.max_capacity }} total</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Main Content -->
            <form @submit="onSubmit">
                <keep-alive>
                    <div class="space-y-6">
                        <!-- Tab Navigation -->
                        <div class="bg-muted text-muted-foreground inline-flex h-9 w-full items-center justify-center rounded-lg p-[3px]">
                            <button
                                type="button"
                                :class="[
                                    'inline-flex h-[calc(100%-1px)] flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50',
                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring',
                                    activeTab === 'configure'
                                        ? 'bg-background text-foreground data-[state=active]:bg-background dark:data-[state=active]:text-foreground dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 shadow-sm'
                                        : 'text-foreground dark:text-muted-foreground hover:text-foreground',
                                ]"
                                @click="activeTab = 'configure'"
                            >
                                Configure Sections
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'inline-flex h-[calc(100%-1px)] flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50',
                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring',
                                    activeTab === 'preview'
                                        ? 'bg-background text-foreground data-[state=active]:bg-background dark:data-[state=active]:text-foreground dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 shadow-sm'
                                        : 'text-foreground dark:text-muted-foreground hover:text-foreground',
                                ]"
                                :disabled="!validationSummary.isValid"
                                @click="activeTab = 'preview'"
                            >
                                Preview & Confirm
                            </button>
                        </div>

                        <!-- Configure Tab Content -->
                        <div v-show="activeTab === 'configure'" class="space-y-6">
                            <!-- Configuration -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Split Configuration</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <FormField name="number_of_sections">
                                            <FormItem>
                                                <FormLabel>Number of Sections</FormLabel>
                                                <FormControl>
                                                    <Input
                                                        type="number"
                                                        min="2"
                                                        max="10"
                                                        :model-value="values.number_of_sections"
                                                        @update:model-value="
                                                            (value) => {
                                                                setFieldValue('number_of_sections', Number(value));
                                                            }
                                                        "
                                                    />
                                                </FormControl>
                                                <!-- Remove debug info -->
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <FormField name="assignment_mode">
                                            <FormItem>
                                                <FormLabel>Student Assignment</FormLabel>
                                                <FormControl>
                                                    <Select
                                                        :model-value="values.assignment_mode"
                                                        @update:model-value="
                                                            (value) => {
                                                                setFieldValue('assignment_mode', value as 'equal' | 'custom');
                                                            }
                                                        "
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select assignment mode" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="equal">Equal Distribution</SelectItem>
                                                            <SelectItem value="custom">Custom Assignment</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </FormControl>
                                                <!-- Remove debug info -->
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>

                                    <div v-if="values.assignment_mode === 'equal'" class="flex items-center gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="shuffleStudents">
                                            <Shuffle class="mr-2 h-4 w-4" />
                                            Shuffle Students
                                        </Button>
                                        <span class="text-muted-foreground text-sm"> Students will be distributed equally across sections </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Sections Configuration -->
                            <div v-if="values.sections && values.sections.length > 0" class="space-y-4">
                                <div v-for="(section, sectionIndex) in values.sections" :key="sectionIndex" class="card">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle class="flex items-center gap-2">
                                                Section {{ section.section_code }}
                                                <Badge variant="secondary"> {{ section.student_ids.length }} students </Badge>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent class="space-y-4">
                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <div>
                                                    <label class="text-sm font-medium">Max Capacity</label>
                                                    <Input
                                                        type="number"
                                                        :model-value="section.max_capacity"
                                                        @update:model-value="(value) => updateSectionCapacity(sectionIndex, Number(value))"
                                                        min="1"
                                                        class="mt-1"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium">Lecturer</label>
                                                    <Select
                                                        :model-value="section.lecture_id"
                                                        @update:model-value="(value) => updateSectionInstructor(sectionIndex, value)"
                                                    >
                                                        <SelectTrigger class="mt-1">
                                                            <SelectValue placeholder="Select lecturer" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">No lecturer assigned</SelectItem>
                                                            <SelectItem
                                                                v-for="instructor in lectures"
                                                                :key="instructor.id"
                                                                :value="instructor.id.toString()"
                                                            >
                                                                {{ instructor.first_name }} {{ instructor.last_name }} ({{ instructor.email }})
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium">Location</label>
                                                    <Input
                                                        :model-value="section.location"
                                                        @update:model-value="(value) => updateSectionLocation(sectionIndex, value)"
                                                        placeholder="Enter location"
                                                        class="mt-1"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Students in this section -->
                                            <div v-if="values.assignment_mode === 'custom'">
                                                <div class="mb-3 flex items-center justify-between">
                                                    <label class="text-sm font-medium">Assigned Students</label>
                                                    <span class="text-muted-foreground text-sm">
                                                        {{ section.student_ids.length }} / {{ section.max_capacity }}
                                                    </span>
                                                </div>
                                                <div class="max-h-40 space-y-2 overflow-y-auto">
                                                    <div
                                                        v-for="studentId in section.student_ids"
                                                        :key="studentId"
                                                        class="bg-muted flex items-center justify-between rounded p-2"
                                                    >
                                                        <div>
                                                            <p class="font-medium">{{ getStudentById(studentId)?.full_name }}</p>
                                                            <p class="text-muted-foreground text-sm">{{ getStudentById(studentId)?.student_id }}</p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            @click="removeStudentFromSection(sectionIndex, studentId)"
                                                        >
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>

                            <!-- Unassigned Students (Custom Mode) -->
                            <Card v-if="values.assignment_mode === 'custom' && unassignedStudents.length > 0">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Users class="h-4 w-4" />
                                        Unassigned Students
                                        <Badge variant="destructive">{{ unassignedStudents.length }}</Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="max-h-60 space-y-2 overflow-y-auto">
                                        <div
                                            v-for="student in unassignedStudents"
                                            :key="student.id"
                                            class="bg-muted flex items-center justify-between rounded p-2"
                                        >
                                            <div>
                                                <p class="font-medium">{{ student.full_name }}</p>
                                                <p class="text-muted-foreground text-sm">{{ student.student_id }}</p>
                                            </div>
                                            <Select @update:model-value="(value) => addStudentToSection(Number(value), student.id)">
                                                <SelectTrigger class="w-32">
                                                    <SelectValue placeholder="Assign to..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="(section, index) in values.sections" :key="index" :value="index.toString()">
                                                        Section {{ section.section_code }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Preview Tab Content -->
                        <div v-show="activeTab === 'preview'" class="space-y-6">
                            <!-- Validation Summary -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Split Summary</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <p class="text-muted-foreground text-sm font-medium">Total Students</p>
                                            <p class="text-2xl font-bold">{{ validationSummary.totalStudents }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-sm font-medium">Students Assigned</p>
                                            <p class="text-2xl font-bold" :class="validationSummary.isValid ? 'text-green-600' : 'text-red-600'">
                                                {{ validationSummary.totalAssigned }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-sm font-medium">Sections Created</p>
                                            <p class="text-2xl font-bold">{{ values.sections?.length || 0 }}</p>
                                        </div>
                                    </div>

                                    <div v-if="!validationSummary.isValid" class="bg-destructive/10 border-destructive mt-4 rounded border p-4">
                                        <p class="text-destructive font-medium">
                                            ⚠️ {{ validationSummary.unassignedCount }} students are not assigned to any section. Please assign all
                                            students before proceeding.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Section Preview -->
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <Card v-for="(section, index) in values.sections" :key="index">
                                    <CardHeader>
                                        <CardTitle>Section {{ section.section_code }}</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p class="font-medium">Capacity:</p>
                                                <p>{{ section.student_ids.length }} / {{ section.max_capacity }}</p>
                                            </div>
                                            <div>
                                                <p class="font-medium">Instructor:</p>
                                                <p>
                                                    {{ getInstructorById(section.lecture_id)?.first_name }}
                                                    {{ getInstructorById(section.lecture_id)?.last_name }} ({{
                                                        getInstructorById(section.lecture_id)?.email
                                                    }})
                                                </p>
                                            </div>
                                            <div class="col-span-2">
                                                <p class="font-medium">Location:</p>
                                                <p>{{ section.location || 'Not specified' }}</p>
                                            </div>
                                        </div>

                                        <Separator />

                                        <div>
                                            <p class="mb-2 text-sm font-medium">Students ({{ section.student_ids.length }}):</p>
                                            <div class="max-h-40 space-y-1 overflow-y-auto">
                                                <div v-for="studentId in section.student_ids" :key="studentId" class="bg-muted rounded p-2 text-sm">
                                                    <p class="font-medium">{{ getStudentById(studentId)?.full_name }}</p>
                                                    <p class="text-muted-foreground">{{ getStudentById(studentId)?.student_id }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </keep-alive>

                <!-- Actions -->
                <div class="flex items-center gap-4 pt-6">
                    <Button type="submit" :disabled="!validationSummary.isValid || isSubmitting" class="flex-1 md:flex-none">
                        {{ isSubmitting ? 'Splitting...' : 'Confirm Split' }}
                    </Button>

                    <Link :href="`/course-offerings/${courseOffering.id}`">
                        <Button variant="outline" type="button"> Cancel </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
