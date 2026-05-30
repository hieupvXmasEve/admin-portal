<script setup lang="ts">
import PrerequisiteGroupsManager from '@/components/PrerequisiteGroupsManager.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { useModuleNavigation } from '@/composables/useModuleNavigation';
import type { EquivalentUnit, FormDefaults, PrerequisiteGroup, Unit } from '@/types/Unit';
import { curriculumRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    formDefaults: FormDefaults;
}>();

// Form handling
const form = useForm({
    code: props.formDefaults.code,
    name: props.formDefaults.name,
    credit_points: props.formDefaults.credit_points,
    level: 0,
    base_fee: null,
    retake_fee: null,
    unit_type: 'general',
});

// Separate reactive data for relationships
const prerequisiteGroups = ref<PrerequisiteGroup[]>([]);
const equivalentUnits = ref<EquivalentUnit[]>([]);

// Real-time validation
const codeValidation = ref<{ valid: boolean; message: string } | null>(null);
const isValidatingCode = ref(false);

// Unit search for equivalents
const unitSearchQuery = ref('');
const unitSearchResults = ref<Unit[]>([]);
const isSearchingUnits = ref(false);
const showUnitSearch = ref(false);

// API instance
const api = useApi();

// Module navigation với return param
const { navigateBack, getReturnUrl } = useModuleNavigation({
    moduleIndexRoute: 'units.index',
});

// Debounced code validation
let codeValidationTimeout: number;
let unitSearchTimeout: number;

const validateCode = async (code: string) => {
    if (!code || code.length < 2) {
        codeValidation.value = null;
        return;
    }

    isValidatingCode.value = true;

    try {
        const result = await api.post('/units/validate-code', {
            code: code,
        });

        if (result.data.value?.success) {
            codeValidation.value = result.data.value.data;
        } else {
            console.error('Code validation failed:', result.data.value?.message || 'Unknown error');
            codeValidation.value = null;
        }
    } catch (error: any) {
        console.error('Code validation failed:', error);
        codeValidation.value = null;
    } finally {
        isValidatingCode.value = false;
    }
};

const searchUnits = async (query: string) => {
    if (!query || query.length < 2) {
        unitSearchResults.value = [];
        return;
    }

    isSearchingUnits.value = true;

    try {
        // Get IDs of already selected units to exclude them
        const excludeIds = [...prerequisiteGroups.value.flatMap((g) => g.conditions.map((c) => c.required_unit_id)), ...equivalentUnits.value.map((e) => e.unit.id)].filter(Boolean);

        const params = {
            q: query,
            exclude: excludeIds.join(','),
            limit: '10',
        };

        const result = await api.get('/units/search', params);

        if (result.data.value?.success) {
            unitSearchResults.value = result.data.value.data;
        } else {
            console.error('Unit search failed:', result.data.value?.message || 'Unknown error');
            unitSearchResults.value = [];
        }
    } catch (error: any) {
        console.error('Unit search failed:', error);
        unitSearchResults.value = [];
    } finally {
        isSearchingUnits.value = false;
    }
};

// Watch for code changes and validate
watch(
    () => form.code,
    (newCode) => {
        clearTimeout(codeValidationTimeout);
        codeValidation.value = null;

        if (newCode && newCode.length >= 2) {
            codeValidationTimeout = setTimeout(() => {
                validateCode(newCode);
            }, 500);
        }
    },
);

// Watch for unit search changes
watch(
    () => unitSearchQuery.value,
    (newQuery) => {
        clearTimeout(unitSearchTimeout);
        unitSearchResults.value = [];

        if (newQuery && newQuery.length >= 2) {
            unitSearchTimeout = setTimeout(() => {
                searchUnits(newQuery);
            }, 300);
        }
    },
);

