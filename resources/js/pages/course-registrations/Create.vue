<script setup lang="ts">
import StudentCombobox from '@/components/StudentCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import type { Student, Unit } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, BookOpen, CheckCircle, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface UnitData {
    unit: Unit;
    offerings: any[];
    is_eligible: boolean;
    is_already_registered: boolean;
    reasons: string[];
}

interface AvailableUnitsResponse {
    semester: any;
    units: UnitData[];
    registered_units: UnitData[];
}

// Form state
const form = ref({
    student_code: '',
    unit_ids: [] as string[],
    notes: '',
});

const errors = ref<any>({});
const isSubmitting = ref(false);
const isLoadingUnits = ref(false);

// State for dynamic data loading
const selectedStudent = ref<Student | null>(null);
const availableUnits = ref<UnitData[]>([]);
const registeredUnits = ref<UnitData[]>([]);
const activeSemester = ref<any>(null);

// Computed properties
const eligibleUnits = computed(() => availableUnits.value.filter((unit) => unit.is_eligible && !unit.is_already_registered));

const ineligibleUnits = computed(() => availableUnits.value.filter((unit) => !unit.is_eligible || unit.is_already_registered));

const selectedUnitsCount = computed(() => form.value.unit_ids.length);

const canSubmit = computed(() => selectedStudent.value && selectedUnitsCount.value > 0 && !isSubmitting.value);

// Handle student selection
const handleStudentSelect = async (student: Student | null) => {
    selectedStudent.value = student;
    availableUnits.value = [];
    registeredUnits.value = [];
    activeSemester.value = null;
    form.value.unit_ids = [];

    if (student) {
        form.value.student_code = student.id.toString();
        await loadAvailableUnits(student.id);
    } else {
        form.value.student_code = '';
    }
};

// Load available units for the selected student
const loadAvailableUnits = async (studentId: number) => {
    if (!studentId) return;

    isLoadingUnits.value = true;
    try {
        const response = await fetch(`/api/course-registrations/available-units?student_code=${studentId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (data.success) {
            const unitsData: AvailableUnitsResponse = data.data;
            availableUnits.value = unitsData.units;
            registeredUnits.value = unitsData.registered_units || [];
            activeSemester.value = unitsData.semester;
        } else {
            console.error('Failed to load available units:', data.message);
        }
    } catch (error) {
        console.error('Error loading available units:', error);
    } finally {
        isLoadingUnits.value = false;
    }
};

// Handle unit selection
const handleUnitSelect = (unitId: string, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        if (!form.value.unit_ids.includes(unitId)) {
            form.value.unit_ids.push(unitId);
        }
    } else {
        form.value.unit_ids = form.value.unit_ids.filter((id) => id !== unitId);
    }
};

// Form submission
const onSubmit = () => {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    errors.value = {};

    const formData = {
        student_code: Number(form.value.student_code),
        unit_ids: form.value.unit_ids.map((id) => Number(id)),
        notes: form.value.notes,
    };

    router.post('/course-registrations', formData, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            toast.success('Registration successful');
        },
        onError: (errs) => {
            errors.value = errs;
            isSubmitting.value = false;
            toast.error('Registration failed');
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Register Student for Units" />
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Register Student for Units</h1>
        <p class="text-muted-foreground">Select a student and register them for multiple units in the active semester</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Registration Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="onSubmit" class="space-y-6">
                        <!-- Student Selection -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Student *</label>
                            <StudentCombobox v-model="form.student_code" :error-message="errors.student_code" placeholder="Search and select a student..." @select="handleStudentSelect" />
                        </div>

                        <!-- No Student Selected -->
                        <div v-if="!selectedStudent" class="py-8 text-center">
                            <div class="flex flex-col items-center space-y-3">
                                <Users class="text-muted-foreground h-12 w-12" />
                                <p class="text-muted-foreground">Please select a student to continue</p>
                            </div>
                        </div>

                        <!-- Loading Units -->
                        <div v-else-if="isLoadingUnits" class="py-8 text-center">
                            <div class="flex flex-col items-center space-y-3">
                                <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
                                <p class="text-muted-foreground">Loading available units...</p>
                            </div>
                        </div>

                        <!-- No Active Semester -->
                        <div v-else-if="!activeSemester" class="py-8 text-center">
                            <div class="flex flex-col items-center space-y-3">
                                <AlertCircle class="text-destructive h-12 w-12" />
                                <p class="text-destructive">No active semester found</p>
                            </div>
                        </div>

                        <!-- Unit Selection -->
                        <div v-else class="space-y-6">
                            <!-- Active Semester Info -->
                            <div class="bg-muted/50 rounded-lg border p-4">
                                <h3 class="mb-1 text-sm font-medium">Active Semester</h3>
                                <p class="text-lg font-semibold">{{ activeSemester.name }}</p>
                            </div>

                            <!-- Registered Units ---->
                            <div v-if="registeredUnits.length > 0" class="space-y-3">
                                <h3 class="text-sm font-medium">Already Registered Units ({{ registeredUnits.length }})</h3>
                                <div class="space-y-2">
                                    <div v-for="unitData in registeredUnits" :key="`registered-${unitData.unit.id}`" class="flex items-start space-x-3 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                        <CheckCircle class="mt-0.5 h-5 w-5 text-blue-600" />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium">{{ unitData.unit.code }} - {{ unitData.unit.name }}</span>
                                                <Badge variant="outline" class="border-blue-300 bg-blue-100 text-xs text-blue-800">Registered</Badge>
                                            </div>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ unitData.unit.credit_points }} credit points</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Eligible Units -->
                            <div v-if="eligibleUnits.length > 0" class="space-y-3">
                                <h3 class="text-sm font-medium">Available Units ({{ eligibleUnits.length }})</h3>
                                <div class="space-y-2">
                                    <div v-for="unitData in eligibleUnits" :key="unitData.unit.id" class="hover:bg-muted/50 flex items-start space-x-3 rounded-lg border p-3">
                                        <Checkbox :id="`unit-${unitData.unit.id}`" :model-value="form.unit_ids.includes(unitData.unit.id.toString())" @update:model-value="(checked) => handleUnitSelect(unitData.unit.id.toString(), checked)" />
                                        <div class="min-w-0 flex-1">
                                            <label :for="`unit-${unitData.unit.id}`" class="block cursor-pointer font-medium"> {{ unitData.unit.code }} - {{ unitData.unit.name }} </label>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ unitData.unit.credit_points }} credit points • {{ unitData.offerings.length }} section(s) available</p>
                                        </div>
                                        <CheckCircle class="h-5 w-5 text-green-600" />
                                    </div>
                                </div>
                            </div>

                            <!-- Ineligible Units -->
                            <div v-if="ineligibleUnits.length > 0" class="space-y-3">
                                <h3 class="text-sm font-medium">Unavailable Units ({{ ineligibleUnits.length }})</h3>
                                <div class="space-y-2">
                                    <div v-for="unitData in ineligibleUnits" :key="unitData.unit.id" class="bg-muted/20 flex items-start space-x-3 rounded-lg border p-3 opacity-60">
                                        <Checkbox :id="`unit-${unitData.unit.id}`" :model-value="false" disabled />
                                        <div class="min-w-0 flex-1">
                                            <label :for="`unit-${unitData.unit.id}`" class="block font-medium"> {{ unitData.unit.code }} - {{ unitData.unit.name }} </label>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ unitData.unit.credit_points }} credit points</p>
                                            <div class="mt-2 space-y-1">
                                                <Badge v-for="reason in unitData.reasons" :key="reason" variant="destructive" class="text-xs">
                                                    {{ reason }}
                                                </Badge>
                                            </div>
                                        </div>
                                        <AlertCircle class="text-destructive h-5 w-5" />
                                    </div>
                                </div>
                            </div>

                            <!-- No Units Available -->
                            <div v-if="availableUnits.length === 0" class="py-8 text-center">
                                <div class="flex flex-col items-center space-y-3">
                                    <BookOpen class="text-muted-foreground h-12 w-12" />
                                    <p class="text-muted-foreground">No units available for registration</p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Notes</label>
                                <Textarea v-model="form.notes" placeholder="Optional registration notes..." class="min-h-[100px]" />
                            </div>

                            <!-- Error Display -->
                            <div v-if="Object.keys(errors).length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4">
                                <h4 class="mb-2 font-medium text-red-800">Please fix the following errors:</h4>
                                <ul class="list-inside list-disc space-y-1 text-sm text-red-700">
                                    <li v-for="(error, field) in errors" :key="field">
                                        <span class="font-medium">{{ field }}:</span> {{ Array.isArray(error) ? error[0] : error }}
                                    </li>
                                </ul>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex space-x-4">
                                <Button type="submit" :disabled="!canSubmit" class="flex-1">
                                    {{ isSubmitting ? 'Registering...' : `Register for ${selectedUnitsCount} Unit(s)` }}
                                </Button>
                                <Button type="button" variant="outline" @click="router.visit('/course-registrations')" class="flex-1"> Cancel </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Summary Sidebar -->
        <div class="space-y-6">
            <!-- Selected Student Info -->
            <Card v-if="selectedStudent">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <Users class="h-5 w-5" />
                        <span>Selected Student</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <p class="font-medium">{{ selectedStudent.full_name }}</p>
                        <p class="text-muted-foreground text-sm">{{ selectedStudent.student_code }}</p>
                        <p class="text-muted-foreground text-sm">{{ selectedStudent.email }}</p>
                    </div>
                    <div v-if="selectedStudent.program">
                        <Badge variant="outline">{{ selectedStudent.program.name }}</Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Registration Summary -->
            <Card v-if="selectedStudent && activeSemester">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <BookOpen class="h-5 w-5" />
                        <span>Registration Summary</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">Semester:</span>
                            <p class="font-medium">{{ activeSemester.name }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Units Selected:</span>
                            <p class="font-medium">{{ selectedUnitsCount }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Available:</span>
                            <p class="font-medium text-green-600">{{ eligibleUnits.length }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Registered:</span>
                            <p class="font-medium text-blue-600">{{ registeredUnits.length }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Unavailable:</span>
                            <p class="font-medium text-red-600">{{ ineligibleUnits.length }}</p>
                        </div>
                    </div>

                    <!-- Selected Units List -->
                    <div v-if="selectedUnitsCount > 0" class="border-t pt-3">
                        <h4 class="mb-2 text-sm font-medium">Selected Units:</h4>
                        <div class="space-y-1">
                            <div v-for="unitId in form.unit_ids" :key="unitId" class="text-sm">
                                <Badge variant="secondary" class="text-xs">
                                    {{ availableUnits.find((u) => u.unit.id.toString() === unitId)?.unit.code }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