// Form submission
const handleSubmit = () => {
    // Create a base form data object
    const formData: any = {
        ...form.data(),
        prerequisite_groups: prerequisiteGroups.value,
    };

    // Add equivalent units if any
    if (equivalentUnits.value.length > 0) {
        formData.equivalent_units = equivalentUnits.value.map((e) => ({
            equivalent_unit_id: e.unit.id,
            reason: e.reason || null,
        }));
    }

    // Add return parameter if exists
    const returnUrl = getReturnUrl();
    if (returnUrl) {
        formData.return = returnUrl;
    }

    console.log('prerequisiteGroups', prerequisiteGroups.value);
    console.log('formData', formData);

    router.post('/units', formData, {
        preserveScroll: true,
        onSuccess: () => {
            console.log('Unit created successfully');
            toast.success('Unit created successfully');
        },
        onError: () => {
            console.log('Unit creation failed');

            // Errors handled by Inertia
            toast.error('Unit creation failed');
        },
    });
};

// Format code to uppercase
const formatCode = (value: string) => {
    form.code = value.toUpperCase().replace(/[^A-Z0-9]/g, '');
};

// Validate credit points
const validateCreditPoints = (value: string) => {
    const num = parseFloat(value);
    if (isNaN(num)) {
        form.credit_points = 0;
    } else {
        form.credit_points = Math.max(0, Math.min(999.99, Math.round(num * 100) / 100));
    }
};

// Equivalent units management
const addEquivalentUnit = (unit: Unit, reason: string = '') => {
    // Check if unit already exists
    const exists = equivalentUnits.value.some((e) => e.unit.id === unit.id);
    if (!exists) {
        equivalentUnits.value.push({ unit, reason });
        unitSearchQuery.value = '';
        unitSearchResults.value = [];
        showUnitSearch.value = false;
    }
};

const removeEquivalentUnit = (index: number) => {
    equivalentUnits.value.splice(index, 1);
};

// Search management
const openUnitSearch = () => {
    showUnitSearch.value = true;
    unitSearchQuery.value = '';
    unitSearchResults.value = [];
};

const selectUnit = (unit: Unit) => {
    addEquivalentUnit(unit);
};

// URL helpers
const indexWithCurrentQuery = (): string => {
    const search = typeof window !== 'undefined' ? window.location.search : '';
    return `${curriculumRoutes.units.index()}${search || ''}`;
};
</script>

<template>
    <Head title="Create Unit" />

    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Create Unit</h1>
        <Button variant="outline" size="sm" @click="navigateBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
        </Button>
    </div>

    <!-- Form -->
    <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription> Create a new unit with code, name, and credit points.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Unit Code -->
                <div class="space-y-2">
                    <Label for="code">Unit Code *</Label>
                    <div class="relative">
                        <Input
                            id="code"
                            v-model="form.code"
                            @input="formatCode($event.target.value)"
                            placeholder="e.g., CS101, MATH201"
                            :class="{
                                'border-red-500': form.errors.code || codeValidation?.valid === false,
                                'border-green-500': codeValidation?.valid === true,
                            }"
                            maxlength="20"
                            required
                        />
                        <div v-if="isValidatingCode" class="absolute top-1/2 right-3 -translate-y-1/2">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                        </div>
                    </div>

                    <!-- Code validation feedback -->
                    <div v-if="codeValidation" class="text-sm">
                        <p
                            :class="{
                                'text-green-600': codeValidation.valid,
                                'text-red-600': !codeValidation.valid,
                            }"
                        >
                            {{ codeValidation.message }}
                        </p>
                    </div>

                    <!-- Server validation errors -->
                    <p v-if="form.errors.code" class="text-sm text-red-600">
                        {{ form.errors.code }}
                    </p>

                    <p class="text-sm text-gray-500">Unit code must be 2-20 characters, uppercase letters and numbers only.</p>
                </div>

                <!-- Unit Name -->
                <div class="space-y-2">
                    <Label for="name">Unit Name *</Label>
                    <Input id="name" v-model="form.name" placeholder="e.g., Introduction to Computer Science" :class="{ 'border-red-500': form.errors.name }" maxlength="255" required />
                    <p v-if="form.errors.name" class="text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>
                    <p class="text-sm text-gray-500">Enter the full name of the unit (3-255 characters).</p>
                </div>

                <!-- Credit Points -->
                <div class="space-y-2">
                    <Label for="credit_points">Credit Points *</Label>
                    <Input
                        id="credit_points"
                        v-model.number="form.credit_points"
                        @input="validateCreditPoints($event.target.value)"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        placeholder="3.00"
                        :class="{ 'border-red-500': form.errors.credit_points }"
                        required
                    />
                    <p v-if="form.errors.credit_points" class="text-sm text-red-600">
                        {{ form.errors.credit_points }}
                    </p>
                    <p class="text-sm text-gray-500">Credit points must be between 0 and 999 (e.g., 3.00, 6.00).</p>
                </div>

                <!-- Level -->
                <div class="space-y-2">
                    <Label for="level">Level *</Label>
                    <Input
                        id="level"
                        v-model.number="form.level"
                        type="number"
                        step="1"
                        min="0"
                        max="10"
                        placeholder="0"
                        :class="{
                            'border-red-500': form.errors.level,
                        }"
                        required
                    />
                    <p v-if="form.errors.level" class="text-sm text-red-600">
                        {{ form.errors.level }}
                    </p>
                    <p class="text-sm text-gray-500">Unit difficulty level (1-10)</p>
                </div>

                <!-- Unit Type -->
                <div class="space-y-2">
                    <Label for="unit_type">Unit Type *</Label>
                    <Select v-model="form.unit_type" required>
                        <SelectTrigger :class="{ 'border-red-500': form.errors.unit_type }">
                            <SelectValue placeholder="Select unit type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Unit Types</SelectLabel>
                                <SelectItem value="general">General</SelectItem>
                                <SelectItem value="egc">EGC (English Global Citizen)</SelectItem>
                                <SelectItem value="semi">Semiconductor</SelectItem>
                                <SelectItem value="ai">Artificial Intelligence</SelectItem>
                                <SelectItem value="mkt">Marketing</SelectItem>
                                <SelectItem value="ba">Business Administration</SelectItem>
                                <SelectItem value="cs">Computer Science</SelectItem>
                                <SelectItem value="ee">Electrical Engineering</SelectItem>
                                <SelectItem value="me">Mechanical Engineering</SelectItem>
                                <SelectItem value="fin">Finance</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.unit_type" class="text-sm text-red-600">
                        {{ form.errors.unit_type }}
                    </p>
                </div>

                <!-- Base Fee -->
                <div class="space-y-2">
                    <Label for="base_fee">Base Fee</Label>
                    <Input
                        id="base_fee"
                        v-model.number="form.base_fee"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        :class="{
                            'border-red-500': form.errors.base_fee,
                        }"
                    />
                    <p v-if="form.errors.base_fee" class="text-sm text-red-600">
                        {{ form.errors.base_fee }}
                    </p>
                    <p class="text-sm text-gray-500">Fee charged for this unit (leave empty if not applicable)</p>
                </div>

                <!-- Retake Fee -->
                <div class="space-y-2">
                    <Label for="retake_fee">Retake Fee</Label>
                    <Input
                        id="retake_fee"
                        v-model.number="form.retake_fee"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        :class="{
                            'border-red-500': form.errors.retake_fee,
                        }"
                    />
                    <p v-if="form.errors.retake_fee" class="text-sm text-red-600">
                        {{ form.errors.retake_fee }}
                    </p>
                    <p class="text-sm text-gray-500">Fee charged for retaking this unit (leave empty if not applicable)</p>
                </div>
            </CardContent>
        </Card>

        <!-- Prerequisites -->
        <Card>
            <CardHeader>
                <CardTitle>Prerequisites</CardTitle>
                <CardDescription>
                    Define prerequisite requirements using five types: P (Prerequisite), Co-req (Co-requisite), Concurrent-req (Concurrent), A (Anti-requisite), and AK (Assumed Knowledge). Group conditions with AND/OR logic operators for complex
                    requirements.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <PrerequisiteGroupsManager :initial-groups="prerequisiteGroups" @update="(groups) => (prerequisiteGroups = groups)" />
            </CardContent>
        </Card>

        <!-- Equivalent Units -->
        <Card>
            <CardHeader>
                <CardTitle>Equivalent Units</CardTitle>
                <CardDescription> Specify units that this unit replaces or is equivalent to (optional). </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Add Equivalent Unit Button -->
                <Button type="button" variant="outline" @click="openUnitSearch()" class="w-full sm:w-auto">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Equivalent Unit
                </Button>

                <!-- Selected Equivalent Units -->
                <div v-if="equivalentUnits.length > 0" class="space-y-3">
                    <div v-for="(equivalent, index) in equivalentUnits" :key="equivalent.unit.id" class="space-y-3 rounded-lg border bg-gray-50 p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="mb-1 flex items-center gap-2">
                                    <code class="rounded bg-white px-2 py-1 font-mono text-sm">
                                        {{ equivalent.unit.code }}
                                    </code>
                                    <Badge class="bg-purple-100 text-purple-800"> Equivalent</Badge>
                                </div>
                                <p class="text-sm text-gray-600">{{ equivalent.unit.name }}</p>
                                <p class="text-xs text-gray-500">{{ equivalent.unit.credit_points }} CP</p>
                            </div>
                            <Button type="button" variant="ghost" size="sm" @click="removeEquivalentUnit(index)">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                        <div>
                            <Label :for="`reason-${index}`" class="text-xs">Reason (optional)</Label>
                            <Input :id="`reason-${index}`" v-model="equivalent.reason" placeholder="e.g., Curriculum update, Content overlap" class="mt-1" />
                        </div>
                    </div>
                </div>

                <p v-else class="text-sm text-gray-500 italic">No equivalent units specified. This unit does not replace any existing units.</p>
            </CardContent>
        </Card>

        <!-- Unit Search Modal -->
        <div v-if="showUnitSearch" class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black">
            <div class="mx-4 max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Search Units - Equivalent Units</h3>
                    <Button variant="ghost" size="sm" @click="showUnitSearch = false">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input v-model="unitSearchQuery" placeholder="Search by unit code or name..." class="pl-9" autofocus />
                    </div>

                    <div v-if="isSearchingUnits" class="py-4 text-center">
                        <div class="mx-auto h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                        <p class="mt-2 text-sm text-gray-500">Searching units...</p>
                    </div>

                    <div v-else-if="unitSearchResults.length > 0" class="max-h-60 space-y-2 overflow-y-auto">
                        <div v-for="unit in unitSearchResults" :key="unit.id" class="cursor-pointer rounded-lg border p-3 hover:bg-gray-50" @click="selectUnit(unit)">
                            <div class="flex items-center justify-between">
                                <div>
                                    <code class="font-mono text-sm">{{ unit.code }}</code>
                                    <p class="text-sm text-gray-600">{{ unit.name }}</p>
                                    <p class="text-xs text-gray-500">{{ unit.credit_points }} CP</p>
                                </div>
                                <Button variant="ghost" size="sm">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="unitSearchQuery.length >= 2" class="py-4 text-center">
                        <p class="text-sm text-gray-500">No units found matching "{{ unitSearchQuery }}"</p>
                    </div>

                    <div v-else class="py-4 text-center">
                        <p class="text-sm text-gray-500">Type at least 2 characters to search for units</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4 pt-6">
            <Button type="button" variant="outline" @click="navigateBack" :disabled="form.processing"> Cancel </Button>
            <Button type="submit" :disabled="form.processing || codeValidation?.valid === false">
                <Save class="mr-2 h-4 w-4" />
                {{ form.processing ? 'Creating...' : 'Create Unit' }}
            </Button>
        </div>
    </form>
</template>
